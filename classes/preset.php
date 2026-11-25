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

use context;
use stdClass;

/**
 * A preset: saved settings, target items and target phrases that a new
 * SpeakUp question can start from.
 *
 * Where a preset is kept says who can use it: in a user context, only its
 * owner; in a course or category context, everyone who makes questions
 * there; in the system context, everyone on the site. Saving a preset
 * anywhere but in your own user context needs qtype/speakup:sharepresets.
 *
 * This class also gives questions the settings of other bases, e.g. an
 * archetype question named in a GIFT file (see apply_base()).
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class preset {
    /** The table of presets. */
    const TABLE = 'qtype_speakup_presets';

    /** The config setting that lists the shortnames of the presets in db/presets.php already added. */
    const SEEDED_CONFIG = 'seededpresets';

    /** @var int the id of the preset, or 0 if it has not been saved. */
    public $id = 0;

    /** @var int the id of the context it is kept in. */
    public $contextid = 0;

    /** @var int the id of its owner, or 0 if the owner has been deleted. */
    public $userid = 0;

    /** @var string names the preset in imports, e.g. instant-response. */
    public $shortname = '';

    /** @var string */
    public $name = '';

    /** @var string */
    public $description = '';

    /** @var array field name => value: the fields of qtype_speakup_options (see get_setting_fields()). */
    public $settings = [];

    /** @var array[] the target items, each with the fields of qtype_speakup_items. */
    public $items = [];

    /** @var array[] the target phrases, each with the fields of qtype_speakup_phrases. */
    public $phrases = [];

    /** @var int */
    public $sortorder = 0;

    /** @var int */
    public $timecreated = 0;

    /** @var int */
    public $timemodified = 0;

    /** The fields of a target item that a preset keeps. */
    const ITEM_FIELDS = ['itemtype', 'itemtarget', 'percent', 'multiplier'];

    /** The fields of a target phrase that a preset keeps. */
    const PHRASE_FIELDS = ['phrasetext', 'phraselabel', 'percent', 'divisor', 'fullmatch', 'casesensitive'];

    /**
     * Make a preset from a record of the presets table, or an empty preset.
     *
     * @param stdClass|null $record
     */
    public function __construct(?stdClass $record = null) {
        if ($record === null) {
            return;
        }
        foreach (['id', 'contextid', 'userid', 'sortorder', 'timecreated', 'timemodified'] as $field) {
            $this->$field = (int) ($record->$field ?? 0);
        }
        foreach (['shortname', 'name', 'description'] as $field) {
            $this->$field = (string) ($record->$field ?? '');
        }
        foreach (['settings', 'items', 'phrases'] as $field) {
            $value = $record->$field ?? null;
            $this->$field = is_string($value) ? (array) json_decode($value, true) : (array) $value;
        }
    }

    /**
     * Get a preset.
     *
     * @param int $id
     * @return self|null null if there is no such preset.
     */
    public static function get(int $id): ?self {
        global $DB;
        $record = $DB->get_record(self::TABLE, ['id' => $id]);
        return $record ? new self($record) : null;
    }

    /**
     * Get the presets that a user can start a question from: their own, and
     * those shared in the context of the question bank or a context above it.
     *
     * @param context $context the context of the question bank.
     * @param int $userid
     * @return self[] id => preset: the user's own first, then the nearest
     *     context first, then by sort order and name.
     */
    public static function get_visible(context $context, int $userid): array {
        global $DB;

        // The rank of each context: the lower, the nearer.
        $contextids = [];
        if ($userid) {
            $contextids[] = \context_user::instance($userid)->id;
        }
        $contextids = array_merge($contextids, [$context->id], $context->get_parent_context_ids());
        $ranks = array_flip(array_values(array_unique($contextids)));

        [$select, $params] = $DB->get_in_or_equal(array_keys($ranks));
        $presets = [];
        foreach ($DB->get_records_select(self::TABLE, "contextid $select", $params) as $record) {
            $presets[$record->id] = new self($record);
        }
        uasort($presets, function (self $a, self $b) use ($ranks) {
            return [$ranks[$a->contextid], $a->sortorder, \core_text::strtolower($a->name), $a->id]
                <=> [$ranks[$b->contextid], $b->sortorder, \core_text::strtolower($b->name), $b->id];
        });
        return $presets;
    }

    /**
     * Find the preset with a shortname that a user can use: their own, or
     * else the one in the nearest context.
     *
     * @param string $shortname
     * @param context $context the context of the question bank.
     * @param int $userid
     * @return self|null null if there is no such preset.
     */
    public static function find(string $shortname, context $context, int $userid): ?self {
        $shortname = self::clean_shortname($shortname);
        foreach (self::get_visible($context, $userid) as $preset) {
            if ($preset->shortname === $shortname) {
                return $preset;
            }
        }
        return null;
    }

    /**
     * Make a preset from the settings, target items and target phrases of a
     * saved question. The preset is not saved.
     *
     * @param int $questionid
     * @return self
     */
    public static function from_question(int $questionid): self {
        global $DB;
        $preset = new self();
        $options = $DB->get_record('qtype_speakup_options', ['questionid' => $questionid], '*', MUST_EXIST);
        $preset->set_settings($options);
        foreach ($DB->get_records('qtype_speakup_items', ['questionid' => $questionid], 'id ASC') as $item) {
            $preset->items[] = self::pick($item, self::ITEM_FIELDS);
        }
        foreach ($DB->get_records('qtype_speakup_phrases', ['questionid' => $questionid], 'id ASC') as $phrase) {
            $preset->phrases[] = self::pick($phrase, self::PHRASE_FIELDS);
        }
        return $preset;
    }

    /**
     * Set the settings of the preset from the settings of a question, e.g. a
     * record of qtype_speakup_options. The AI provider is kept by its name,
     * so that the preset still finds it on another site, or after a backup
     * is restored.
     *
     * @param array|stdClass $options field name => value.
     */
    public function set_settings($options): void {
        $options = (array) $options;
        $this->settings = [];
        foreach (self::get_setting_fields() as $field) {
            if ($field === 'aiproviderid') {
                $id = (int) ($options[$field] ?? 0);
                $this->settings['aiprovider'] = $id ? ai_provider::get_name($id) : '';
            } else if (array_key_exists($field, $options)) {
                $this->settings[$field] = $options[$field];
            }
        }
    }

    /**
     * Get the settings of the preset, as for a question: with the id of its
     * AI provider, if the provider can be used in the given context. If not,
     * the AI bridge chooses one ("Automatic").
     *
     * @param context|null $context the context of the question bank.
     * @return array field name => value.
     */
    public function get_settings(?context $context = null): array {
        $settings = $this->settings;
        if (array_key_exists('aiprovider', $settings)) {
            $name = (string) $settings['aiprovider'];
            $settings['aiproviderid'] = ($name === '') ? null : (ai_provider::find_by_name($name, $context) ?: null);
            unset($settings['aiprovider']);
        }
        return array_intersect_key($settings, array_flip(self::get_setting_fields()));
    }

    /**
     * Give a question the settings, target items and target phrases of this
     * preset (see apply_base()).
     *
     * @param stdClass $question the question being saved, as from the edit form.
     * @param context|null $context the context of the question bank.
     * @param string[] $given the settings that the question gives itself.
     */
    public function apply_to(stdClass $question, ?context $context = null, array $given = []): void {
        self::apply_base($question, $this->get_settings($context), $this->items, $this->phrases, $given);
    }

    /**
     * Save the preset: add it, or update it if it has an id.
     */
    public function save(): void {
        global $DB;
        $now = time();
        $this->timemodified = $now;
        $record = (object) [
            'contextid' => $this->contextid,
            'userid' => $this->userid,
            'shortname' => self::clean_shortname($this->shortname),
            'name' => trim($this->name),
            'description' => $this->description,
            'settings' => json_encode((object) $this->settings),
            'items' => json_encode(array_values($this->items)),
            'phrases' => json_encode(array_values($this->phrases)),
            'sortorder' => $this->sortorder,
            'timemodified' => $now,
        ];
        if ($this->id) {
            $record->id = $this->id;
            $DB->update_record(self::TABLE, $record);
        } else {
            $this->timecreated = $now;
            $record->timecreated = $now;
            $this->id = (int) $DB->insert_record(self::TABLE, $record);
        }
    }

    /**
     * Tidy a shortname: no spaces around it, and in lower case, so that
     * imports find a preset whatever case its shortname is written in.
     *
     * @param string $shortname
     * @return string
     */
    public static function clean_shortname(string $shortname): string {
        return \core_text::strtolower(trim($shortname));
    }

    /**
     * Is a shortname valid? It has letters, digits, "-" and "_", and starts
     * with a letter or digit, e.g. "instant-response".
     *
     * @param string $shortname
     * @return bool
     */
    public static function is_valid_shortname(string $shortname): bool {
        return (bool) preg_match('/^[a-z0-9][a-z0-9_-]*$/', self::clean_shortname($shortname))
            && \core_text::strlen($shortname) <= 100;
    }

    /**
     * Get the preset with a shortname in a context, if there is one.
     *
     * @param string $shortname
     * @param int $contextid
     * @return self|null
     */
    public static function get_in_context(string $shortname, int $contextid): ?self {
        global $DB;
        $record = $DB->get_record(self::TABLE, [
            'contextid' => $contextid,
            'shortname' => self::clean_shortname($shortname),
        ]);
        return $record ? new self($record) : null;
    }

    /**
     * Get the contexts where a user can save a preset made on the form of a
     * question: their own user context ("Only me"), and the course, the
     * categories and the site above the question bank, where they have
     * qtype/speakup:sharepresets.
     *
     * @param context $context the context of the question bank.
     * @param int $userid
     * @return string[] context id => who can use a preset saved there.
     */
    public static function get_save_contexts(context $context, int $userid): array {
        $plugin = 'qtype_speakup';
        $menu = [];
        if ($userid) {
            $menu[\context_user::instance($userid)->id] = get_string('presetsaveuser', $plugin);
        }
        $contexts = array_merge([$context], array_map(
            fn($id) => context::instance_by_id($id),
            $context->get_parent_context_ids()
        ));
        foreach ($contexts as $parent) {
            if (self::can_save_in($parent, $userid)) {
                $preset = new self();
                $preset->contextid = $parent->id;
                $menu[$parent->id] = $preset->get_users_label();
            }
        }
        return $menu;
    }

    /**
     * Say who can use the preset, e.g. "Teachers in the course: French 1".
     *
     * @return string
     */
    public function get_users_label(): string {
        $plugin = 'qtype_speakup';
        $context = context::instance_by_id($this->contextid, IGNORE_MISSING);
        if (!$context) {
            return '?';
        }
        switch ($context->contextlevel) {
            case CONTEXT_USER:
                return get_string('presetsaveuser', $plugin);
            case CONTEXT_COURSE:
                return get_string('presetsavecourse', $plugin, $context->get_context_name(false));
            case CONTEXT_COURSECAT:
                return get_string('presetsavecategory', $plugin, $context->get_context_name(false));
            case CONTEXT_SYSTEM:
                return get_string('presetsavesystem', $plugin);
            default:
                return $context->get_context_name();
        }
    }

    /**
     * Move the preset up or down in the menu, among the presets in the same
     * context. The sort orders there are renumbered 1, 2, 3, ...
     *
     * @param int $direction -1 to move it up, 1 to move it down.
     * @return bool false if it cannot move that way, e.g. it is already first.
     */
    public function move(int $direction): bool {
        global $DB;
        $records = $DB->get_records(self::TABLE, ['contextid' => $this->contextid], 'sortorder ASC, name ASC, id ASC', 'id');
        $ids = array_map('intval', array_keys($records));
        $index = array_search($this->id, $ids);
        $other = $index + ($direction < 0 ? -1 : 1);
        if ($index === false || $other < 0 || $other >= count($ids)) {
            return false;
        }
        [$ids[$index], $ids[$other]] = [$ids[$other], $ids[$index]];
        foreach ($ids as $i => $id) {
            $DB->set_field(self::TABLE, 'sortorder', $i + 1, ['id' => $id]);
        }
        $this->sortorder = $other + 1;
        return true;
    }

    /**
     * Save the settings, target items and target phrases of a question that
     * has just been saved from the question form, as a preset, if the
     * teacher asked for that (see edit_speakup_form::add_save_preset()):
     * a new preset, or one that replaces the preset with the same shortname
     * in the same context. The teacher becomes its owner.
     *
     * @param stdClass $question the data from the question form, with the id of the saved question.
     * @param int $userid the teacher.
     * @return self|null the preset, or null if none was asked for.
     * @throws \moodle_exception if the preset cannot be saved there.
     */
    public static function save_from_question(stdClass $question, int $userid): ?self {
        global $DB;
        if (empty($question->savepreset)) {
            return null;
        }
        $context = context::instance_by_id((int) ($question->presetcontextid ?? 0), IGNORE_MISSING);
        if (!$context || !self::can_save_in($context, $userid)) {
            throw new \moodle_exception('nopermissions', 'error', '', get_string('presetsave', 'qtype_speakup'));
        }
        $shortname = (string) ($question->presetshortname ?? '');
        if (!self::is_valid_shortname($shortname) || trim((string) ($question->presetname ?? '')) === '') {
            throw new \moodle_exception('err_presetshortname', 'qtype_speakup');
        }

        $preset = self::get_in_context($shortname, $context->id);
        if ($preset && empty($question->presetreplace)) {
            throw new \moodle_exception('err_presetexists', 'qtype_speakup', '', format_string($preset->name));
        }

        $fromquestion = self::from_question((int) $question->id);
        if (!$preset) {
            $preset = new self();
            $preset->contextid = $context->id;
            $preset->shortname = $shortname;
            $preset->sortorder = 1 + (int) $DB->get_field(self::TABLE, 'MAX(sortorder)', ['contextid' => $context->id]);
        }
        $preset->userid = $userid;
        $preset->name = trim((string) $question->presetname);
        $preset->description = trim((string) ($question->presetdescription ?? ''));
        $preset->settings = $fromquestion->settings;
        $preset->items = $fromquestion->items;
        $preset->phrases = $fromquestion->phrases;
        $preset->save();
        return $preset;
    }

    /**
     * Delete the preset.
     */
    public function delete(): void {
        global $DB;
        if ($this->id) {
            $DB->delete_records(self::TABLE, ['id' => $this->id]);
            $this->id = 0;
        }
    }

    /**
     * Get the initials of the owner, e.g. "GB", to tell apart presets with
     * the same name.
     *
     * @return string '' if the owner has been deleted.
     */
    public function get_owner_initials(): string {
        $user = $this->userid ? \core_user::get_user($this->userid) : null;
        if (!$user || !empty($user->deleted)) {
            return '';
        }
        $initials = '';
        foreach ([$user->firstname, $user->lastname] as $name) {
            $initials .= \core_text::strtoupper(\core_text::substr(trim((string) $name), 0, 1));
        }
        return $initials;
    }

    /**
     * Get the name of the preset to show, with its owner's initials, e.g.
     * "Video monologue (GB)".
     *
     * @return string
     */
    public function get_display_name(): string {
        $name = format_string($this->name, true, ['context' => context::instance_by_id($this->contextid, IGNORE_MISSING)
            ?: \context_system::instance()]);
        $initials = $this->get_owner_initials();
        return ($initials === '') ? $name : get_string('presetwithowner', 'qtype_speakup', (object) [
            'name' => $name,
            'owner' => $initials,
        ]);
    }

    /**
     * Can a user save a preset in a context? Anyone can save presets for
     * themselves, in their own user context. Sharing a preset in a course, a
     * category or the whole site needs qtype/speakup:sharepresets there.
     *
     * @param context $context
     * @param int $userid
     * @return bool
     */
    public static function can_save_in(context $context, int $userid): bool {
        switch ($context->contextlevel) {
            case CONTEXT_USER:
                return $userid && (int) $context->instanceid === $userid;
            case CONTEXT_COURSE:
            case CONTEXT_COURSECAT:
            case CONTEXT_SYSTEM:
                return $userid && has_capability('qtype/speakup:sharepresets', $context, $userid);
            default:
                return false;
        }
    }

    /**
     * Can a user change or delete this preset? Only if they could save it
     * where it is kept.
     *
     * @param int $userid
     * @return bool
     */
    public function can_edit(int $userid): bool {
        $context = context::instance_by_id($this->contextid, IGNORE_MISSING);
        return $context && self::can_save_in($context, $userid);
    }

    /**
     * Get the settings of a new question on the question form, for the
     * settings that a preset does not give.
     *
     * @return array field name => value.
     */
    public static function get_default_settings(): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/question/type/speakup/questiontype.php');
        $columns = $DB->get_columns('qtype_speakup_options');
        $settings = [];
        foreach (self::get_setting_fields() as $field) {
            if ($field === 'aiproviderid') {
                // The AI bridge chooses the provider ("Automatic").
                $settings['aiprovider'] = '';
                continue;
            }
            $column = $columns[$field] ?? null;
            if (!$column || !$column->has_default) {
                $settings[$field] = null;
            } else if ($column->meta_type === 'I') {
                $settings[$field] = (int) $column->default_value;
            } else if ($column->meta_type === 'N') {
                $settings[$field] = (float) $column->default_value;
            } else {
                $settings[$field] = $column->default_value;
            }
        }

        // The question form's defaults, where they differ from the database's.
        $settings['gradingmethod'] = SPEAKUP_GRADING_AUTO;
        $settings['statstypes'] = implode(',', text_stats::get_default_stat_types((string) $settings['sttlanguage']));
        return $settings;
    }

    /**
     * Add the presets in db/presets.php to the site, in the system context,
     * owned by the main admin. Each one is only added once: if an admin
     * deletes it, it is not added again.
     */
    public static function seed(): void {
        global $CFG, $DB;
        $presets = [];
        require($CFG->dirroot . '/question/type/speakup/db/presets.php');

        $seeded = array_filter(explode(',', (string) get_config('qtype_speakup', self::SEEDED_CONFIG)));
        $contextid = \context_system::instance()->id;
        $admin = get_admin();
        $defaults = null;
        $sortorder = (int) $DB->get_field(self::TABLE, 'MAX(sortorder)', ['contextid' => $contextid]);

        foreach ($presets as $data) {
            if (in_array($data['shortname'], $seeded)) {
                continue;
            }
            $seeded[] = $data['shortname'];
            if ($DB->record_exists(self::TABLE, ['contextid' => $contextid, 'shortname' => $data['shortname']])) {
                continue;
            }
            if ($defaults === null) {
                $defaults = self::get_default_settings();
            }
            $preset = new self();
            $preset->contextid = $contextid;
            $preset->userid = $admin ? (int) $admin->id : 0;
            $preset->shortname = $data['shortname'];
            $preset->name = get_string($data['name'], 'qtype_speakup');
            $preset->description = get_string($data['description'], 'qtype_speakup');
            $preset->settings = array_merge($defaults, $data['settings'] ?? []);
            $preset->items = $data['items'] ?? [];
            $preset->phrases = $data['phrases'] ?? [];
            $preset->sortorder = ++$sortorder;
            $preset->save();
        }
        set_config(self::SEEDED_CONFIG, implode(',', $seeded), 'qtype_speakup');
    }

    /**
     * Rename settings in all presets, after fields of qtype_speakup_options
     * have been renamed.
     *
     * @param array $renames old field name => new field name.
     */
    public static function rename_settings(array $renames): void {
        global $DB;
        $dbman = $DB->get_manager();
        if (!$renames || !$dbman->table_exists(self::TABLE)) {
            return;
        }
        $records = $DB->get_recordset(self::TABLE, null, '', 'id, settings');
        foreach ($records as $record) {
            $settings = (array) json_decode((string) $record->settings, true);
            $changed = false;
            foreach ($renames as $oldname => $newname) {
                if (array_key_exists($oldname, $settings)) {
                    if (!array_key_exists($newname, $settings)) {
                        $settings[$newname] = $settings[$oldname];
                    }
                    unset($settings[$oldname]);
                    $changed = true;
                }
            }
            if ($changed) {
                $DB->set_field(self::TABLE, 'settings', json_encode((object) $settings), ['id' => $record->id]);
            }
        }
        $records->close();
    }

    /**
     * Delete or keep the presets of a user who is being deleted: their own
     * presets are deleted; those they shared are kept, without an owner.
     *
     * @param int $userid
     */
    public static function delete_user(int $userid): void {
        global $DB;
        $context = \context_user::instance($userid, IGNORE_MISSING);
        if ($context) {
            $DB->delete_records(self::TABLE, ['contextid' => $context->id]);
        }
        $DB->set_field(self::TABLE, 'userid', 0, ['userid' => $userid]);
    }

    /**
     * The fields of qtype_speakup_options that a base can give a question.
     *
     * The transcript of the audio prompt belongs to each question, so it is
     * never taken from a base.
     *
     * @return string[]
     */
    public static function get_setting_fields(): array {
        $fields = \question_bank::get_qtype('speakup')->extra_question_fields();
        array_shift($fields); // Omit the table name.
        return array_values(array_diff($fields, ['prompttranscript']));
    }

    /**
     * Give a question the settings, target items and target phrases of a base.
     *
     * The question keeps the settings it gives itself (those named in $given).
     * It takes the base's target items and target phrases only if it has none
     * of its own. The values are copied, so later changes to the base do not
     * change the question.
     *
     * @param stdClass $question the question being saved, as from the edit form.
     * @param array|stdClass $settings the base's settings: field name => value.
     * @param array $items the base's target items, each with the fields of
     *     qtype_speakup_items (itemtype, itemtarget, percent, multiplier).
     * @param array $phrases the base's target phrases, each with the fields of
     *     qtype_speakup_phrases (phrasetext, phraselabel, percent, divisor,
     *     fullmatch, casesensitive).
     * @param string[] $given the settings that the question gives itself.
     */
    public static function apply_base(
        stdClass $question,
        $settings,
        array $items = [],
        array $phrases = [],
        array $given = []
    ): void {
        $settings = (array) $settings;
        foreach (self::get_setting_fields() as $field) {
            if (!in_array($field, $given) && array_key_exists($field, $settings)) {
                $question->$field = $settings[$field];
            }
        }

        if (empty($question->itemtype)) {
            foreach ($items as $item) {
                $item = (object) $item;
                $question->itemtype[] = $item->itemtype;
                $question->itemtarget[] = $item->itemtarget;
                $question->itempercent[] = $item->percent;
                $question->itemmultiplier[] = $item->multiplier;
            }
        }
        if (empty($question->phrasematch)) {
            foreach ($phrases as $phrase) {
                $phrase = (object) $phrase;
                $question->phrasematch[] = $phrase->phrasetext;
                $question->phraselabel[] = (string) ($phrase->phraselabel ?? '');
                $question->phrasepercent[] = $phrase->percent;
                $question->phrasedivisor[] = $phrase->divisor;
                $question->phrasefullmatch[] = $phrase->fullmatch;
                $question->phrasecasesensitive[] = $phrase->casesensitive;
            }
        }
    }

    /**
     * Pick some fields of a record.
     *
     * @param stdClass $record
     * @param string[] $fields
     * @return array field name => value.
     */
    protected static function pick(stdClass $record, array $fields): array {
        $values = [];
        foreach ($fields as $field) {
            $values[$field] = $record->$field ?? null;
        }
        return $values;
    }
}
