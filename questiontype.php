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

/**
 * Automated grading: a grade made up of matched target phrases, plus the
 * AI assessment's score (if used) multiplied by its weighting.
 */
define('SPEAKUP_GRADING_AUTO', 'auto');
/** No automatic grading; a teacher must grade manually. */
define('SPEAKUP_GRADING_MANUAL', 'manual');

/** Feedback is for the student's own improvement, not necessarily graded. */
define('SPEAKUP_FEEDBACK_FORMATIVE', 'formative');
/** Feedback accompanies a final grade. */
define('SPEAKUP_FEEDBACK_SUMMATIVE', 'summative');

/** Show this grading detail to nobody. (Values as in qtype_essayautograde.) */
define('SPEAKUP_SHOW_NONE', 0);
/** Show this grading detail to students only. */
define('SPEAKUP_SHOW_STUDENTS_ONLY', 1);
/** Show this grading detail to teachers only. */
define('SPEAKUP_SHOW_TEACHERS_ONLY', 2);
/** Show this grading detail to teachers and students. */
define('SPEAKUP_SHOW_TEACHERS_AND_STUDENTS', 3);

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
            'aiassessment',
            'aiweighting',
            'showcalculation',
            'showphrases',
            'showitems',
            'showcomment',
            'showtranscript',
            'showstats',
            'statsitems',
        ];
    }

    #[\Override]
    public function questionid_column_name() {
        return 'questionid';
    }

    #[\Override]
    public function save_question_options($question) {
        global $DB;

        // Without AI assessment, aiproviderid holds the transcription
        // provider, which the question form has in a separate field (see
        // edit_speakup_form.php). It is only needed for uploaded files.
        if ($question->gradingmethod === SPEAKUP_GRADING_AUTO && empty($question->aiassessment)
                && property_exists($question, 'transcriptionproviderid')) {
            $isupload = ($question->responsemethod ?? SPEAKUP_RESPONSE_RECORD) === SPEAKUP_RESPONSE_UPLOAD;
            $question->aiproviderid = ($isupload && !empty($question->transcriptionproviderid))
                    ? (int) $question->transcriptionproviderid : null;
        }

        // The form sends the statistics to show as checkboxes,
        // statsitems[name] => 0 or 1. They are stored as a list, e.g.
        // "words,uniquewords", keeping only the statistics that exist.
        if (isset($question->statsitems) && is_array($question->statsitems)) {
            $allowed = array_merge(\qtype_speakup\text_stats::get_stat_types(false),
                    \qtype_speakup\text_stats::get_stat_types(true));
            $question->statsitems = implode(',', array_intersect($allowed, array_keys(array_filter($question->statsitems))));
        }

        parent::save_question_options($question);

        // Save one row per target phrase, from the repeated phrase fields
        // (see edit_speakup_form.php): "If [phrasematch] is used, award
        // [phrasepercent] divided by [phrasedivisor] of the question grade",
        // modelled on qtype_essayautograde's target phrases.
        $DB->delete_records('qtype_speakup_answers', ['questionid' => $question->id]);

        if (!empty($question->phrasematch)) {
            foreach ($question->phrasematch as $key => $phrase) {
                $phrase = trim($phrase);
                if ($phrase === '') {
                    continue;
                }
                $record = new stdClass();
                $record->questionid = $question->id;
                $record->answertext = $phrase;
                $record->isregex = (int) preg_match('#^/.*/[a-zA-Z]*$#', $phrase);
                $record->percent = min(100, max(0, (int) ($question->phrasepercent[$key] ?? 0)));
                if ($record->percent == 0) {
                    // A phrase that awards nothing is ignored, like an empty one.
                    continue;
                }
                $record->divisor = min(20, max(1, (int) ($question->phrasedivisor[$key] ?? 1)));
                $record->fullmatch = empty($question->phrasefullmatch[$key]) ? 0 : 1;
                $record->casesensitive = empty($question->phrasecasesensitive[$key]) ? 0 : 1;
                $DB->insert_record('qtype_speakup_answers', $record);
            }
        }

        // Save one row per target item: "Target for [itemtype] is
        // [itemcount]. Award up to [itempercent] [multiplied by
        // itemmultiplier] of the question grade". Rows with no percent or
        // no target are skipped.
        $DB->delete_records('qtype_speakup_items', ['questionid' => $question->id]);

        if (!empty($question->itemtype)) {
            foreach ($question->itemtype as $key => $itemtype) {
                $percent = min(100, max(0, (int) ($question->itempercent[$key] ?? 0)));
                $itemcount = max(0, (float) ($question->itemcount[$key] ?? 0));
                if ($percent == 0 || $itemcount == 0 || !in_array($itemtype, \qtype_speakup\text_stats::get_item_types())) {
                    continue;
                }
                $record = new stdClass();
                $record->questionid = $question->id;
                $record->itemtype = $itemtype;
                $record->itemcount = $itemcount;
                $record->percent = $percent;
                $record->multiplier = min(10, max(1, (int) ($question->itemmultiplier[$key] ?? 1)));
                $DB->insert_record('qtype_speakup_items', $record);
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

        $items = $DB->get_records('qtype_speakup_items', ['questionid' => $question->id], 'id ASC');
        $question->options->targetitems = array_values($items);

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
        $question->targetitems = $questiondata->options->targetitems ?? [];
    }

    #[\Override]
    public function delete_question($questionid, $contextid) {
        global $DB;
        $DB->delete_records('qtype_speakup_answers', ['questionid' => $questionid]);
        $DB->delete_records('qtype_speakup_items', ['questionid' => $questionid]);
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
