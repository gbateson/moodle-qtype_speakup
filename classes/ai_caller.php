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

use tool_aibridge\action\generate_text;
use tool_aibridge\manager;
use tool_aibridge\response;

/**
 * Asks the AI, through the AI bridge (tool_aibridge), to assess or
 * transcribe a response, and reads the score and feedback in its reply.
 *
 * The bridge chooses the route: Moodle's own AI, or one of the bridge's
 * providers. It checks the AI policy and rate limits, and logs each request.
 *
 * The prompt is sent as {{PROMPT}}: the rubric, the instructions for the
 * score, and the transcript, if there is one. For request templates that
 * were written for SpeakUp's own providers, {{RUBRIC}} (the rubric and
 * instructions) and {{TRANSCRIPT}} are also set.
 *
 * Every failure throws ai_request_exception, which says whether trying again
 * later might work. The task that assesses responses catches it.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ai_caller {
    /**
     * Assess a response.
     *
     * @param int $providerid the question's provider, or 0 for "Automatic".
     * @param int $contextid where the response is assessed.
     * @param int $userid the student.
     * @param string $rubric the assessment instructions.
     * @param string $transcript the transcript, which may be empty if the files are sent.
     * @param \stored_file[] $files the recording, or the images, for providers that take files.
     * @return array [float $fraction 0..1, string $feedback]
     * @throws \moodle_exception
     */
    public static function assess(
        int $providerid,
        int $contextid,
        int $userid,
        string $rubric,
        string $transcript,
        array $files = []
    ): array {
        $fullrubric = self::build_rubric_with_instructions($rubric);
        $prompt = $fullrubric;
        if (trim($transcript) !== '') {
            $prompt .= "\n\n" . get_string('aiprompttranscriptlabel', 'qtype_speakup') . ' ' . $transcript;
        } else if (empty($files)) {
            throw new \moodle_exception('errornotranscript', 'qtype_speakup');
        }
        $action = new generate_text($contextid, $userid, $prompt, '', $files);
        $action->set_purpose('qtype_speakup', 'assess');
        $action->set_token('RUBRIC', $fullrubric);
        $action->set_token('TRANSCRIPT', $transcript);
        $content = self::process($action, $providerid);
        return self::parse_score_and_feedback($content);
    }

    /**
     * Ask the AI to transcribe a recording, or the handwriting in some images,
     * word for word. This is for uploaded files without AI assessment, so that
     * the target phrases and items can be checked in the transcript.
     *
     * @param int $providerid the question's provider, or 0 for "Automatic".
     * @param int $contextid where the response is assessed.
     * @param int $userid the student.
     * @param \stored_file[] $files the audio or video file, or the images.
     * @param string $language the language the student is expected to use, e.g. "English (en-US)".
     * @param bool $isimage whether the files are images (or PDFs) of handwriting.
     * @return string the transcript.
     * @throws \moodle_exception
     */
    public static function transcribe(
        int $providerid,
        int $contextid,
        int $userid,
        array $files,
        string $language,
        bool $isimage = false
    ): string {
        if (empty($files)) {
            throw new \moodle_exception('errornotranscript', 'qtype_speakup');
        }
        $prompt = get_string($isimage ? 'aiprompttranscribeonlyimage' : 'aiprompttranscribeonly', 'qtype_speakup', $language);
        $action = new generate_text($contextid, $userid, $prompt, '', $files);
        $action->set_purpose('qtype_speakup', 'transcribe');
        $action->set_token('RUBRIC', $prompt);
        $action->set_token('TRANSCRIPT', '');
        $content = trim(self::process($action, $providerid));
        // Remove a "TRANSCRIPT:" label, if the AI added one anyway.
        return trim((string) preg_replace('/^[\s*#]*TRANSCRIPT\s*:[\s*]*/i', '', $content));
    }

    /**
     * Send an action through the bridge, and return the text of the reply.
     *
     * @param generate_text $action
     * @param int $providerid
     * @return string
     * @throws ai_request_exception
     */
    protected static function process(generate_text $action, int $providerid): string {
        $response = manager::process($action, $providerid);
        if (!$response->get_success()) {
            throw ai_request_exception::from_response($response);
        }
        return $response->get_content();
    }

    /**
     * Separate the transcript from the feedback in an AI reply that was
     * asked for both (see the aiprompttranscribe string): "TRANSCRIPT:"
     * followed by the transcript, then "FEEDBACK:" followed by the feedback.
     *
     * @param string $content the AI's reply, without its SCORE line.
     * @return array [string|null $transcript, string $feedback] - the
     *      transcript is null if the reply did not include one.
     */
    public static function split_transcript(string $content): array {
        // Allow for Markdown around the labels, e.g. "**TRANSCRIPT:**".
        // After each colon, only the closing "**" of a bold label is removed,
        // so that a bullet ("* ") at the start of the feedback is kept.
        $pattern = '/^[\s*#]*TRANSCRIPT\s*:\**(.*?)[\s*#]*FEEDBACK\s*:\**(.*)$/is';
        if (preg_match($pattern, $content, $matches)) {
            $transcript = trim($matches[1]);
            return [($transcript === '') ? null : $transcript, trim($matches[2])];
        }
        return [null, $content];
    }

    /**
     * Add the instruction to end with "SCORE: N" to a question's rubric.
     *
     * @param string $rubric
     * @return string
     */
    public static function build_rubric_with_instructions(string $rubric): string {
        return $rubric . "\n\n" . get_string('aipromptinstructions', 'qtype_speakup');
    }

    /**
     * Read the score and feedback in the AI's reply. The score is on a line
     * such as "SCORE: 85" (out of 100), as aipromptinstructions asks.
     *
     * @param string $content
     * @return array [float $fraction, string $feedback]
     * @throws \moodle_exception if there is no score.
     */
    public static function parse_score_and_feedback(string $content): array {
        if (preg_match('/SCORE:\s*(\d{1,3})/i', $content, $matches)) {
            $score = max(0, min(100, (int) $matches[1]));
            $feedback = trim((string) preg_replace('/SCORE:\s*\d{1,3}\s*$/i', '', $content));
            return [$score / 100.0, $feedback];
        }
        throw new \moodle_exception('erroraiscoreunparseable', 'qtype_speakup');
    }
}
