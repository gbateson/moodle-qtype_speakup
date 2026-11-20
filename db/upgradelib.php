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
 * Upgrade helpers for qtype_speakup: make the database match db/install.xml.
 *
 * db/install.xml is the single source of truth for the tables. On upgrade,
 * xmldb_qtype_speakup_check_structure() compares it with the database, and
 * creates, changes or drops whatever differs, so there is no need for an
 * upgrade step for each change. Based on xmldb_vocab_check_structure() in
 * mod_vocab.
 *
 * The one thing it cannot tell is a rename: it would see a column that is
 * not expected (and drop it, with its data) and a column that is missing
 * (and add it, empty). So renamed fields are listed in db/upgrade.php, and
 * renamed first by xmldb_qtype_speakup_rename_fields().
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Load the structure of the tables from db/install.xml.
 *
 * @return xmldb_structure
 * @throws ddl_exception if the file cannot be loaded.
 */
function xmldb_qtype_speakup_get_structure(): xmldb_structure {
    global $CFG;
    $filepath = $CFG->dirroot . '/question/type/speakup/db/install.xml';
    $file = new xmldb_file($filepath);
    if (!$file->fileExists() || !$file->loadXMLStructure() || !$file->isLoaded()) {
        $structure = $file->getStructure();
        $errors = $structure ? $structure->getAllErrors() : '';
        throw new ddl_exception('ddlxmlfileerror', null, "$filepath $errors");
    }
    return $file->getStructure();
}

/**
 * Rename fields, if they have not been renamed already. Each field gets its
 * new definition from db/install.xml, so only the names are needed. Several
 * old names can be given for one new name, e.g. from successive renames: the
 * first one that exists is renamed, and the others are left to be dropped.
 *
 * @param database_manager $dbman
 * @param array $renames table name => [old field name => new field name]
 */
function xmldb_qtype_speakup_rename_fields(database_manager $dbman, array $renames): void {
    $structure = xmldb_qtype_speakup_get_structure();
    foreach ($renames as $tablename => $fields) {
        $table = $structure->getTable($tablename);
        if (!$table || !$dbman->table_exists($tablename)) {
            continue;
        }
        foreach ($fields as $oldname => $newname) {
            $field = $table->getField($newname);
            if (!$field || $dbman->field_exists($tablename, $newname) || !$dbman->field_exists($tablename, $oldname)) {
                continue;
            }
            $oldfield = clone($field);
            $oldfield->setName($oldname);
            $oldfield->setNotNull(false); // The old field may have nulls. The check sets NOT NULL afterwards.
            $dbman->rename_field(new xmldb_table($tablename), $oldfield, $newname);
        }
    }
}

/**
 * Make the SpeakUp tables in the database match db/install.xml: create
 * missing tables, add, change and drop fields, add and drop indexes, and
 * drop SpeakUp tables that are no longer in db/install.xml.
 *
 * @param database_manager $dbman
 * @param string[] $keeptables tables not to drop, even though they are not in db/install.xml.
 */
function xmldb_qtype_speakup_check_structure(database_manager $dbman, array $keeptables = []): void {
    global $DB;

    $structure = xmldb_qtype_speakup_get_structure();

    // Old SpeakUp tables that are no longer used.
    $prefix = 'qtype_speakup_';
    $xmltables = [];
    foreach ($structure->getTables() as $table) {
        $xmltables[] = $table->getName();
    }
    foreach ($DB->get_tables(false) as $tablename) {
        if (strpos($tablename, $prefix) === 0 && !in_array($tablename, $xmltables) && !in_array($tablename, $keeptables)) {
            $dbman->drop_table(new xmldb_table($tablename));
        }
    }

    // Only this plugin's tables are in $structure, so leave out the other tables in the database.
    $errors = $dbman->check_database_schema($structure, ['extratables' => false]);

    foreach ($errors as $tablename => $messages) {
        $table = $structure->getTable($tablename);
        if (!$table) {
            continue;
        }
        if (in_array('table is missing', $messages)) {
            $dbman->create_table($table);
            continue;
        }

        // Fields to add, change or drop, and indexes to add or drop.
        $changes = ['missing' => [], 'changed' => [], 'unexpected' => []];
        $missingindexes = [];
        $unexpectedindexes = [];
        foreach ($messages as $message) {
            if (preg_match("/^column '(\w+)' is missing/", $message, $match)) {
                $changes['missing'][$match[1]] = true;
            } else if (preg_match("/^column '(\w+)' is not expected/", $message, $match)) {
                $changes['unexpected'][$match[1]] = true;
            } else if (preg_match("/^column '(\w+)' /", $message, $match)) {
                $changes['changed'][$match[1]] = true;
            } else if (preg_match("/^Missing index '(\w+)'/", $message, $match)) {
                $missingindexes[$match[1]] = true;
            } else if (preg_match("/^Unexpected index '(\w+)'/", $message, $match)) {
                $unexpectedindexes[$match[1]] = true;
            }
        }

        // The database manager cannot change or drop a field that is in an
        // index, so drop those indexes first, and add them back at the end.
        $affected = array_keys($changes['changed'] + $changes['unexpected']);
        $dropped = [];
        foreach (xmldb_qtype_speakup_get_indexes($table) as $name => $index) {
            if (array_intersect($index->getFields(), $affected) && $dbman->index_exists($table, $index)) {
                $dbman->drop_index($table, $index);
                $dropped[$name] = $index;
            }
        }
        foreach ($DB->get_indexes($tablename) as $name => $info) {
            if (array_intersect($info['columns'], $affected) || isset($unexpectedindexes[$name])) {
                $index = new xmldb_index($name, $info['unique'] ? XMLDB_INDEX_UNIQUE : XMLDB_INDEX_NOTUNIQUE, $info['columns']);
                if ($dbman->index_exists($table, $index)) {
                    $dbman->drop_index($table, $index);
                }
            }
        }

        foreach (array_keys($changes['unexpected']) as $name) {
            $dbman->drop_field($table, new xmldb_field($name));
        }
        foreach (array_keys($changes['missing']) as $name) {
            xmldb_qtype_speakup_add_field($dbman, $table, $table->getField($name));
        }
        foreach (array_keys($changes['changed']) as $name) {
            $field = $table->getField($name);
            if ($field->getNotNull()) {
                // NOT NULL cannot be set while there are nulls.
                $DB->set_field_select($tablename, $name, xmldb_qtype_speakup_get_default($field), "$name IS NULL");
            }
            $dbman->change_field_type($table, $field);
            $dbman->change_field_default($table, $field);
        }

        // Add the missing indexes, and those that were dropped above.
        foreach (xmldb_qtype_speakup_get_indexes($table) as $name => $index) {
            if ((isset($missingindexes[$name]) || isset($dropped[$name])) && !$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }
        }
    }
}

/**
 * Get the indexes of a table in db/install.xml, including those that Moodle
 * makes for its keys (other than the primary key), by name.
 *
 * @param xmldb_table $table
 * @return xmldb_index[]
 */
function xmldb_qtype_speakup_get_indexes(xmldb_table $table): array {
    $indexes = [];
    foreach ($table->getKeys() as $key) {
        if ($key->getType() == XMLDB_KEY_PRIMARY) {
            continue;
        }
        $unique = in_array($key->getType(), [XMLDB_KEY_UNIQUE, XMLDB_KEY_FOREIGN_UNIQUE]);
        $indexes[$key->getName()] = new xmldb_index(
            $key->getName(),
            $unique ? XMLDB_INDEX_UNIQUE : XMLDB_INDEX_NOTUNIQUE,
            $key->getFields()
        );
    }
    foreach ($table->getIndexes() as $index) {
        $indexes[$index->getName()] = $index;
    }
    return $indexes;
}

/**
 * Add a field. A NOT NULL field with no default cannot be added to a table
 * that has rows, so it is added as nullable, filled in, and then made NOT NULL.
 *
 * @param database_manager $dbman
 * @param xmldb_table $table
 * @param xmldb_field $field
 */
function xmldb_qtype_speakup_add_field(database_manager $dbman, xmldb_table $table, xmldb_field $field): void {
    global $DB;
    if (!$field->getNotNull() || $field->getDefault() !== null) {
        $dbman->add_field($table, $field);
        return;
    }
    $nullable = clone($field);
    $nullable->setNotNull(false);
    $dbman->add_field($table, $nullable);
    $DB->set_field($table->getName(), $field->getName(), xmldb_qtype_speakup_get_default($field));
    $dbman->change_field_notnull($table, $field);
}

/**
 * Get the value to put in place of nulls in a field: its default, or else
 * 0 for numbers and '' for text.
 *
 * @param xmldb_field $field
 * @return int|string
 */
function xmldb_qtype_speakup_get_default(xmldb_field $field) {
    if ($field->getDefault() !== null) {
        return $field->getDefault();
    }
    return in_array($field->getType(), [XMLDB_TYPE_CHAR, XMLDB_TYPE_TEXT]) ? '' : 0;
}
