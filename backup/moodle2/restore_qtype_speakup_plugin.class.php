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
 * Restores SpeakUp questions: their settings, target phrases and target
 * items. The audio prompt file is restored by Moodle (see
 * backup_qtype_speakup_plugin::get_qtype_fileareas()).
 *
 * The AI provider is matched by name. If this site has no provider with
 * that name, none is set, and a teacher must choose one.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_qtype_speakup_plugin extends restore_qtype_plugin {

    /**
     * Define the paths of a SpeakUp question in the backup.
     *
     * @return restore_path_element[]
     */
    protected function define_question_plugin_structure() {
        return [
            new restore_path_element('speakup', $this->get_pathfor('/speakup')),
            new restore_path_element('speakup_phrase', $this->get_pathfor('/phrases/phrase')),
            new restore_path_element('speakup_item', $this->get_pathfor('/items/item')),
        ];
    }

    /**
     * Was the current question created by this restore? If not, it already
     * existed (e.g. restoring into the same site), and its settings, phrases
     * and items are already there.
     *
     * @return bool
     */
    protected function is_question_created(): bool {
        $oldquestionid = $this->get_old_parentid('question');
        return (bool) $this->get_mappingid('question_created', $oldquestionid);
    }

    /**
     * Restore the question's settings.
     *
     * @param array $data
     */
    public function process_speakup($data) {
        global $DB;

        if (!$this->is_question_created()) {
            return;
        }
        $data = (object) $data;
        $data->questionid = $this->get_new_parentid('question');
        $data->aiproviderid = self::get_provider_id($data->aiprovidername ?? null);
        unset($data->id, $data->aiprovidername);

        // Older backups may not have every setting: leave those out, so they
        // get their default values.
        $columns = $DB->get_columns('qtype_speakup_options');
        $data = (object) array_intersect_key((array) $data, $columns);

        if (!$DB->record_exists('qtype_speakup_options', ['questionid' => $data->questionid])) {
            $DB->insert_record('qtype_speakup_options', $data);
        }
    }

    /**
     * Restore one target phrase.
     *
     * @param array $data
     */
    public function process_speakup_phrase($data) {
        global $DB;
        if ($this->is_question_created()) {
            $data = (object) $data;
            $data->questionid = $this->get_new_parentid('question');
            unset($data->id);
            $DB->insert_record('qtype_speakup_answers', $data);
        }
    }

    /**
     * Restore one target item.
     *
     * @param array $data
     */
    public function process_speakup_item($data) {
        global $DB;
        if ($this->is_question_created()) {
            $data = (object) $data;
            $data->questionid = $this->get_new_parentid('question');
            unset($data->id);
            $DB->insert_record('qtype_speakup_items', $data);
        }
    }

    /**
     * Get the id of the AI provider with this name on this site.
     *
     * @param string|null $name
     * @return int|null the id, or null if there is no such provider.
     */
    protected static function get_provider_id(?string $name): ?int {
        global $DB;
        if ($name === null || trim($name) === '') {
            return null;
        }
        $id = $DB->get_field('qtype_speakup_ai_providers', 'id', ['name' => $name], IGNORE_MULTIPLE);
        return $id ? (int) $id : null;
    }

    /**
     * Convert a SpeakUp question in the backup to the structure returned by
     * qtype_speakup::get_question_options(), so that Moodle can tell whether
     * the same question already exists on this site.
     *
     * @param array $backupdata
     * @return stdClass
     */
    #[\Override]
    public static function convert_backup_to_questiondata(array $backupdata): \stdClass {
        $questiondata = parent::convert_backup_to_questiondata($backupdata);
        $plugindata = $backupdata['plugin_qtype_speakup_question'] ?? [];

        // The provider is backed up by name. Questions in the database have
        // the provider's id (a string, as the database returns it).
        $providerid = self::get_provider_id($questiondata->options->aiprovidername ?? null);
        $questiondata->options->aiproviderid = ($providerid === null) ? null : (string) $providerid;
        unset($questiondata->options->aiprovidername);

        $questiondata->options->expectedanswers = array_map(
            fn($phrase) => (object) $phrase,
            $plugindata['phrases']['phrase'] ?? []
        );
        $questiondata->options->targetitems = array_map(
            fn($item) => (object) $item,
            $plugindata['items']['item'] ?? []
        );
        return $questiondata;
    }

    /**
     * Fields that differ between sites, so are not compared when deciding
     * whether the same question already exists.
     *
     * @return string[]
     */
    #[\Override]
    protected function define_excluded_identity_hash_fields(): array {
        return [
            '/options/expectedanswers/id',
            '/options/expectedanswers/questionid',
            '/options/targetitems/id',
            '/options/targetitems/questionid',
        ];
    }
}
