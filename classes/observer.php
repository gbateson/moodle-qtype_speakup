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
 * Event observer for qtype_speakup.
 *
 * The natural trigger point for queuing AI assessment is a quiz attempt
 * being submitted - there is no generic "question attempt finished" core
 * event to hook into directly, so this listens for mod_quiz's own
 * attempt_submitted event and scans the attempt for any SpeakUp questions
 * using AI grading with a real recording to assess.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {

    /**
     * @param \mod_quiz\event\attempt_submitted $event
     */
    public static function quiz_attempt_submitted(\mod_quiz\event\attempt_submitted $event): void {
        global $DB;

        $attempt = $DB->get_record('quiz_attempts', ['id' => $event->objectid]);
        if (!$attempt) {
            return;
        }

        $quba = \question_engine::load_questions_usage_by_activity($attempt->uniqueid);

        foreach ($quba->get_slots() as $slot) {
            $question = $quba->get_question($slot);
            if (!($question instanceof \qtype_speakup_question)) {
                continue;
            }
            if ($question->gradingmethod !== SPEAKUP_GRADING_AI) {
                continue;
            }

            $qa = $quba->get_question_attempt($slot);
            if (!$question->is_complete_response($qa->get_last_qt_data())) {
                // Nothing recorded for this slot - nothing to assess.
                continue;
            }

            ai_queue::queue((int) $attempt->uniqueid, (int) $slot, (int) $attempt->userid, (int) $event->contextid);
        }
    }
}
