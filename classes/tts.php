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

/**
 * Turns text into speech with a speech provider (an external provider whose
 * capability is "speech"), e.g. to make an audio prompt from its transcript.
 *
 * The request body comes from the provider's request template, with these
 * tokens: {{TEXT}}, {{VOICE}}, {{LANGUAGE}}, {{MODEL}} and {{APIKEY}}.
 * The reply is either the audio itself (e.g. OpenAI), or JSON with the audio
 * base64-encoded at the provider's return path (e.g. Google Cloud and
 * Gemini). Gemini sends raw PCM samples, which are saved as a WAV file.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tts extends ai_caller {

    /** The most text sent in one request. Most speech APIs allow 4000-5000 characters. */
    const MAX_TEXT_LENGTH = 4000;

    /** Sample rate of raw PCM audio, if the provider does not say (Gemini uses 24 kHz). */
    const DEFAULT_PCM_RATE = 24000;

    /**
     * Turn text into speech.
     *
     * @param \stdClass $provider a speech provider, from qtype_speakup_ai_providers.
     * @param string $text
     * @param string $voice the voice name, or '' for the provider's default.
     * @param string $language the language code, e.g. "en-US".
     * @return array [string $audio, string $extension, string $mimetype]
     * @throws \moodle_exception on any failure.
     */
    public static function generate(\stdClass $provider, string $text, string $voice, string $language): array {
        if ($provider->providertype !== ai_provider::TYPE_EXTERNAL
                || $provider->capability !== ai_provider::CAPABILITY_SPEECH
                || empty($provider->url) || empty($provider->requesttemplate)) {
            throw new \moodle_exception('errorproviderconfig', 'qtype_speakup');
        }
        $text = trim($text);
        if ($text === '') {
            throw new \moodle_exception('errortts', 'qtype_speakup', '', get_string('errorttsnotext', 'qtype_speakup'));
        }
        if (\core_text::strlen($text) > self::MAX_TEXT_LENGTH) {
            throw new \moodle_exception('errortts', 'qtype_speakup', '',
                    get_string('errorttstoolong', 'qtype_speakup', self::MAX_TEXT_LENGTH));
        }

        $model = (string) ($provider->model ?? '');
        $apikey = (string) ($provider->apikey ?? '');
        $tokens = ['MODEL' => $model, 'APIKEY' => $apikey, 'VOICE' => $voice, 'LANGUAGE' => $language];

        $url = self::substitute_plain($provider->url, $tokens);
        $headers = self::substitute_plain((string) $provider->headers, $tokens);
        $body = self::substitute_escaped($provider->requesttemplate, $tokens + ['TEXT' => $text]);
        json_decode($body);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \moodle_exception('errorrequestnotvalidjson', 'qtype_speakup');
        }

        $info = [];
        $response = self::send_request($url, $headers, $apikey, $body, $info);

        $mimetype = '';
        if (trim((string) $provider->returnpath) === '') {
            // The reply is the audio itself.
            $audio = $response;
            $mimetype = (string) ($info['content_type'] ?? '');
        } else {
            $decoded = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \moodle_exception('erroraiproviderresponse', 'qtype_speakup');
            }
            $data = self::extract_by_path($decoded, $provider->returnpath);
            $audio = is_string($data) ? base64_decode($data, true) : false;
            if ($audio === false || $audio === '') {
                throw new \moodle_exception('erroraiproviderresponse', 'qtype_speakup');
            }
            $mimetype = self::find_mimetype($decoded, $provider->returnpath);
        }
        return self::identify_audio($audio, $mimetype);
    }

    /**
     * Find the MIME type next to the audio data in a JSON reply, e.g.
     * Gemini's "inlineData": {"mimeType": "audio/L16;codec=pcm;rate=24000", "data": "..."}.
     *
     * @param array $decoded the decoded JSON reply.
     * @param string $path the return path of the audio data.
     * @return string the MIME type, or '' if there is none.
     */
    protected static function find_mimetype(array $decoded, string $path): string {
        $keys = array_map('trim', explode(',', $path));
        array_pop($keys);
        $parent = $keys ? self::extract_by_path($decoded, implode(',', $keys)) : $decoded;
        if (is_array($parent)) {
            foreach (['mimeType', 'mime_type', 'mimetype', 'contentType', 'content_type'] as $key) {
                if (isset($parent[$key]) && is_string($parent[$key])) {
                    return $parent[$key];
                }
            }
        }
        return '';
    }

    /**
     * Work out what kind of audio this is, from its first bytes. Raw PCM
     * samples (audio/L16 or audio/pcm) are given a WAV header.
     *
     * @param string $audio
     * @param string $mimetype the MIME type the provider gave, if any.
     * @return array [string $audio, string $extension, string $mimetype]
     * @throws \moodle_exception if this is not audio.
     */
    public static function identify_audio(string $audio, string $mimetype = ''): array {
        $start = substr($audio, 0, 12);
        if (strncmp($start, 'ID3', 3) === 0 || (strlen($start) > 1 && ord($start[0]) === 0xFF
                && (ord($start[1]) & 0xE0) === 0xE0)) {
            return [$audio, 'mp3', 'audio/mpeg'];
        }
        if (strncmp($start, 'RIFF', 4) === 0 && substr($start, 8, 4) === 'WAVE') {
            return [$audio, 'wav', 'audio/wav'];
        }
        if (strncmp($start, 'OggS', 4) === 0) {
            return [$audio, 'ogg', 'audio/ogg'];
        }
        if (strncmp($start, 'fLaC', 4) === 0) {
            return [$audio, 'flac', 'audio/flac'];
        }
        if (substr($start, 4, 4) === 'ftyp') {
            return [$audio, 'm4a', 'audio/mp4'];
        }
        if (strncmp($start, "\x1A\x45\xDF\xA3", 4) === 0) {
            return [$audio, 'webm', 'audio/webm'];
        }
        if (preg_match('#^audio/(L16|pcm)\b#i', $mimetype)) {
            $rate = preg_match('/rate=(\d+)/i', $mimetype, $matches) ? (int) $matches[1] : self::DEFAULT_PCM_RATE;
            return [self::pcm_to_wav($audio, $rate), 'wav', 'audio/wav'];
        }
        // The text of a JSON or HTML reply, e.g. an error page, is not audio.
        throw new \moodle_exception('errortts', 'qtype_speakup', '', get_string('errorttsnotaudio', 'qtype_speakup'));
    }

    /**
     * Add a WAV header to raw 16-bit little-endian mono PCM samples.
     *
     * @param string $pcm
     * @param int $rate samples per second.
     * @return string the WAV file.
     */
    public static function pcm_to_wav(string $pcm, int $rate): string {
        $channels = 1;
        $bits = 16;
        $blockalign = $channels * $bits / 8;
        $length = strlen($pcm);
        return 'RIFF' . pack('V', 36 + $length) . 'WAVE' .
                'fmt ' . pack('VvvVVvv', 16, 1, $channels, $rate, $rate * $blockalign, $blockalign, $bits) .
                'data' . pack('V', $length) . $pcm;
    }

    /**
     * Choose the voice for an audio prompt: the question's own voice, or
     * else the site's voice for the question's language, or else the
     * site's default voice.
     *
     * The site's voices for each language are set one per line, e.g.
     * "en-GB=fable" or "ja=Kore". A line for "en" is used for any English
     * that has no line of its own, such as "en-AU".
     *
     * @param string|null $questionvoice
     * @param string|null $language e.g. "en-US".
     * @return string the voice, or '' to use the provider's default.
     */
    public static function choose_voice(?string $questionvoice, ?string $language): string {
        $questionvoice = trim((string) $questionvoice);
        if ($questionvoice !== '') {
            return $questionvoice;
        }
        $voices = [];
        foreach (preg_split('/[\r\n]+/', (string) get_config('qtype_speakup', 'ttsvoices')) as $line) {
            if (preg_match('/^\s*([A-Za-z0-9_-]+)\s*=\s*(.+?)\s*$/', $line, $matches)) {
                $voices[strtolower($matches[1])] = $matches[2];
            }
        }
        $language = strtolower(str_replace('_', '-', trim((string) $language)));
        if ($language !== '') {
            if (isset($voices[$language])) {
                return $voices[$language];
            }
            $base = explode('-', $language)[0];
            if (isset($voices[$base])) {
                return $voices[$base];
            }
        }
        return trim((string) get_config('qtype_speakup', 'ttsvoice'));
    }
}
