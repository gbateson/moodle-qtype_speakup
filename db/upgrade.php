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

/**
 * Upgrade steps for qtype_speakup.
 *
 * db/install.xml is the single source of truth for the tables: on every
 * upgrade, the database is made to match it (see db/upgradelib.php). So to
 * add, change or remove a field, just change db/install.xml and the version.
 *
 * To rename a field, also add it to the list of renames below, or else its
 * data will be lost. Keep old renames in the list, for sites that are still
 * on older versions.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/upgradelib.php');

/**
 * Upgrade the SpeakUp question type's database tables and settings.
 *
 * Every step here can safely be run more than once, so they all run on every upgrade.
 *
 * @param int $oldversion the version we are upgrading from.
 * @return bool always true.
 */
function xmldb_qtype_speakup_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    // Renamed fields: old name => new name. Where a field was renamed more
    // than once, list its newer old names first.
    xmldb_qtype_speakup_rename_fields($dbman, [
        'qtype_speakup_options' => [
            'allowpause' => 'responsepause',
            'allowpausing' => 'responsepause',
            'audioprompttranscript' => 'prompttranscript',
            'audiopromptvoice' => 'promptvoice',
            'transcriptpenalty' => 'prompttranscriptpenalty',
            'recordafterprompt' => 'autorecord',
            'statsitems' => 'statstypes',
        ],
        'qtype_speakup_items' => [
            'itemcount' => 'itemtarget',
            'maxcount' => 'itemtarget',
        ],
    ]);

    // SpeakUp's own AI providers have moved to the AI bridge (tool_aibridge).
    // If the bridge is not installed yet, they are moved when it is installed
    // (see qtype_speakup_aibridge_installed()), so keep their table till then.
    \qtype_speakup\provider_migration::run();

    xmldb_qtype_speakup_check_structure($dbman, ['qtype_speakup_ai_providers']);

    // Files are now converted by the AI bridge, so its settings say where ffmpeg is.
    $path = get_config('qtype_speakup', 'pathtoffmpeg');
    if ($path !== false && $path !== '' && get_config('tool_aibridge', 'pathtoffmpeg') === false) {
        set_config('pathtoffmpeg', $path, 'tool_aibridge');
    }
    unset_config('pathtoffmpeg', 'qtype_speakup');

    // Add or update SpeakUp's prompt and output format in the AI bridge's library, so
    // that tools such as the Vocabulary activity's question bank tool can generate
    // SpeakUp questions. (If the bridge is not installed yet, it adds them itself.)
    if ($dbman->table_exists('tool_aibridge_templates')) {
        \tool_aibridge\library::sync_component('qtype_speakup');
    }

    return true;
}
