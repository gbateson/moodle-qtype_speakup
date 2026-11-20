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
require_once($CFG->dirroot . '/question/type/speakup/db/upgradelib.php');
require_once($CFG->dirroot . '/question/type/speakup/db/upgrade.php');

/**
 * Tests for making the database match db/install.xml on upgrade.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    ::xmldb_qtype_speakup_check_structure
 * @covers    ::xmldb_qtype_speakup_rename_fields
 * @covers    ::xmldb_qtype_speakup_upgrade
 */
final class upgradelib_test extends \advanced_testcase {
    #[\Override]
    protected function tearDown(): void {
        global $DB;
        // Whatever happened, leave the tables as db/install.xml says.
        $dbman = $DB->get_manager();
        foreach (['qtype_speakup_oldtable', 'qtype_speakup_ai_providers'] as $tablename) {
            if ($dbman->table_exists($tablename)) {
                $dbman->drop_table(new \xmldb_table($tablename));
            }
        }
        xmldb_qtype_speakup_check_structure($dbman);
        parent::tearDown();
    }

    /**
     * Get the differences between the database and db/install.xml.
     *
     * @return array table name => messages
     */
    protected function get_schema_errors(): array {
        global $DB;
        return $DB->get_manager()->check_database_schema(xmldb_qtype_speakup_get_structure(), ['extratables' => false]);
    }

    /**
     * An old database, with old field names, missing and unexpected fields,
     * a wrong default and an old table, is upgraded to match db/install.xml,
     * keeping the data in renamed fields.
     */
    public function test_upgrade_old_structure(): void {
        global $DB;
        $this->resetAfterTest();
        $dbman = $DB->get_manager();
        $this->assertSame([], $this->get_schema_errors());

        $options = new \xmldb_table('qtype_speakup_options');
        $items = new \xmldb_table('qtype_speakup_items');

        // Fields with their old names.
        $dbman->rename_field($options, new \xmldb_field('prompttranscript', XMLDB_TYPE_TEXT), 'audioprompttranscript');
        $dbman->rename_field(
            $options,
            new \xmldb_field('responsepause', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0'),
            'allowpausing'
        );
        $dbman->rename_field(
            $items,
            new \xmldb_field('itemtarget', XMLDB_TYPE_NUMBER, '10, 2', null, XMLDB_NOTNULL, null, '0'),
            'maxcount'
        );
        // A field from before it was renamed, which is no longer needed.
        $dbman->add_field($items, new \xmldb_field('mincount', XMLDB_TYPE_NUMBER, '10, 2', null, XMLDB_NOTNULL, null, '0'));
        // A field that is not there yet, and one with an old default.
        $dbman->drop_field($options, new \xmldb_field('promptpause'));
        $dbman->change_field_default(
            $options,
            new \xmldb_field('maxfiles', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '5')
        );
        // A table that is no longer used.
        $old = new \xmldb_table('qtype_speakup_oldtable');
        $old->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $old->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $dbman->create_table($old);
        // A table that is kept until its data has been moved elsewhere.
        $providers = new \xmldb_table('qtype_speakup_ai_providers');
        $providers->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $providers->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $dbman->create_table($providers);

        $optionid = $DB->insert_record('qtype_speakup_options', (object) [
            'questionid' => 123, 'audioprompttranscript' => 'Hello', 'allowpausing' => 1,
        ]);
        $itemid = $DB->insert_record('qtype_speakup_items', (object) [
            'questionid' => 123, 'itemtype' => 'words', 'maxcount' => 42.5, 'mincount' => 10,
        ]);
        $this->assertNotSame([], $this->get_schema_errors());

        xmldb_qtype_speakup_rename_fields($dbman, [
            'qtype_speakup_options' => ['allowpause' => 'responsepause', 'allowpausing' => 'responsepause',
                'audioprompttranscript' => 'prompttranscript'],
            'qtype_speakup_items' => ['itemcount' => 'itemtarget', 'maxcount' => 'itemtarget'],
        ]);
        xmldb_qtype_speakup_check_structure($dbman, ['qtype_speakup_ai_providers']);

        $this->assertSame([], $this->get_schema_errors());
        $this->assertFalse($dbman->table_exists('qtype_speakup_oldtable'));
        $this->assertTrue($dbman->table_exists('qtype_speakup_ai_providers'));

        $option = $DB->get_record('qtype_speakup_options', ['id' => $optionid]);
        $this->assertSame('Hello', $option->prompttranscript);
        $this->assertEquals(1, $option->responsepause);
        $this->assertEquals(1, $option->promptpause); // The default from db/install.xml.
        $item = $DB->get_record('qtype_speakup_items', ['id' => $itemid]);
        $this->assertEquals(42.5, $item->itemtarget);
        $this->assertObjectNotHasProperty('mincount', $item);
    }

    /**
     * Upgrading a database that already matches db/install.xml changes nothing.
     */
    public function test_upgrade_current_structure(): void {
        global $DB;
        $this->resetAfterTest();
        $this->assertSame([], $this->get_schema_errors());
        $this->assertTrue(xmldb_qtype_speakup_upgrade(0));
        $this->assertSame([], $this->get_schema_errors());
        $this->assertFalse($DB->get_manager()->table_exists('qtype_speakup_ai_providers'));
    }
}
