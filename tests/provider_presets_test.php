<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace qtype_speakup;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->dirroot . '/question/type/speakup/tests/helper.php');

/**
 * Tests for the ready-made settings for common AI providers.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \qtype_speakup\provider_presets
 */
final class provider_presets_test extends \advanced_testcase {

    /**
     * Data for the tests: each preset.
     *
     * @return array
     */
    public static function preset_provider(): array {
        $names = ['gemini', 'geminiupload', 'openai', 'claude', 'geminitts', 'openaitts'];
        return array_combine($names, array_map(fn($name) => [$name], $names));
    }

    /**
     * Each preset passes the provider form's checks.
     *
     * @dataProvider preset_provider
     * @param string $name
     */
    public function test_preset_is_valid(string $name): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $preset = provider_presets::get_presets()[$name];
        $form = new form\provider_form();
        $errors = $form->validation(array_merge(['corename' => '', 'apikey' => 'key'], $preset), []);
        $this->assertSame([], $errors);
    }

    /**
     * Each multimodal preset's request is valid JSON with no files, one or
     * several, and sends each kind of file it says it takes.
     *
     * @dataProvider preset_provider
     * @param string $name
     */
    public function test_preset_requests(string $name): void {
        $this->resetAfterTest();
        $preset = provider_presets::get_presets()[$name];
        if ($preset['capability'] !== ai_provider::CAPABILITY_MULTIMODAL) {
            $this->assertStringContainsString('{{TEXT}}', $preset['requesttemplate']);
            return;
        }
        $context = \context_system::instance();
        $fs = get_file_storage();
        $files = [];
        foreach (['01-a.png' => 'image/png', '02-b.jpg' => 'image/jpeg', '03-c.pdf' => 'application/pdf'] as $filename => $type) {
            $files[] = $fs->create_file_from_string(['contextid' => $context->id, 'component' => 'qtype_speakup',
                    'filearea' => 'test', 'itemid' => 0, 'filepath' => '/', 'filename' => $filename, 'mimetype' => $type],
                    'content');
        }
        foreach ([[], [$files[0]], $files] as $set) {
            $tokens = array_map(fn($file) => ['FILE_MIMETYPE' => $file->get_mimetype(), 'FILE_BASE64' => 'AAAA',
                    'FILE_URI' => 'https://example.com/file', 'FILE_NAME' => $file->get_filename()], $set);
            $parts = ai_caller::build_file_parts($preset['filetemplate'], $set, $tokens);
            $body = str_replace(['{{FILE_PARTS}}', '{{RUBRIC}}', '{{TRANSCRIPT}}', '{{MODEL}}'],
                    [$parts, 'rubric', 'transcript', 'model'], $preset['requesttemplate']);
            $decoded = json_decode($body, true);
            $this->assertIsArray($decoded, "$name with " . count($set) . ' files');
            $this->assertStringContainsString('"rubric\n\ntranscript"', json_encode($decoded));
            $sent = json_encode($decoded, JSON_UNESCAPED_SLASHES);
            $this->assertSame(count($set), substr_count($sent, 'AAAA') + substr_count($sent, 'https://example.com/file'));
        }
    }
}
