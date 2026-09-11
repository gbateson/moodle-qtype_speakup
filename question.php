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
        // Always use manualgraded, regardless of $preferredbehaviour or our
        // own gradingmethod setting. This mirrors qtype_recordrtc, which
        // never hands off to the quiz's configured behaviour either.
        //
        // Behaviours like deferredfeedback/adaptive/immediatefeedback
        // require the question to implement question_automatically_gradable
        // (specifically a synchronous grade_response() method), which we do
        // not yet do - see grade_by_pattern() below, which is not currently
        // wired into that interface. Even once SPEAKUP_GRADING_EXACT is
        // wired up properly, SPEAKUP_GRADING_AI cannot ever be synchronous:
        // assessment happens later, in an adhoc task, after the student has
        // already submitted. So regardless of gradingmethod, this question
        // is graded outside the normal engine grading flow - a human (or,
        // later, an async regrade once AI results land) sets the grade
        // after the fact, which is exactly what manualgraded supports.
        return question_engine::make_behaviour('manualgraded', $qa, $preferredbehaviour);
    }

    #[\Override]
    public function get_expected_data(): array {
        return ['recording' => question_attempt::PARAM_FILES];
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
        if (empty($response['recording']) || !is_object($response['recording'])
                || !method_exists($response['recording'], 'get_files')) {
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
        // Note: $response['recording'] is not reliably an object with a
        // get_files() method. In particular, qbehaviour_manualgraded's
        // process_finish() builds $response from
        // $this->qa->get_last_step()->get_qt_data() - a raw, previously
        // persisted value for the field, not a freshly re-inflated file
        // object - so it can be a plain string here. Moodle core's own
        // qtype_essay handles this the same defensive way (checking
        // "instanceof question_response_files" before ever calling
        // get_files()), rather than assuming the type, so we do the same.
        if (empty($response['recording']) || !is_object($response['recording'])
                || !method_exists($response['recording'], 'get_files')) {
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
