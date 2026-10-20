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
            if ($question->gradingmethod === SPEAKUP_GRADING_EXACT) {
                [, $matchfeedback] = $question->grade_transcript($browsertranscript);
                if ($matchfeedback !== '') {
                    $output .= html_writer::tag('div', s($matchfeedback),
                            ['class' => 'qtype_speakup-suggested-match']);
                }
            } else if ($question->gradingmethod === SPEAKUP_GRADING_AI) {
                // AI assessment runs later, in an adhoc task. Until it is
                // done, say so, rather than silently showing no grade.
                // Once it is done, show nothing here: the AI's feedback is
                // saved as the grading comment, which Moodle shows itself.
                $airesponse = $qadatabaseid ? $DB->get_record('qtype_speakup_response',
                        ['questionattemptid' => $qadatabaseid]) : false;
                if ($airesponse && $airesponse->aistatus === 'done') {
                    // Nothing to add - see above.
                } else if ($airesponse && $airesponse->aistatus === 'failed') {
                    $output .= html_writer::tag('div', get_string('aiassessmentfailed', 'qtype_speakup'),
                            ['class' => 'qtype_speakup-ai-feedback text-danger']);
                } else {
                    if (!$airesponse) {
                        // Previews never fire the quiz event that queues
                        // AI assessment, so queue them here instead.
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

            if ($question->gradingmethod === SPEAKUP_GRADING_EXACT || $question->gradingmethod === SPEAKUP_GRADING_AI) {
                // Visible live transcript, filled in (and shown/hidden) by
                // amd/src/speechrecognition.js as the student speaks. Stays
                // empty and invisible for browsers/languages where live
                // transcription isn't available - it never blocks or
                // affects the recording itself. Also used for AI mode when
                // the chosen provider is text-only (e.g. core AI), which
                // has no other way to get a transcript.
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
            // only meaningful for pattern-matching questions. It is always
            // safe to call this even on unsupported browsers - the module
            // simply does nothing in that case, leaving the hidden
            // 'browsertranscript' field empty and the recording/upload
            // path completely unaffected.
            if ($question->gradingmethod === SPEAKUP_GRADING_EXACT || $question->gradingmethod === SPEAKUP_GRADING_AI) {
                $this->page->requires->js_call_amd('qtype_speakup/speechrecognition', 'init',
                        [$qa->get_outer_question_div_unique_id(), $question->sttlanguage]);
            }
        }

        $this->page->requires->js_call_amd('qtype_speakup/avrecording', 'addPlaybackErrorHandlingToVideoElements',
                [$qa->get_outer_question_div_unique_id()]);

        return $output;
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
