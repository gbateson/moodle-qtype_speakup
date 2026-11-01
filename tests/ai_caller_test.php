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
 * Tests for calling AI providers. Requests to external providers get
 * mocked responses (see curl::mock_response()).
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \qtype_speakup\ai_caller
 */
final class ai_caller_test extends \advanced_testcase {

    /**
     * Call a protected static method of ai_caller.
     *
     * @param string $name
     * @param mixed ...$args
     * @return mixed
     */
    protected static function call(string $name, ...$args) {
        $method = new \ReflectionMethod(ai_caller::class, $name);
        return $method->invoke(null, ...$args);
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
     * Test assessing a transcript with an external provider.
     */
    public function test_assess_external(): void {
        $this->resetAfterTest();
        \qtype_speakup_test_helper::allow_any_curl_url();
        $provider = \qtype_speakup_test_helper::create_provider();

        self::mock_chat_reply("Good use of the present perfect.\nSCORE: 80");
        [$fraction, $feedback] = ai_caller::assess($provider, 1, 2, 'Assess this.', 'I have played the guitar.');
        $this->assertEqualsWithDelta(0.8, $fraction, 0.0001);
        $this->assertSame('Good use of the present perfect.', $feedback);
    }

    /**
     * A reply without a score is an error.
     */
    public function test_assess_without_score(): void {
        $this->resetAfterTest();
        \qtype_speakup_test_helper::allow_any_curl_url();
        $provider = \qtype_speakup_test_helper::create_provider();

        self::mock_chat_reply('Nice try.');
        $this->expectException(\moodle_exception::class);
        ai_caller::assess($provider, 1, 2, 'Assess this.', 'Hello.');
    }

    /**
     * A reply without the expected path is an error.
     */
    public function test_assess_wrong_return_path(): void {
        $this->resetAfterTest();
        \qtype_speakup_test_helper::allow_any_curl_url();
        $provider = \qtype_speakup_test_helper::create_provider(['returnpath' => 'output,text']);

        self::mock_chat_reply("SCORE: 50");
        $this->expectException(\moodle_exception::class);
        ai_caller::assess($provider, 1, 2, 'Assess this.', 'Hello.');
    }

    /**
     * A provider without a URL or request template is an error.
     */
    public function test_assess_misconfigured(): void {
        $this->resetAfterTest();
        $provider = \qtype_speakup_test_helper::create_provider(['url' => '']);
        $this->expectException(\moodle_exception::class);
        ai_caller::assess($provider, 1, 2, 'Assess this.', 'Hello.');
    }

    /**
     * Only multimodal external providers can transcribe.
     */
    public function test_transcribe_needs_multimodal(): void {
        $this->resetAfterTest();
        $provider = \qtype_speakup_test_helper::create_provider();
        $file = get_file_storage()->create_file_from_string(['contextid' => \context_system::instance()->id,
                'component' => 'qtype_speakup', 'filearea' => 'test', 'itemid' => 0, 'filepath' => '/',
                'filename' => 'a.mp3'], 'ID3');
        $this->expectException(\moodle_exception::class);
        ai_caller::transcribe($provider, $file, 'English (en-US)');
    }

    /**
     * A multimodal provider transcribes a file sent inline, and any
     * "TRANSCRIPT:" label is removed.
     */
    public function test_transcribe(): void {
        $this->resetAfterTest();
        \qtype_speakup_test_helper::allow_any_curl_url();
        $provider = \qtype_speakup_test_helper::create_provider([
            'capability' => ai_provider::CAPABILITY_MULTIMODAL,
            'uploadstyle' => ai_provider::UPLOAD_INLINE_BASE64,
            'requesttemplate' => '{"prompt": "{{RUBRIC}}", "audio": "{{AUDIO_BASE64}}", "type": "{{AUDIO_MIMETYPE}}"}',
        ]);
        $file = get_file_storage()->create_file_from_string(['contextid' => \context_system::instance()->id,
                'component' => 'qtype_speakup', 'filearea' => 'test', 'itemid' => 0, 'filepath' => '/',
                'filename' => 'a.mp3'], 'ID3');

        self::mock_chat_reply('**TRANSCRIPT:** I have played the guitar.');
        $this->assertSame('I have played the guitar.', ai_caller::transcribe($provider, $file, 'English (en-US)'));
    }

    /**
     * A multimodal provider's template must have a slot for the recording.
     */
    public function test_template_missing_audio(): void {
        $this->resetAfterTest();
        $provider = \qtype_speakup_test_helper::create_provider([
            'capability' => ai_provider::CAPABILITY_MULTIMODAL,
            'uploadstyle' => ai_provider::UPLOAD_INLINE_BASE64,
        ]);
        $file = get_file_storage()->create_file_from_string(['contextid' => \context_system::instance()->id,
                'component' => 'qtype_speakup', 'filearea' => 'test', 'itemid' => 0, 'filepath' => '/',
                'filename' => 'a.mp3'], 'ID3');
        $this->expectException(\moodle_exception::class);
        ai_caller::transcribe($provider, $file, 'English (en-US)');
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
        $this->assertSame([0.75, 'Well done.'], self::call('parse_score_and_feedback', "Well done.\nSCORE: 75"));
        $this->assertSame([1.0, 'Great.'], self::call('parse_score_and_feedback', "Great.\nscore: 150"));
        $this->assertSame([0.0, 'Hmm.'], self::call('parse_score_and_feedback', "Hmm.\nSCORE: 0"));
    }

    /**
     * Test finding a value in a JSON reply.
     */
    public function test_extract_by_path(): void {
        $data = ['choices' => [['message' => ['content' => 'Hi']]]];
        $this->assertSame('Hi', self::call('extract_by_path', $data, 'choices, 0, message, content'));
        $this->assertNull(self::call('extract_by_path', $data, 'choices,1'));
        $this->assertNull(self::call('extract_by_path', $data, ''));
    }

    /**
     * Tokens are inserted as they are in URLs, and JSON-escaped in request bodies.
     */
    public function test_substitute(): void {
        $tokens = ['MODEL' => 'm1', 'TEXT' => "Say \"hi\"\nnow"];
        $this->assertSame('https://x/m1?t=Say "hi"' . "\n" . 'now',
                self::call('substitute_plain', 'https://x/{{MODEL}}?t={{TEXT}}', $tokens));
        $body = self::call('substitute_escaped', '{"model": "{{MODEL}}", "input": "{{TEXT}}"}', $tokens);
        $this->assertSame(['model' => 'm1', 'input' => "Say \"hi\"\nnow"], json_decode($body, true));
    }

    /**
     * The rubric always ends with the instruction for the score's format.
     */
    public function test_build_prompt(): void {
        $prompt = self::call('build_prompt', 'My rubric.', 'My transcript.');
        $this->assertStringStartsWith('My rubric.', $prompt);
        $this->assertStringContainsString(get_string('aipromptinstructions', 'qtype_speakup'), $prompt);
        $this->assertStringEndsWith('My transcript.', $prompt);
    }
}
