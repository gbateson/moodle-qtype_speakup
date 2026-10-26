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
 * Extends question_graded_automatically (rather than question_with_responses
 * directly) so that SPEAKUP_GRADING_EXACT can produce a real automatic
 * grade via grade_response() below - that base class already implements
 * question_automatically_gradable's get_hint()/get_right_answer_summary(),
 * and inherits classify_response()/un_summarise_response() from
 * question_with_responses, so grade_response() is the only genuinely new
 * method this class needs to add. Modelled on qtype_essayautograde, which
 * uses this same "one grade_response() serves both automatic and manual
 * modes" pattern to add automatic grading on top of a normally
 * manually-graded question type.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class qtype_speakup_question extends question_graded_automatically {

    /**
     * Seconds by which an uploaded file may exceed the time limit before
     * it is flagged as too long (on the review page, and to the AI).
     */
    const DURATION_LEEWAY_SECONDS = 5;

    /** @var string one of SPEAKUP_RESPONSE_RECORD / SPEAKUP_RESPONSE_UPLOAD. */
    public $responsemethod;

    /** @var int time limit in seconds for the recording. */
    public $timelimitinseconds;

    /** @var string one of SPEAKUP_GRADING_EXACT / SPEAKUP_GRADING_AI / SPEAKUP_GRADING_MANUAL. */
    public $gradingmethod;

    /** @var string one of SPEAKUP_MEDIA_AUDIO / SPEAKUP_MEDIA_VIDEO. */
    public $mediatype;

    /** @var string BCP-47 language tag for live browser speech recognition, e.g. 'en-US'. */
    public $sttlanguage;

    /** @var bool whether the student may pause/resume mid-recording. */
    public $allowpause;

    /** @var string one of SPEAKUP_FEEDBACK_FORMATIVE / SPEAKUP_FEEDBACK_SUMMATIVE. */
    public $feedbackmode;

    /** @var string instructions sent to the AI assessment engine, if used. */
    public $airubric;

    /** @var int|null id of the qtype_speakup_ai_providers row to use for AI assessment, if any. */
    public $aiproviderid;

    /** @var stdClass[] expected answers/regexes, each with ->answertext and ->isregex. */
    public $expectedanswers = [];

    /** The single recorded-audio filename used in the response file area. */
    const RESPONSE_FILE_AREA = 'response_audio';

    #[\Override]
    public function make_behaviour(question_attempt $qa, $preferredbehaviour) {
        if ($this->gradingmethod === SPEAKUP_GRADING_EXACT) {
            // Automatic grading via grade_response() below (weighted
            // phrase matching against the live browser transcript).
            // make_archetypal_behaviour() (rather than a hardcoded
            // 'deferredfeedback') respects whatever behaviour the quiz is
            // actually configured with - matching both
            // qtype_essayautograde's own approach and core's own
            // question_graded_automatically_with_countback::make_behaviour().
            return question_engine::make_archetypal_behaviour($preferredbehaviour, $qa);
        }

        // SPEAKUP_GRADING_MANUAL and SPEAKUP_GRADING_AI both stay on
        // manualgraded. For AI mode this is deliberate, not a limitation:
        // grade_response() only ever receives $response (the submitted
        // qt_data), never the question_attempt itself or its database id,
        // so it has no way to look up a per-attempt stored AI result at
        // regrade time. Instead, once the async AI call completes (see
        // classes/task/assess_response.php), the result is posted via
        // question_attempt::manual_grade() - the exact same mechanism a
        // human teacher's grading action uses - which needs no special
        // support from grade_response() at all, and gives a teacher
        // full, ordinary override capability on top of it for free.
        return question_engine::make_behaviour('manualgraded', $qa, $preferredbehaviour);
    }

    #[\Override]
    public function get_expected_data(): array {
        return [
            'recording' => question_attempt::PARAM_FILES,
            // Live transcript captured client-side by the browser's own
            // speech recognition, if available (see amd/src/speechrecognition.js).
            // Silently empty if the browser/language is unsupported - this
            // never blocks a submission, it only affects whether a
            // pattern-matching suggestion can be computed (see
            // grade_transcript() below).
            'browsertranscript' => PARAM_RAW,
        ];
    }

    /**
     * @return int the upload file size limit that applies here.
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

    /**
     * Checks file access for a question of this type.
     *
     * Without this, Moodle's default question_definition::check_file_access()
     * does not recognise our custom file areas as servable and silently
     * refuses them (a generic "file not found" rather than a permission
     * error, for security reasons) - this bit us for both the recorded
     * response and the audio prompt. Ported from qtype_recordrtc's
     * equivalent check (The Open University, GPL v3+), which authorizes
     * exactly this 'response_recording' file area the same way; the
     * 'qtype_speakup'/'audioprompt' branch is new here, for the optional
     * audio prompt set on the editing form.
     *
     * @param question_attempt $qa the question attempt being displayed.
     * @param question_display_options $options the options that control display of the question.
     * @param string $component the name of the component we are serving files for.
     * @param string $filearea the name of the file area.
     * @param array $args the remaining bits of the file path.
     * @param bool $forcedownload whether the user must be forced to download the file.
     * @return bool true if the user can access this file.
     */
    #[\Override]
    public function check_file_access($qa, $options, $component, $filearea,
            $args, $forcedownload): bool {
        if ($component === 'question' && $filearea === 'response_recording') {
            // The student's own recorded response - always accessible to
            // whoever can already see this question attempt.
            return true;
        }

        if ($component === 'qtype_speakup' && $filearea === 'audioprompt') {
            // The (optional) audio version of the question prompt, set on
            // the editing form - part of the question content itself.
            return true;
        }

        return parent::check_file_access($qa, $options, $component, $filearea,
                $args, $forcedownload);
    }

    #[\Override]
    public function summarise_response(array $response) {
        // Same caveat as is_complete_response(): $response['recording'] is
        // not guaranteed to be an object here (see the comment there).
        if (empty($response['recording'])) {
            return get_string('norecording', 'qtype_speakup');
        }
        if (is_object($response['recording']) && method_exists($response['recording'], 'get_files')) {
            $files = $response['recording']->get_files();
            foreach ($files as $file) {
                return s($file->get_filename());
            }
            return get_string('norecording', 'qtype_speakup');
        }
        // Non-empty but not a file-like object - a real recording exists
        // (see is_complete_response()) but we can't cheaply get its actual
        // filename from this raw value, so fall back to a generic label.
        return get_string('recordingsubmitted', 'qtype_speakup');
    }

    #[\Override]
    public function is_complete_response(array $response): bool {
        // Note: $response['recording'] is not reliably an object with a
        // get_files() method. In particular, qbehaviour_manualgraded's
        // process_finish() builds $response from
        // $this->qa->get_last_step()->get_qt_data() - a raw, previously
        // persisted value for the field, not a freshly re-inflated file
        // object - so it can be a plain string here. Moodle core's own
        // qtype_essay handles this the same defensive way (checking
        // "instanceof question_response_files" before ever calling
        // get_files()), rather than assuming the type, so we do the same.
        if (empty($response['recording'])) {
            return false;
        }
        if (is_object($response['recording']) && method_exists($response['recording'], 'get_files')) {
            return count($response['recording']->get_files()) > 0;
        }
        // A non-empty value that isn't a file-like object still indicates
        // a previously-saved recording: Moodle only ever stores a
        // non-empty value here if files were genuinely associated with
        // this response when it was saved. Treating this as "incomplete"
        // (as an earlier version of this method did) caused real,
        // successfully recorded responses to be marked "not answered" at
        // finish time - a worse bug than the crash this guard originally
        // fixed.
        return true;
    }

    #[\Override]
    public function is_gradable_response(array $response): bool {
        return $this->is_complete_response($response);
    }

    /**
     * Return an error to display under the response if it is not valid.
     *
     * Now genuinely part of the question_automatically_gradable interface
     * contract (inherited via question_graded_automatically), unlike
     * earlier when this class extended question_with_responses directly
     * and this was just a plain helper with no matching ancestor.
     *
     * @param array $response responses, as returned by
     *      {@see question_attempt_step::get_qt_data()}.
     * @return string the message to display, or an empty string if the
     *      response is already complete (see is_complete_response()).
     */
    #[\Override]
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
     * Grade a response to the question. Required by the
     * question_automatically_gradable interface (inherited via
     * question_graded_automatically).
     *
     * Only ever actually produces an automatic grade for
     * SPEAKUP_GRADING_EXACT - see make_behaviour() above for why
     * SPEAKUP_GRADING_MANUAL and SPEAKUP_GRADING_AI never reach here via
     * the normal grading flow (they stay on manualgraded behaviour), but
     * this method still needs to handle being called for them defensively
     * (e.g. if a regrade is attempted after a gradingmethod change), in
     * which case it defers to manual grading rather than fabricating a
     * grade for a mode that has none yet.
     *
     * @param array $response responses, as returned by
     *      {@see question_attempt_step::get_qt_data()}.
     * @return array (float|null $fraction, question_state $state).
     */
    /**
     * Grade a response to the question. Required by the
     * question_automatically_gradable interface (inherited via
     * question_graded_automatically).
     *
     * Only ever actually produces an automatic grade for
     * SPEAKUP_GRADING_EXACT - see make_behaviour() above for why
     * SPEAKUP_GRADING_MANUAL and SPEAKUP_GRADING_AI never reach here via
     * the normal grading flow (they stay on manualgraded behaviour), but
     * this method still needs to handle being called for them defensively
     * (e.g. if a regrade is attempted after a gradingmethod change), in
     * which case it defers to manual grading rather than fabricating a
     * grade for a mode that has none yet.
     *
     * @param array $response responses, as returned by
     *      {@see question_attempt_step::get_qt_data()}.
     * @return array (float|null $fraction, question_state $state).
     */
    #[\Override]
    public function grade_response(array $response): array {
        if ($this->gradingmethod === SPEAKUP_GRADING_EXACT) {
            $transcript = (string) ($response['browsertranscript'] ?? '');
            [$fraction, ] = $this->grade_by_pattern($transcript);
            if ($fraction !== null) {
                return [$fraction, question_state::graded_state_for_fraction($fraction)];
            }
            // No live transcript was captured (unsupported browser/
            // language) - nothing to auto-grade from, so fall through to
            // manual grading below rather than fabricating a fraction.
        }

        return [null, question_state::manually_graded_state_for_fraction(null)];
    }

    /**
     * Entry point called (typically from the renderer, during review) once
     * a transcript is available, to compute a suggested grade/feedback for
     * display purposes.
     *
     * For SPEAKUP_GRADING_EXACT, this now doubles as the actual official
     * grading logic too, via grade_response() above - they deliberately
     * share grade_by_pattern() so the number shown to a student/teacher as
     * a "suggestion" is always identical to what the grade actually is.
     *
     * The transcript passed in is normally the student's browser-captured
     * live transcript (qt_data field 'browsertranscript', from
     * amd/src/speechrecognition.js) - instant, client-side, no server
     * call. If the student's browser/language did not support live
     * transcription, that field is simply empty and the caller should
     * treat this as "no suggestion available", not an error.
     *
     * For SPEAKUP_GRADING_AI this would normally be invoked (from
     * \qtype_speakup\task\assess_response) once the async AI call returns,
     * using the server-side transcript instead.
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
     * Compare a transcript against the configured expected phrases/regexes,
     * using weighted, additive scoring: each matched phrase contributes up
     * to its own weight% of the grade, capped at 100% overall. Modelled on
     * qtype_essayautograde's target-phrase scoring.
     *
     * Plain-text phrases match as a case-insensitive (unless the phrase's
     * own casesensitive flag is set) substring anywhere in the transcript -
     * not a whole-transcript equality check, since a real spoken transcript
     * will usually contain more than just the target phrase.
     *
     * @param string $transcript
     * @return array [fraction, feedback html] - fraction is null (not 0.0)
     *      if there was no transcript to check at all, since that is a lack
     *      of data, not evidence of a wrong answer.
     */
    protected function grade_by_pattern(string $transcript): array {
        if (trim($transcript) === '') {
            return [null, get_string('suggestedmatch_none', 'qtype_speakup')];
        }

        $totalweight = 0.0;
        $matchedweight = 0.0;
        $matchedphrases = [];

        foreach ($this->expectedanswers as $expected) {
            $weight = isset($expected->weight) ? (float) $expected->weight : 0.0;
            if ($weight <= 0) {
                continue;
            }
            $totalweight += $weight;

            if (!empty($expected->isregex)) {
                $ismatch = (bool) @preg_match($this->to_php_regex($expected->answertext), $transcript);
            } else {
                $needle = trim($expected->answertext);
                if ($needle === '') {
                    continue;
                }
                $ismatch = empty($expected->casesensitive)
                        ? (stripos($transcript, $needle) !== false)
                        : (strpos($transcript, $needle) !== false);
            }

            if ($ismatch) {
                $matchedweight += $weight;
                $matchedphrases[] = $expected->answertext;
            }
        }

        if ($totalweight <= 0) {
            // No phrases configured with a positive weight - nothing to
            // check against, so there is no suggestion to make.
            return [null, get_string('suggestedmatch_none', 'qtype_speakup')];
        }

        $fraction = min(1.0, $matchedweight / $totalweight);

        if ($fraction >= 1.0) {
            $feedback = get_string('suggestedmatch_yes', 'qtype_speakup');
        } else if ($fraction <= 0.0) {
            $feedback = get_string('suggestedmatch_no', 'qtype_speakup');
        } else {
            $feedback = get_string('suggestedmatch_partial', 'qtype_speakup', round($fraction * 100));
        }

        return [$fraction, $feedback];
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
