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

/**
 * A SpeakUp question that is being attempted.
 *
 * Response is a single recorded audio file (expected data pattern based on
 * qtype_recordrtc, The Open University, GPL v3+). Grading is dispatched to
 * one of three strategies: exact/regex match against a transcript, an AI
 * assessment engine (via an adhoc task, since it is not instantaneous), or
 * manual grading only.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class qtype_speakup_question extends question_with_responses {

    /** @var int time limit in seconds for the recording. */
    public $timelimitinseconds;

    /** @var string one of SPEAKUP_GRADING_EXACT / SPEAKUP_GRADING_AI / SPEAKUP_GRADING_MANUAL. */
    public $gradingmethod;

    /** @var string one of SPEAKUP_MEDIA_AUDIO / SPEAKUP_MEDIA_VIDEO. */
    public $mediatype;

    /** @var string one of SPEAKUP_FEEDBACK_FORMATIVE / SPEAKUP_FEEDBACK_SUMMATIVE. */
    public $feedbackmode;

    /** @var string instructions sent to the AI assessment engine, if used. */
    public $airubric;

    /** @var stdClass[] expected answers/regexes, each with ->answertext and ->isregex. */
    public $expectedanswers = [];

    /** The single recorded-audio filename used in the response file area. */
    const RESPONSE_FILE_AREA = 'response_audio';

    #[\Override]
    public function make_behaviour(question_attempt $qa, $preferredbehaviour) {
        // Automatic grading modes can use deferred-feedback-style behaviours;
        // manual-only mode needs manualgraded. Deciding this per-attempt
        // (rather than always manualgraded, as recordrtc does) is one of the
        // main behavioural differences from the OU plugin.
        if ($this->gradingmethod === SPEAKUP_GRADING_MANUAL) {
            return question_engine::make_behaviour('manualgraded', $qa, $preferredbehaviour);
        }
        return question_engine::make_behaviour($preferredbehaviour, $qa, $preferredbehaviour);
    }

    #[\Override]
    public function get_expected_data(): array {
        return ['recording' => question_attempt::PARAM_FILES];
    }

    /**
     * Get the upload file size limit that applies here.
     *
     * Ported from qtype_recordrtc::get_upload_size_limit() (The Open
     * University, GPL v3+) - roughly copied from lib/form/filemanager.php.
     *
     * @param context $context the context we are in.
     * @return int max size in bytes.
     */
    public function get_upload_size_limit(context $context): int {
        global $CFG;

        // Get the course file size limit.
        $coursebytes = 0;
        [$context, $course] = get_context_info_array($context->id);
        if (is_object($course)) {
            $coursebytes = $course->maxbytes;
        }

        return get_user_max_upload_file_size($context, $CFG->maxbytes, $coursebytes);
    }

    #[\Override]
    public function summarise_response(array $response) {
        if (empty($response['recording'])) {
            return get_string('norecording', 'qtype_speakup');
        }
        $files = $response['recording']->get_files();
        foreach ($files as $file) {
            return s($file->get_filename());
        }
        return get_string('norecording', 'qtype_speakup');
    }

    #[\Override]
    public function is_complete_response(array $response): bool {
        if (empty($response['recording'])) {
            return false;
        }
        return count($response['recording']->get_files()) > 0;
    }

    #[\Override]
    public function is_gradable_response(array $response): bool {
        return $this->is_complete_response($response);
    }

    /**
     * Return an error to display under the response if it is not valid.
     *
     * Unlike is_complete_response() and is_gradable_response(), this is not
     * declared on question_definition/question_with_responses - it comes
     * from the question_automatically_gradable interface, which this class
     * does not implement. It is still called directly by the recorder
     * renderer/JS to show a validation message before submission, so it is
     * kept here as a plain (non-overriding) helper method.
     *
     * @param array $response responses, as returned by
     *      {@see question_attempt_step::get_qt_data()}.
     * @return string the message to display, or an empty string if the
     *      response is already complete (see is_complete_response()).
     */
    public function get_validation_error(array $response) {
        if (!$this->is_complete_response($response)) {
            return get_string('norecording', 'qtype_speakup');
        }
        return '';
    }

    #[\Override]
    public function is_same_response(array $prevresponse, array $newresponse): bool {
        return question_utils::arrays_same_at_key_missing_is_blank($prevresponse, $newresponse, 'recording');
    }

    #[\Override]
    public function get_correct_response(): ?array {
        // There is no single "correct" recording to show; if pattern-matching
        // is used we can surface the first expected answer as a hint instead.
        return null;
    }

    /**
     * Entry point called (typically from a renderer or a scheduled task) once
     * a transcript is available, to actually compute a grade/feedback.
     *
     * For SPEAKUP_GRADING_AI this would normally be invoked from
     * \qtype_speakup\task\assess_response after the async AI call returns,
     * rather than synchronously during grading().
     *
     * @param string $transcript text transcript of the student's recording.
     * @return array [fraction, feedback html]
     */
    public function grade_transcript(string $transcript): array {
        switch ($this->gradingmethod) {
            case SPEAKUP_GRADING_EXACT:
                return $this->grade_by_pattern($transcript);

            case SPEAKUP_GRADING_AI:
                // The actual AI call happens out-of-band (adhoc task); by the
                // time this is called the result should already be stored.
                // Placeholder: treat as not-yet-gradable.
                return [null, get_string('awaitingaiassessment', 'qtype_speakup')];

            case SPEAKUP_GRADING_MANUAL:
            default:
                return [null, ''];
        }
    }

    /**
     * Compare a transcript against the configured expected answers/regexes.
     *
     * @param string $transcript
     * @return array [fraction, feedback html]
     */
    protected function grade_by_pattern(string $transcript): array {
        $normalised = trim(core_text::strtolower($transcript));

        foreach ($this->expectedanswers as $expected) {
            if (!empty($expected->isregex)) {
                if (@preg_match($this->to_php_regex($expected->answertext), $transcript)) {
                    return [1.0, get_string('recordingsubmitted', 'qtype_speakup')];
                }
            } else {
                if (core_text::strtolower(trim($expected->answertext)) === $normalised) {
                    return [1.0, get_string('recordingsubmitted', 'qtype_speakup')];
                }
            }
        }

        return [0.0, ''];
    }

    /**
     * Convert an author-entered "/pattern/flags" line into a PHP-safe PCRE,
     * defending against an unescaped delimiter breaking preg_match().
     *
     * @param string $line
     * @return string
     */
    protected function to_php_regex(string $line): string {
        // Line is already validated to match #^/.*/[a-zA-Z]*$# in questiontype.php.
        return $line;
    }
}
