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

use stdClass;

/**
 * Settings that a new SpeakUp question can start from, e.g. a preset, or
 * an archetype question named in a GIFT file.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class preset {
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
}
