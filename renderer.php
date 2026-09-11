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
        /** @var qtype_speakup_question $question */
        $question = $qa->get_question();
        $candownload = has_capability('qtype/speakup:downloadrecordings', $this->page->context);
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

        } else {
            // Being attempted: active recorder widget.
            $recordingurl = $existingfile ? moodle_url::make_draftfile_url($draftitemid, '/', $filename) : null;
            $widget = ($question->mediatype === SPEAKUP_MEDIA_VIDEO)
                    ? new video_recorder($filename, $question->timelimitinseconds,
                            false, $recordingurl, $candownload)
                    : new audio_recorder($filename, $question->timelimitinseconds,
                            false, $recordingurl, $candownload);
            $output .= $this->render($widget);

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
