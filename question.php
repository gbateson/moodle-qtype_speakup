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
 * qtype_recordrtc, The Open University, GPL v3+). Grading is either manual,
 * or automated: target phrases matched in a transcript, plus an optional
 * AI assessment (via an adhoc task, since it is not instantaneous).
 *
 * Extends question_graded_automatically (rather than question_with_responses
 * directly) so that automated grading can produce a real automatic
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

    /** @var string one of SPEAKUP_GRADING_AUTO / SPEAKUP_GRADING_MANUAL. */
    public $gradingmethod;

    /** @var bool for automated grading: whether the response is also assessed by AI. */
    public $aiassessment;

    /** @var int for automated grading with AI: percentage of the question grade given by the AI's score. */
    public $aiweighting;

    /** @var int who sees the grade calculation: one of the SPEAKUP_SHOW_* constants. */
    public $showcalculation;

    /** @var int who sees which target phrases were used: one of the SPEAKUP_SHOW_* constants. */
    public $showphrases;

    /** @var int who sees the target item values: one of the SPEAKUP_SHOW_* constants. */
    public $showitems;

    /** @var int who sees the grading comment: one of the SPEAKUP_SHOW_* constants. */
    public $showcomment;

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

    /**
     * @var stdClass[] target phrases, each with ->answertext, ->isregex,
     *      ->percent, ->divisor, ->fullmatch and ->casesensitive.
     */
    public $expectedanswers = [];

    /**
     * @var stdClass[] target items, each with ->itemtype, ->percent,
     *      ->mincount and ->maxcount.
     */
    public $targetitems = [];

    /** The single recorded-audio filename used in the response file area. */
    const RESPONSE_FILE_AREA = 'response_audio';

    /**
     * Is this question graded automatically?
     *
     * @return bool
     */
    public function is_automated(): bool {
        return $this->gradingmethod === SPEAKUP_GRADING_AUTO;
    }

    /**
     * Is the response assessed by AI?
     *
     * @return bool
     */
    public function uses_ai(): bool {
        return $this->is_automated() && !empty($this->aiassessment);
    }

    /**
     * Are students asked to upload a file, rather than record live?
     *
     * @return bool
     */
    public function is_upload(): bool {
        return $this->responsemethod === SPEAKUP_RESPONSE_UPLOAD;
    }

    /**
     * Is this question graded later, in a background task, rather than
     * straight away when the response is submitted?
     *
     * That is the case when the response is assessed by AI, or when it is
     * an uploaded file, which must first be transcribed by an AI provider.
     * Either way, the result arrives some time after the response is
     * submitted.
     *
     * @return bool
     */
    public function is_graded_later(): bool {
        return $this->is_automated() && ($this->uses_ai() || $this->is_upload());
    }

    #[\Override]
    public function make_behaviour(question_attempt $qa, $preferredbehaviour) {
        if ($this->is_automated() && !$this->is_graded_later()) {
            // Graded straight away, by grade_response() below.
            // make_archetypal_behaviour() respects whatever behaviour the
            // quiz is set to use (as qtype_essayautograde does).
            return question_engine::make_archetypal_behaviour($preferredbehaviour, $qa);
        }

        // Manual grading, and grading done later in a background task,
        // both use manualgraded. The background task posts its grade via
        // question_attempt::manual_grade() (see
        // classes/task/assess_response.php), the same way a teacher grades,
        // so a teacher can still override it. grade_response() can't be
        // used for that, because it only gets the response data, not the
        // question attempt, so it can't find the background task's result.
        return question_engine::make_behaviour('manualgraded', $qa, $preferredbehaviour);
    }

    /**
     * Combine the parts of an automated grade into one fraction.
     *
     * The grade is the target phrase score, plus the target item score,
     * plus the AI's score multiplied by the AI weighting, capped at 1
     * (i.e. 100%).
     *
     * @param float|null $phrasefraction score from target phrases (0..1),
     *      or null if there are none to check.
     * @param float|null $itemfraction score from target items (0..1), or
     *      null if there are none to check.
     * @param float|null $aifraction the AI's score (0..1), or null if the
     *      response was not assessed by AI.
     * @return float|null the grade (0..1), or null if there is nothing to
     *      grade from, in which case a teacher must grade it.
     */
    public function combine_fractions(?float $phrasefraction, ?float $itemfraction, ?float $aifraction): ?float {
        if ($phrasefraction === null && $itemfraction === null && $aifraction === null) {
            return null;
        }
        $fraction = (float) $phrasefraction + (float) $itemfraction;
        if ($aifraction !== null) {
            $fraction += $aifraction * ((int) $this->aiweighting / 100);
        }
        return min(1.0, max(0.0, $fraction));
    }

    /**
     * Get the target items that can award something: those with a percent
     * and an upper number.
     *
     * @return stdClass[]
     */
    protected function get_active_items(): array {
        return array_filter($this->targetitems, function ($item) {
            return (int) $item->percent > 0 && (float) $item->maxcount > 0;
        });
    }

    /**
     * Do any of the target items need the length of the recording?
     *
     * @return bool
     */
    public function needs_duration(): bool {
        foreach ($this->get_active_items() as $item) {
            if ($item->itemtype === \qtype_speakup\text_stats::ITEM_SPEECHRATE) {
                return true;
            }
        }
        return false;
    }

    /**
     * Score a transcript against the target items.
     *
     * Each item reads "Proportionally award up to [percent] of the question
     * grade for [mincount] to [maxcount] [itemtype]". Below mincount, the
     * item awards nothing. From mincount, it awards percent x value /
     * maxcount, up to the full percent at maxcount. The items' scores add
     * up, capped at 100%.
     *
     * @param string $transcript
     * @param float|null $duration length of the recording in seconds, if known.
     * @return array [fraction|null, feedback] - fraction is null if there
     *      are no target items, or no transcript to check them in.
     */
    public function grade_by_items(string $transcript, ?float $duration): array {
        $results = $this->item_results($transcript, $duration);
        if (empty($results)) {
            return [null, ''];
        }
        if (trim($transcript) === '') {
            return [null, get_string('noitemtranscript', 'qtype_speakup')];
        }

        $percent = 0.0;
        $lines = [];
        foreach ($results as $result) {
            $percent += $result->percent;
            $lines[] = get_string('itemscore', 'qtype_speakup', (object) [
                'item' => $result->label,
                'value' => ($result->value === null) ? get_string('itemunknown', 'qtype_speakup') : round($result->value, 1),
                'percent' => round($result->percent, 1),
            ]);
        }
        $percent = min(100.0, $percent);

        return [$percent / 100, implode(' ', $lines)];
    }

    /**
     * Get the result of each target item for a transcript.
     *
     * @param string $transcript
     * @param float|null $duration length of the recording in seconds, if known.
     * @return stdClass[] each with ->label (the item's name), ->value (or
     *      null if unknown) and ->percent (the share of the question grade
     *      it awards). Empty if there are no target items.
     */
    public function item_results(string $transcript, ?float $duration): array {
        $results = [];
        foreach ($this->get_active_items() as $item) {
            $value = null;
            $percent = 0.0;
            if (trim($transcript) !== '') {
                $value = \qtype_speakup\text_stats::get_value($item->itemtype, $transcript, $duration);
                if ($value !== null && $value >= (float) $item->mincount) {
                    $max = (float) $item->maxcount;
                    $percent = (int) $item->percent * min($value, $max) / $max;
                }
            }
            $results[] = (object) [
                'label' => get_string('item' . $item->itemtype, 'qtype_speakup'),
                'value' => $value,
                'percent' => $percent,
            ];
        }
        return $results;
    }

    /**
     * Get the length of the recording in a response, if the target items
     * need it.
     *
     * @param array $response responses, as returned by
     *      {@see question_attempt_step::get_qt_data()}.
     * @return float|null the length in seconds, or null if it is unknown or
     *      not needed.
     */
    protected function get_response_duration(array $response): ?float {
        if (!$this->needs_duration() || empty($response['recording'])) {
            return null;
        }
        if (!($response['recording'] instanceof question_response_files)) {
            return null;
        }
        $files = $response['recording']->get_files();
        return \qtype_speakup\media_info::get_duration(reset($files) ?: null);
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
     * Only produces a grade for automated questions that are graded
     * straight away (see make_behaviour()), from the live browser
     * transcript. Anything else is left for a teacher, or for the
     * background task, to grade.
     *
     * @param array $response responses, as returned by
     *      {@see question_attempt_step::get_qt_data()}.
     * @return array (float|null $fraction, question_state $state).
     */
    #[\Override]
    public function grade_response(array $response): array {
        if ($this->is_automated() && !$this->is_graded_later()) {
            $transcript = (string) ($response['browsertranscript'] ?? '');
            [$phrasefraction, ] = $this->grade_by_pattern($transcript);
            [$itemfraction, ] = $this->grade_by_items($transcript, $this->get_response_duration($response));
            $fraction = $this->combine_fractions($phrasefraction, $itemfraction, null);
            if ($fraction !== null) {
                return [$fraction, question_state::graded_state_for_fraction($fraction)];
            }
            // No live transcript was captured (unsupported browser or
            // language), so there is nothing to grade from. Leave it for a
            // teacher, rather than giving a grade of 0.
        }

        return [null, question_state::manually_graded_state_for_fraction(null)];
    }

    /**
     * Get the target phrase score and feedback for a transcript.
     *
     * Used by grade_response() above, by the background task (with the
     * transcript it has), and by the renderer to show which phrases
     * matched, so all three always agree.
     *
     * @param string $transcript text transcript of the student's recording.
     * @return array [fraction|null, feedback] - see grade_by_pattern().
     */
    public function grade_transcript(string $transcript): array {
        if (!$this->is_automated()) {
            return [null, ''];
        }
        return $this->grade_by_pattern($transcript);
    }

    /**
     * Get the share of the question grade (0..100) a target phrase awards
     * if it is used: its percent divided by its divisor.
     *
     * @param stdClass $phrase a target phrase.
     * @return float
     */
    public static function phrase_percent(stdClass $phrase): float {
        $divisor = max(1, (int) ($phrase->divisor ?? 1));
        return max(0, (int) ($phrase->percent ?? 0)) / $divisor;
    }

    /**
     * Is a target phrase used in a transcript?
     *
     * A plain phrase matches anywhere in the transcript, case-insensitively
     * unless "case sensitive" is set, and only as whole words if "full
     * match" is set. Spaces in the phrase match any run of spaces. A
     * phrase written as /pattern/flags is used as a regular expression.
     *
     * @param stdClass $phrase a target phrase.
     * @param string $transcript
     * @return bool
     */
    protected function phrase_is_used(stdClass $phrase, string $transcript): bool {
        $text = trim($phrase->answertext);
        if ($text === '') {
            return false;
        }
        if (!empty($phrase->isregex)) {
            return (bool) @preg_match($this->to_php_regex($text), $transcript);
        }
        $pattern = preg_replace('/\s+/u', '\\s+', preg_quote($text, '/'));
        if (!empty($phrase->fullmatch)) {
            // Not touching a letter or digit on either side. Unlike \b,
            // this also works for letters outside a-z, e.g. "café".
            $pattern = '(?<![\p{L}\p{N}])' . $pattern . '(?![\p{L}\p{N}])';
        }
        $flags = 'u' . (empty($phrase->casesensitive) ? 'i' : '');
        return (bool) preg_match('/' . $pattern . '/' . $flags, $transcript);
    }

    /**
     * Score a transcript against the target phrases.
     *
     * Each target phrase that is used awards its own share of the question
     * grade ("If [phrase] is used, award [percent] divided by [divisor] of
     * the question grade"). The shares add up, capped at 100%. Modelled on
     * qtype_essayautograde's target phrases.
     *
     * @param string $transcript
     * @return array [fraction, feedback] - fraction is null (not 0.0) if
     *      there was no transcript, or no target phrases, to check, since
     *      that is a lack of data, not evidence of a wrong answer.
     */
    protected function grade_by_pattern(string $transcript): array {
        $results = $this->phrase_results($transcript);
        if (empty($results)) {
            return [null, ''];
        }
        if (trim($transcript) === '') {
            return [null, get_string('suggestedmatch_none', 'qtype_speakup')];
        }

        $matched = 0;
        $percent = 0.0;
        foreach ($results as $result) {
            if ($result->used) {
                $matched++;
                $percent += $result->percent;
            }
        }
        $percent = min(100.0, $percent);

        $feedback = get_string('phrasesmatched', 'qtype_speakup', (object) [
            'matched' => $matched,
            'total' => count($results),
            'percent' => round($percent, 1),
        ]);
        return [$percent / 100, $feedback];
    }

    /**
     * Get the result of each target phrase for a transcript.
     *
     * @param string $transcript
     * @return stdClass[] each with ->phrase, ->used (bool) and ->percent
     *      (the share of the question grade it awards if used). Empty if
     *      there are no target phrases.
     */
    public function phrase_results(string $transcript): array {
        $results = [];
        foreach ($this->expectedanswers as $phrase) {
            if (trim($phrase->answertext) === '' || self::phrase_percent($phrase) <= 0) {
                continue;
            }
            $results[] = (object) [
                'phrase' => $phrase->answertext,
                'used' => (trim($transcript) !== '') && $this->phrase_is_used($phrase, $transcript),
                'percent' => self::phrase_percent($phrase),
            ];
        }
        return $results;
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
