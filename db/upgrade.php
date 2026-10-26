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
 * Note: db/install.xml only applies on a *fresh* install. Once installed,
 * schema changes must go through explicit steps here, or Moodle will keep
 * using whatever table shape was created at the original install time -
 * this file exists because we learned that the hard way (adding
 * 'sttlanguage' to install.xml alone did nothing for already-installed
 * sites).
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * @param int $oldversion the version we are upgrading from.
 * @return bool always true.
 */
function xmldb_qtype_speakup_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026091111) {
        $table = new xmldb_table('qtype_speakup_options');
        $field = new xmldb_field('sttlanguage', XMLDB_TYPE_CHAR, '20',
                null, XMLDB_NOTNULL, null, 'en-US', 'gradingmethod');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026091111, 'qtype', 'speakup');
    }

    if ($oldversion < 2026091116) {
        $table = new xmldb_table('qtype_speakup_options');
        $field = new xmldb_field('allowpausing', XMLDB_TYPE_INTEGER, '1',
                null, XMLDB_NOTNULL, null, '0', 'sttlanguage');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026091116, 'qtype', 'speakup');
    }

    if ($oldversion < 2026091117) {
        // Renamed 'allowpausing' to 'allowpause'. Handled defensively since
        // it's not certain whether every site actually reached 2026091116
        // above (i.e. whether the old-named column exists) before this
        // rename was made.
        $table = new xmldb_table('qtype_speakup_options');
        $oldfield = new xmldb_field('allowpausing', XMLDB_TYPE_INTEGER, '1',
                null, XMLDB_NOTNULL, null, '0', 'sttlanguage');
        $newfield = new xmldb_field('allowpause', XMLDB_TYPE_INTEGER, '1',
                null, XMLDB_NOTNULL, null, '0', 'sttlanguage');

        if ($dbman->field_exists($table, $oldfield) && !$dbman->field_exists($table, $newfield)) {
            $dbman->rename_field($table, $oldfield, 'allowpause');
        } else if (!$dbman->field_exists($table, $newfield)) {
            $dbman->add_field($table, $newfield);
        }

        upgrade_plugin_savepoint(true, 2026091117, 'qtype', 'speakup');
    }

    if ($oldversion < 2026091120) {
        $table = new xmldb_table('qtype_speakup_ai_providers');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('providertype', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'external');
            $table->add_field('capability', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'text');
            $table->add_field('corename', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('url', XMLDB_TYPE_CHAR, '1333', null, null, null, null);
            $table->add_field('username', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('apikey', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('headers', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('model', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('returnpath', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('uploadstyle', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'none');
            $table->add_field('enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026091120, 'qtype', 'speakup');
    }

    if ($oldversion < 2026091124) {
        $table = new xmldb_table('qtype_speakup_answers');

        $weight = new xmldb_field('weight', XMLDB_TYPE_NUMBER, '10, 5',
                null, XMLDB_NOTNULL, null, '100', 'isregex');
        if (!$dbman->field_exists($table, $weight)) {
            $dbman->add_field($table, $weight);
        }

        $casesensitive = new xmldb_field('casesensitive', XMLDB_TYPE_INTEGER, '1',
                null, XMLDB_NOTNULL, null, '0', 'weight');
        if (!$dbman->field_exists($table, $casesensitive)) {
            $dbman->add_field($table, $casesensitive);
        }

        upgrade_plugin_savepoint(true, 2026091124, 'qtype', 'speakup');
    }

    if ($oldversion < 2026091125) {
        $table = new xmldb_table('qtype_speakup_options');
        $field = new xmldb_field('aiproviderid', XMLDB_TYPE_INTEGER, '10',
                null, null, null, null, 'airubric');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026091125, 'qtype', 'speakup');
    }

    if ($oldversion < 2026091126) {
        $table = new xmldb_table('qtype_speakup_ai_providers');
        $field = new xmldb_field('requesttemplate', XMLDB_TYPE_TEXT,
                null, null, null, null, null, 'returnpath');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026091126, 'qtype', 'speakup');
    }

    if ($oldversion < 2026091146) {
        $table = new xmldb_table('qtype_speakup_ai_providers');

        $field = new xmldb_field('uploadurl', XMLDB_TYPE_CHAR, '255',
                null, null, null, null, 'uploadstyle');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('uploadprotocol', XMLDB_TYPE_CHAR, '30',
                null, null, null, null, 'uploadurl');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026091146, 'qtype', 'speakup');
    }

    if ($oldversion < 2026091147) {
        // Providers saved with uploadstyle=upload_reference before the
        // uploadprotocol field existed have it empty, which makes every AI
        // assessment fail with "upload protocol not recognised". Gemini is
        // the only protocol implemented so far, so it is the only value
        // such a provider can have meant.
        $DB->execute("UPDATE {qtype_speakup_ai_providers}
                         SET uploadprotocol = ?
                       WHERE uploadstyle = ?
                         AND (uploadprotocol IS NULL OR uploadprotocol = '')",
                [\qtype_speakup\upload\uploader_factory::PROTOCOL_GEMINI, 'upload_reference']);

        upgrade_plugin_savepoint(true, 2026091147, 'qtype', 'speakup');
    }

    if ($oldversion < 2026091148) {
        // How the student gives their response: record or upload.
        // Existing questions keep live recording only.
        $table = new xmldb_table('qtype_speakup_options');
        $field = new xmldb_field('responsemethod', XMLDB_TYPE_CHAR, '20',
                null, XMLDB_NOTNULL, null, 'record', 'aiproviderid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026091148, 'qtype', 'speakup');
    }

    if ($oldversion < 2026091149) {
        // The grading method is now simply "auto" (automated) or "manual".
        // Automated grading combines target phrases with an optional AI
        // assessment, which has its own on/off setting and weighting.
        $table = new xmldb_table('qtype_speakup_options');

        $field = new xmldb_field('aiassessment', XMLDB_TYPE_INTEGER, '1',
                null, XMLDB_NOTNULL, null, '0', 'responsemethod');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('aiweighting', XMLDB_TYPE_INTEGER, '3',
                null, XMLDB_NOTNULL, null, '100', 'aiassessment');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Pattern matching becomes automated grading without AI.
        $DB->execute("UPDATE {qtype_speakup_options}
                         SET gradingmethod = 'auto', aiassessment = 0
                       WHERE gradingmethod = 'exact'");

        // AI grading becomes automated grading with AI, weighted at 100%.
        $DB->execute("UPDATE {qtype_speakup_options}
                         SET gradingmethod = 'auto', aiassessment = 1, aiweighting = 100
                       WHERE gradingmethod = 'ai'");

        upgrade_plugin_savepoint(true, 2026091149, 'qtype', 'speakup');
    }

    return true;
}
