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

use core_external\external_api;
use qtype_speakup\external\get_assessment_status;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/type/speakup/tests/helper.php');

/**
 * Tests for telling students, and their review page, when their response has been assessed.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \qtype_speakup\external\get_assessment_status
 * @covers    \qtype_speakup\assessment_notifier
 */
final class assessment_status_test extends \advanced_testcase {
    /** @var \stdClass the quiz. */
    protected $quiz;

    /** @var \stdClass the student. */
    protected $student;

    /** @var \stdClass the teacher. */
    protected $teacher;

    /** @var \stdClass the student's quiz attempt. */
    protected $attempt;

    /** @var \question_usage_by_activity the attempt's questions. */
    protected $quba;

    #[\Override]
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $this->teacher = $generator->create_and_enrol($course, 'editingteacher');
        $this->student = $generator->create_and_enrol($course, 'student');
        $this->quiz = $generator->create_module('quiz', ['course' => $course->id, 'name' => 'Speaking quiz']);

        /** @var \core_question_generator $questiongenerator */
        $questiongenerator = $generator->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category(['contextid' => \context_course::instance($course->id)->id]);
        $question = $questiongenerator->create_question('speakup', 'basic', ['category' => $category->id, 'name' => 'Guitar']);
        \qtype_speakup_test_helper::add_to_quiz($question->id, $this->quiz);
        $question = $questiongenerator->create_question('speakup', 'basic', ['category' => $category->id, 'name' => 'Piano']);
        \qtype_speakup_test_helper::add_to_quiz($question->id, $this->quiz);

        $this->setUser($this->student);
        [$this->attempt, $this->quba] = \qtype_speakup_test_helper::start_quiz_attempt($this->quiz, $this->student);
        // Submitted just now.
        $this->attempt->state = 'finished';
        $this->attempt->timefinish = time();
        $DB->update_record('quiz_attempts', $this->attempt);
    }

    /**
     * Get the status of the student's response, through the web service.
     *
     * @return string
     */
    protected function get_status(): string {
        $qaid = (int) $this->quba->get_question_attempt(1)->get_database_id();
        $result = get_assessment_status::execute($qaid);
        return external_api::clean_returnvalue(get_assessment_status::execute_returns(), $result)['status'];
    }

    /**
     * Save the result of the background assessment.
     *
     * @param string $status
     * @param int $slot
     */
    protected function set_status(string $status, int $slot = 1): void {
        global $DB;
        $qaid = (int) $this->quba->get_question_attempt($slot)->get_database_id();
        $record = $DB->get_record('qtype_speakup_response', ['questionattemptid' => $qaid]);
        if ($record) {
            $DB->set_field('qtype_speakup_response', 'aistatus', $status, ['id' => $record->id]);
        } else {
            $DB->insert_record('qtype_speakup_response', (object) ['questionattemptid' => $qaid, 'aistatus' => $status,
                    'timecreated' => time(), 'timemodified' => time()]);
        }
    }

    /**
     * The status follows the background assessment.
     */
    public function test_status(): void {
        $this->assertSame('pending', $this->get_status());
        $this->set_status('pending');
        $this->assertSame('pending', $this->get_status());
        $this->set_status('done');
        $this->assertSame('done', $this->get_status());
        $this->set_status('failed');
        $this->assertSame('failed', $this->get_status());

        // Teachers can check it too.
        $this->setUser($this->teacher);
        $this->assertSame('failed', $this->get_status());
    }

    /**
     * Other students cannot check someone else's response.
     */
    public function test_status_other_student(): void {
        $other = $this->getDataGenerator()->create_and_enrol(get_course($this->quiz->course), 'student');
        $this->setUser($other);
        $this->expectException(\required_capability_exception::class);
        $this->get_status();
    }

    /**
     * The student is told once that their responses have been assessed,
     * unless the quiz does not let them see the marks or the comments yet.
     */
    public function test_notify_done(): void {
        global $DB;
        $sink = $this->redirectMessages();
        $usageid = $this->quba->get_id();

        $this->set_status('done');
        $this->assertSame(1, assessment_notifier::notify_done($usageid));
        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $this->assertEquals($this->student->id, $messages[0]->useridto);
        $this->assertSame('assessmentdone', $messages[0]->eventtype);
        $this->assertSame('Your response has been assessed', $messages[0]->subject);
        $this->assertStringContainsString('Guitar', $messages[0]->fullmessage);
        $this->assertStringContainsString('Speaking quiz', $messages[0]->fullmessage);
        $this->assertStringContainsString('/mod/quiz/review.php?attempt=' . $this->attempt->id, $messages[0]->contexturl);

        // Not again for the same response.
        $sink->clear();
        $this->assertSame(0, assessment_notifier::notify_done($usageid));
        $this->assertCount(0, $sink->get_messages());

        // Marks and comments are not shown until the quiz closes.
        $DB->set_field('qtype_speakup_response', 'timenotified', null);
        $DB->update_record('quiz', (object) ['id' => $this->quiz->id, 'reviewmarks' => 0, 'reviewmaxmarks' => 0,
                'reviewspecificfeedback' => 0]);
        $this->assertSame(0, assessment_notifier::notify_done($usageid));
        $this->assertCount(0, $sink->get_messages());
        // The response is not told about later, either.
        $this->assertSame(0, $DB->count_records('qtype_speakup_response', ['timenotified' => null]));
    }

    /**
     * The student is told once for the attempt, when the last of its
     * responses has been assessed.
     */
    public function test_notify_done_attempt(): void {
        global $DB;
        $sink = $this->redirectMessages();
        $usageid = $this->quba->get_id();
        $contextid = \context_module::instance($this->quiz->cmid)->id;
        \qtype_speakup\ai_queue::queue($usageid, 1, (int) $this->student->id, $contextid);
        \qtype_speakup\ai_queue::queue($usageid, 2, (int) $this->student->id, $contextid);

        // The second response is still waiting.
        $this->set_status('done', 1);
        $this->assertSame(0, assessment_notifier::notify_done($usageid));
        $this->set_status('pending', 2);
        $this->assertSame(0, assessment_notifier::notify_done($usageid));
        $this->assertCount(0, $sink->get_messages());

        // Both assessed: one message about both.
        $this->set_status('done', 2);
        $this->assertSame(1, assessment_notifier::notify_done($usageid));
        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $this->assertSame('Your responses have been assessed', $messages[0]->subject);
        $this->assertStringContainsString('2 questions', $messages[0]->fullmessage);

        // A response that could not be assessed is not waiting, and is not told about.
        $sink->clear();
        $DB->set_field('qtype_speakup_response', 'timenotified', null);
        $this->set_status('failed', 2);
        $this->assertSame(1, assessment_notifier::notify_done($usageid));
        $messages = $sink->get_messages();
        $this->assertSame('Your response has been assessed', $messages[0]->subject);
        $this->assertStringContainsString('Guitar', $messages[0]->fullmessage);
    }
}
