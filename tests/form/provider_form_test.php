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

namespace qtype_speakup\form;

use qtype_speakup\ai_provider;

/**
 * Tests for validating the AI provider form.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \qtype_speakup\form\provider_form
 */
final class provider_form_test extends \advanced_testcase {

    /**
     * Validate some form data.
     *
     * @param array $data fields that differ from a valid text provider.
     * @return array the errors.
     */
    protected function validate(array $data): array {
        $form = new provider_form();
        return $form->validation(array_merge([
            'name' => 'Test',
            'providertype' => ai_provider::TYPE_EXTERNAL,
            'capability' => ai_provider::CAPABILITY_TEXT,
            'corename' => '',
            'url' => 'https://api.example.com/v1/models/{{MODEL}}:generate',
            'headers' => '',
            'requesttemplate' => '{"prompt": "{{RUBRIC}}", "text": "{{TRANSCRIPT}}"}',
            'uploadstyle' => ai_provider::UPLOAD_NONE,
            'uploadprotocol' => '',
            'uploadurl' => '',
        ], $data), []);
    }

    /**
     * Data for test_validation().
     *
     * @return array
     */
    public static function validation_provider(): array {
        return [
            'valid text provider' => [[], []],
            'no url' => [['url' => ''], ['url']],
            'invalid url' => [['url' => 'not a url'], ['url']],
            'invalid headers' => [['headers' => '{bad json'], ['headers']],
            'no template' => [['requesttemplate' => ''], ['requesttemplate']],
            'invalid template' => [['requesttemplate' => '{"prompt": {{RUBRIC}}}'], ['requesttemplate']],
            'inline upload without audio token' => [[
                'capability' => ai_provider::CAPABILITY_MULTIMODAL,
                'uploadstyle' => ai_provider::UPLOAD_INLINE_BASE64,
            ], ['requesttemplate']],
            'reference upload without settings' => [[
                'capability' => ai_provider::CAPABILITY_MULTIMODAL,
                'uploadstyle' => ai_provider::UPLOAD_REFERENCE,
            ], ['uploadprotocol', 'uploadurl', 'requesttemplate']],
            'valid speech provider' => [[
                'capability' => ai_provider::CAPABILITY_SPEECH,
                'url' => 'https://api.example.com/v1/audio/speech?voice={{VOICE}}&lang={{LANGUAGE}}',
                'requesttemplate' => '{"input": "{{TEXT}}", "voice": "{{VOICE}}", "language": "{{LANGUAGE}}"}',
            ], []],
            'speech provider without text token' => [[
                'capability' => ai_provider::CAPABILITY_SPEECH,
                'requesttemplate' => '{"voice": "{{VOICE}}"}',
            ], ['requesttemplate']],
            'valid core provider' => [['providertype' => ai_provider::TYPE_CORE, 'corename' => 'aiprovider_openai'], []],
            'invalid core name' => [['providertype' => ai_provider::TYPE_CORE, 'corename' => 'openai'], ['corename']],
        ];
    }

    /**
     * Test validating the form.
     *
     * @dataProvider validation_provider
     * @param array $data
     * @param string[] $expectederrors the fields with errors.
     */
    public function test_validation(array $data, array $expectederrors): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->assertEqualsCanonicalizing($expectederrors, array_keys($this->validate($data)));
    }
}
