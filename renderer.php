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

/**
 * Renders SpeakUp questions: the prompt, the recorder or upload widget, and the feedback.
 *
 * @package   qtype_speakup
 * @copyright 2019 The Open University (original qtype_recordrtc implementation)
 * @copyright 2026 Gordon Bateson and contributors (qtype_speakup adaptation)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_speakup_renderer extends qtype_renderer {
    /** @var int|null the draft file area for the response, if given (see mobile_response()). */
    protected $responsedraftitemid = null;

    /** @var bool whether to leave out the question text (see mobile_response()). */
    protected $hidequestiontext = false;

    /**
     * If the response is sent to the AI, and the student has not yet accepted
     * the AI bridge's policy, say so, with a link to the policy. Responses are
     * not sent to the AI until it is accepted.
     *
     * @param qtype_speakup_question $question
     * @param question_display_options $options
     * @return string
     */
    protected function ai_policy_notice(qtype_speakup_question $question, question_display_options $options): string {
        global $USER;
        if (!$question->sends_to_ai() || \tool_aibridge\policy::has_accepted((int) $USER->id)) {
            return '';
        }
        $returnurl = $this->page->has_set_url() ? $this->page->url : null;
        $url = \tool_aibridge\policy::get_url($options->context, $returnurl);
        $link = html_writer::link($url, get_string('aipolicylink', 'qtype_speakup'));
        return $this->output->notification(
            get_string('aipolicynotice', 'qtype_speakup', $link),
            \core\output\notification::NOTIFY_WARNING,
            false
        );
    }

    /**
     * Show the response part of a question (the audio prompt and the
     * recorder or uploader), for the Moodle app's frame (see respond.php).
     *
     * The app shows the question text itself, and sends the response with
     * the quiz, so the response must go into the app's draft file area.
     *
     * @param question_attempt $qa
     * @param question_display_options $options
     * @param int $draftitemid the app's draft file area for the response.
     * @return string HTML
     */
    public function mobile_response(question_attempt $qa, question_display_options $options, int $draftitemid): string {
        $this->responsedraftitemid = $draftitemid;
        $this->hidequestiontext = true;
        try {
            $html = $this->formulation_and_controls($qa, $options);
        } finally {
            $this->responsedraftitemid = null;
            $this->hidequestiontext = false;
        }
        return html_writer::div(
            html_writer::div($html, 'formulation clearfix'),
            'que speakup',
            ['id' => $qa->get_outer_question_div_unique_id()]
        );
    }

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
            $draftitemid = $this->responsedraftitemid
                    ?? $qa->prepare_response_files_draft_itemid('recording', $options->context->id);

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
            $output .= $this->ai_policy_notice($question, $options);
        }

        if ($qa->get_state() == question_state::$invalid) {
            $output .= html_writer::nonempty_tag(
                'div',
                $question->get_validation_error($qa->get_last_qt_data()),
                ['class' => 'validationerror']
            );
        }

        $questiontext = $question->format_text(
            $question->questiontext,
            $question->questiontextformat,
            $qa,
            'question',
            'questiontext',
            $question->id
        );
        if (!$this->hidequestiontext) {
            $output .= html_writer::tag('div', $questiontext, ['class' => 'qtext']);
        }
        $output .= $this->audio_prompt($qa, $options);
        $output .= $this->prompt_controls($qa, $options);

        if ($options->readonly) {
            // Review: read-only playback widget, no recorder controls.
            // Note: get_response_file_url() already returns a plain string
            // URL, not a moodle_url object - do not call ->out() on it.
            if ($question->is_image()) {
                // The pages, in order, each opening full size.
                $output .= $this->image_pages($qa, $existingfiles);
            } else {
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
            }

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
            if ($question->needs_duration() || ($question->is_upload() && !$question->is_image())) {
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
                $output .= html_writer::tag(
                    'div',
                    html_writer::tag('strong', $transcriptlabel . ': ') . s($transcript),
                    ['class' => 'qtype_speakup-transcript']
                );
            }

            if ($question->is_automated() && !$question->is_graded_later()) {
                // Graded straight away, from the live transcript.
                $output .= $this->grading_details(
                    $question,
                    $options,
                    $transcript,
                    $isaitranscript,
                    $duration,
                    null,
                    $qa
                );
            } else if ($question->is_graded_later()) {
                // Grading happens later, in an adhoc task. Until it is
                // done, say so, rather than silently showing no grade.
                // Once it is done, show the grading details. The feedback
                // is saved as the grading comment, which Moodle shows itself.
                if ($airesponse && $airesponse->aistatus === 'done') {
                    $aifraction = ($question->uses_ai() && $airesponse->aiscore !== null) ? (float) $airesponse->aiscore : null;
                    $output .= $this->grading_details(
                        $question,
                        $options,
                        $transcript,
                        $isaitranscript,
                        $duration,
                        $aifraction,
                        $qa
                    );
                } else if ($airesponse && $airesponse->aistatus === 'failed') {
                    // Teachers see why; students see that a teacher will grade it.
                    if ($this->can_see_errors($options) && !empty($airesponse->aierror)) {
                        $message = get_string('aiassessmentfailedreason', 'qtype_speakup', s($airesponse->aierror));
                    } else {
                        $message = get_string('aiassessmentfailed', 'qtype_speakup');
                    }
                    $output .= html_writer::tag('div', $message, ['class' => 'qtype_speakup-ai-feedback text-danger']);
                } else if (!$airesponse && $qa->get_state()->is_graded()) {
                    // Already graded, but with no record of the background
                    // grading, e.g. an attempt restored from a backup (which
                    // keeps the grade and comment, but not that record), or
                    // graded by hand. Nothing is pending.
                    $output .= '';
                } else {
                    if (!$airesponse) {
                        // Previews never fire the quiz event that queues
                        // background grading, so queue them here instead.
                        \qtype_speakup\ai_queue::queue_preview($qa);
                    }
                    // The page checks for the result, and updates itself when it
                    // is ready: on the site, see amd/src/assessmentstatus.js; in
                    // the Moodle app, see mobile/mobile.js.
                    $qaid = (int) $qa->get_database_id();
                    $output .= html_writer::tag(
                        'div',
                        get_string('aiassessmentpending', 'qtype_speakup') . ' ' .
                                get_string('aiassessmentpendingnote', 'qtype_speakup'),
                        ['class' => 'qtype_speakup-ai-feedback text-muted', 'data-qtype-speakup-pending' => $qaid]
                    );
                    if ($qaid) {
                        $this->page->requires->js_call_amd('qtype_speakup/assessmentstatus', 'init', [$qaid]);
                    }
                    if ($airesponse && !empty($airesponse->aierror) && $this->can_see_errors($options)) {
                        // An earlier try failed, and it will be tried again.
                        $output .= html_writer::tag(
                            'div',
                            get_string('aiassessmentretrying', 'qtype_speakup', s($airesponse->aierror)),
                            ['class' => 'qtype_speakup-ai-error text-warning']
                        );
                    }
                }
            }
        } else {
            // Being attempted.
            $repositories = repository::get_instances(
                ['type' => 'upload', 'currentcontext' => $options->context]
            );
            if (empty($repositories)) {
                throw new moodle_exception('errornouploadrepo', 'moodle');
            }
            $uploadrepository = reset($repositories); // Get the first (and only) upload repo.

            if ($question->is_image()) {
                // Image upload widget: up to maxfiles images or PDFs.
                return $output . $this->image_widget($qa, $question, $options, $draftitemid, (int) $uploadrepository->id);
            }

            if ($question->is_upload()) {
                // File upload widget, instead of the recorder.
                $output .= $this->upload_widget(
                    $qa,
                    $question,
                    $options,
                    $existingfile,
                    $draftitemid,
                    (int) $uploadrepository->id,
                    $candownload
                );
                $this->page->requires->js_call_amd(
                    'qtype_speakup/avrecording',
                    'addPlaybackErrorHandlingToVideoElements',
                    [$qa->get_outer_question_div_unique_id()]
                );
                return $output;
            }

            // Active recorder widget.
            $recordingurl = $existingfile ? moodle_url::make_draftfile_url($draftitemid, '/', $filename) : null;
            $widget = ($question->mediatype === SPEAKUP_MEDIA_VIDEO)
                    ? new video_recorder(
                        $filename,
                        $question->timelimitinseconds,
                        (bool) $question->responsepause,
                        $recordingurl,
                        $candownload
                    )
                    : new audio_recorder(
                        $filename,
                        $question->timelimitinseconds,
                        (bool) $question->responsepause,
                        $recordingurl,
                        $candownload
                    );
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
                // Stop recording after this many seconds of silence (0 = never).
                'silenceStop' => (int) $question->silencestop,
            ];
            $this->page->requires->strings_for_js($this->strings_for_js(), 'qtype_speakup');
            $this->page->requires->js_call_amd(
                'qtype_speakup/avrecording',
                'init',
                [$qa->get_outer_question_div_unique_id(), $settings]
            );

            // Live client-side transcription (browser speech recognition),
            // for automated grading of live recordings. It is always safe
            // to call this even on unsupported browsers - the module simply
            // does nothing in that case, leaving the hidden
            // 'browsertranscript' field empty and the recording/upload
            // path completely unaffected.
            if ($question->is_automated() && !$question->is_upload()) {
                $this->page->requires->js_call_amd(
                    'qtype_speakup/speechrecognition',
                    'init',
                    [$qa->get_outer_question_div_unique_id(), $question->sttlanguage]
                );
            }
        }

        $this->page->requires->js_call_amd(
            'qtype_speakup/avrecording',
            'addPlaybackErrorHandlingToVideoElements',
            [$qa->get_outer_question_div_unique_id()]
        );

        return $output;
    }

    /**
     * Show the audio prompt (recorded or uploaded on the question form), if
     * there is one, as an audio player, with its transcript (if any) below.
     *
     * @param question_attempt $qa
     * @param question_display_options $options
     * @return string HTML, or an empty string if there is no audio prompt.
     */
    protected function audio_prompt(question_attempt $qa, question_display_options $options): string {
        /** @var qtype_speakup_question $question */
        $question = $qa->get_question();
        $files = get_file_storage()->get_area_files(
            $question->contextid,
            'qtype_speakup',
            'audioprompt',
            $question->id,
            'filename',
            false
        );
        $file = reset($files);
        if (!$file) {
            // The audio prompt may still be being generated from its transcript.
            if (
                trim((string) $question->prompttranscript) !== ''
                    && \qtype_speakup\task\generate_audio_prompt::is_pending($question->id)
            ) {
                return html_writer::div(
                    get_string('audiopromptnotready', 'qtype_speakup'),
                    'qtype_speakup-audio-prompt alert alert-info my-2'
                );
            }
            return '';
        }
        // A question file URL, served by question_pluginfile() (see lib.php),
        // which checks access with qtype_speakup_question::check_file_access().
        $url = $qa->rewrite_pluginfile_urls(
            '@@PLUGINFILE@@/' . rawurlencode($file->get_filename()),
            'qtype_speakup',
            'audioprompt',
            $question->id
        );
        return html_writer::div(
            html_writer::tag('audio', '', ['controls' => 'controls', 'src' => $url, 'class' => 'w-100']) .
                $this->prompt_transcript($qa, $options),
            'qtype_speakup-audio-prompt my-2'
        );
    }

    /**
     * Set up the controls for the prompt and the responses, while the
     * question is being attempted (see amd/src/promptcontrol.js): starting
     * the recording when the prompt finishes, the limits on how many times
     * the prompt can be played and how many responses can be given, and
     * whether the prompt can be paused.
     *
     * The counts so far are kept in hidden fields, saved with the response.
     *
     * @param question_attempt $qa
     * @param question_display_options $options
     * @return string HTML
     */
    protected function prompt_controls(question_attempt $qa, question_display_options $options): string {
        /** @var qtype_speakup_question $question */
        $question = $qa->get_question();
        $autorecord = !empty($question->autorecord) && !$question->is_upload();
        $maxplays = (int) $question->maxpromptplays;
        $maxresponses = (int) $question->maxresponses;
        $promptpause = !isset($question->promptpause) || !empty($question->promptpause);
        if ($options->readonly || (!$autorecord && !$maxplays && !$maxresponses && $promptpause)) {
            return '';
        }
        $plugin = 'qtype_speakup';

        $output = '';
        foreach (['promptplays', 'responsecount'] as $name) {
            $output .= html_writer::empty_tag('input', ['type' => 'hidden',
                    'class' => 'qtype_speakup-' . $name . '-field',
                    'name' => $qa->get_qt_field_name($name),
                    'value' => (int) $qa->get_last_qt_var($name, 0)]);
        }
        $notes = [];
        if ($autorecord) {
            $notes[] = get_string('autorecordnote', $plugin);
        }
        if ($maxplays) {
            $notes[] = get_string('maxpromptplaysnote', $plugin, $maxplays);
        }
        if ($maxresponses) {
            $notes[] = get_string('maxresponsesnote', $plugin, $maxresponses);
        }
        $output .= html_writer::div(implode(' ', $notes), 'qtype_speakup-prompt-notes small text-muted mb-1');
        $output .= html_writer::div(
            '',
            'qtype_speakup-prompt-message small text-warning mb-1 hide',
            ['role' => 'status', 'aria-live' => 'polite']
        );

        $this->page->requires->strings_for_js(['listenfirst', 'playprompt', 'playpromptagain', 'promptplaying',
                'promptplaysused', 'responsesused'], $plugin);
        $this->page->requires->js_call_amd('qtype_speakup/promptcontrol', 'init', [
            $qa->get_outer_question_div_unique_id(),
            [
                'autoRecord' => $autorecord,
                'maxPromptPlays' => $maxplays,
                'maxResponses' => $maxresponses,
                'promptPause' => $promptpause,
            ],
        ]);
        return $output;
    }

    /**
     * Show the transcript of the audio prompt, as the question is set to:
     * not at all, collapsed ("Show transcript"), or expanded.
     *
     * When it is collapsed, and showing it has a penalty, students are told
     * so (unless they are exempt), and a hidden field records whether they
     * showed it (see amd/src/prompttranscript.js).
     *
     * @param question_attempt $qa
     * @param question_display_options $options
     * @return string HTML
     */
    protected function prompt_transcript(question_attempt $qa, question_display_options $options): string {
        /** @var qtype_speakup_question $question */
        $question = $qa->get_question();
        $plugin = 'qtype_speakup';
        $transcript = trim((string) $question->prompttranscript);
        $display = (int) $question->prompttranscriptdisplay;
        if ($transcript === '' || $display === SPEAKUP_PROMPTTRANSCRIPT_HIDE) {
            return '';
        }
        $text = html_writer::div(nl2br(s($transcript)), 'qtype_speakup-prompt-transcript-text mt-1');

        if ($display === SPEAKUP_PROMPTTRANSCRIPT_EXPANDED) {
            return html_writer::div(
                html_writer::tag('strong', get_string('transcript', $plugin)) . $text,
                'qtype_speakup-prompt-transcript mt-1'
            );
        }

        $summary = get_string('showtranscript', $plugin);
        $userid = (int) $qa->get_step(0)->get_user_id();
        $exempt = qtype_speakup_question::is_exempt_from_transcript_penalty($userid, $options->context);
        $penalty = ($question->has_transcript_penalty() && !$exempt) ? (int) $question->prompttranscriptpenalty : 0;
        if ($penalty) {
            $summary .= ' ' . html_writer::span(get_string('showtranscriptpenalty', $plugin, $penalty), 'small');
        }
        $viewed = !empty($qa->get_last_qt_var('prompttranscriptviewed'));

        $output = '';
        if (!$options->readonly) {
            $output .= html_writer::empty_tag('input', ['type' => 'hidden',
                    'class' => 'qtype_speakup-prompttranscriptviewed-field',
                    'name' => $qa->get_qt_field_name('prompttranscriptviewed'),
                    'value' => $viewed ? 1 : 0]);
            // Before showing it the first time, students the penalty applies
            // to are asked to confirm (see amd/src/prompttranscript.js).
            $this->page->requires->strings_for_js(
                ['confirmtranscript', 'confirmtranscripttitle', 'showtranscript'],
                $plugin
            );
            $this->page->requires->js_call_amd(
                'qtype_speakup/prompttranscript',
                'init',
                [$qa->get_outer_question_div_unique_id(), $viewed ? 0 : $penalty]
            );
        }
        $output .= html_writer::tag(
            'details',
            html_writer::tag('summary', $summary) . $text,
            ['class' => 'qtype_speakup-prompt-transcript mt-1']
        );
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
    protected function upload_widget(
        question_attempt $qa,
        qtype_speakup_question $question,
        question_display_options $options,
        ?stored_file $existingfile,
        int $draftitemid,
        int $repositoryid,
        bool $candownload
    ): string {
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
        $widget = new \qtype_speakup\output\file_uploader(
            $fileurl,
            $mimetype,
            implode(',', array_merge(['audio/*', 'video/*'], $extensions)),
            $candownload
        );

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
            'uploadtoobigmedia', 'uploadtoolong', 'uploadwait', 'uploadwrongtype',
        ], 'qtype_speakup');
        $this->page->requires->js_call_amd(
            'qtype_speakup/fileupload',
            'init',
            [$qa->get_outer_question_div_unique_id(), $settings]
        );

        return $this->render($widget);
    }

    /**
     * Show the widget for uploading images or PDFs, e.g. scans of handwriting.
     *
     * @param question_attempt $qa
     * @param qtype_speakup_question $question
     * @param question_display_options $options
     * @param int $draftitemid the draft file area to upload to.
     * @param int $repositoryid id of the "upload" repository.
     * @return string HTML
     */
    protected function image_widget(
        question_attempt $qa,
        qtype_speakup_question $question,
        question_display_options $options,
        int $draftitemid,
        int $repositoryid
    ): string {
        // Any image that Moodle recognises, or a PDF.
        $extensions = array_values(array_unique(array_merge(file_get_typegroup('extension', ['image']), ['.pdf'])));
        $maxfiles = $question->get_max_files();
        $settings = [
            'uploadRepositoryId' => $repositoryid,
            'contextId' => $options->context->id,
            'draftItemId' => $draftitemid,
            'maxUploadSize' => $question->get_upload_size_limit($options->context),
            'acceptedTypes' => $extensions,
            'maxFiles' => $maxfiles,
        ];
        $this->page->requires->strings_for_js([
            'fileuploaded', 'imageshrunk', 'imagetoobig', 'pagerotated', 'pageremoved', 'pagex', 'removepage',
            'removepagex', 'rotateleft', 'rotateleftx', 'rotateright', 'rotaterightx', 'toomanyfiles', 'uploadanotherimage',
            'uploadfailed', 'uploadimage', 'uploadprogress', 'uploadtoobigpdf', 'uploadwait', 'uploadwrongtypeimage',
        ], 'qtype_speakup');
        $this->page->requires->js_call_amd(
            'qtype_speakup/imageupload',
            'init',
            [$qa->get_outer_question_div_unique_id(), $settings]
        );

        $notes = ($maxfiles > 1) ? html_writer::div(
            get_string('maxfilesnote', 'qtype_speakup', $maxfiles),
            'qtype_speakup-image-notes small text-muted mb-1'
        ) : '';
        return $notes . $this->render_from_template('qtype_speakup/image_uploader', [
            'accept' => implode(',', array_merge(['image/*', 'application/pdf'], $extensions)),
            'multiple' => $maxfiles > 1,
        ]);
    }

    /**
     * Show the pages of an image response, in order. Each opens full size.
     *
     * @param question_attempt $qa
     * @param stored_file[] $files
     * @return string HTML
     */
    protected function image_pages(question_attempt $qa, array $files): string {
        if (!$files) {
            return html_writer::div(get_string('nofileuploaded', 'qtype_speakup'), 'qtype_speakup-no-recording-placeholder');
        }
        $items = '';
        $number = 0;
        foreach ($files as $file) {
            $number++;
            $label = get_string('pagex', 'qtype_speakup', $number);
            $url = $qa->get_response_file_url($file);
            if ($file->get_mimetype() === 'application/pdf') {
                $content = html_writer::link(
                    $url,
                    s(preg_replace('/^\d+-/', '', $file->get_filename())),
                    ['class' => 'qtype_speakup-image-pdf d-block py-3', 'target' => '_blank', 'rel' => 'noopener']
                );
            } else {
                $content = html_writer::link(
                    $url,
                    html_writer::empty_tag(
                        'img',
                        ['src' => $url, 'alt' => $label, 'class' => 'qtype_speakup-image-thumb']
                    ),
                    ['target' => '_blank', 'rel' => 'noopener']
                );
            }
            $items .= html_writer::tag(
                'li',
                html_writer::div($label, 'small fw-bold') . $content,
                ['class' => 'qtype_speakup-image-item card p-1 me-2 mb-2 text-center']
            );
        }
        $class = 'qtype_speakup-image-list qtype_speakup-image-pages list-unstyled d-flex flex-wrap my-1';
        return html_writer::tag('ol', $items, ['class' => $class]);
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
        if (
            $question->is_automated() && $options->manualcomment == question_display_options::VISIBLE
                && !$this->can_show((int) $question->showcomment, $options)
        ) {
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
     * Can the current viewer see why background grading failed? Only
     * teachers, i.e. viewers who can grade quizzes, as in can_show().
     *
     * @param question_display_options $options
     * @return bool
     */
    protected function can_see_errors(question_display_options $options): bool {
        return has_capability('mod/quiz:grade', $options->context);
    }

    /**
     * Show the details of an automated grade, according to the question's
     * "Show ..." settings, each in its own section, as qtype_essayautograde
     * does: statistics about the transcript, the target items, the target
     * phrases, how the grade was calculated, and hints to improve it.
     *
     * @param qtype_speakup_question $question
     * @param question_display_options $options
     * @param string $transcript the transcript the response was graded from.
     * @param bool $isaitranscript whether the transcript was made by AI (and
     *      so has punctuation, for counting sentences).
     * @param float|null $duration length of the recording in seconds, if known.
     * @param float|null $aifraction the AI's score (0..1), if assessed by AI.
     * @param question_attempt $qa the attempt, for whether (and by whom) the
     *      audio prompt transcript was shown, and for its grade.
     * @return string HTML
     */
    protected function grading_details(
        qtype_speakup_question $question,
        question_display_options $options,
        string $transcript,
        bool $isaitranscript,
        ?float $duration,
        ?float $aifraction,
        question_attempt $qa
    ): string {
        $plugin = 'qtype_speakup';
        $output = '';

        // Did the student show the audio prompt transcript, and if so, does
        // the penalty for that apply to them?
        $viewed = !empty($qa->get_last_qt_var('prompttranscriptviewed'));
        $exempt = $viewed && qtype_speakup_question::is_exempt_from_transcript_penalty(
            (int) $qa->get_step(0)->get_user_id(),
            $options->context
        );
        $penalty = $question->transcript_penalty_applies($viewed, $exempt);

        $phrases = $question->phrase_results($transcript);
        $items = $question->item_results($transcript, $duration);
        $hastranscript = (trim($transcript) !== '');
        $showitems = $items && $this->can_show((int) $question->showitems, $options);
        $showphrases = $phrases && $this->can_show((int) $question->showphrases, $options);

        if (!empty($question->showstats) && $hastranscript) {
            $output .= $this->statistics($question, $transcript, $isaitranscript);
        }
        if ($showitems) {
            $output .= $this->item_details($items);
        }
        if ($showphrases) {
            $output .= $this->phrase_details($phrases, has_capability('mod/quiz:grade', $options->context));
        }
        if (($phrases || $items || $aifraction !== null) && $this->can_show((int) $question->showcalculation, $options)) {
            $output .= $this->grade_calculation($question, $qa, $options, $items, $phrases, $aifraction, $penalty);
        }
        if ($hastranscript) {
            // Only for the items and phrases that this viewer can see.
            $output .= $this->hints($showitems ? $items : [], $showphrases ? $phrases : []);
        }

        // Teachers see whether the student showed the transcript.
        if ($viewed && $this->can_show(SPEAKUP_SHOW_TEACHERS_ONLY, $options)) {
            $output .= html_writer::div(
                get_string($exempt ? 'transcriptviewedexempt' : 'transcriptviewed', $plugin),
                'qtype_speakup-transcript-viewed small text-muted mt-1'
            );
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
        $strman = get_string_manager();
        $selected = explode(',', (string) $question->statstypes);
        $types = \qtype_speakup\text_stats::get_stat_types(false);
        if ($isaitranscript) {
            $types = array_merge($types, \qtype_speakup\text_stats::get_stat_types(true));
        }
        $table = $this->details_table([
            get_string('detailsstatistic', $plugin),
            get_string('detailsvalue', $plugin),
        ]);
        foreach ($types as $type) {
            if (!in_array($type, $selected)) {
                continue;
            }
            $label = get_string('item' . $type, $plugin);
            if ($strman->string_exists('item' . $type . '_help', $plugin)) {
                $label .= $this->help_icon('item' . $type, $plugin);
            }
            $value = \qtype_speakup\text_stats::get_value($type, $transcript);
            $table->data[] = [$label, $this->format_number($value)];
        }
        if (empty($table->data)) {
            return '';
        }
        return $this->details_section(get_string('statistics', $plugin), html_writer::table($table), 'qtype_speakup-statistics');
    }

    /**
     * Show the target items, as grade bands: for each band, its range, how
     * much of it the response covers, and the share of the question grade
     * it earned, out of the share it awards in full.
     *
     * @param stdClass[] $items as returned by qtype_speakup_question::item_results().
     * @return string HTML
     */
    protected function item_details(array $items): string {
        $plugin = 'qtype_speakup';
        $table = $this->details_table([
            get_string('detailsitem', $plugin),
            get_string('detailsband', $plugin),
            get_string('detailsresponse', $plugin),
            get_string('detailsgrade', $plugin),
        ]);
        foreach ($items as $item) {
            $width = $item->target - $item->lower;
            if ($item->progress === null) {
                $response = $this->format_number(null);
            } else {
                $response = get_string('detailsprogress', $plugin, (object) [
                    'progress' => $this->format_number($item->progress),
                    'width' => $this->format_number($width),
                ]);
                if ($item->progress >= $width) {
                    $response .= ' ' . $this->feedback_image(1.0);
                }
            }
            $table->data[] = [
                $item->label,
                $this->band_range($item),
                $response,
                $this->percent_of($item->percent, $item->maxpercent),
            ];
        }
        return $this->details_section(get_string('itemsheader', $plugin), html_writer::table($table), 'qtype_speakup-items');
    }

    /**
     * Show the range of a grade band, e.g. "101 - 130" words, or "100 - 130"
     * for an item that can have decimals, such as the speech rate.
     *
     * @param stdClass $item as returned by qtype_speakup_question::item_results().
     * @return string
     */
    protected function band_range(stdClass $item): string {
        $lower = $item->lower;
        if (\qtype_speakup\text_stats::is_count($item->itemtype) && $lower == floor($lower)) {
            // Counts start at the next whole number: the first band at 1.
            $lower++;
        }
        return get_string('detailsrange', 'qtype_speakup', (object) [
            'lower' => $this->format_number($lower),
            'upper' => $this->format_number($item->target),
        ]);
    }

    /**
     * Explain what a grade band earned, for the grade calculation, e.g. "+
     * 15% for the first 100 words" or "+ 1% for 6 of the next 30 words
     * (6/30 x 5%)".
     *
     * @param stdClass $item as returned by qtype_speakup_question::item_results().
     * @return string
     */
    protected function band_detail(stdClass $item): string {
        $plugin = 'qtype_speakup';
        $width = $item->target - $item->lower;
        $a = (object) [
            'percent' => $this->format_number($item->percent),
            'progress' => $this->format_number($item->progress),
            'width' => $this->format_number($width),
            'award' => $this->format_number($item->maxpercent),
        ];
        $complete = ($item->progress >= $width);
        if (\qtype_speakup\text_stats::is_count($item->itemtype)) {
            // E.g. "the first 100 words", "6 of the next 30 words".
            $a->amount = $this->item_amount($item->itemtype, $width);
            $name = ($item->lower > 0) ? 'calcitemnext' : 'calcitemfirst';
        } else {
            // E.g. "reaching 120 words per minute", "106 words per minute".
            $a->amount = $this->item_amount($item->itemtype, $complete ? $item->target : $item->value);
            $name = 'calcitemreached';
        }
        return get_string($name . ($complete ? '' : 'part'), $plugin, $a);
    }

    /**
     * Show the target phrases: for each one, whether it was used (and the
     * words that matched), and the share of the question grade it earned.
     * Students see each phrase's label, if it has one, rather than the
     * phrase itself, and never see a regular expression. Teachers also see
     * the phrase.
     *
     * @param stdClass[] $phrases as returned by qtype_speakup_question::phrase_results().
     * @param bool $isteacher
     * @return string HTML
     */
    protected function phrase_details(array $phrases, bool $isteacher): string {
        $plugin = 'qtype_speakup';
        $table = $this->details_table([
            '',
            get_string('detailsphrase', $plugin),
            get_string('detailsresult', $plugin),
            get_string('detailsgrade', $plugin),
        ]);
        foreach (array_values($phrases) as $i => $phrase) {
            $name = $this->phrase_name($phrase, $i + 1);
            $cell = s($name);
            if ($isteacher && $name !== $phrase->phrase) {
                $cell .= html_writer::div(s($phrase->phrase), 'small text-muted');
            }
            if ($phrase->used) {
                $matched = trim((string) $phrase->matched);
                if ($matched === '') {
                    $result = get_string('phraseusedplain', $plugin);
                } else {
                    $result = get_string('phraseused', $plugin, s($matched));
                }
                $result = $this->feedback_image(1.0) . ' ' . $result;
            } else {
                $result = $this->feedback_image(0.0) . ' ' . get_string('phrasenotused', $plugin);
            }
            $table->data[] = [
                $i + 1,
                $cell,
                $result,
                $this->percent_of($phrase->used ? $phrase->percent : 0, $phrase->percent),
            ];
        }
        return $this->details_section(get_string('phrasesheader', $plugin), html_writer::table($table), 'qtype_speakup-phrases');
    }

    /**
     * Explain how the grade was calculated, in sentences, as
     * qtype_essayautograde does: the maximum grade, the raw percentage and
     * what it is made of, any cap or penalty, the automatic grade, and any
     * later change to it by a teacher.
     *
     * The calculation follows qtype_speakup_question::combine_fractions()
     * and apply_transcript_penalty(). (Capping the target items and target
     * phrases at 100% each, as combine_fractions() does, makes no
     * difference, since the total is capped at 100% too.)
     *
     * @param qtype_speakup_question $question
     * @param question_attempt $qa
     * @param question_display_options $options
     * @param stdClass[] $items as returned by qtype_speakup_question::item_results().
     * @param stdClass[] $phrases as returned by qtype_speakup_question::phrase_results().
     * @param float|null $aifraction the AI's score (0..1), if assessed by AI.
     * @param bool $penalty whether the penalty for showing the transcript applies.
     * @return string HTML
     */
    protected function grade_calculation(
        qtype_speakup_question $question,
        question_attempt $qa,
        question_display_options $options,
        array $items,
        array $phrases,
        ?float $aifraction,
        bool $penalty
    ): string {
        $plugin = 'qtype_speakup';
        $dp = isset($options->markdp) ? (int) $options->markdp : 2;
        $maxgrade = $qa->format_max_mark($dp);

        $raw = 0.0;
        $details = [];
        foreach ($items as $item) {
            if ($item->progress !== null && $item->percent > 0) {
                $raw += $item->percent;
                $details[] = $this->band_detail($item);
            }
        }
        foreach (array_values($phrases) as $i => $phrase) {
            if ($phrase->used) {
                $raw += $phrase->percent;
                $details[] = get_string('calcphrase', $plugin, (object) [
                    'percent' => $this->format_number($phrase->percent),
                    'phrase' => s($this->phrase_name($phrase, $i + 1)),
                ]);
            }
        }
        if ($aifraction !== null) {
            $percent = $aifraction * (int) $question->aiweighting;
            $raw += $percent;
            $details[] = get_string('calcholistic', $plugin, (object) [
                'percent' => $this->format_number($percent),
                'score' => $this->format_number($aifraction * 100),
                'weighting' => (int) $question->aiweighting,
            ]);
        }

        $lines = [get_string('calcmaxgrade', $plugin, $maxgrade)];
        if ($details) {
            $lines[] = get_string('calcrawpercentdetails', $plugin, (object) [
                'percent' => $this->format_number($raw),
                'details' => implode(html_writer::empty_tag('br'), $details),
            ]);
        } else {
            $lines[] = get_string('calcrawpercent', $plugin, $this->format_number($raw));
        }
        $percent = $raw;
        if ($percent > 100) {
            $lines[] = get_string('calccapped', $plugin);
            $percent = 100.0;
        }
        if ($penalty) {
            // Taken off after the cap, as in apply_transcript_penalty().
            $lines[] = get_string('calcpenalty', $plugin, (int) $question->prompttranscriptpenalty);
            $percent = max(0.0, $percent - (int) $question->prompttranscriptpenalty);
        }
        $grade = $qa->get_max_mark() * $percent / 100;
        $lines[] = get_string('calcgrade', $plugin, (object) [
            'grade' => format_float($grade, $dp),
            'percent' => $this->format_number($percent),
            'maxgrade' => $maxgrade,
        ]);
        if ($override = $this->grade_override($question, $qa, $grade, $dp)) {
            $lines[] = $override;
        }

        $html = implode('', array_map(fn($line) => html_writer::tag('p', $line, ['class' => 'mb-1']), $lines));
        return $this->details_section(get_string('calculation', $plugin), $html, 'qtype_speakup-calculation');
    }

    /**
     * Say when a teacher changed the automatic grade, and to what, if they did.
     *
     * A teacher's grade is saved in a step with a "mark". A response graded
     * in the background also has one for the automatic grade, which comes
     * first (see classes/task/assess_response.php), so that one is skipped.
     *
     * @param qtype_speakup_question $question
     * @param question_attempt $qa
     * @param float $autograde the automatic grade.
     * @param int $dp decimal places for grades.
     * @return string the sentence, or '' if the grade was not changed.
     */
    protected function grade_override(qtype_speakup_question $question, question_attempt $qa, float $autograde, int $dp): string {
        $steps = [];
        foreach ($qa->get_step_iterator() as $step) {
            $mark = $step->get_behaviour_var('mark');
            if ($mark !== null && $mark !== '') {
                $steps[] = $step;
            }
        }
        if ($question->is_graded_later()) {
            array_shift($steps);
        }
        $step = end($steps);
        if (!$step) {
            return '';
        }
        $grade = format_float((float) $step->get_behaviour_var('mark'), $dp);
        if ($grade === format_float($autograde, $dp)) {
            return '';
        }
        return get_string('calcoverride', 'qtype_speakup', (object) [
            'date' => userdate($step->get_timecreated(), get_string('strftimedatetime', 'langconfig')),
            'grade' => $grade,
        ]);
    }

    /**
     * Suggest how to improve the grade next time: reach the targets that
     * were not reached, and use the target phrases that were not used.
     *
     * @param stdClass[] $items the target items that the viewer can see.
     * @param stdClass[] $phrases the target phrases that the viewer can see.
     * @return string HTML
     */
    protected function hints(array $items, array $phrases): string {
        $plugin = 'qtype_speakup';

        // For each kind of item, the highest target that was not reached.
        $targets = [];
        foreach ($items as $item) {
            if ($item->value !== null && $item->value < $item->target) {
                $targets[$item->itemtype] = max($targets[$item->itemtype] ?? 0, $item->target);
            }
        }
        $hints = [];
        foreach ($targets as $itemtype => $target) {
            $hints[] = get_string('hintitem', $plugin, $this->item_amount($itemtype, $target));
        }

        // A regular expression with no label means nothing to students, so
        // for those, just suggest using all the phrases.
        $unnamed = false;
        foreach ($phrases as $phrase) {
            if ($phrase->used) {
                continue;
            }
            if ($phrase->label !== '') {
                $hints[] = get_string('hintphrase', $plugin, s($phrase->label));
            } else if (!$phrase->isregex) {
                $hints[] = get_string('hintphrase', $plugin, s($phrase->phrase));
            } else {
                $unnamed = true;
            }
        }
        if ($unnamed) {
            $hints[] = get_string('hintphrases', $plugin);
        }

        if (empty($hints)) {
            return '';
        }
        return $this->details_section(get_string('hintsheader', $plugin), html_writer::alist($hints), 'qtype_speakup-hints');
    }

    /**
     * Get the name of a target phrase to show students: its label, if it
     * has one, or else the phrase itself, unless it is a regular expression.
     *
     * @param stdClass $phrase as returned by qtype_speakup_question::phrase_results().
     * @param int $number the phrase's number in the list.
     * @return string plain text
     */
    protected function phrase_name(stdClass $phrase, int $number): string {
        if ($phrase->label !== '') {
            return $phrase->label;
        }
        if ($phrase->isregex) {
            return get_string('phrasenumber', 'qtype_speakup', $number);
        }
        return $phrase->phrase;
    }

    /**
     * Describe an amount of a target item, e.g. "53 words".
     *
     * @param string $itemtype
     * @param float $value
     * @return string
     */
    protected function item_amount(string $itemtype, float $value): string {
        return get_string('rubricitem' . $itemtype, 'qtype_speakup', $this->format_number($value));
    }

    /**
     * Format a number to at most one decimal place, without trailing zeros.
     *
     * @param float|null $value
     * @return string the number, or "unknown" if it is null.
     */
    protected function format_number(?float $value): string {
        if ($value === null) {
            return get_string('itemunknown', 'qtype_speakup');
        }
        return format_float(round($value, 1), 1, true, true);
    }

    /**
     * Show a share of the question grade out of the most it could be, e.g. "8% (of 10%)".
     *
     * @param float $percent
     * @param float $maxpercent
     * @return string
     */
    protected function percent_of(float $percent, float $maxpercent): string {
        return get_string('detailspercentof', 'qtype_speakup', (object) [
            'percent' => $this->format_number($percent),
            'max' => $this->format_number($maxpercent),
        ]);
    }

    /**
     * Make a striped table for one section of the grading details.
     *
     * @param string[] $head the column headings.
     * @return html_table
     */
    protected function details_table(array $head): html_table {
        $table = new html_table();
        $table->head = $head;
        $table->attributes['class'] = 'generaltable qtype_speakup-details-table w-auto mb-1';
        return $table;
    }

    /**
     * Show one section of the grading details: a heading and its content.
     *
     * @param string $heading
     * @param string $content HTML
     * @param string $class CSS class for the section.
     * @return string HTML
     */
    protected function details_section(string $heading, string $content, string $class): string {
        return html_writer::div(
            html_writer::tag('h5', $heading, ['class' => 'mt-3 mb-1']) . $content,
            'qtype_speakup-grading-details ' . $class
        );
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
            'silencestopped',
            'startrecording',
            'stoprecording',
            'startsharescreen',
            'timedisplay',
            'uploadaborted',
            'uploadwait',
            'uploadcomplete',
            'uploadfailed',
            'uploadfailed404',
            'uploadpreparing',
            'uploadpreparingpercent',
            'uploadprogress',
        ];
    }
}
