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
            'feedbackmode',
            'airubric',
        ];
    }

    #[\Override]
    public function questionid_column_name() {
        return 'questionid';
    }

    #[\Override]
    public function save_question_options($question) {
        global $DB;
        parent::save_question_options($question);

        // Save one row per expected-answer / regex line into a child table,
        // similar in spirit to how shortanswer/regexp question types store
        // multiple qtype_*_options rows keyed on questionid.
        $DB->delete_records('qtype_speakup_answers', ['questionid' => $question->id]);

        if (!empty($question->expectedanswers)) {
            $lines = preg_split('/\r\n|\r|\n/', $question->expectedanswers);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $record = new stdClass();
                $record->questionid = $question->id;
                $record->answertext = $line;
                $record->isregex = (bool) preg_match('#^/.*/[a-zA-Z]*$#', $line);
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
