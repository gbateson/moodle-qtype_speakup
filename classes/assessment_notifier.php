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
 * Tell students when their response has been assessed in the background,
 * so that they know their grade and feedback are ready, e.g. as a push
 * notification in the Moodle app.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assessment_notifier {
    /**
     * Tell a student that their response in a quiz attempt has been
     * assessed, if the quiz lets them see the mark or the comment now.
     * Previews are left out: the person previewing is on the page, which
     * updates itself.
     *
     * @param \question_attempt $qa
     * @param int $usageid the question usage id of the quiz attempt.
     * @return int how many people were told (0 or 1).
     */
    public static function notify_done(\question_attempt $qa, int $usageid): int {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        $attempt = $DB->get_record('quiz_attempts', ['uniqueid' => $usageid]);
        if (!$attempt) {
            return 0;
        }
        $quiz = $DB->get_record('quiz', ['id' => $attempt->quiz]);
        $cm = $quiz ? get_coursemodule_from_instance('quiz', $quiz->id, $quiz->course) : false;
        $student = \core_user::get_user((int) $attempt->userid);
        if (!$cm || !$student || $student->deleted || $student->suspended) {
            return 0;
        }

        // What the quiz lets the student see now, e.g. not until the quiz closes.
        $options = \mod_quiz\question\display_options::make_from_quiz($quiz, quiz_attempt_state($quiz, $attempt));
        $canseemark = $options->marks >= \question_display_options::MARK_AND_MAX;
        if (!$options->attempt || (!$canseemark && !$options->manualcomment)) {
            return 0;
        }

        $context = \context_module::instance($cm->id);
        $url = new \moodle_url('/mod/quiz/review.php', ['attempt' => $attempt->id]);
        $a = (object) [
            'question' => format_string($qa->get_question(false)->name),
            'quiz' => format_string($cm->name, true, ['context' => $context]),
            'url' => $url->out(false),
        ];
        return task_failure::send(
            'assessmentdone',
            [$student],
            (int) $cm->course,
            get_string('assessmentdonesubject', 'qtype_speakup'),
            get_string('assessmentdonemessage', 'qtype_speakup', $a),
            $url->out(false),
            get_string('reviewthisattempt', 'quiz')
        );
    }
}
