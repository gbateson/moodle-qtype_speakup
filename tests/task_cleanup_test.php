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

use qtype_speakup\task\assess_response;
use qtype_speakup\task\generate_audio_prompt;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/question/type/speakup/tests/helper.php');

/**
 * Tests for removing background tasks that are no longer needed, when
 * attempts, quizzes, courses or questions are deleted.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \qtype_speakup\task_cleanup
 * @covers    \qtype_speakup\observer
 */
final class task_cleanup_test extends \advanced_testcase {

    /** @var \stdClass the course. */
    protected $course;

    /** @var \stdClass the quiz. */
    protected $quiz;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course();
        $this->quiz = $generator->create_module('quiz', ['course' => $this->course->id]);
        /** @var \core_question_generator $questiongenerator */
        $questiongenerator = $generator->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category(
                ['contextid' => \context_course::instance($this->course->id)->id]);
        $question = $questiongenerator->create_question('speakup', 'basic', ['category' => $category->id]);
        \qtype_speakup_test_helper::add_to_quiz($question->id, $this->quiz);
    }

    /**
     * Start an attempt at the quiz, and queue a task to assess it.
     *
     * @return \stdClass the quiz_attempts record.
     */
    protected function attempt_and_queue(): \stdClass {
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        [$attempt, $quba] = \qtype_speakup_test_helper::start_quiz_attempt($this->quiz, $student);
        ai_queue::queue((int) $quba->get_id(), 1, (int) $student->id, (int) $quba->get_owning_context()->id);
        return $attempt;
    }

    /**
     * Get the usage ids of the queued assessment tasks.
     *
     * @return int[]
     */
    protected function queued_usages(): array {
        $ids = [];
        foreach (\core\task\manager::get_adhoc_tasks(assess_response::class) as $task) {
            $ids[] = (int) $task->get_custom_data()->usageid;
        }
        sort($ids);
        return $ids;
    }

    /**
     * Deleting an attempt removes its tasks, but not other attempts' tasks.
     */
    public function test_attempt_deleted(): void {
        $attempt1 = $this->attempt_and_queue();
        $attempt2 = $this->attempt_and_queue();
        $this->assertSame([(int) $attempt1->uniqueid, (int) $attempt2->uniqueid], $this->queued_usages());

        quiz_delete_attempt($attempt1, $this->quiz);
        $this->assertSame([(int) $attempt2->uniqueid], $this->queued_usages());
    }

    /**
     * Deleting a quiz removes the tasks for all its attempts.
     */
    public function test_quiz_deleted(): void {
        $this->attempt_and_queue();
        $this->attempt_and_queue();
        $this->assertCount(2, $this->queued_usages());

        course_delete_module($this->quiz->cmid);
        $this->assertSame([], $this->queued_usages());
    }

    /**
     * Resetting a course, with its quiz attempts, removes their tasks.
     */
    public function test_course_reset(): void {
        $this->attempt_and_queue();
        $this->assertCount(1, $this->queued_usages());

        reset_course_userdata((object) ['id' => $this->course->id, 'reset_quiz_attempts' => 1,
                'reset_start_date_old' => $this->course->startdate, 'reset_end_date_old' => $this->course->enddate]);
        $this->assertSame([], $this->queued_usages());
    }

    /**
     * Deleting a course removes the tasks for its attempts and questions.
     */
    public function test_course_deleted(): void {
        $this->attempt_and_queue();
        $this->assertCount(1, $this->queued_usages());

        delete_course($this->course, false);
        $this->assertSame([], $this->queued_usages());
    }

    /**
     * Deleting a question removes the task that generates its audio prompt.
     */
    public function test_question_deleted(): void {
        \qtype_speakup_test_helper::create_speech_provider();
        /** @var \core_question_generator $questiongenerator */
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category();
        $question1 = $questiongenerator->create_question('speakup', 'prompt', ['category' => $category->id]);
        $question2 = $questiongenerator->create_question('speakup', 'prompt', ['category' => $category->id]);
        $this->assertTrue(generate_audio_prompt::is_pending($question1->id));
        $this->assertTrue(generate_audio_prompt::is_pending($question2->id));

        question_delete_question($question1->id);
        $this->assertFalse(generate_audio_prompt::is_pending($question1->id));
        $this->assertTrue(generate_audio_prompt::is_pending($question2->id));
    }

    /**
     * A task that is already running is left alone.
     */
    public function test_running_task_kept(): void {
        global $DB;
        $attempt = $this->attempt_and_queue();
        $DB->set_field('task_adhoc', 'timestarted', time(), ['classname' => '\\' . assess_response::class]);

        $this->assertSame(0, task_cleanup::remove_assessments((int) $attempt->uniqueid));
        $this->assertSame([(int) $attempt->uniqueid], $this->queued_usages());
    }
}
