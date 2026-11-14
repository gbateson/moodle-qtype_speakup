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

/**
 * Tests for moving SpeakUp's own AI providers into the AI bridge.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \qtype_speakup\provider_migration
 */
final class provider_migration_test extends \advanced_testcase {

    /**
     * Make the old table, as it was before SpeakUp used the AI bridge.
     */
    protected function create_old_table(): void {
        global $DB;
        $table = new \xmldb_table(provider_migration::OLD_TABLE);
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
        $table->add_field('providertype', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'external');
        $table->add_field('capability', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'text');
        $table->add_field('corename', XMLDB_TYPE_CHAR, '255');
        $table->add_field('url', XMLDB_TYPE_CHAR, '1333');
        $table->add_field('username', XMLDB_TYPE_CHAR, '255');
        $table->add_field('apikey', XMLDB_TYPE_TEXT);
        $table->add_field('headers', XMLDB_TYPE_TEXT);
        $table->add_field('model', XMLDB_TYPE_CHAR, '255');
        $table->add_field('returnpath', XMLDB_TYPE_CHAR, '255');
        $table->add_field('requesttemplate', XMLDB_TYPE_TEXT);
        $table->add_field('filetemplate', XMLDB_TYPE_TEXT);
        $table->add_field('uploadstyle', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'none');
        $table->add_field('uploadurl', XMLDB_TYPE_CHAR, '255');
        $table->add_field('uploadprotocol', XMLDB_TYPE_CHAR, '30');
        $table->add_field('enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $DB->get_manager()->create_table($table);
    }

    /**
     * Add a provider to the old table.
     *
     * @param array $data
     * @return int the old id.
     */
    protected function add_old_provider(array $data): int {
        global $DB;
        return $DB->insert_record(provider_migration::OLD_TABLE, (object) array_merge([
            'providertype' => 'external',
            'capability' => 'text',
            'url' => 'https://api.example.com/v1/chat',
            'apikey' => '',
            'headers' => '',
            'model' => 'm1',
            'returnpath' => 'choices,0,message,content',
            'requesttemplate' => '{"prompt": "{{RUBRIC}}\n\n{{TRANSCRIPT}}"}',
            'uploadstyle' => 'none',
            'enabled' => 1,
            'sortorder' => 0,
        ], $data));
    }

    /**
     * Providers, questions and the speech provider setting are moved, and the old table is removed.
     */
    public function test_run(): void {
        global $DB;
        $this->resetAfterTest();
        $this->create_old_table();

        $coreid = $this->add_old_provider(['name' => 'Moodle AI', 'providertype' => 'core', 'url' => '']);
        $geminiid = $this->add_old_provider([
            'name' => 'Gemini (large files)',
            'capability' => 'multimodal',
            'apikey' => 'gemini-secret',
            'headers' => '{"x-goog-api-key": "{{APIKEY}}"}',
            'requesttemplate' => '{"contents": [{"parts": [{{FILE_PARTS}} {"text": "{{RUBRIC}}\n\n{{TRANSCRIPT}}"}]}]}',
            'filetemplate' => '{"file_data": {"mime_type": "{{AUDIO_MIMETYPE}}", "file_uri": "{{AUDIO_FILE_URI}}"}}',
            'uploadstyle' => 'upload_reference',
            'uploadprotocol' => 'gemini',
            'uploadurl' => 'https://generativelanguage.googleapis.com/upload/v1beta/files?key={{APIKEY}}',
        ]);
        $speechid = $this->add_old_provider(['name' => 'Speech', 'capability' => 'speech', 'apikey' => 'tts-secret',
                'requesttemplate' => '{"input": "{{TEXT}}"}', 'returnpath' => '']);
        set_config('ttsproviderid', $speechid, 'qtype_speakup');

        $options = [];
        foreach ([$coreid, $geminiid, $geminiid, 999, null] as $i => $oldid) {
            $option = (object) ['questionid' => 1000 + $i, 'aiproviderid' => $oldid];
            $options[$i] = $DB->insert_record('qtype_speakup_options', $option);
        }

        $this->assertTrue(provider_migration::run());
        $this->assertFalse($DB->get_manager()->table_exists(provider_migration::OLD_TABLE));
        $this->assertFalse(get_config('qtype_speakup', 'movetoaibridge'));

        // Moodle's own AI is not copied: the bridge uses it itself.
        $this->assertFalse($DB->record_exists('tool_aibridge_providers', ['name' => 'Moodle AI']));

        $newid = $DB->get_field('tool_aibridge_providers', 'id', ['name' => 'Gemini (large files)']);
        $gemini = \tool_aibridge\provider::get((int) $newid);
        $record = $gemini->get_record();
        $this->assertSame('multimodal', $record->capability);
        $this->assertEquals(SYSCONTEXTID, $record->contextid);
        $this->assertEquals(get_admin()->id, $record->ownerid);
        $this->assertSame('gemini-secret', $gemini->get_apikey());
        $this->assertNotSame('gemini-secret', $record->apikey);
        $this->assertSame('{"contents": [{"parts": [{{FILE_PARTS}} {"text": "{{PROMPT}}"}]}]}', $record->requesttemplate);
        $this->assertSame('{"file_data": {"mime_type": "{{FILE_MIMETYPE}}", "file_uri": "{{FILE_URI}}"}}', $record->filetemplate);
        $this->assertSame('upload_reference', $record->uploadstyle);
        $this->assertSame('https://generativelanguage.googleapis.com/upload/v1beta/files', $record->uploadurl);

        $speech = \tool_aibridge\provider::get((int) $DB->get_field('tool_aibridge_providers', 'id', ['name' => 'Speech']));
        $this->assertSame('speech', $speech->get_capability());
        $this->assertSame('tts-secret', $speech->get_apikey());
        $this->assertEquals($speech->get_id(), get_config('qtype_speakup', 'ttsproviderid'));

        // Questions: Moodle's AI and unknown providers become "Automatic".
        $ids = [];
        foreach ($options as $i => $id) {
            $ids[$i] = $DB->get_field('qtype_speakup_options', 'aiproviderid', ['id' => $id]);
        }
        $this->assertEquals([null, $gemini->get_id(), $gemini->get_id(), null, null], $ids);

        // Running again does nothing.
        $this->assertTrue(provider_migration::run());
    }

    /**
     * Without a speech provider, audio prompts stay turned off.
     */
    public function test_run_without_speech(): void {
        $this->resetAfterTest();
        $this->create_old_table();
        $this->add_old_provider(['name' => 'Chat']);
        set_config('ttsproviderid', 0, 'qtype_speakup');
        provider_migration::run();
        $this->assertEquals(provider_migration::TTS_NONE, get_config('qtype_speakup', 'ttsproviderid'));
        $this->assertFalse(ai_provider::is_tts_available(\context_system::instance(), (int) get_admin()->id));
    }
}
