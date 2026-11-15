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
 * Moves SpeakUp's own AI providers (the old qtype_speakup_ai_providers
 * table) into the AI bridge (tool_aibridge).
 *
 * Each provider becomes a bridge provider for the whole site, owned by the
 * main administrator, with its API key encrypted. Providers that used
 * Moodle's own AI become "Automatic", since the bridge sends requests there
 * itself. Questions, and the speech provider setting, are pointed at the new
 * providers, and the old table is removed.
 *
 * Moodle upgrades question types before admin tools, so when the bridge is
 * installed in the same upgrade, its tables do not exist yet when SpeakUp is
 * upgraded. Then the move is done when the bridge is installed (see
 * qtype_speakup_aibridge_installed() in lib.php).
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider_migration {
    /** The old table. */
    const OLD_TABLE = 'qtype_speakup_ai_providers';

    /** The value of the speech provider setting that turns audio prompts off. */
    const TTS_NONE = -1;

    /**
     * Move the providers, if the bridge is installed; else say that this is still to do.
     *
     * @return bool true if the providers were moved, or there were none to move.
     */
    public static function run(): bool {
        global $DB;
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists(self::OLD_TABLE)) {
            unset_config('movetoaibridge', 'qtype_speakup');
            return true;
        }
        if (!$dbman->table_exists(\tool_aibridge\provider::TABLE)) {
            set_config('movetoaibridge', 1, 'qtype_speakup');
            return false;
        }

        $map = [];
        $adminid = get_admin() ? (int) get_admin()->id : 0;
        foreach ($DB->get_records(self::OLD_TABLE, null, 'sortorder, id') as $old) {
            if ($old->providertype === 'core') {
                $map[$old->id] = 0;
                continue;
            }
            $record = self::convert($old, $adminid);
            $apikey = trim((string) $old->apikey);
            $map[$old->id] = \tool_aibridge\provider::save($record, ($apikey === '') ? null : $apikey)->get_id();
        }

        // Point the questions at the new providers. Unknown providers become "Automatic".
        $options = $DB->get_records_select('qtype_speakup_options', 'aiproviderid IS NOT NULL', null, '', 'id, aiproviderid');
        foreach ($options as $option) {
            $newid = $map[$option->aiproviderid] ?? 0;
            $DB->set_field('qtype_speakup_options', 'aiproviderid', $newid ? $newid : null, ['id' => $option->id]);
        }

        // The speech provider: "None" (0) used to turn audio prompts off. Now 0 means any speech provider.
        $ttsid = (int) get_config('qtype_speakup', 'ttsproviderid');
        set_config('ttsproviderid', ($ttsid && !empty($map[$ttsid])) ? $map[$ttsid] : self::TTS_NONE, 'qtype_speakup');

        $dbman->drop_table(new \xmldb_table(self::OLD_TABLE));
        unset_config('movetoaibridge', 'qtype_speakup');
        return true;
    }

    /**
     * Convert an old provider to a bridge provider.
     *
     * @param \stdClass $old
     * @param int $ownerid
     * @return \stdClass
     */
    public static function convert(\stdClass $old, int $ownerid): \stdClass {
        $capability = in_array($old->capability, ['text', 'multimodal', 'speech']) ? $old->capability : 'text';
        $uploadstyle = 'none';
        if ($capability === 'multimodal') {
            $uploadstyle = ($old->uploadstyle === 'upload_reference') ? 'upload_reference' : 'inline_base64';
        }
        $uploadurl = '';
        if ($uploadstyle === 'upload_reference') {
            // The bridge sends the key in a header, so it need not be in the URL.
            $uploadurl = preg_replace('/[?&]key=\{\{APIKEY\}\}$/', '', trim((string) $old->uploadurl));
        }
        $record = (object) [
            'name' => $old->name,
            'ownerid' => $ownerid,
            'contextid' => SYSCONTEXTID,
            'capability' => $capability,
            'url' => (string) $old->url,
            'headers' => (string) $old->headers,
            'model' => (string) $old->model,
            'requesttemplate' => self::convert_template((string) $old->requesttemplate),
            'filetemplate' => self::convert_template((string) ($old->filetemplate ?? '')),
            'returnpath' => (string) $old->returnpath,
            'tokendefaults' => '',
            'uploadstyle' => $uploadstyle,
            'uploadprotocol' => ($uploadstyle === 'upload_reference') ? ((string) $old->uploadprotocol ?: 'gemini') : '',
            'uploadurl' => $uploadurl,
            'datanotice' => '',
            'userratelimit' => 0,
            'enabled' => (int) $old->enabled,
            'sortorder' => (int) $old->sortorder,
            'sharedfrom' => 0,
            'shareduntil' => 0,
        ];
        // The old providers had no default token values, e.g. no VOICE, so use those of the
        // matching preset. Older versions of the bridge's upgrade add them instead.
        if (method_exists('\\tool_aibridge\\presets', 'find_match')) {
            $preset = \tool_aibridge\presets::find_match($record);
            if ($preset !== '') {
                $record->tokendefaults = (string) (\tool_aibridge\presets::get_presets()[$preset]['tokendefaults'] ?? '');
            }
        }
        return $record;
    }

    /**
     * Convert SpeakUp's tokens in a template to the bridge's tokens. The
     * rubric and transcript of SpeakUp's presets become {{PROMPT}}; other
     * uses of {{RUBRIC}} and {{TRANSCRIPT}} still work, since SpeakUp sets them.
     *
     * @param string $template
     * @return string
     */
    public static function convert_template(string $template): string {
        return str_replace(
            ['{{RUBRIC}}\n\n{{TRANSCRIPT}}', '{{AUDIO_BASE64}}', '{{AUDIO_MIMETYPE}}', '{{AUDIO_FILE_URI}}'],
            ['{{PROMPT}}', '{{FILE_BASE64}}', '{{FILE_MIMETYPE}}', '{{FILE_URI}}'],
            $template
        );
    }
}
