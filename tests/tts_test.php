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
 * Tests for text-to-speech. Requests to speech providers get mocked
 * responses (see curl::mock_response()).
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \qtype_speakup\tts
 */
final class tts_test extends \advanced_testcase {
    /** @var string the start of an MP3 file. */
    const MP3 = "ID3\x04\x00\x00\x00\x00\x00\x00audio";

    /**
     * Generate speech with a provider, as the admin, in the site's context.
     *
     * @param \stdClass $provider
     * @param string $text
     * @param string $voice
     * @return array
     */
    protected function generate(\stdClass $provider, string $text = 'Hello.', string $voice = ''): array {
        return tts::generate((int) $provider->id, SYSCONTEXTID, (int) get_admin()->id, $text, $voice, 'en-US');
    }

    /**
     * Test choosing the voice: the question's own, then the site's for the
     * language, then the site's default.
     */
    public function test_choose_voice(): void {
        $this->resetAfterTest();
        $this->assertSame('', tts::choose_voice(null, 'en-US'));

        set_config('ttsvoice', 'alloy', 'qtype_speakup');
        set_config('ttsvoices', "en-GB = fable\nen=echo\nja=Kore\nnot a line", 'qtype_speakup');
        $this->assertSame('nova', tts::choose_voice(' nova ', 'en-GB'));
        $this->assertSame('fable', tts::choose_voice('', 'en-GB'));
        $this->assertSame('fable', tts::choose_voice('', 'en_gb'));
        $this->assertSame('echo', tts::choose_voice('', 'en-AU'));
        $this->assertSame('Kore', tts::choose_voice('', 'ja-JP'));
        $this->assertSame('alloy', tts::choose_voice('', 'fr-FR'));
        $this->assertSame('alloy', tts::choose_voice('', ''));
    }

    /**
     * A provider that replies with the audio itself (e.g. OpenAI).
     */
    public function test_generate_raw_audio(): void {
        $this->resetAfterTest();
        \qtype_speakup_test_helper::allow_any_curl_url();
        $provider = \qtype_speakup_test_helper::create_speech_provider();

        \curl::mock_response(self::MP3);
        [$audio, $extension, $mimetype] = $this->generate($provider, 'Hello.', 'alloy');
        $this->assertSame(self::MP3, $audio);
        $this->assertSame('mp3', $extension);
        $this->assertSame('audio/mpeg', $mimetype);
        $this->assertEquals(1, $GLOBALS['DB']->count_records(
            'tool_aibridge_log',
            ['component' => 'qtype_speakup', 'purpose' => 'audioprompt']
        ));
    }

    /**
     * A provider that replies with base64 PCM in JSON, with its MIME type
     * next to it (e.g. Gemini).
     */
    public function test_generate_json_pcm(): void {
        $this->resetAfterTest();
        \qtype_speakup_test_helper::allow_any_curl_url();
        $provider = \qtype_speakup_test_helper::create_speech_provider([
            'returnpath' => 'candidates,0,content,parts,0,inlineData,data',
        ]);

        $pcm = str_repeat("\x00\x01", 100);
        \curl::mock_response(json_encode(['candidates' => [['content' => ['parts' => [['inlineData' => [
            'mimeType' => 'audio/L16;codec=pcm;rate=24000',
            'data' => base64_encode($pcm),
        ]]]]]]]));
        [$audio, $extension] = $this->generate($provider, 'Hello.', 'Kore');
        $this->assertSame('wav', $extension);
        $this->assertSame(\tool_aibridge\audio::pcm_to_wav($pcm, 24000), $audio);
    }

    /**
     * Data for test_generate_errors().
     *
     * @return array
     */
    public static function generate_errors_provider(): array {
        return [
            'not a speech provider' => [['capability' => ai_provider::CAPABILITY_TEXT], 'Hello.', null],
            'no text' => [[], '  ', null],
            'text too long' => [[], str_repeat('a', 4001), null],
            'not audio' => [[], 'Hello.', '<html>Error</html>'],
            'json without audio' => [['returnpath' => 'audioContent'], 'Hello.', '{"error": "bad"}'],
            'bad template' => [['requesttemplate' => '{"input": {{TEXT}}}'], 'Hello.', null],
        ];
    }

    /**
     * Test errors while generating speech.
     *
     * @dataProvider generate_errors_provider
     * @param array $providerdata
     * @param string $text
     * @param string|null $response the mocked response, if a request is made.
     */
    public function test_generate_errors(array $providerdata, string $text, ?string $response): void {
        $this->resetAfterTest();
        \qtype_speakup_test_helper::allow_any_curl_url();
        $provider = \qtype_speakup_test_helper::create_speech_provider($providerdata);
        if ($response !== null) {
            \curl::mock_response($response);
        }
        $this->expectException(ai_request_exception::class);
        $this->generate($provider, $text);
    }
}
