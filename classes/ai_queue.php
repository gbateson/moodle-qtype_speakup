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
 * Queues AI assessment of SpeakUp responses.
 *
 * Real quiz attempts are queued by observer::quiz_attempt_submitted().
 * Previews never fire that event: mod_quiz skips it for quiz previews,
 * and the question bank preview isn't a quiz attempt at all. So previews
 * are queued by the renderer instead, when it first shows a finished
 * preview that has no AI result yet. This lets teachers try out AI
 * grading before giving the question to students.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ai_queue {

    /**
     * Queue AI assessment of one question attempt, unless it is already queued.
     *
     * @param int $usageid question usage id.
     * @param int $slot slot of the question in the usage.
     * @param int $userid the user who made the attempt.
     * @param int $contextid the usage's context.
     */
    public static function queue(int $usageid, int $slot, int $userid, int $contextid): void {
        $task = new task\assess_response();
        $task->set_custom_data([
            'usageid' => $usageid,
            'slot' => $slot,
            'userid' => $userid,
            'contextid' => $contextid,
        ]);
        // Don't queue the same attempt twice, e.g. if the page is reloaded
        // before cron has run the task.
        \core\task\manager::queue_adhoc_task($task, true);
    }

    /**
     * Queue AI assessment of a finished preview, if it needs one.
     *
     * Call this only when the attempt has no AI result yet.
     *
     * @param \question_attempt $qa
     */
    public static function queue_preview(\question_attempt $qa): void {
        global $DB;

        if (!$qa->get_state()->is_finished()) {
            return;
        }

        /** @var \qtype_speakup_question $question */
        $question = $qa->get_question();
        if (!$question->is_graded_later()) {
            return;
        }
        if (!$question->is_complete_response($qa->get_last_qt_data())) {
            // Nothing recorded - nothing to assess.
            return;
        }

        $usage = $DB->get_record('question_usages', ['id' => $qa->get_usage_id()], 'id, contextid, component');
        if (!$usage || !self::is_preview($usage)) {
            return;
        }

        self::queue((int) $usage->id, (int) $qa->get_slot(),
                (int) $qa->get_step(0)->get_user_id(), (int) $usage->contextid);
    }

    /**
     * Is this question usage a preview (question bank or quiz)?
     *
     * Only previews are queued from the renderer. Real attempts are
     * queued when submitted, so viewing an old attempt (e.g. one made
     * before the question was switched to AI grading) never sends it
     * to the AI.
     *
     * @param \stdClass $usage record from question_usages.
     * @return bool
     */
    protected static function is_preview(\stdClass $usage): bool {
        global $DB;
        if ($usage->component === 'core_question_preview') {
            return true;
        }
        if ($usage->component === 'mod_quiz') {
            return (bool) $DB->get_field('quiz_attempts', 'preview', ['uniqueid' => $usage->id]);
        }
        return false;
    }
}
