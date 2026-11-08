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

namespace qtype_speakup\task;

use qtype_speakup\file_reducer;

/**
 * Adhoc task: replace a response's large files by smaller ones (see
 * \qtype_speakup\file_reducer), for questions that are graded by hand.
 * Questions graded in the background do this in assess_response, first.
 *
 * Queued by \qtype_speakup\observer::quiz_attempt_submitted().
 *
 * custom_data: usageid, slot and contextid.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reduce_response_files extends \core\task\adhoc_task {

    #[\Override]
    public function get_name() {
        return get_string('taskreduceresponsefiles', 'qtype_speakup');
    }

    #[\Override]
    public function execute() {
        global $CFG, $DB;
        require_once($CFG->libdir . '/questionlib.php');

        $data = $this->get_custom_data();
        $usageid = (int) $data->usageid;
        $slot = (int) $data->slot;

        if (!file_reducer::is_enabled() || !file_reducer::replaces_originals()) {
            mtrace('qtype_speakup: large response files are not replaced on this site.');
            return;
        }
        if (!\qtype_speakup\task_cleanup::response_exists($usageid, $slot)) {
            mtrace('qtype_speakup: the response at usage ' . $usageid . ' slot ' . $slot .
                    ' no longer exists (e.g. its attempt, or the question of a preview, was deleted).');
            return;
        }
        $quba = \question_engine::load_questions_usage_by_activity($usageid);
        if (!in_array($slot, $quba->get_slots())) {
            mtrace('qtype_speakup: usage ' . $usageid . ' has no slot ' . $slot . '.');
            return;
        }
        $count = file_reducer::reduce_response($quba->get_question_attempt($slot), (int) $data->contextid);
        mtrace('qtype_speakup: ' . $count . ' file(s) were made smaller for usage ' . $usageid . ' slot ' . $slot . '.');
    }
}
