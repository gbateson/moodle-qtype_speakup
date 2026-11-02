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

use question_bank;
use question_engine;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/type/speakup/tests/helper.php');

/**
 * Tests for grading responses in the background: with the AI's score,
 * target phrases and items, and the transcript penalty. The AI's replies
 * are mocked (see curl::mock_response()).
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \qtype_speakup\task\assess_response
 */
final class assess_response_test extends \advanced_testcase {

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        \qtype_speakup_test_helper::allow_any_curl_url();
    }

    /**
     * Create a question.
     *
     * @param string $which
     * @param array $overrides
     * @return int the question id.
     */
    protected function create_question(string $which, array $overrides = []): int {
        /** @var \core_question_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category();
        return $generator->create_question('speakup', $which, ['category' => $category->id] + $overrides)->id;
    }

    /**
     * Attempt a question as the current user, and finish the attempt.
     *
     * @param int $questionid
     * @param string $transcript the live transcript.
     * @param array $extra other response data, e.g. prompttranscriptviewed.
     * @return array [usage id, slot]
     */
    protected function attempt(int $questionid, string $transcript, array $extra = [],
            array $filenames = ['recording.mp3']): array {
        global $USER;
        $quba = question_engine::make_questions_usage_by_activity('core_question_preview', \context_system::instance());
        $quba->set_preferred_behaviour('deferredfeedback');
        $slot = $quba->add_question(question_bank::load_question($questionid), 1);
        $quba->start_all_questions();

        $draftid = file_get_unused_draft_itemid();
        foreach ($filenames as $filename) {
            get_file_storage()->create_file_from_string(['contextid' => \context_user::instance($USER->id)->id,
                    'component' => 'user', 'filearea' => 'draft', 'itemid' => $draftid, 'filepath' => '/',
                    'filename' => $filename], "ID3\x04\x00\x00\x00\x00\x00\x00content");
        }
        $quba->process_action($slot, [
            'recording' => new \question_file_saver($draftid, 'question', 'response_recording'),
            'browsertranscript' => $transcript,
        ] + $extra);
        $quba->finish_all_questions();
        question_engine::save_questions_usage_by_activity($quba);
        return [$quba->get_id(), $slot];
    }

    /**
     * Run the task for an attempt.
     *
     * @param int $usageid
     * @param int $slot
     * @return \question_attempt the attempt, reloaded.
     */
    protected function assess(int $usageid, int $slot): \question_attempt {
        global $USER;
        $task = new assess_response();
        $task->set_custom_data(['usageid' => $usageid, 'slot' => $slot, 'userid' => $USER->id,
                'contextid' => \context_system::instance()->id]);
        ob_start();
        try {
            $task->execute();
        } finally {
            ob_end_clean();
        }
        return question_engine::load_questions_usage_by_activity($usageid)->get_question_attempt($slot);
    }

    /**
     * Get the stored result of the assessment.
     *
     * @param \question_attempt $qa
     * @return \stdClass
     */
    protected function get_record(\question_attempt $qa): \stdClass {
        global $DB;
        return $DB->get_record('qtype_speakup_response', ['questionattemptid' => $qa->get_database_id()], '*', MUST_EXIST);
    }

    /**
     * Mock the reply of an OpenAI-style chat provider.
     *
     * @param string $content
     */
    protected static function mock_chat_reply(string $content): void {
        \curl::mock_response(json_encode(['choices' => [['message' => ['content' => $content]]]]));
    }

    /**
     * The grade is the AI's score multiplied by its weighting, and the
     * AI's feedback is the comment.
     */
    public function test_ai_only(): void {
        $this->setUser($this->getDataGenerator()->create_user());
        $provider = \qtype_speakup_test_helper::create_provider();
        $questionid = $this->create_question('basic', [
            'aiassessment' => 1, 'aiproviderid' => $provider->id, 'aiweighting' => 50,
            'phrasematch' => ['', '', ''], 'itemcount' => [0],
        ]);
        [$usageid, $slot] = $this->attempt($questionid, 'I have played the guitar.');

        self::mock_chat_reply("Good use of the present perfect.\nSCORE: 80");
        $qa = $this->assess($usageid, $slot);

        $this->assertEqualsWithDelta(0.4, $qa->get_mark(), 0.0001);
        $this->assertSame('Good use of the present perfect.', $qa->get_manual_comment()[0]);
        $record = $this->get_record($qa);
        $this->assertSame('done', $record->aistatus);
        $this->assertEqualsWithDelta(0.8, $record->aiscore, 0.0001);
        $this->assertSame('I have played the guitar.', $record->transcript);
    }

    /**
     * The grade adds the target phrases and items to the AI's score.
     */
    public function test_ai_with_phrases_and_items(): void {
        $this->setUser($this->getDataGenerator()->create_user());
        $provider = \qtype_speakup_test_helper::create_provider();
        $questionid = $this->create_question('basic', [
            'aiassessment' => 1, 'aiproviderid' => $provider->id, 'aiweighting' => 10,
        ]);
        [$usageid, $slot] = $this->attempt($questionid, 'I have played the guitar.');

        self::mock_chat_reply("SCORE: 80");
        $qa = $this->assess($usageid, $slot);

        // Phrases: guitar 40% + "I have played" 30%. Items: 5 of 20 words, of 30%. AI: 80% of 10%.
        $this->assertEqualsWithDelta(0.40 + 0.30 + 0.075 + 0.08, $qa->get_mark(), 0.0001);
    }

    /**
     * If the AI's reply cannot be used, the attempt is left for a teacher to grade.
     */
    public function test_ai_failure(): void {
        $this->setUser($this->getDataGenerator()->create_user());
        $provider = \qtype_speakup_test_helper::create_provider();
        $questionid = $this->create_question('basic', ['aiassessment' => 1, 'aiproviderid' => $provider->id]);
        [$usageid, $slot] = $this->attempt($questionid, 'Hello.');

        self::mock_chat_reply('I cannot score this.');
        $qa = $this->assess($usageid, $slot);

        $this->assertNull($qa->get_mark());
        $this->assertSame('needsgrading', $qa->get_state()->__toString());
        $this->assertSame('failed', $this->get_record($qa)->aistatus);
    }

    /**
     * Showing the audio prompt transcript takes the penalty off the grade,
     * unless the student has the exemption capability.
     */
    public function test_transcript_penalty(): void {
        $student = $this->getDataGenerator()->create_user();
        $this->setUser($student);
        $questionid = $this->create_question('prompt');

        // Not viewed: no penalty. Phrases 70% + items 7.5%.
        [$usageid, $slot] = $this->attempt($questionid, 'I have played the guitar.', ['prompttranscriptviewed' => 0]);
        $this->assertEqualsWithDelta(0.775, $this->assess($usageid, $slot)->get_mark(), 0.0001);

        // Viewed: 30% off.
        [$usageid, $slot] = $this->attempt($questionid, 'I have played the guitar.', ['prompttranscriptviewed' => 1]);
        $this->assertEqualsWithDelta(0.475, $this->assess($usageid, $slot)->get_mark(), 0.0001);

        // Viewed, but exempt.
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability(\qtype_speakup_question::TRANSCRIPT_PENALTY_EXEMPT, CAP_ALLOW, $roleid,
                \context_system::instance()->id);
        role_assign($roleid, $student->id, \context_system::instance()->id);
        [$usageid, $slot] = $this->attempt($questionid, 'I have played the guitar.', ['prompttranscriptviewed' => 1]);
        $this->assertEqualsWithDelta(0.775, $this->assess($usageid, $slot)->get_mark(), 0.0001);
    }

    /**
     * A question that is no longer graded in the background is skipped.
     */
    public function test_not_graded_later(): void {
        global $DB;
        $this->setUser($this->getDataGenerator()->create_user());
        $questionid = $this->create_question('manual');
        [$usageid, $slot] = $this->attempt($questionid, 'Hello.');
        $qa = $this->assess($usageid, $slot);
        $this->assertNull($qa->get_mark());
        $this->assertFalse($DB->record_exists('qtype_speakup_response', ['questionattemptid' => $qa->get_database_id()]));
    }

    /**
     * The pages of an image response are sent to the AI together, and the
     * target phrases and items are checked in its transcript.
     */
    public function test_image_response(): void {
        global $DB;
        $this->setUser($this->getDataGenerator()->create_user());
        $provider = \qtype_speakup_test_helper::create_multimodal_provider();
        $questionid = $this->create_question('image', [
            'aiassessment' => 1, 'aiproviderid' => $provider->id, 'aiweighting' => 10,
        ]);
        [$usageid, $slot] = $this->attempt($questionid, '', [], ['01-page.png', '02-page.pdf']);

        \curl::mock_response(json_encode(['candidates' => [['content' => ['parts' => [['text' =>
                "TRANSCRIPT: Page 1: I have played the guitar.\nFEEDBACK: Good handwriting.\nSCORE: 80"]]]]]]));
        $qa = $this->assess($usageid, $slot);

        // Phrases: guitar 40% + "I have played" 30%. Items: 7 of 20 words, of 30%. AI: 80% of 10%.
        $this->assertEqualsWithDelta(0.40 + 0.30 + 0.30 * 7 / 20 + 0.08, $qa->get_mark(), 0.0001);
        $this->assertSame('Good handwriting.', $qa->get_manual_comment()[0]);
        $record = $this->get_record($qa);
        $this->assertSame('Page 1: I have played the guitar.', $record->aitranscript);
    }
}
