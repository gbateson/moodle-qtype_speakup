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

            if ($question->is_upload()) {
                // Add a hidden form field to receive the length of the
                // uploaded file, as measured by the browser (see
                // amd/src/fileupload.js). Used if the server can't read it.
                $output .= html_writer::empty_tag('input', ['type' => 'hidden',
                        'class' => 'qtype_speakup-browserduration-field',
                        'name' => $qa->get_qt_field_name('browserduration'),
                        'value' => (string) $qa->get_last_qt_var('browserduration', '')]);
            } else {
                // Warning for browsers that can't record.
                $output .= $this->cannot_work_warnings();
            }
        }

        if ($qa->get_state() == question_state::$invalid) {
            $output .= html_writer::nonempty_tag('div',
                    $question->get_validation_error($qa->get_last_qt_data()), ['class' => 'validationerror']);
        }

        $questiontext = $question->format_text($question->questiontext, $question->questiontextformat,
                $qa, 'question', 'questiontext', $question->id);
        $output .= html_writer::tag('div', $questiontext, ['class' => 'qtext']);

        if ($options->readonly) {
            // Review: read-only playback widget, no recorder controls.
            // Note: get_response_file_url() already returns a plain string
            // URL, not a moodle_url object - do not call ->out() on it.
            $recordingurl = $existingfile ? $qa->get_response_file_url($existingfile) : null;
            // An uploaded file may be audio or video, whatever the question's
            // media type, so use a player that suits the file itself.
            $isvideo = ($question->mediatype === SPEAKUP_MEDIA_VIDEO);
            if ($question->is_upload() && $existingfile) {
                $isvideo = (strpos($existingfile->get_mimetype(), 'video/') === 0);
            }
            $widget = $isvideo
                    ? new video_playback($filename, $recordingurl, $candownload)
                    : new audio_playback($filename, $recordingurl, $candownload);
            $output .= $this->render($widget);

            // The transcript the response is graded from: the AI's transcript,
            // if the background task made one, or else the live transcript
            // from the browser's speech recognition.
            $browsertranscript = (string) $qa->get_last_qt_var('browsertranscript', '');
            $transcript = $browsertranscript;
            $isaitranscript = false;
            $transcriptlabel = get_string('browsertranscript', 'qtype_speakup');
            $airesponse = false;
            if ($question->is_graded_later() && $qadatabaseid) {
                $airesponse = $DB->get_record('qtype_speakup_response', ['questionattemptid' => $qadatabaseid]);
                if ($airesponse && $airesponse->aistatus === 'done' && !empty($airesponse->aitranscript)) {
                    $transcript = (string) $airesponse->aitranscript;
                    $isaitranscript = true;
                    $transcriptlabel = get_string('transcript', 'qtype_speakup');
                }
            }

            // The length of the recording, if it is needed: getID3 reads it
            // from the file if it can, or else the browser measured it.
            $duration = null;
            if ($question->needs_duration() || $question->is_upload()) {
                $duration = qtype_speakup_question::best_duration($existingfile, $qa->get_last_qt_var('browserduration'));
            }

            // An uploaded file may be longer than the time limit allows.
            // (Live recordings stop at the time limit.)
            if ($question->is_upload() && $question->is_too_long($duration)) {
                $output .= html_writer::div(get_string('durationtoolong', 'qtype_speakup', (object) [
                    'duration' => round($duration),
                    'limit' => (int) $question->timelimitinseconds,
                ]), 'qtype_speakup-duration-warning alert alert-warning py-1 my-1');
            }

            if ($transcript !== '' && $this->can_show((int) $question->showtranscript, $options)) {
                $output .= html_writer::tag('div',
                        html_writer::tag('strong', $transcriptlabel . ': ') . s($transcript),
                        ['class' => 'qtype_speakup-transcript']);
            }

            if ($question->is_automated() && !$question->is_graded_later()) {
                // Graded straight away, from the live transcript.
                $output .= $this->grading_details($question, $options, $transcript, $isaitranscript, $duration, null);
            } else if ($question->is_graded_later()) {
                // Grading happens later, in an adhoc task. Until it is
                // done, say so, rather than silently showing no grade.
                // Once it is done, show the grading details. The feedback
                // is saved as the grading comment, which Moodle shows itself.
                if ($airesponse && $airesponse->aistatus === 'done') {
                    $aifraction = ($question->uses_ai() && $airesponse->aiscore !== null) ? (float) $airesponse->aiscore : null;
                    $output .= $this->grading_details($question, $options, $transcript, $isaitranscript, $duration, $aifraction);
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
            // Being attempted.
            $repositories = repository::get_instances(
                    ['type' => 'upload', 'currentcontext' => $options->context]);
            if (empty($repositories)) {
                throw new moodle_exception('errornouploadrepo', 'moodle');
            }
            $uploadrepository = reset($repositories); // Get the first (and only) upload repo.

            if ($question->is_upload()) {
                // File upload widget, instead of the recorder.
                $output .= $this->upload_widget($qa, $question, $options, $existingfile,
                        $draftitemid, (int) $uploadrepository->id, $candownload);
                $this->page->requires->js_call_amd('qtype_speakup/avrecording', 'addPlaybackErrorHandlingToVideoElements',
                        [$qa->get_outer_question_div_unique_id()]);
                return $output;
            }

            // Active recorder widget.
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
     * Show the file upload widget, used instead of the recorder when the
     * question's response method is "Upload", and set up its JavaScript.
     *
     * @param question_attempt $qa
     * @param qtype_speakup_question $question
     * @param question_display_options $options
     * @param stored_file|null $existingfile the file already uploaded, if any.
     * @param int $draftitemid the draft file area to upload to.
     * @param int $repositoryid id of the "upload" repository.
     * @param bool $candownload whether the current user may download the file.
     * @return string HTML
     */
    protected function upload_widget(question_attempt $qa, qtype_speakup_question $question,
            question_display_options $options, ?stored_file $existingfile, int $draftitemid,
            int $repositoryid, bool $candownload): string {
        // Accept any file that Moodle recognises as audio or video,
        // whatever the question's media type: some students prefer not
        // to upload video.
        $extensions = array_values(array_unique(file_get_typegroup('extension', ['audio', 'video'])));

        $fileurl = null;
        $mimetype = '';
        if ($existingfile) {
            $fileurl = moodle_url::make_draftfile_url($draftitemid, '/', $existingfile->get_filename())->out(false);
            $mimetype = $existingfile->get_mimetype();
        }
        $widget = new \qtype_speakup\output\file_uploader($fileurl, $mimetype,
                implode(',', array_merge(['audio/*', 'video/*'], $extensions)), $candownload);

        $settings = [
            'uploadRepositoryId' => $repositoryid,
            'contextId' => $options->context->id,
            'draftItemId' => $draftitemid,
            'maxUploadSize' => $question->get_upload_size_limit($options->context),
            'acceptedTypes' => $extensions,
            'timeLimit' => (int) $question->timelimitinseconds,
            'leeway' => qtype_speakup_question::DURATION_LEEWAY_SECONDS,
        ];
        $this->page->requires->strings_for_js([
            'fileuploaded', 'uploaddifferentfile', 'uploadfailed', 'uploadprogress',
            'uploadtoobig', 'uploadtoolong', 'uploadwrongtype',
        ], 'qtype_speakup');
        $this->page->requires->js_call_amd('qtype_speakup/fileupload', 'init',
                [$qa->get_outer_question_div_unique_id(), $settings]);

        return $this->render($widget);
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
     * "Show ..." settings: statistics about the transcript, the target item
     * values, which target phrases were used, and how the grade was
     * calculated.
     *
     * @param qtype_speakup_question $question
     * @param question_display_options $options
     * @param string $transcript the transcript the response was graded from.
     * @param bool $isaitranscript whether the transcript was made by AI (and
     *      so has punctuation, for counting sentences).
     * @param float|null $duration length of the recording in seconds, if known.
     * @param float|null $aifraction the AI's score (0..1), if assessed by AI.
     * @return string HTML
     */
    protected function grading_details(qtype_speakup_question $question, question_display_options $options,
            string $transcript, bool $isaitranscript, ?float $duration, ?float $aifraction): string {
        $plugin = 'qtype_speakup';
        $output = '';

        $phrases = $question->phrase_results($transcript);
        $items = $question->item_results($transcript, $duration);

        if (!empty($question->showstats) && trim($transcript) !== '') {
            $output .= $this->statistics($question, $transcript, $isaitranscript);
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

        if ($phrases && $this->can_show((int) $question->showphrases, $options)) {
            $list = [];
            foreach ($phrases as $result) {
                $a = (object) ['phrase' => s($result->phrase), 'percent' => round($result->percent, 1)];
                $list[] = get_string($result->used ? 'phraseresultused' : 'phraseresultnotused', $plugin, $a);
            }
            $output .= $this->details_section(get_string('expectedanswers', $plugin), $list, 'qtype_speakup-phrases');
        }

        if (($phrases || $items || $aifraction !== null) && $this->can_show((int) $question->showcalculation, $options)) {
            $list = [];
            $total = 0.0;
            if ($items) {
                $percent = 0.0;
                foreach ($items as $result) {
                    $percent += $result->percent;
                }
                $percent = min(100.0, $percent);
                $total += $percent;
                $list[] = get_string('calcitems', $plugin, round($percent, 1));
            }
            if ($phrases) {
                $percent = 0.0;
                foreach ($phrases as $result) {
                    $percent += $result->used ? $result->percent : 0;
                }
                $percent = min(100.0, $percent);
                $total += $percent;
                $list[] = get_string('calcphrases', $plugin, round($percent, 1));
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
     * Show the statistics about the transcript that the question is set to
     * show. Those that need sentences are only shown if the transcript was
     * made by AI, since the live transcript from the browser has no
     * punctuation.
     *
     * @param qtype_speakup_question $question
     * @param string $transcript
     * @param bool $isaitranscript whether the transcript was made by AI.
     * @return string HTML
     */
    protected function statistics(qtype_speakup_question $question, string $transcript, bool $isaitranscript): string {
        $plugin = 'qtype_speakup';
        $selected = explode(',', (string) $question->statsitems);
        $types = \qtype_speakup\text_stats::get_stat_types(false);
        if ($isaitranscript) {
            $types = array_merge($types, \qtype_speakup\text_stats::get_stat_types(true));
        }
        $list = [];
        foreach ($types as $type) {
            if (!in_array($type, $selected)) {
                continue;
            }
            $value = \qtype_speakup\text_stats::get_value($type, $transcript);
            $list[] = get_string('statvalue', $plugin, (object) [
                'label' => get_string('item' . $type, $plugin),
                'value' => ($value === null) ? get_string('itemunknown', $plugin) : round($value, 1),
            ]);
        }
        if (empty($list)) {
            return '';
        }
        return $this->details_section(get_string('statistics', $plugin), $list, 'qtype_speakup-statistics');
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
