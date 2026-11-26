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
 * Tell students when their responses have been assessed in the background,
 * so that they know their grades and feedback are ready, e.g. as a push
 * notification in the Moodle app.
 *
 * A quiz may have many SpeakUp questions, so students are told once for each
 * quiz attempt, when the last of its responses has been assessed, not once
 * for each response.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assessment_notifier {
    /**
     * Tell a student that their responses in a quiz attempt have been
     * assessed, once none of them are still waiting to be assessed, and if
     * the quiz lets them see the marks or the comments now.
     *
     * Each response is told about only once: they are marked as told, even
     * if the quiz does not let the student see them yet. Previews are left
     * out: the person previewing is on the page, which updates itself.
     *
     * @param int $usageid the question usage id of the quiz attempt.
     * @return int how many people were told (0 or 1).
     */
    public static function notify_done(int $usageid): int {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        $attempt = $DB->get_record('quiz_attempts', ['uniqueid' => $usageid]);
        if (!$attempt) {
            return 0;
        }

        // Two responses in the attempt may finish at the same time, in
        // different cron processes. Only one of them should tell the student.
        $factory = \core\lock\lock_config::get_lock_factory('qtype_speakup_notify');
        $lock = $factory->get_lock('usage' . $usageid, 60);
        if (!$lock) {
            return 0;
        }
        try {
            if (self::is_waiting($usageid)) {
                return 0;
            }
            $responses = self::get_untold($usageid);
            if (!$responses) {
                return 0;
            }
            [$insql, $params] = $DB->get_in_or_equal(array_keys($responses));
            $DB->set_field_select('qtype_speakup_response', 'timenotified', time(), "id $insql", $params);
        } finally {
            $lock->release();
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
            'count' => count($responses),
            'question' => format_string(reset($responses)->questionname, true, ['context' => $context]),
            'quiz' => format_string($cm->name, true, ['context' => $context]),
            'url' => $url->out(false),
        ];
        $several = (count($responses) > 1) ? 's' : '';
        return task_failure::send(
            'assessmentdone',
            [$student],
            (int) $cm->course,
            get_string('assessmentdonesubject' . $several, 'qtype_speakup'),
            get_string('assessmentdonemessage' . $several, 'qtype_speakup', $a),
            $url->out(false),
            get_string('reviewthisattempt', 'quiz')
        );
    }

    /**
     * Are any responses in a question usage still waiting to be assessed?
     * They are, if a task to assess them is queued or running, and it has
     * not yet saved the result (done or failed).
     *
     * @param int $usageid
     * @return bool
     */
    protected static function is_waiting(int $usageid): bool {
        global $DB;
        foreach (\core\task\manager::get_adhoc_tasks(task\assess_response::class) as $task) {
            $data = $task->get_custom_data();
            if (!is_object($data) || (int) ($data->usageid ?? 0) !== $usageid) {
                continue;
            }
            $status = $DB->get_field_sql('
                    SELECT r.aistatus
                      FROM {question_attempts} qa
                      JOIN {qtype_speakup_response} r ON r.questionattemptid = qa.id
                     WHERE qa.questionusageid = ? AND qa.slot = ?', [$usageid, (int) ($data->slot ?? 0)]);
            if ($status !== 'done' && $status !== 'failed') {
                return true;
            }
        }
        return false;
    }

    /**
     * Get the responses in a question usage that have been assessed, but
     * that the student has not been told about yet.
     *
     * @param int $usageid
     * @return \stdClass[] response id => (id, questionname), in slot order.
     */
    protected static function get_untold(int $usageid): array {
        global $DB;
        return $DB->get_records_sql("
                SELECT r.id, q.name AS questionname
                  FROM {question_attempts} qa
                  JOIN {question} q ON q.id = qa.questionid
                  JOIN {qtype_speakup_response} r ON r.questionattemptid = qa.id
                 WHERE qa.questionusageid = ? AND r.aistatus = 'done' AND r.timenotified IS NULL
              ORDER BY qa.slot", [$usageid]);
    }
}
