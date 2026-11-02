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

use question_engine;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/question/type/speakup/tests/helper.php');

/**
 * Tests for SpeakUp questions in the Moodle app.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \qtype_speakup\mobile_support
 * @covers    \qtype_speakup\output\mobile
 */
final class mobile_support_test extends \advanced_testcase {

    /**
     * Meet all the conditions for SpeakUp in the app.
     */
    protected function meet_conditions(): void {
        global $CFG;
        set_config('mobileapp', 1, 'qtype_speakup');
        set_config('enablewebservices', 1);
        set_config('enablemobilewebservice', 1);
        set_config('allowframembedding', 1);
        // Secure cookies need HTTPS.
        $CFG->wwwroot = str_replace('http://', 'https://', $CFG->wwwroot);
        $CFG->cookiesecure = 1;
    }

    /**
     * SpeakUp is supported only if every condition is met, and the app is recent enough.
     */
    public function test_conditions(): void {
        $this->resetAfterTest();
        $this->meet_conditions();
        $this->assertSame(['mobileapp' => true, 'mobileservice' => true, 'framembedding' => true, 'securecookies' => true],
                mobile_support::get_conditions());
        $this->assertTrue(mobile_support::is_supported());
        $this->assertTrue(mobile_support::is_supported(44000));
        $this->assertFalse(mobile_support::is_supported(39000));
        $this->assertStringContainsString(get_string('mobilesupported', 'qtype_speakup'), mobile_support::get_status_html());

        foreach (['mobileapp' => 'qtype_speakup', 'allowframembedding' => null, 'enablemobilewebservice' => null] as $name => $plugin) {
            set_config($name, 0, $plugin);
            $this->assertFalse(mobile_support::is_supported(), $name);
            $this->assertStringContainsString(get_string('mobilenotsupported', 'qtype_speakup'),
                    mobile_support::get_status_html());
            set_config($name, 1, $plugin);
        }

        global $CFG;
        $CFG->cookiesecure = 0;
        $this->assertFalse(mobile_support::get_conditions()['securecookies']);
        $this->assertFalse(mobile_support::is_supported());
    }

    /**
     * The app's init method switches SpeakUp off when it is not supported.
     */
    public function test_mobile_init(): void {
        $this->resetAfterTest();
        $this->assertTrue(output\mobile::mobile_init(['appversioncode' => 44000])['disabled']);
        $this->meet_conditions();
        $this->assertFalse(output\mobile::mobile_init(['appversioncode' => 44000])['disabled']);
        $this->assertTrue(output\mobile::mobile_init(['appversioncode' => 38000])['disabled']);
    }

    /**
     * The app gets a template, which shows a frame for the response, and the JavaScript.
     */
    public function test_mobile_get_speakup(): void {
        $result = output\mobile::mobile_get_speakup([]);
        $this->assertSame('main', $result['templates'][0]['id']);
        $this->assertStringContainsString('question.speakupFrameId', $result['templates'][0]['html']);
        $this->assertStringContainsString('respond.php', $result['javascript']);
        $this->assertStringContainsString('componentInit', $result['javascript']);
    }

    /**
     * Start a quiz attempt with a SpeakUp question and a short-answer question.
     *
     * @param \stdClass $user
     * @return array [usage id, quiz]
     */
    protected function start_attempt(\stdClass $user): array {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $generator->enrol_user($user->id, $course->id, 'student');
        $quiz = $generator->create_module('quiz', ['course' => $course->id, 'sumgrades' => 2]);
        /** @var \core_question_generator $qgenerator */
        $qgenerator = $generator->get_plugin_generator('core_question');
        $category = $qgenerator->create_question_category(['contextid' => \context_module::instance($quiz->cmid)->id]);
        $speakup = $qgenerator->create_question('speakup', 'basic', ['category' => $category->id]);
        $shortanswer = $qgenerator->create_question('shortanswer', null, ['category' => $category->id]);
        quiz_add_quiz_question($speakup->id, $quiz);
        quiz_add_quiz_question($shortanswer->id, $quiz);

        $quizobj = \mod_quiz\quiz_settings::create($quiz->id, $user->id);
        $quba = question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
        $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);
        $timenow = time();
        $attempt = quiz_create_attempt($quizobj, 1, false, $timenow, false, $user->id);
        quiz_start_new_attempt($quizobj, $quba, $attempt, 1, $timenow);
        quiz_attempt_save_started($quizobj, $quba, $attempt);
        return [$quba->get_id(), $quiz];
    }

    /**
     * Students can respond in the frame only to their own SpeakUp questions,
     * in attempts that are still in progress.
     */
    public function test_get_question_attempt(): void {
        global $DB;
        $this->resetAfterTest();
        $student = $this->getDataGenerator()->create_user();
        $this->setUser($student);
        [$usageid] = $this->start_attempt($student);

        [$qa, $context] = mobile_support::get_question_attempt($usageid, 1);
        $this->assertInstanceOf(\qtype_speakup_question::class, $qa->get_question());
        $this->assertSame(CONTEXT_MODULE, $context->contextlevel);

        // Not a SpeakUp question, or no such slot.
        foreach ([2, 99] as $slot) {
            try {
                mobile_support::get_question_attempt($usageid, $slot);
                $this->fail("Slot $slot should not be allowed");
            } catch (\moodle_exception $e) {
                $this->assertSame('mobilenoattempt', $e->errorcode);
            }
        }

        // Someone else's attempt.
        $this->setUser($this->getDataGenerator()->create_user());
        try {
            mobile_support::get_question_attempt($usageid, 1);
            $this->fail('Another user should not be allowed');
        } catch (\moodle_exception $e) {
            $this->assertSame('mobilenoattempt', $e->errorcode);
        }

        // A finished attempt.
        $this->setUser($student);
        $DB->set_field('quiz_attempts', 'state', 'finished', ['uniqueid' => $usageid]);
        $this->expectException(\moodle_exception::class);
        mobile_support::get_question_attempt($usageid, 1);
    }

    /**
     * The response part, for the app's frame, uses the app's draft file
     * area, and leaves out the question text.
     */
    public function test_mobile_response(): void {
        global $PAGE;
        $this->resetAfterTest();
        $student = $this->getDataGenerator()->create_user();
        $this->setUser($student);
        [$usageid] = $this->start_attempt($student);
        [$qa, $context] = mobile_support::get_question_attempt($usageid, 1);

        $PAGE->set_url('/question/type/speakup/respond.php');
        $PAGE->set_context($context);
        $options = new \question_display_options();
        $options->context = $context;
        $html = $PAGE->get_renderer('qtype_speakup')->mobile_response($qa, $options, 123456789);

        $this->assertStringContainsString('id="' . $qa->get_outer_question_div_unique_id() . '"', $html);
        $this->assertStringContainsString('name="' . $qa->get_qt_field_name('recording') . '" value="123456789"', $html);
        $this->assertStringNotContainsString('class="qtext"', $html);
        $this->assertStringContainsString('qtype_speakup-audio-widget', $html);
    }
}
