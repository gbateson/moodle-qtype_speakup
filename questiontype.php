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
 * Question type class for qtype_speakup.
 *
 * Storage/upload pattern (draft file area -> permanent question_attempt file
 * area) follows the same approach used by qtype_recordrtc (The Open
 * University, GPL v3+), adapted for a single timed audio response rather
 * than multiple av widgets.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/type/questiontypebase.php');

/** Grade the transcript against expected answers / regular expressions. */
define('SPEAKUP_GRADING_EXACT', 'exact');
/** Send the transcript (and/or audio) to an AI assessment engine. */
define('SPEAKUP_GRADING_AI', 'ai');
/** No automatic grading; a teacher must grade manually. */
define('SPEAKUP_GRADING_MANUAL', 'manual');

/** Feedback is for the student's own improvement, not necessarily graded. */
define('SPEAKUP_FEEDBACK_FORMATIVE', 'formative');
/** Feedback accompanies a final grade. */
define('SPEAKUP_FEEDBACK_SUMMATIVE', 'summative');

/** Student records audio only. */
define('SPEAKUP_MEDIA_AUDIO', 'audio');
/** Student records audio + webcam video, e.g. for assessing non-verbal communication too. */
define('SPEAKUP_MEDIA_VIDEO', 'video');

/** Student records their response live in the browser. */
define('SPEAKUP_RESPONSE_RECORD', 'record');
/**
 * Student uploads an audio or video file. On a smartphone, choosing a
 * file usually also offers to record one there and then.
 */
define('SPEAKUP_RESPONSE_UPLOAD', 'upload');

/**
 * Default recorder quality/size settings.
 *
 * TODO: move these to an admin settings.php page (audio/video bitrate,
 * video width/height) once the plugin has one - qtype_recordrtc exposes
 * these as site-wide admin settings rather than hard-coded constants.
 */
define('SPEAKUP_AUDIO_BITRATE', 128000);
define('SPEAKUP_VIDEO_BITRATE', 2500000);
define('SPEAKUP_VIDEO_WIDTH', 640);
define('SPEAKUP_VIDEO_HEIGHT', 480);

class qtype_speakup extends question_type {

    #[\Override]
    public function extra_question_fields() {
        return [
            'qtype_speakup_options',
            'timelimitinseconds',
            'mediatype',
            'gradingmethod',
            'sttlanguage',
            'allowpause',
            'feedbackmode',
            'airubric',
            'aiproviderid',
            'responsemethod',
        ];
    }

    #[\Override]
    public function questionid_column_name() {
        return 'questionid';
    }

    #[\Override]
    public function save_question_options($question) {
        global $DB;

        // For pattern matching, aiproviderid holds the transcription
        // provider, which the question form has in a separate field (see
        // edit_speakup_form.php). It is only needed for uploaded files.
        if ($question->gradingmethod === SPEAKUP_GRADING_EXACT && property_exists($question, 'transcriptionproviderid')) {
            $isupload = ($question->responsemethod ?? SPEAKUP_RESPONSE_RECORD) === SPEAKUP_RESPONSE_UPLOAD;
            $question->aiproviderid = ($isupload && !empty($question->transcriptionproviderid))
                    ? (int) $question->transcriptionproviderid : null;
        }

        parent::save_question_options($question);

        // Save one row per phrase, from the repeated answertext/weight/
        // casesensitive form fields (see edit_speakup_form.php). Similar in
        // spirit to how shortanswer/regexp question types store multiple
        // rows keyed on questionid, but with a weighted-phrase shape
        // modelled on qtype_essayautograde's target-phrase scoring.
        $DB->delete_records('qtype_speakup_answers', ['questionid' => $question->id]);

        if (!empty($question->answertext)) {
            foreach ($question->answertext as $key => $answertext) {
                $answertext = trim($answertext);
                if ($answertext === '') {
                    continue;
                }
                $record = new stdClass();
                $record->questionid = $question->id;
                $record->answertext = $answertext;
                $record->isregex = (bool) preg_match('#^/.*/[a-zA-Z]*$#', $answertext);
                $record->weight = isset($question->weight[$key]) ? round((float) $question->weight[$key], 2) : 0;
                $record->casesensitive = !empty($question->casesensitive[$key]) ? 1 : 0;
                $DB->insert_record('qtype_speakup_answers', $record);
            }
        }

        return true;
    }

    #[\Override]
    public function get_question_options($question) {
        global $DB;
        parent::get_question_options($question);

        $answers = $DB->get_records('qtype_speakup_answers', ['questionid' => $question->id], 'id ASC');
        $question->options->expectedanswers = array_values($answers);

        return true;
    }

    /**
     * Copy the expected-answers array from $questiondata->options onto the
     * live question instance.
     *
     * Moodle's base question_type::initialise_question_instance() only
     * auto-copies scalar fields from $questiondata->options onto $question
     * - it deliberately skips arrays. Since our expected answers live in a
     * separate child table (qtype_speakup_answers) rather than a flat
     * column, and are loaded as an array in get_question_options() above,
     * they need to be wired across explicitly here - the same pattern
     * Moodle core's own qtype_shortanswer uses for its own answers table.
     * Without this, $question->expectedanswers is always empty and
     * grade_by_pattern() silently never matches anything, regardless of
     * what was actually said.
     *
     * @param question_definition $question the question instance.
     * @param object $questiondata the question data loaded from the database.
     */
    #[\Override]
    protected function initialise_question_instance(question_definition $question, $questiondata) {
        parent::initialise_question_instance($question, $questiondata);
        $question->expectedanswers = $questiondata->options->expectedanswers ?? [];
    }

    #[\Override]
    public function delete_question($questionid, $contextid) {
        global $DB;
        $DB->delete_records('qtype_speakup_answers', ['questionid' => $questionid]);
        parent::delete_question($questionid, $contextid);
    }

    #[\Override]
    public function move_files($questionid, $oldcontextid, $newcontextid) {
        parent::move_files($questionid, $oldcontextid, $newcontextid);
        $fs = get_file_storage();
        $fs->move_area_files_to_new_context($oldcontextid, $newcontextid, 'qtype_speakup', 'audioprompt', $questionid);
    }

    #[\Override]
    protected function delete_files($questionid, $contextid) {
        parent::delete_files($questionid, $contextid);
        $fs = get_file_storage();
        $fs->delete_area_files($contextid, 'qtype_speakup', 'audioprompt', $questionid);
    }
}
