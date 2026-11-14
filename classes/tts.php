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

use tool_aibridge\action\generate_speech;
use tool_aibridge\manager;

/**
 * Turns text into speech through the AI bridge (tool_aibridge), e.g. to make
 * an audio prompt from its transcript.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tts {
    /** How speech providers should read audio prompts: a little slowly, e.g. for CEFR A1-A2 learners. */
    const SPEECH_STYLE = 'Say this clearly, a little slower than normal conversation';

    /**
     * Turn text into speech.
     *
     * @param int $providerid the speech provider, or 0 for any.
     * @param int $contextid where the question is.
     * @param int $userid the teacher.
     * @param string $text
     * @param string $voice the voice name, or '' for the provider's default.
     * @param string $language the language code, e.g. "en-US".
     * @return array [string $audio, string $extension, string $mimetype]
     * @throws \moodle_exception
     */
    public static function generate(int $providerid, int $contextid, int $userid, string $text,
            string $voice, string $language): array {
        $action = new generate_speech($contextid, $userid, $text, $voice, $language);
        $action->set_purpose('qtype_speakup', 'audioprompt');
        $action->set_token('STYLE', self::SPEECH_STYLE);
        $response = manager::process($action, $providerid);
        if (!$response->get_success()) {
            throw ai_request_exception::from_response($response);
        }
        return [$response->get_audio(), $response->get_extension(), $response->get_mimetype()];
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
