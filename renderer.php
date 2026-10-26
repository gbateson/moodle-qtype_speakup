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
 * qtype_speakup question renderer class.
 *
 * Structurally based on qtype_recordrtc's renderer (The Open University,
 * GPL v3+), simplified because qtype_speakup always has exactly one
 * recorder per question - there is no loop over several named widgets
 * parsed out of placeholders in the question text. The recorder widget is
 * appended after the question text, the way the essay question type
 * appends its answer box, rather than being substituted into the text.
 *
 * @package   qtype_speakup
 * @copyright 2019 The Open University (original qtype_recordrtc implementation)
 * @copyright 2026 Gordon Bateson and contributors (qtype_speakup adaptation)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/repository/lib.php');

use qtype_speakup\output\audio_playback;
use qtype_speakup\output\audio_recorder;
use qtype_speakup\output\video_playback;
use qtype_speakup\output\video_recorder;

class qtype_speakup_renderer extends qtype_renderer {

    #[\Override]
    public function formulation_and_controls(question_attempt $qa, question_display_options $options): string {
        global $DB;

        /** @var qtype_speakup_question $question */
        $question = $qa->get_question();
        $candownload = has_capability('qtype/speakup:downloadrecordings', $this->page->context);
        $qadatabaseid = $qa->get_database_id();
        $output = '';

        $existingfiles = $qa->get_last_qt_files('recording', $options->context->id);
        $existingfile = reset($existingfiles) ?: null;
        $filename = $existingfile ? $existingfile->get_filename() : $this->default_filename($question);

        $draftitemid = 0;
        if (!$options->readonly) {
            // Prepare a draft file area to store the recording.
            $draftitemid = $qa->prepare_response_files_draft_itemid('recording', $options->context->id);

            // Add a hidden form field with the draft item id.
            $output .= html_writer::empty_tag('input', ['type' => 'hidden',
                    'name' => $qa->get_qt_field_name('recording'), 'value' => $draftitemid]);

            // Add a hidden form field to receive the live browser transcript
            // (if any), filled in by amd/src/speechrecognition.js. Matched
            // by CSS class rather than DOM id, since get_qt_field_name()
            // values aren't guaranteed to be simple CSS-selector-safe ids.
            $output .= html_writer::empty_tag('input', ['type' => 'hidden',
                    'class' => 'qtype_speakup-browsertranscript-field',
                    'name' => $qa->get_qt_field_name('browsertranscript'), 'value' => '']);

            // Warning for browsers that won't work.
            $output .= $this->cannot_work_warnings();
        }

        if ($qa->get_state() == question_state::$invalid) {
            $output .= html_writer::nonempty_tag('div',
                    $question->get_validation_error([]), ['class' => 'validationerror']);
        }

        $questiontext = $question->format_text($question->questiontext, $question->questiontextformat,
                $qa, 'question', 'questiontext', $question->id);
        $output .= html_writer::tag('div', $questiontext, ['class' => 'qtext']);

        if ($options->readonly) {
            // Review: read-only playback widget, no recorder controls.
            // Note: get_response_file_url() already returns a plain string
            // URL, not a moodle_url object - do not call ->out() on it.
            $recordingurl = $existingfile ? $qa->get_response_file_url($existingfile) : null;
            $widget = ($question->mediatype === SPEAKUP_MEDIA_VIDEO)
                    ? new video_playback($filename, $recordingurl, $candownload)
                    : new audio_playback($filename, $recordingurl, $candownload);
            $output .= $this->render($widget);

            // Show the live browser transcript (if any was captured) and,
            // for pattern-matching questions, a suggested match result -
            // this is only ever a suggestion for whoever is grading; it
            // never sets a grade itself (see make_behaviour() above).
            $browsertranscript = (string) $qa->get_last_qt_var('browsertranscript', '');
            if ($browsertranscript !== '') {
                $output .= html_writer::tag('div',
                        html_writer::tag('strong', get_string('browsertranscript', 'qtype_speakup') . ': ') .
                        s($browsertranscript),
                        ['class' => 'qtype_speakup-transcript']);
            }
            if ($question->is_automated() && !$question->is_graded_later()) {
                // Graded straight away, from the live transcript.
                $output .= $this->grading_details($question, $options, $browsertranscript, $existingfile, null);
            } else if ($question->is_graded_later()) {
                // Grading happens later, in an adhoc task. Until it is
                // done, say so, rather than silently showing no grade.
                // Once it is done, show the grading details. The feedback
                // is saved as the grading comment, which Moodle shows itself.
                $airesponse = $qadatabaseid ? $DB->get_record('qtype_speakup_response',
                        ['questionattemptid' => $qadatabaseid]) : false;
                if ($airesponse && $airesponse->aistatus === 'done') {
                    $transcript = (string) ($airesponse->transcript ?? '');
                    if ($transcript === '') {
                        $transcript = $browsertranscript;
                    }
                    $aifraction = ($question->uses_ai() && $airesponse->aiscore !== null) ? (float) $airesponse->aiscore : null;
                    $output .= $this->grading_details($question, $options, $transcript, $existingfile, $aifraction);
                } else if ($airesponse && $airesponse->aistatus === 'failed') {
                    $output .= html_writer::tag('div', get_string('aiassessmentfailed', 'qtype_speakup'),
                            ['class' => 'qtype_speakup-ai-feedback text-danger']);
                } else {
                    if (!$airesponse) {
                        // Previews never fire the quiz event that queues
                        // background grading, so queue them here instead.
                        \qtype_speakup\ai_queue::queue_preview($qa);
                    }
                    $output .= html_writer::tag('div', get_string('aiassessmentpending', 'qtype_speakup'),
                            ['class' => 'qtype_speakup-ai-feedback text-muted']);
                }
            }

        } else {
            // Being attempted: active recorder widget.
            $recordingurl = $existingfile ? moodle_url::make_draftfile_url($draftitemid, '/', $filename) : null;
            $widget = ($question->mediatype === SPEAKUP_MEDIA_VIDEO)
                    ? new video_recorder($filename, $question->timelimitinseconds,
                            (bool) $question->allowpause, $recordingurl, $candownload)
                    : new audio_recorder($filename, $question->timelimitinseconds,
                            (bool) $question->allowpause, $recordingurl, $candownload);
            $output .= $this->render($widget);

            if ($question->is_automated() && !$question->is_upload()) {
                // Visible live transcript, filled in (and shown/hidden) by
                // amd/src/speechrecognition.js as the student speaks. Stays
                // empty and invisible for browsers/languages where live
                // transcription isn't available - it never blocks or
                // affects the recording itself. Also used for AI
                // assessment when the chosen provider is text-only (e.g.
                // core AI), which has no other way to get a transcript.
                $output .= html_writer::tag('div', '', [
                    'class' => 'qtype_speakup-live-transcript text-muted small mt-1',
                    'aria-live' => 'polite',
                ]);
            }

            // Initialise the JavaScript.
            $repositories = repository::get_instances(
                    ['type' => 'upload', 'currentcontext' => $options->context]);
            if (empty($repositories)) {
                throw new moodle_exception('errornouploadrepo', 'moodle');
            }
            $uploadrepository = reset($repositories); // Get the first (and only) upload repo.
            $settings = [
                'audioBitRate' => SPEAKUP_AUDIO_BITRATE,
                'videoBitRate' => SPEAKUP_VIDEO_BITRATE,
                'screenBitRate' => SPEAKUP_VIDEO_BITRATE,
                'maxUploadSize' => $question->get_upload_size_limit($options->context),
                'uploadRepositoryId' => (int) $uploadrepository->id,
                'contextId' => $options->context->id,
                'draftItemId' => $draftitemid,
                'videoWidth' => SPEAKUP_VIDEO_WIDTH,
                'videoHeight' => SPEAKUP_VIDEO_HEIGHT,
                'screenWidth' => SPEAKUP_VIDEO_WIDTH,
                'screenHeight' => SPEAKUP_VIDEO_HEIGHT,
            ];
            $this->page->requires->strings_for_js($this->strings_for_js(), 'qtype_speakup');
            $this->page->requires->js_call_amd('qtype_speakup/avrecording', 'init',
                    [$qa->get_outer_question_div_unique_id(), $settings]);

            // Live client-side transcription (browser speech recognition),
            // for automated grading of live recordings. It is always safe
            // to call this even on unsupported browsers - the module simply
            // does nothing in that case, leaving the hidden
            // 'browsertranscript' field empty and the recording/upload
            // path completely unaffected.
            if ($question->is_automated() && !$question->is_upload()) {
                $this->page->requires->js_call_amd('qtype_speakup/speechrecognition', 'init',
                        [$qa->get_outer_question_div_unique_id(), $question->sttlanguage]);
            }
        }

        $this->page->requires->js_call_amd('qtype_speakup/avrecording', 'addPlaybackErrorHandlingToVideoElements',
                [$qa->get_outer_question_div_unique_id()]);

        return $output;
    }

    /**
     * Hide the grading comment (e.g. the AI's feedback) from viewers who
     * should not see it, according to the question's "Show grading comment"
     * setting.
     *
     * Core calls this just before the behaviour shows the comment, with the
     * same display options. Core clones the display options for each
     * question (see core_question_renderer::question()), so hiding the
     * comment here only affects this question.
     *
     * @param question_attempt $qa
     * @param question_display_options $options
     * @return string
     */
    #[\Override]
    public function manual_comment(question_attempt $qa, question_display_options $options) {
        /** @var qtype_speakup_question $question */
        $question = $qa->get_question();
        if ($question->is_automated() && $options->manualcomment == question_display_options::VISIBLE
                && !$this->can_show((int) $question->showcomment, $options)) {
            $options->manualcomment = question_display_options::HIDDEN;
        }
        return parent::manual_comment($qa, $options);
    }

    /**
     * Can the current viewer see a grading detail?
     *
     * As in qtype_essayautograde, a viewer who can grade quizzes counts as a
     * teacher, and anyone else as a student.
     *
     * @param int $setting one of the SPEAKUP_SHOW_* constants.
     * @param question_display_options $options
     * @return bool
     */
    protected function can_show(int $setting, question_display_options $options): bool {
        $isteacher = has_capability('mod/quiz:grade', $options->context);
        switch ($setting) {
            case SPEAKUP_SHOW_STUDENTS_ONLY:
                return !$isteacher;
            case SPEAKUP_SHOW_TEACHERS_ONLY:
                return $isteacher;
            case SPEAKUP_SHOW_TEACHERS_AND_STUDENTS:
                return true;
            default:
                return false;
        }
    }

    /**
     * Show the details of an automated grade, according to the question's
     * "Show ..." settings: which target phrases were used, the target item
     * values, and how the grade was calculated.
     *
     * @param qtype_speakup_question $question
     * @param question_display_options $options
     * @param string $transcript the transcript the response was graded from.
     * @param stored_file|null $file the recording, for its length.
     * @param float|null $aifraction the AI's score (0..1), if assessed by AI.
     * @return string HTML
     */
    protected function grading_details(qtype_speakup_question $question, question_display_options $options,
            string $transcript, ?stored_file $file, ?float $aifraction): string {
        $plugin = 'qtype_speakup';
        $output = '';

        $phrases = $question->phrase_results($transcript);
        $duration = $question->needs_duration() ? \qtype_speakup\media_info::get_duration($file) : null;
        $items = $question->item_results($transcript, $duration);

        if ($phrases && $this->can_show((int) $question->showphrases, $options)) {
            $list = [];
            foreach ($phrases as $result) {
                $a = (object) ['phrase' => s($result->phrase), 'percent' => round($result->percent, 1)];
                $list[] = get_string($result->used ? 'phraseresultused' : 'phraseresultnotused', $plugin, $a);
            }
            $output .= $this->details_section(get_string('expectedanswers', $plugin), $list, 'qtype_speakup-phrases');
        }

        if ($items && $this->can_show((int) $question->showitems, $options)) {
            $list = [];
            foreach ($items as $result) {
                $list[] = get_string('itemscore', $plugin, (object) [
                    'item' => $result->label,
                    'value' => ($result->value === null) ? get_string('itemunknown', $plugin) : round($result->value, 1),
                    'percent' => round($result->percent, 1),
                ]);
            }
            $output .= $this->details_section(get_string('itemsheader', $plugin), $list, 'qtype_speakup-items');
        }

        if (($phrases || $items || $aifraction !== null) && $this->can_show((int) $question->showcalculation, $options)) {
            $list = [];
            $total = 0.0;
            if ($phrases) {
                $percent = 0.0;
                foreach ($phrases as $result) {
                    $percent += $result->used ? $result->percent : 0;
                }
                $percent = min(100.0, $percent);
                $total += $percent;
                $list[] = get_string('calcphrases', $plugin, round($percent, 1));
            }
            if ($items) {
                $percent = 0.0;
                foreach ($items as $result) {
                    $percent += $result->percent;
                }
                $percent = min(100.0, $percent);
                $total += $percent;
                $list[] = get_string('calcitems', $plugin, round($percent, 1));
            }
            if ($aifraction !== null) {
                $percent = $aifraction * (int) $question->aiweighting;
                $total += $percent;
                $list[] = get_string('calcholistic', $plugin, (object) [
                    'score' => round($aifraction * 100, 1),
                    'weighting' => (int) $question->aiweighting,
                    'percent' => round($percent, 1),
                ]);
            }
            $capped = ($total > 100);
            $total = min(100.0, $total);
            $list[] = html_writer::tag('strong', get_string($capped ? 'calctotalcapped' : 'calctotal', $plugin, round($total, 1)));
            $output .= $this->details_section(get_string('calculation', $plugin), $list, 'qtype_speakup-calculation');
        }

        return $output;
    }

    /**
     * Show one section of the grading details: a heading and a list.
     *
     * @param string $heading
     * @param string[] $list HTML list items.
     * @param string $class CSS class for the section.
     * @return string HTML
     */
    protected function details_section(string $heading, array $list, string $class): string {
        return html_writer::div(
                html_writer::tag('h5', $heading, ['class' => 'mt-2 mb-1']) .
                html_writer::alist($list, ['class' => 'mb-1']),
                'qtype_speakup-grading-details ' . $class);
    }

    /**
     * Work out a sensible default filename for a not-yet-made recording,
     * based on the question's configured media type.
     *
     * @param qtype_speakup_question $question
     * @return string
     */
    protected function default_filename(qtype_speakup_question $question): string {
        return $question->mediatype === SPEAKUP_MEDIA_VIDEO ? 'recording.webm' : 'recording.ogg';
    }

    /**
     * These messages are hidden unless revealed by the JavaScript.
     *
     * @return string HTML for the 'this can't work here' messages.
     */
    protected function cannot_work_warnings(): string {
        return $this->render_from_template('qtype_speakup/cannot_work_warnings', []);
    }

    /**
     * Strings our JS will need, preloaded via strings_for_js() so
     * M.util.get_string() can resolve them client-side without an
     * additional AJAX round-trip.
     *
     * @return string[] lang string names from the qtype_speakup lang file.
     */
    public function strings_for_js(): array {
        return [
            'gumabort',
            'gumabort_title',
            'gumnotallowed',
            'gumnotallowed_title',
            'gumnotfound',
            'gumnotfound_title',
            'gumnotreadable',
            'gumnotreadable_title',
            'gumnotsupported',
            'gumnotsupported_title',
            'gumoverconstrained',
            'gumoverconstrained_title',
            'gumsecurity',
            'gumsecurity_title',
            'gumtype',
            'gumtype_title',
            'nearingmaxsize',
            'nearingmaxsize_title',
            'pause',
            'recordagainx',
            'recordingfailed',
            'resume',
            'startrecording',
            'stoprecording',
            'startsharescreen',
            'timedisplay',
            'uploadaborted',
            'uploadcomplete',
            'uploadfailed',
            'uploadfailed404',
            'uploadpreparing',
            'uploadpreparingpercent',
            'uploadprogress',
        ];
    }
}
