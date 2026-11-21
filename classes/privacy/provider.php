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

namespace qtype_speakup\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\provider as metadata_provider;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\core_userlist_provider as userlist_provider;
use core_privacy\local\request\plugin\provider as plugin_provider;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy Subsystem implementation for qtype_speakup.
 *
 * What this plugin actually stores, and why:
 *
 * - The audio/video recording itself, and the live browser-transcribed text
 *   captured while the student speaks (the 'browsertranscript' qt_data
 *   field), are both stored via Moodle's own standard question-response
 *   mechanisms (file areas and question_attempt_step_data respectively).
 *   These are already fully handled by \core_question\privacy\provider -
 *   this plugin does not need to, and must not, duplicate that handling.
 *
 * - qtype_speakup_response is this plugin's own extra table, holding the
 *   AI pipeline's own record of a single attempt: a (possibly re-derived)
 *   transcript, and the AI's feedback/score. This is genuine personal
 *   data - the transcript is a text rendering of the student's own voice,
 *   and the AI feedback is commentary specifically about that student's
 *   performance - so this plugin is responsible for it here.
 *
 * - qtype_speakup_options and qtype_speakup_phrases hold only the
 *   teacher's own question configuration (grading method, target phrases,
 *   AI rubric, etc.) - authored content about the question, not about any
 *   individual student, in the same way core's own question_answers table
 *   is documented as containing no personal data. Not declared here for
 *   that reason.
 *
 * - AI providers are set up in the AI bridge (tool_aibridge), which logs
 *   each request, and declares and exports that data itself.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements metadata_provider, plugin_provider, userlist_provider {
    /**
     * Describe the personal data stored by this plugin.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('qtype_speakup_response', [
            'questionattemptid' => 'privacy:metadata:qtype_speakup_response:questionattemptid',
            'transcript' => 'privacy:metadata:qtype_speakup_response:transcript',
            'aifeedback' => 'privacy:metadata:qtype_speakup_response:aifeedback',
            'aiscore' => 'privacy:metadata:qtype_speakup_response:aiscore',
            'timecreated' => 'privacy:metadata:qtype_speakup_response:timecreated',
            'timemodified' => 'privacy:metadata:qtype_speakup_response:timemodified',
        ], 'privacy:metadata:qtype_speakup_response');

        // For AI-graded questions, the student's recording and/or
        // transcript is sent, through the AI bridge, to an AI service
        // (Moodle's own AI subsystem, or a provider such as OpenAI or Google
        // Gemini) for transcription, assessment and feedback. That service
        // is outside Moodle's control once the request is sent.
        $collection->add_external_location_link('ai_provider', [
            'transcript' => 'privacy:metadata:ai_provider:transcript',
            'recording' => 'privacy:metadata:ai_provider:recording',
        ], 'privacy:metadata:ai_provider');

        return $collection;
    }

    /**
     * Get the list of contexts that contain personal data for a user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT ctx.id
                  FROM {qtype_speakup_response} sr
                  JOIN {question_attempts} qa ON qa.id = sr.questionattemptid
                  JOIN {question_usages} qu ON qu.id = qa.questionusageid
                  JOIN {context} ctx ON ctx.id = qu.contextid
                 WHERE EXISTS (SELECT 1
                                 FROM {question_attempt_steps} qas
                                WHERE qas.questionattemptid = qa.id
                                  AND qas.userid = :userid)";
        $contextlist->add_from_sql($sql, ['userid' => $userid]);

        return $contextlist;
    }

    /**
     * Get the users who have personal data in a context: those who made the
     * question attempts that have SpeakUp responses.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist): void {
        $sql = "SELECT qas.userid
                  FROM {qtype_speakup_response} sr
                  JOIN {question_attempts} qa ON qa.id = sr.questionattemptid
                  JOIN {question_usages} qu ON qu.id = qa.questionusageid
                  JOIN {question_attempt_steps} qas ON qas.questionattemptid = qa.id
                 WHERE qu.contextid = :contextid";
        $userlist->add_from_sql('userid', $sql, ['contextid' => $userlist->get_context()->id]);
    }

    /**
     * Export all personal data for the approved contexts for a user.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            $records = self::get_response_records($userid, $context->id);

            foreach ($records as $record) {
                $data = (object) [
                    'transcript' => $record->transcript,
                    'transcriptstatus' => $record->transcriptstatus,
                    'aifeedback' => $record->aifeedback,
                    'aiscore' => $record->aiscore,
                    'aistatus' => $record->aistatus,
                    'timecreated' => transform::datetime($record->timecreated),
                    'timemodified' => transform::datetime($record->timemodified),
                ];
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'qtype_speakup'), $record->questionattemptid],
                    $data
                );
            }
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        $ids = self::get_response_ids_in_context($context->id);
        self::delete_response_records($ids);
    }

    /**
     * Delete all personal data for the approved contexts for a user.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            $ids = self::get_response_ids_in_context($context->id, $userid);
            self::delete_response_records($ids);
        }
    }

    /**
     * Delete the personal data of some users in a context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        $contextid = $userlist->get_context()->id;
        foreach ($userlist->get_userids() as $userid) {
            self::delete_response_records(self::get_response_ids_in_context($contextid, (int) $userid));
        }
    }

    /**
     * Fetch this user's qtype_speakup_response rows within a context.
     *
     * @param int $userid
     * @param int $contextid
     * @return array of stdClass, keyed by id.
     */
    protected static function get_response_records(int $userid, int $contextid): array {
        global $DB;

        $sql = "SELECT sr.*
                  FROM {qtype_speakup_response} sr
                  JOIN {question_attempts} qa ON qa.id = sr.questionattemptid
                  JOIN {question_usages} qu ON qu.id = qa.questionusageid
                 WHERE qu.contextid = :contextid
                   AND EXISTS (SELECT 1
                                 FROM {question_attempt_steps} qas
                                WHERE qas.questionattemptid = qa.id
                                  AND qas.userid = :userid)";
        return $DB->get_records_sql($sql, ['userid' => $userid, 'contextid' => $contextid]);
    }

    /**
     * Fetch qtype_speakup_response.id values within a context, optionally
     * restricted to one user.
     *
     * @param int $contextid
     * @param int|null $userid
     * @return array of int.
     */
    protected static function get_response_ids_in_context(int $contextid, ?int $userid = null): array {
        global $DB;

        $sql = "SELECT sr.id
                  FROM {qtype_speakup_response} sr
                  JOIN {question_attempts} qa ON qa.id = sr.questionattemptid
                  JOIN {question_usages} qu ON qu.id = qa.questionusageid";
        $params = ['contextid' => $contextid];

        if ($userid !== null) {
            $sql .= " WHERE qu.contextid = :contextid
                        AND EXISTS (SELECT 1
                                      FROM {question_attempt_steps} qas
                                     WHERE qas.questionattemptid = qa.id
                                       AND qas.userid = :userid)";
            $params['userid'] = $userid;
        } else {
            $sql .= " WHERE qu.contextid = :contextid";
        }

        return $DB->get_fieldset_sql($sql, $params);
    }

    /**
     * Delete qtype_speakup_response rows by id.
     *
     * @param array $ids
     */
    protected static function delete_response_records(array $ids): void {
        global $DB;

        if (empty($ids)) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($ids);
        $DB->delete_records_select('qtype_speakup_response', "id $insql", $params);
    }
}
