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
 * Tests for queueing responses to be assessed in the background.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \qtype_speakup\ai_queue
 */
final class ai_queue_test extends \advanced_testcase {

    /**
     * Each attempt is queued once, with its details.
     */
    public function test_queue(): void {
        $this->resetAfterTest();
        ai_queue::queue(10, 1, 2, 3);
        ai_queue::queue(10, 1, 2, 3);
        ai_queue::queue(10, 2, 2, 3);

        $tasks = \core\task\manager::get_adhoc_tasks(task\assess_response::class);
        $this->assertCount(2, $tasks);
        $slots = array_map(fn($task) => $task->get_custom_data()->slot, $tasks);
        $this->assertEqualsCanonicalizing([1, 2], array_values($slots));
        $data = reset($tasks)->get_custom_data();
        $this->assertEquals(10, $data->usageid);
        $this->assertEquals(2, $data->userid);
        $this->assertEquals(3, $data->contextid);
    }

    /**
     * Making a response's files smaller is queued once, and removed with its attempt.
     */
    public function test_queue_reduce_files(): void {
        $this->resetAfterTest();
        ai_queue::queue_reduce_files(10, 1, 3);
        ai_queue::queue_reduce_files(10, 1, 3);

        $tasks = \core\task\manager::get_adhoc_tasks(task\reduce_response_files::class);
        $this->assertCount(1, $tasks);
        $data = reset($tasks)->get_custom_data();
        $this->assertEquals(10, $data->usageid);
        $this->assertEquals(3, $data->contextid);

        $this->assertSame(1, task_cleanup::remove_assessments(10));
        $this->assertCount(0, \core\task\manager::get_adhoc_tasks(task\reduce_response_files::class));
    }
}
