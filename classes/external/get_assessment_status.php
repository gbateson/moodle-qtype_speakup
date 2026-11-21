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

namespace qtype_speakup\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Say whether a response that is graded in the background has been assessed
 * yet, so that the review page, on the site or in the Moodle app, can update
 * itself when it has (see amd/src/assessmentstatus.js and mobile/mobile.js).
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_assessment_status extends external_api {
    /** Waiting to be assessed, or being assessed. */
    const PENDING = 'pending';

    /** Assessed: the grade and feedback are ready. */
    const DONE = 'done';

    /** It could not be assessed, so a teacher will grade it. */
    const FAILED = 'failed';

    /**
     * Describe the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'questionattemptid' => new external_value(PARAM_INT, 'The id of the question attempt.'),
        ]);
    }

    /**
     * Get the status, for the person who gave the response, or someone who
     * can see everyone's grades.
     *
     * @param int $questionattemptid
     * @return array with "status".
     */
    public static function execute(int $questionattemptid): array {
        global $CFG, $DB, $USER;
        require_once($CFG->libdir . '/questionlib.php');

        ['questionattemptid' => $questionattemptid] = self::validate_parameters(
            self::execute_parameters(),
            ['questionattemptid' => $questionattemptid]
        );

        $row = $DB->get_record('question_attempts', ['id' => $questionattemptid], 'id, questionusageid, slot', MUST_EXIST);
        $quba = \question_engine::load_questions_usage_by_activity((int) $row->questionusageid);
        $qa = $quba->get_question_attempt((int) $row->slot);
        $context = $quba->get_owning_context();
        self::validate_context($context);
        if (!($qa->get_question(false) instanceof \qtype_speakup_question)) {
            throw new \moodle_exception('invalidrecord', 'error', '', 'question_attempts');
        }
        if ((int) $qa->get_step(0)->get_user_id() !== (int) $USER->id) {
            require_capability('moodle/grade:viewall', $context);
        }

        return ['status' => self::get_status($qa)];
    }

    /**
     * Get the status of a response.
     *
     * @param \question_attempt $qa
     * @return string PENDING, DONE or FAILED.
     */
    public static function get_status(\question_attempt $qa): string {
        global $DB;
        $status = $DB->get_field('qtype_speakup_response', 'aistatus', ['questionattemptid' => $qa->get_database_id()]);
        if ($status === self::FAILED) {
            return self::FAILED;
        }
        if ($status === self::PENDING || ($status === false && !$qa->get_state()->is_graded())) {
            return self::PENDING;
        }
        // Done; or not graded in the background at all.
        return self::DONE;
    }

    /**
     * Describe the result.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_ALPHA, 'pending, done or failed.'),
        ]);
    }
}
