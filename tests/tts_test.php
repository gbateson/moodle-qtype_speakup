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
     * Data for test_identify_audio().
     *
     * @return array
     */
    public static function identify_audio_provider(): array {
        return [
            'mp3 with ID3' => [self::MP3, '', 'mp3', 'audio/mpeg'],
            'mp3 frame' => ["\xFF\xFB\x90\x00abcdefgh", '', 'mp3', 'audio/mpeg'],
            'wav' => ["RIFF\x00\x00\x00\x00WAVEfmt ", '', 'wav', 'audio/wav'],
            'ogg' => ['OggS' . str_repeat("\0", 8), '', 'ogg', 'audio/ogg'],
            'flac' => ['fLaC' . str_repeat("\0", 8), '', 'flac', 'audio/flac'],
            'm4a' => ["\0\0\0\x20ftypM4A ", '', 'm4a', 'audio/mp4'],
            'webm' => ["\x1A\x45\xDF\xA3" . str_repeat("\0", 8), '', 'webm', 'audio/webm'],
            'pcm' => [str_repeat("\x01\x02", 50), 'audio/L16;codec=pcm;rate=16000', 'wav', 'audio/wav'],
        ];
    }

    /**
     * Test recognising the kind of audio.
     *
     * @dataProvider identify_audio_provider
     * @param string $audio
     * @param string $mimetype
     * @param string $extension
     * @param string $expectedmimetype
     */
    public function test_identify_audio(string $audio, string $mimetype, string $extension, string $expectedmimetype): void {
        [$result, $resultextension, $resultmimetype] = tts::identify_audio($audio, $mimetype);
        $this->assertSame($extension, $resultextension);
        $this->assertSame($expectedmimetype, $resultmimetype);
        if ($mimetype === '') {
            $this->assertSame($audio, $result);
        }
    }

    /**
     * Text, such as an error page, is not audio.
     */
    public function test_identify_audio_not_audio(): void {
        $this->expectException(\moodle_exception::class);
        tts::identify_audio('{"error": "quota exceeded"}', 'application/json');
    }

    /**
     * Raw PCM samples get a valid WAV header, at the given rate.
     */
    public function test_pcm_to_wav(): void {
        $pcm = str_repeat("\x00\x01", 24000);
        $wav = tts::pcm_to_wav($pcm, 24000);
        $this->assertSame(44 + strlen($pcm), strlen($wav));
        $header = unpack('a4riff/Vsize/a4wave/a4fmt/Vfmtsize/vformat/vchannels/Vrate/Vbyterate/vblockalign/vbits/a4data/Vdatasize',
                $wav);
        $this->assertSame('RIFF', $header['riff']);
        $this->assertSame('WAVE', $header['wave']);
        $this->assertSame(1, $header['format']);
        $this->assertSame(1, $header['channels']);
        $this->assertSame(24000, $header['rate']);
        $this->assertSame(48000, $header['byterate']);
        $this->assertSame(16, $header['bits']);
        $this->assertSame(strlen($pcm), $header['datasize']);
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
        [$audio, $extension, $mimetype] = tts::generate($provider, 'Hello.', 'alloy', 'en-US');
        $this->assertSame(self::MP3, $audio);
        $this->assertSame('mp3', $extension);
        $this->assertSame('audio/mpeg', $mimetype);
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
        [$audio, $extension] = tts::generate($provider, 'Hello.', 'Kore', 'en-US');
        $this->assertSame('wav', $extension);
        $this->assertSame(tts::pcm_to_wav($pcm, 24000), $audio);
    }

    /**
     * A provider that replies with base64 MP3 in JSON (e.g. Google Cloud).
     */
    public function test_generate_json_mp3(): void {
        $this->resetAfterTest();
        \qtype_speakup_test_helper::allow_any_curl_url();
        $provider = \qtype_speakup_test_helper::create_speech_provider(['returnpath' => 'audioContent']);

        \curl::mock_response(json_encode(['audioContent' => base64_encode(self::MP3)]));
        [$audio, $extension] = tts::generate($provider, 'Hello.', '', 'en-US');
        $this->assertSame(self::MP3, $audio);
        $this->assertSame('mp3', $extension);
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
            'text too long' => [[], str_repeat('a', tts::MAX_TEXT_LENGTH + 1), null],
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
        $provider = \qtype_speakup_test_helper::create_speech_provider();
        foreach ($providerdata as $field => $value) {
            $provider->$field = $value;
        }
        if ($response !== null) {
            \curl::mock_response($response);
        }
        $this->expectException(\moodle_exception::class);
        tts::generate($provider, $text, '', 'en-US');
    }
}
