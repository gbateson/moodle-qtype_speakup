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
use qtype_speakup\mobile_support;

/**
 * Get a single-use key for showing the response part of a SpeakUp question
 * in a frame in the Moodle app (see respond.php). The frame has no session,
 * since phones may not keep cookies in frames, so the key identifies the
 * student instead. It can be used once, within a few minutes.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_response_key extends external_api {

    /**
     * Describe the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'usage' => new external_value(PARAM_INT, 'The question usage id of the quiz attempt.'),
            'slot' => new external_value(PARAM_INT, 'The slot of the SpeakUp question in the attempt.'),
        ]);
    }

    /**
     * Get the key, for the current user's own quiz attempt, still in progress.
     *
     * @param int $usage
     * @param int $slot
     * @return array with "key".
     */
    public static function execute(int $usage, int $slot): array {
        global $USER;
        ['usage' => $usage, 'slot' => $slot] = self::validate_parameters(self::execute_parameters(),
                ['usage' => $usage, 'slot' => $slot]);

        [, $context] = mobile_support::get_question_attempt($usage, $slot);
        self::validate_context($context);

        return ['key' => mobile_support::create_response_key((int) $USER->id, $usage)];
    }

    /**
     * Describe the result.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'key' => new external_value(PARAM_ALPHANUM, 'A single-use key for respond.php.'),
        ]);
    }
}
