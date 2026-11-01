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
require_once($CFG->dirroot . '/question/type/speakup/tests/helper.php');

/**
 * Tests for the AI provider records.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \qtype_speakup\ai_provider
 */
final class ai_provider_test extends \advanced_testcase {

    /**
     * Test saving, getting and deleting providers.
     */
    public function test_save_get_delete(): void {
        $this->resetAfterTest();
        $provider = \qtype_speakup_test_helper::create_provider();
        $this->assertSame('Test AI', $provider->name);
        $this->assertNotEmpty($provider->timecreated);

        $provider->name = 'Renamed';
        ai_provider::save($provider);
        $this->assertSame('Renamed', ai_provider::get($provider->id)->name);
        $this->assertCount(1, ai_provider::get_all());

        ai_provider::delete($provider->id);
        $this->assertFalse(ai_provider::get($provider->id));
    }

    /**
     * Upload settings are cleared unless they apply, and core providers are text only.
     */
    public function test_save_clears_settings_that_do_not_apply(): void {
        $this->resetAfterTest();
        $provider = \qtype_speakup_test_helper::create_provider([
            'uploadstyle' => ai_provider::UPLOAD_REFERENCE,
            'uploadprotocol' => 'gemini',
            'uploadurl' => 'https://example.com/upload',
        ]);
        $this->assertSame(ai_provider::UPLOAD_NONE, $provider->uploadstyle);
        $this->assertNull($provider->uploadprotocol);
        $this->assertNull($provider->uploadurl);

        $provider = \qtype_speakup_test_helper::create_provider([
            'name' => 'Core',
            'providertype' => ai_provider::TYPE_CORE,
            'capability' => ai_provider::CAPABILITY_SPEECH,
        ]);
        $this->assertSame(ai_provider::CAPABILITY_TEXT, $provider->capability);
    }

    /**
     * The question form's menu leaves out speech providers, and the speech
     * menu has only those.
     */
    public function test_menus(): void {
        $this->resetAfterTest();
        $text = \qtype_speakup_test_helper::create_provider(['name' => 'Text']);
        $core = \qtype_speakup_test_helper::create_provider(['name' => 'Core', 'providertype' => ai_provider::TYPE_CORE]);
        $disabled = \qtype_speakup_test_helper::create_provider(['name' => 'Disabled', 'enabled' => 0]);
        $speech = \qtype_speakup_test_helper::create_speech_provider(['name' => 'Speech']);

        $menu = ai_provider::get_menu();
        $this->assertArrayHasKey($text->id, $menu);
        $this->assertArrayHasKey($core->id, $menu);
        $this->assertStringContainsString('Core (', $menu[$core->id]);
        $this->assertArrayNotHasKey($disabled->id, $menu);
        $this->assertArrayNotHasKey($speech->id, $menu);
        $this->assertArrayHasKey($disabled->id, ai_provider::get_menu(false));

        $this->assertSame([$speech->id => 'Speech'], ai_provider::get_speech_menu());
    }

    /**
     * The speech provider is only used if it is chosen, enabled and really a speech provider.
     */
    public function test_get_tts_provider(): void {
        $this->resetAfterTest();
        $this->assertNull(ai_provider::get_tts_provider());

        $speech = \qtype_speakup_test_helper::create_speech_provider();
        $this->assertEquals($speech->id, ai_provider::get_tts_provider()->id);

        $speech->enabled = 0;
        ai_provider::save($speech);
        $this->assertNull(ai_provider::get_tts_provider());

        $text = \qtype_speakup_test_helper::create_provider();
        set_config('ttsproviderid', $text->id, 'qtype_speakup');
        $this->assertNull(ai_provider::get_tts_provider());

        set_config('ttsproviderid', 999999, 'qtype_speakup');
        $this->assertNull(ai_provider::get_tts_provider());
    }
}
