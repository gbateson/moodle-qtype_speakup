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
     * The file part template is repeated for each file, each followed by a comma.
     */
    public function test_build_file_parts(): void {
        $this->resetAfterTest();
        $files = [self::make_file('01-a.png'), self::make_file('02-b.jpg')];
        $tokens = [
            ['FILE_MIMETYPE' => 'image/png', 'FILE_BASE64' => 'AAAA', 'FILE_NAME' => '01-a.png'],
            ['FILE_MIMETYPE' => 'image/jpeg', 'FILE_BASE64' => 'BBBB', 'FILE_NAME' => '02-b.jpg'],
        ];
        $parts = ai_caller::build_file_parts('{"mime": "{{FILE_MIMETYPE}}", "data": "{{FILE_BASE64}}"}', $files, $tokens);
        $json = json_decode('[' . $parts . '{"text": "prompt"}]', true);
        $this->assertSame([
            ['mime' => 'image/png', 'data' => 'AAAA'],
            ['mime' => 'image/jpeg', 'data' => 'BBBB'],
            ['text' => 'prompt'],
        ], $json);
        $this->assertSame('', ai_caller::build_file_parts('{"data": "{{FILE_BASE64}}"}', [], []));
    }

    /**
     * A file part template can have a different part for each kind of file.
     */
    public function test_choose_file_part(): void {
        $template = '{"image/*": {"type": "image"}, "application/pdf": {"type": "document"}, "*": {"type": "other"}}';
        $this->assertSame('{"type":"image"}', ai_caller::choose_file_part($template, 'image/jpeg'));
        $this->assertSame('{"type":"document"}', ai_caller::choose_file_part($template, 'application/pdf'));
        $this->assertSame('{"type":"other"}', ai_caller::choose_file_part($template, 'audio/mpeg'));
        // One part, for any file.
        $this->assertSame('{"data": "{{FILE_BASE64}}"}', ai_caller::choose_file_part('{"data": "{{FILE_BASE64}}"}', 'audio/mpeg'));
        $this->assertTrue(ai_caller::is_mimetype_map(['image/*' => ['a' => 1]]));
        $this->assertFalse(ai_caller::is_mimetype_map(['inline_data' => ['a' => 1]]));
        $this->assertFalse(ai_caller::is_mimetype_map([['a' => 1]]));
    }

    /**
     * A file with no matching part, or a template that is not JSON, is an error.
     *
     * @dataProvider choose_file_part_errors_provider
     * @param string $template
     * @param string $mimetype
     */
    public function test_choose_file_part_errors(string $template, string $mimetype): void {
        $this->expectException(\moodle_exception::class);
        ai_caller::choose_file_part($template, $mimetype);
    }

    /**
     * Data for test_choose_file_part_errors().
     *
     * @return array
     */
    public static function choose_file_part_errors_provider(): array {
        return [
            'no part for audio' => ['{"image/*": {"type": "image"}}', 'audio/mpeg'],
            'not JSON' => ['{"data": {{FILE_BASE64}}}', 'image/png'],
        ];
    }

    /**
     * A multimodal provider with {{FILE_PARTS}} is sent several images.
     */
    public function test_assess_several_files(): void {
        $this->resetAfterTest();
        \qtype_speakup_test_helper::allow_any_curl_url();
        $provider = \qtype_speakup_test_helper::create_multimodal_provider();
        $files = [self::make_file('01-a.png'), self::make_file('02-b.png'), self::make_file('03-c.pdf')];

        \curl::mock_response(json_encode(['candidates' => [['content' => ['parts' => [['text' => "Neat work.\nSCORE: 90"]]]]]]));
        [$fraction, $feedback] = ai_caller::assess($provider, 1, 2, 'Assess this.', '', $files);
        $this->assertEqualsWithDelta(0.9, $fraction, 0.0001);
        $this->assertSame('Neat work.', $feedback);

        // With no files, the same template still works, e.g. for a transcript.
        \curl::mock_response(json_encode(['candidates' => [['content' => ['parts' => [['text' => "SCORE: 50"]]]]]]));
        [$fraction] = ai_caller::assess($provider, 1, 2, 'Assess this.', 'A transcript.', []);
        $this->assertEqualsWithDelta(0.5, $fraction, 0.0001);
    }

    /**
     * A template for one file cannot be sent several, and {{FILE_PARTS}}
     * needs a file part template.
     */
    public function test_assess_several_files_errors(): void {
        $this->resetAfterTest();
        $files = [self::make_file('01-a.png'), self::make_file('02-b.png')];

        $provider = \qtype_speakup_test_helper::create_multimodal_provider([
            'requesttemplate' => '{"prompt": "{{RUBRIC}}", "data": "{{FILE_BASE64}}"}',
        ]);
        try {
            ai_caller::assess($provider, 1, 2, 'Assess this.', '', $files);
            $this->fail('Expected an exception');
        } catch (\moodle_exception $e) {
            $this->assertSame('errortemplatemultiplefiles', $e->errorcode);
        }

        $provider = \qtype_speakup_test_helper::create_multimodal_provider(['name' => 'No parts', 'filetemplate' => '']);
        try {
            ai_caller::assess($provider, 1, 2, 'Assess this.', '', $files);
            $this->fail('Expected an exception');
        } catch (\moodle_exception $e) {
            $this->assertSame('errorfiletemplatemissing', $e->errorcode);
        }
    }

    /**
     * Transcribing images uses the handwriting prompt.
     */
    public function test_transcribe_images(): void {
        $this->resetAfterTest();
        \qtype_speakup_test_helper::allow_any_curl_url();
        $provider = \qtype_speakup_test_helper::create_multimodal_provider();
        \curl::mock_response(json_encode(['candidates' => [['content' => ['parts' => [['text' => "TRANSCRIPT: Page 1: Hello."]]]]]]));
        $this->assertSame('Page 1: Hello.', ai_caller::transcribe($provider, [self::make_file('01-a.png')],
                'English (en-US)', true));
    }
}
