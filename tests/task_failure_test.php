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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/type/speakup/tests/helper.php');

/**
 * Tests for telling teachers about background tasks that failed.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \qtype_speakup\task_failure
 */
final class task_failure_test extends \advanced_testcase {

    /**
     * When a quiz response could not be assessed, the course's teachers
     * are sent a message, with a link to grade it, but students are not.
     */
    public function test_notify_assessment_failed(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $student = $generator->create_and_enrol($course, 'student', ['firstname' => 'Sam', 'lastname' => 'Student']);
        $quiz = $generator->create_module('quiz', ['course' => $course->id, 'name' => 'Speaking quiz']);

        /** @var \core_question_generator $questiongenerator */
        $questiongenerator = $generator->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category(['contextid' => \context_course::instance($course->id)->id]);
        $question = $questiongenerator->create_question('speakup', 'basic', ['category' => $category->id, 'name' => 'Guitar']);
        \qtype_speakup_test_helper::add_to_quiz($question->id, $quiz);

        $this->setUser($student);
        [$attempt, $quba] = \qtype_speakup_test_helper::start_quiz_attempt($quiz, $student);

        $sink = $this->redirectMessages();
        $count = task_failure::notify_assessment_failed($quba->get_question_attempt(1), $quba->get_id(), 1,
                (int) $student->id, 'HTTP 503: The model is overloaded');

        $this->assertSame(1, $count);
        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $message = $messages[0];
        $this->assertEquals($teacher->id, $message->useridto);
        $this->assertSame('qtype_speakup', $message->component);
        $this->assertSame('assessmentfailed', $message->eventtype);
        $this->assertStringContainsString('Sam Student', $message->fullmessage);
        $this->assertStringContainsString('Guitar', $message->fullmessage);
        $this->assertStringContainsString('Speaking quiz', $message->fullmessage);
        $this->assertStringContainsString('HTTP 503: The model is overloaded', $message->fullmessage);
        $this->assertStringContainsString('/mod/quiz/comment.php?attempt=' . $attempt->id . '&slot=1',
                $message->contexturl);
    }

    /**
     * Error messages are kept short, and say what kind of error they are
     * when that is not obvious.
     */
    public function test_describe(): void {
        $this->assertSame(get_string('errorproviderconfig', 'qtype_speakup'),
                task_failure::describe(new \moodle_exception('errorproviderconfig', 'qtype_speakup')));
        $this->assertSame('RuntimeException: Oops', task_failure::describe(new \RuntimeException('Oops')));
        $long = task_failure::describe(new \RuntimeException(str_repeat('x', 5000)));
        $this->assertSame(task_failure::MAX_LENGTH, \core_text::strlen($long));
    }
}
