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
 * It also removes queued background tasks that are no longer needed,
 * when attempts, quizzes, courses or questions are deleted.
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
            $qa = $quba->get_question_attempt($slot);
            if (!$question->is_complete_response($qa->get_last_qt_data())) {
                // Nothing recorded for this slot - nothing to assess.
                continue;
            }

            if ($question->is_graded_later()) {
                // This also makes large files smaller, first.
                ai_queue::queue((int) $attempt->uniqueid, (int) $slot, (int) $attempt->userid, (int) $event->contextid);
            } else if (file_reducer::is_enabled() && file_reducer::replaces_originals()) {
                ai_queue::queue_reduce_files((int) $attempt->uniqueid, (int) $slot, (int) $event->contextid);
            }
        }
    }

    /**
     * Remove the tasks for a quiz attempt that was deleted.
     *
     * @param \mod_quiz\event\attempt_deleted $event
     */
    public static function quiz_attempt_deleted(\mod_quiz\event\attempt_deleted $event): void {
        try {
            $attempt = $event->get_record_snapshot('quiz_attempts', $event->objectid);
        } catch (\Throwable $e) {
            $attempt = null;
        }
        if ($attempt && !empty($attempt->uniqueid)) {
            task_cleanup::remove_assessments((int) $attempt->uniqueid);
        } else {
            task_cleanup::remove_orphans();
        }
    }

    /**
     * Remove the tasks for attempts or questions that no longer exist, after
     * an activity was deleted (e.g. a quiz, with its attempts), or a course
     * was reset or deleted.
     *
     * @param \core\event\base $event
     */
    public static function remove_orphaned_tasks(\core\event\base $event): void {
        if ($event instanceof \core\event\course_module_deleted && ($event->other['modulename'] ?? '') !== 'quiz') {
            return;
        }
        task_cleanup::remove_orphans();
    }

    /**
     * Remove the tasks for a question that was deleted.
     *
     * @param \core\event\question_deleted $event
     */
    public static function question_deleted(\core\event\question_deleted $event): void {
        task_cleanup::remove_audio_prompts((int) $event->objectid);
    }
}
