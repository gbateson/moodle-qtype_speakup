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

/** The audio prompt transcript is not shown to students. */
define('SPEAKUP_PROMPTTRANSCRIPT_HIDE', 0);
/** The audio prompt transcript is collapsed: students can choose to show it. */
define('SPEAKUP_PROMPTTRANSCRIPT_COLLAPSED', 1);
/** The audio prompt transcript is shown to students. */
define('SPEAKUP_PROMPTTRANSCRIPT_EXPANDED', 2);

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
            'audioprompttranscript',
            'prompttranscriptdisplay',
            'transcriptpenalty',
            'recordafterprompt',
            'maxpromptplays',
            'maxresponses',
            'silencestop',
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

        // Save the audio prompt (recorded or uploaded on the form) from its
        // draft file area. At most one file is kept.
        if (isset($question->audioprompt) && isset($question->context)) {
            file_save_draft_area_files($question->audioprompt, $question->context->id,
                    'qtype_speakup', 'audioprompt', $question->id, ['subdirs' => 0, 'maxfiles' => 1]);
        }

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

    /**
     * Export a question to Moodle XML.
     *
     * Writes the question's settings, its AI provider (by name, since
     * provider ids differ between sites), the audio prompt file, and the
     * target items and phrases.
     *
     * @param stdClass $question the question data, with ->options.
     * @param qformat_xml $format
     * @param mixed $extra
     * @return string the XML for this question type's own data.
     */
    #[\Override]
    public function export_to_xml($question, qformat_xml $format, $extra = null) {
        global $DB;

        $output = '';
        $fields = $this->extra_question_fields();
        array_shift($fields); // Omit the table name.
        foreach ($fields as $field) {
            // Not set (null) is left out, so that importing it gives the
            // default, e.g. statsitems: null means "never chosen", whereas
            // an empty value means "none ticked".
            if ($field === 'aiproviderid' || !isset($question->options->$field)) {
                continue;
            }
            $value = $format->xml_escape((string) $question->options->$field);
            $output .= "    <{$field}>{$value}</{$field}>\n";
        }

        if (!empty($question->options->aiproviderid)) {
            $name = $DB->get_field('qtype_speakup_ai_providers', 'name', ['id' => $question->options->aiproviderid]);
            if ($name !== false) {
                $output .= '    <aiprovider>' . $format->xml_escape($name) . "</aiprovider>\n";
            }
        }

        $files = get_file_storage()->get_area_files($question->contextid, 'qtype_speakup', 'audioprompt',
                $question->id, 'filename', false);
        if ($files) {
            $output .= "    <audioprompt>\n" . $format->write_files($files) . "\n    </audioprompt>\n";
        }

        foreach ($question->options->targetitems ?? [] as $item) {
            $output .= "    <targetitem>\n";
            foreach (['itemtype', 'itemcount', 'percent', 'multiplier'] as $field) {
                $output .= "      <{$field}>" . $format->xml_escape((string) $item->$field) . "</{$field}>\n";
            }
            $output .= "    </targetitem>\n";
        }

        foreach ($question->options->expectedanswers ?? [] as $phrase) {
            $output .= "    <targetphrase>\n";
            $output .= '      <text>' . $format->xml_escape($phrase->answertext) . "</text>\n";
            foreach (['percent', 'divisor', 'fullmatch', 'casesensitive'] as $field) {
                $output .= "      <{$field}>" . $format->xml_escape((string) $phrase->$field) . "</{$field}>\n";
            }
            $output .= "    </targetphrase>\n";
        }

        return $output;
    }

    /**
     * Import a question from Moodle XML (see export_to_xml()).
     *
     * The target items and phrases, and the audio prompt, are turned into
     * the same data the question form sends, so save_question_options()
     * saves them in the usual way. The AI provider is matched by name; if
     * this site has no provider with that name, none is set.
     *
     * @param array $data the XML for one question, as parsed by qformat_xml.
     * @param stdClass|null $question
     * @param qformat_xml $format
     * @param mixed $extra
     * @return stdClass|false the question data, or false if not a SpeakUp question.
     */
    #[\Override]
    public function import_from_xml($data, $question, qformat_xml $format, $extra = null) {
        global $DB;

        if (($data['@']['type'] ?? '') !== $this->name()) {
            return false;
        }

        $qo = $format->import_headers($data);
        $qo->qtype = $this->name();

        $fields = $this->extra_question_fields();
        array_shift($fields); // Omit the table name.
        foreach ($fields as $field) {
            if ($field === 'aiproviderid') {
                continue;
            }
            $default = $this->get_field_default($field);
            $qo->$field = $format->getpath($data, ['#', $field, 0, '#'], $default);
        }

        $qo->aiproviderid = null;
        $providername = trim($format->getpath($data, ['#', 'aiprovider', 0, '#'], ''));
        if ($providername !== '') {
            $providerid = $DB->get_field('qtype_speakup_ai_providers', 'id', ['name' => $providername], IGNORE_MULTIPLE);
            if ($providerid) {
                $qo->aiproviderid = (int) $providerid;
            }
        }

        $files = $format->getpath($data, ['#', 'audioprompt', 0, '#', 'file'], []);
        if ($files) {
            $qo->audioprompt = $format->import_files_as_draft($files);
        }

        $qo->itemtype = [];
        $qo->itemcount = [];
        $qo->itempercent = [];
        $qo->itemmultiplier = [];
        foreach ($format->getpath($data, ['#', 'targetitem'], []) as $item) {
            $qo->itemtype[] = $format->getpath($item, ['#', 'itemtype', 0, '#'], '');
            $qo->itemcount[] = $format->getpath($item, ['#', 'itemcount', 0, '#'], 0);
            $qo->itempercent[] = $format->getpath($item, ['#', 'percent', 0, '#'], 0);
            $qo->itemmultiplier[] = $format->getpath($item, ['#', 'multiplier', 0, '#'], 1);
        }

        $qo->phrasematch = [];
        $qo->phrasepercent = [];
        $qo->phrasedivisor = [];
        $qo->phrasefullmatch = [];
        $qo->phrasecasesensitive = [];
        foreach ($format->getpath($data, ['#', 'targetphrase'], []) as $phrase) {
            $qo->phrasematch[] = $format->getpath($phrase, ['#', 'text', 0, '#'], '');
            $qo->phrasepercent[] = $format->getpath($phrase, ['#', 'percent', 0, '#'], 0);
            $qo->phrasedivisor[] = $format->getpath($phrase, ['#', 'divisor', 0, '#'], 1);
            $qo->phrasefullmatch[] = $format->getpath($phrase, ['#', 'fullmatch', 0, '#'], 0);
            $qo->phrasecasesensitive[] = $format->getpath($phrase, ['#', 'casesensitive', 0, '#'], 0);
        }

        return $qo;
    }

    /**
     * Get the default value of a field in qtype_speakup_options, for
     * importing a question that was exported before the field existed.
     *
     * @param string $field
     * @return mixed the default, or '' if it has none.
     */
    protected function get_field_default(string $field) {
        global $DB;
        static $columns = null;
        if ($columns === null) {
            $columns = $DB->get_columns('qtype_speakup_options');
        }
        if (isset($columns[$field]) && $columns[$field]->has_default) {
            return $columns[$field]->default_value;
        }
        return isset($columns[$field]) && !$columns[$field]->not_null ? null : '';
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
