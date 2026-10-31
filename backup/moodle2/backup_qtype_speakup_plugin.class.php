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

defined('MOODLE_INTERNAL') || die();

/**
 * Backs up SpeakUp questions: their settings, target phrases and target
 * items, and the audio prompt file.
 *
 * The AI provider is backed up by name only (as "aiprovidername"), since
 * provider ids differ between sites. Its credentials (URL, API key,
 * headers) are never backed up: providers are site settings, managed by
 * administrators, and are not part of a course.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_qtype_speakup_plugin extends backup_qtype_plugin {

    /**
     * The fields of qtype_speakup_options that are backed up: all the
     * settings except aiproviderid, which is replaced by aiprovidername.
     *
     * @return string[]
     */
    public static function get_option_fields(): array {
        global $CFG;
        require_once($CFG->dirroot . '/question/type/speakup/questiontype.php');
        $fields = (new qtype_speakup())->extra_question_fields();
        array_shift($fields); // Omit the table name.
        $fields = array_values(array_diff($fields, ['aiproviderid']));
        $fields[] = 'aiprovidername';
        return $fields;
    }

    /**
     * Define the structure of a SpeakUp question in the backup.
     *
     * @return backup_plugin_element
     */
    protected function define_question_plugin_structure() {
        $plugin = $this->get_plugin_element(null, '../../qtype', 'speakup');
        $pluginwrapper = new backup_nested_element($this->get_recommended_name());
        $plugin->add_child($pluginwrapper);

        $options = new backup_nested_element('speakup', ['id'], self::get_option_fields());
        $phrases = new backup_nested_element('phrases');
        $phrase = new backup_nested_element('phrase', ['id'],
                ['answertext', 'isregex', 'casesensitive', 'percent', 'divisor', 'fullmatch']);
        $items = new backup_nested_element('items');
        $item = new backup_nested_element('item', ['id'], ['itemtype', 'itemcount', 'percent', 'multiplier']);

        $pluginwrapper->add_child($options);
        $pluginwrapper->add_child($phrases);
        $phrases->add_child($phrase);
        $pluginwrapper->add_child($items);
        $items->add_child($item);

        // The provider's name, not its id or credentials.
        $options->set_source_sql("
                SELECT o.*, p.name AS aiprovidername
                  FROM {qtype_speakup_options} o
             LEFT JOIN {qtype_speakup_ai_providers} p ON p.id = o.aiproviderid
                 WHERE o.questionid = ?", [backup::VAR_PARENTID]);
        $phrase->set_source_table('qtype_speakup_answers', ['questionid' => backup::VAR_PARENTID], 'id ASC');
        $item->set_source_table('qtype_speakup_items', ['questionid' => backup::VAR_PARENTID], 'id ASC');

        return $plugin;
    }

    /**
     * Get the file areas of this question type, and the mapping their
     * item ids use. The audio prompt's item id is the question id.
     *
     * @return array file area => mapping name
     */
    public static function get_qtype_fileareas() {
        return [
            'audioprompt' => 'question_created',
        ];
    }
}
