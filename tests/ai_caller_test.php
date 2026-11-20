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
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->dirroot . '/question/type/speakup/tests/helper.php');

/**
 * Tests for asking the AI, through the AI bridge, to assess and transcribe
 * responses. Requests get mocked responses (see curl::mock_response()).
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \qtype_speakup\ai_caller
 */
final class ai_caller_test extends \advanced_testcase {
    /** @var int the student's id. */
    protected $userid;

    /** @var int the context in which requests are made. */
    protected $contextid;

    /**
     * Set up a student, and allow requests to any host.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        \qtype_speakup_test_helper::allow_any_curl_url();
        $this->userid = (int) $this->getDataGenerator()->create_user()->id;
        $this->contextid = (int) \context_system::instance()->id;
        // Moodle's own AI is not used in these tests.
        set_config('coreroute', 'never', 'tool_aibridge');
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
     * Assess a transcript.
     *
     * @param int $providerid
     * @param string $transcript
     * @param \stored_file[] $files
     * @return array
     */
    protected function assess(int $providerid, string $transcript = 'Hello.', array $files = []): array {
        return ai_caller::assess($providerid, $this->contextid, $this->userid, 'Assess this.', $transcript, $files);
    }

    /**
     * Save some content as a file.
     *
     * @param string $filename
     * @param string $content
     * @return \stored_file
     */
    protected static function make_file(string $filename, string $content = 'content'): \stored_file {
        return get_file_storage()->create_file_from_string(['contextid' => \context_system::instance()->id,
                'component' => 'qtype_speakup', 'filearea' => 'test', 'itemid' => 0, 'filepath' => '/',
                'filename' => $filename], $content);
    }

    /**
     * A transcript is assessed by the question's provider, and by "Automatic".
     */
    public function test_assess(): void {
        $provider = \qtype_speakup_test_helper::create_provider();

        self::mock_chat_reply("Good use of the present perfect.\nSCORE: 80");
        [$fraction, $feedback] = $this->assess((int) $provider->id, 'I have played the guitar.');
        $this->assertEqualsWithDelta(0.8, $fraction, 0.0001);
        $this->assertSame('Good use of the present perfect.', $feedback);

        self::mock_chat_reply("SCORE: 60");
        [$fraction] = $this->assess(0);
        $this->assertEqualsWithDelta(0.6, $fraction, 0.0001);

        // Each request is logged by the AI bridge, for SpeakUp.
        $this->assertEquals(2, $GLOBALS['DB']->count_records(
            'tool_aibridge_log',
            ['component' => 'qtype_speakup', 'purpose' => 'assess', 'success' => 1]
        ));
    }

    /**
     * Templates written for SpeakUp's own providers get {{RUBRIC}} and
     * {{TRANSCRIPT}}; others get {{PROMPT}}, with the rubric, the
     * instructions and the transcript.
     */
    public function test_assess_tokens(): void {
        global $DB;
        set_config('logprompts', 1, 'tool_aibridge');
        $provider = \qtype_speakup_test_helper::create_provider(['requesttemplate' => '{"prompt": "{{PROMPT}}"}']);
        self::mock_chat_reply("SCORE: 70");
        $this->assess((int) $provider->id, 'My transcript.');
        $prompt = $DB->get_field('tool_aibridge_log', 'prompt', ['component' => 'qtype_speakup']);
        $this->assertStringStartsWith('Assess this.', $prompt);
        $this->assertStringContainsString(get_string('aipromptinstructions', 'qtype_speakup'), $prompt);
        $this->assertStringEndsWith(get_string('aiprompttranscriptlabel', 'qtype_speakup') . ' My transcript.', $prompt);

        $rubric = ai_caller::build_rubric_with_instructions('My rubric.');
        $this->assertStringStartsWith('My rubric.', $rubric);
        $this->assertStringEndsWith(get_string('aipromptinstructions', 'qtype_speakup'), $rubric);
    }

    /**
     * Failures say whether trying again later might work.
     */
    public function test_assess_failures(): void {
        $provider = \qtype_speakup_test_helper::create_provider();

        // No score.
        self::mock_chat_reply('Nice try.');
        try {
            $this->assess((int) $provider->id);
            $this->fail('Expected an exception');
        } catch (\moodle_exception $e) {
            $this->assertSame('erroraiscoreunparseable', $e->errorcode);
            $this->assertTrue(ai_request_exception::is_temporary($e));
        }

        // The provider cannot be reached: nothing listens on port 1.
        $unreachable = \qtype_speakup_test_helper::create_provider(['name' => 'Unreachable', 'url' => 'http://127.0.0.1:1/']);
        try {
            $this->assess((int) $unreachable->id);
            $this->fail('Expected an exception');
        } catch (ai_request_exception $e) {
            $this->assertSame('request', $e->bridgeerror);
            $this->assertTrue($e->temporary);
        }

        // The provider's settings are wrong: trying again will not help.
        $broken = \qtype_speakup_test_helper::create_provider(['name' => 'Broken', 'url' => '']);
        try {
            $this->assess((int) $broken->id);
            $this->fail('Expected an exception');
        } catch (ai_request_exception $e) {
            $this->assertSame('providerconfig', $e->bridgeerror);
            $this->assertFalse($e->temporary);
        }

        // No transcript and no files.
        $this->expectException(\moodle_exception::class);
        $this->assess((int) $provider->id, '');
    }

    /**
     * Responses are not sent until the student has accepted the AI policy.
     */
    public function test_assess_policy(): void {
        $provider = \qtype_speakup_test_helper::create_provider();
        set_config('requirepolicy', 1, 'tool_aibridge');
        try {
            $this->assess((int) $provider->id);
            $this->fail('Expected an exception');
        } catch (ai_request_exception $e) {
            $this->assertSame('policynotaccepted', $e->bridgeerror);
            $this->assertFalse($e->temporary);
            $this->assertStringContainsString(get_string('errorpolicynotaccepted', 'qtype_speakup'), $e->getMessage());
        }

        \tool_aibridge\policy::accept($this->userid, $this->contextid);
        self::mock_chat_reply("SCORE: 90");
        [$fraction] = $this->assess((int) $provider->id);
        $this->assertEqualsWithDelta(0.9, $fraction, 0.0001);
    }

    /**
     * A provider that takes files transcribes them, and any "TRANSCRIPT:" label is removed.
     */
    public function test_transcribe(): void {
        $provider = \qtype_speakup_test_helper::create_provider([
            'capability' => ai_provider::CAPABILITY_MULTIMODAL,
            'uploadstyle' => \tool_aibridge\provider::UPLOAD_INLINE_BASE64,
            'requesttemplate' => '{"prompt": "{{RUBRIC}}", "audio": "{{FILE_BASE64}}", "type": "{{FILE_MIMETYPE}}"}',
        ]);
        $file = self::make_file('a.mp3', 'ID3');

        self::mock_chat_reply('**TRANSCRIPT:** I have played the guitar.');
        $this->assertSame(
            'I have played the guitar.',
            ai_caller::transcribe((int) $provider->id, $this->contextid, $this->userid, [$file], 'English (en-US)')
        );

        // A text provider cannot take the file.
        $text = \qtype_speakup_test_helper::create_provider(['name' => 'Text only']);
        $this->expectException(ai_request_exception::class);
        ai_caller::transcribe((int) $text->id, $this->contextid, $this->userid, [$file], 'English (en-US)');
    }

    /**
     * Data for test_split_transcript().
     *
     * @return array
     */
    public static function split_transcript_provider(): array {
        return [
            'plain labels' => ["TRANSCRIPT: Hello there.\nFEEDBACK: Good.", ['Hello there.', 'Good.']],
            'bold labels' => ["**TRANSCRIPT:** Hello.\n\n**FEEDBACK:**\n* Speak up", ['Hello.', '* Speak up']],
            'empty transcript' => ["TRANSCRIPT:\nFEEDBACK: Nothing was said.", [null, 'Nothing was said.']],
            'no labels' => ['Just feedback.', [null, 'Just feedback.']],
        ];
    }

    /**
     * Test separating the transcript from the feedback.
     *
     * @dataProvider split_transcript_provider
     * @param string $content
     * @param array $expected
     */
    public function test_split_transcript(string $content, array $expected): void {
        $this->assertSame($expected, ai_caller::split_transcript($content));
    }

    /**
     * Test parsing the score and feedback.
     */
    public function test_parse_score_and_feedback(): void {
        $this->assertSame([0.75, 'Well done.'], ai_caller::parse_score_and_feedback("Well done.\nSCORE: 75"));
        $this->assertSame([1.0, 'Great.'], ai_caller::parse_score_and_feedback("Great.\nscore: 150"));
        $this->assertSame([0.0, 'Hmm.'], ai_caller::parse_score_and_feedback("Hmm.\nSCORE: 0"));
    }

    /**
     * A provider with {{FILE_PARTS}} is sent several images; with "Automatic",
     * the files go to a provider that takes files.
     */
    public function test_assess_several_files(): void {
        \qtype_speakup_test_helper::create_provider(['name' => 'Text only']);
        $provider = \qtype_speakup_test_helper::create_multimodal_provider();
        $files = [self::make_file('01-a.png'), self::make_file('02-b.png'), self::make_file('03-c.pdf')];

        \curl::mock_response(json_encode(['candidates' => [['content' => ['parts' => [['text' => "Neat work.\nSCORE: 90"]]]]]]));
        [$fraction, $feedback] = $this->assess((int) $provider->id, '', $files);
        $this->assertEqualsWithDelta(0.9, $fraction, 0.0001);
        $this->assertSame('Neat work.', $feedback);

        $this->assertTrue(ai_provider::takes_files(0, $this->contextid, $this->userid, $files));
        \curl::mock_response(json_encode(['candidates' => [['content' => ['parts' => [['text' => "SCORE: 50"]]]]]]));
        [$fraction] = $this->assess(0, '', $files);
        $this->assertEqualsWithDelta(0.5, $fraction, 0.0001);
    }

    /**
     * A template for one file cannot be sent several, and {{FILE_PARTS}}
     * needs a file part template.
     */
    public function test_assess_several_files_errors(): void {
        $files = [self::make_file('01-a.png'), self::make_file('02-b.png')];

        $provider = \qtype_speakup_test_helper::create_multimodal_provider([
            'requesttemplate' => '{"prompt": "{{RUBRIC}}", "data": "{{FILE_BASE64}}"}',
        ]);
        try {
            $this->assess((int) $provider->id, '', $files);
            $this->fail('Expected an exception');
        } catch (ai_request_exception $e) {
            $this->assertSame('templateonefile', $e->bridgeerror);
        }

        $provider = \qtype_speakup_test_helper::create_multimodal_provider(['name' => 'No parts', 'filetemplate' => '']);
        try {
            $this->assess((int) $provider->id, '', $files);
            $this->fail('Expected an exception');
        } catch (ai_request_exception $e) {
            $this->assertSame('filetemplatemissing', $e->bridgeerror);
        }
    }

    /**
     * Transcribing images uses the handwriting prompt.
     */
    public function test_transcribe_images(): void {
        $provider = \qtype_speakup_test_helper::create_multimodal_provider();
        $reply = ['candidates' => [['content' => ['parts' => [['text' => "TRANSCRIPT: Page 1: Hello."]]]]]];
        \curl::mock_response(json_encode($reply));
        $this->assertSame('Page 1: Hello.', ai_caller::transcribe(
            (int) $provider->id,
            $this->contextid,
            $this->userid,
            [self::make_file('01-a.png')],
            'English (en-US)',
            true
        ));
    }
}
