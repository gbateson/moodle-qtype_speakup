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
/** Student uploads one or more images or PDFs, e.g. scans of handwriting or drawings. */
define('SPEAKUP_MEDIA_IMAGE', 'image');
/** The most files a student can upload for an image response. */
define('SPEAKUP_MAX_IMAGE_FILES', 5);

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
            'audiopromptvoice',
            'prompttranscriptdisplay',
            'transcriptpenalty',
            'recordafterprompt',
            'maxpromptplays',
            'maxresponses',
            'silencestop',
            'maxfiles',
        ];
    }

    #[\Override]
    public function questionid_column_name() {
        return 'questionid';
    }

    #[\Override]
    public function save_question_options($question) {
        global $DB;

        // Images (e.g. scans of handwriting) are always uploaded.
        if (($question->mediatype ?? '') === SPEAKUP_MEDIA_IMAGE) {
            $question->responsemethod = SPEAKUP_RESPONSE_UPLOAD;
        }
        if (isset($question->maxfiles)) {
            $question->maxfiles = max(1, min(SPEAKUP_MAX_IMAGE_FILES, (int) $question->maxfiles));
        }

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

        // Without an audio prompt, generate one from its transcript, in the
        // background, if a speech provider is set up.
        if (isset($question->context)) {
            \qtype_speakup\task\generate_audio_prompt::queue_if_needed($question->id, $question->context->id,
                    $question->audioprompttranscript ?? null);
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
     * Names of the settings in GIFT, in upper case, => what they set. Each
     * setting can be given by its field name or by a shorter name.
     *
     * Most settings set the field of the same name. These set more than one
     * field, or need converting: "aiprovider" (a provider name), "ai"
     * (assessment, provider and weighting), "show" (the five "Show ..."
     * settings), "stats" (statistics), "targetitem" and "targetphrase".
     *
     * @return array
     */
    protected function get_gift_names(): array {
        $names = [];
        $fields = $this->extra_question_fields();
        array_shift($fields); // Omit the table name.
        foreach ($fields as $field) {
            $names[strtoupper($field)] = $field;
        }
        $names['AIPROVIDERID'] = 'aiprovider';
        return $names + [
            'TIMELIMIT' => 'timelimitinseconds',
            'MEDIA' => 'mediatype',
            'RESPONSE' => 'responsemethod',
            'LANGUAGE' => 'sttlanguage',
            'LANG' => 'sttlanguage',
            'PAUSE' => 'allowpause',
            'GRADING' => 'gradingmethod',
            'FEEDBACK' => 'feedbackmode',
            'RUBRIC' => 'airubric',
            'AIPROVIDER' => 'aiprovider',
            'PROVIDER' => 'aiprovider',
            'WEIGHTING' => 'aiweighting',
            'AI' => 'ai',
            'SHOW' => 'show',
            'STATS' => 'stats',
            'AUDIO' => 'audioprompttranscript',
            'VOICE' => 'audiopromptvoice',
            'PROMPTTRANSCRIPT' => 'prompttranscriptdisplay',
            'TRANSCRIPT' => 'prompttranscriptdisplay',
            'PENALTY' => 'transcriptpenalty',
            'AUTORECORD' => 'recordafterprompt',
            'PLAYS' => 'maxpromptplays',
            'RESPONSES' => 'maxresponses',
            'SILENCE' => 'silencestop',
            'FILES' => 'maxfiles',
            'PAGES' => 'maxfiles',
            'TARGETITEM' => 'targetitem',
            'TARGETITEMS' => 'targetitem',
            'ITEM' => 'targetitem',
            'ITEMS' => 'targetitem',
            'TARGETPHRASE' => 'targetphrase',
            'TARGETPHRASES' => 'targetphrase',
            'PHRASE' => 'targetphrase',
            'PHRASES' => 'targetphrase',
        ];
    }

    /**
     * Words for the values of the "Show ..." settings in GIFT.
     *
     * @return array word => value. The first word for each value is the one exported.
     */
    protected static function get_gift_show_words(): array {
        return [
            'none' => SPEAKUP_SHOW_NONE, 'no' => SPEAKUP_SHOW_NONE, 'nobody' => SPEAKUP_SHOW_NONE,
            'students' => SPEAKUP_SHOW_STUDENTS_ONLY, 'student' => SPEAKUP_SHOW_STUDENTS_ONLY,
            'teachers' => SPEAKUP_SHOW_TEACHERS_ONLY, 'teacher' => SPEAKUP_SHOW_TEACHERS_ONLY,
            'both' => SPEAKUP_SHOW_TEACHERS_AND_STUDENTS, 'all' => SPEAKUP_SHOW_TEACHERS_AND_STUDENTS,
            'yes' => SPEAKUP_SHOW_TEACHERS_AND_STUDENTS,
        ];
    }

    /**
     * Words for the values of the prompt transcript setting in GIFT.
     *
     * @return array word => value. The first word for each value is the one exported.
     */
    protected static function get_gift_transcript_words(): array {
        return [
            'hide' => SPEAKUP_PROMPTTRANSCRIPT_HIDE, 'no' => SPEAKUP_PROMPTTRANSCRIPT_HIDE,
            'none' => SPEAKUP_PROMPTTRANSCRIPT_HIDE, 'hidden' => SPEAKUP_PROMPTTRANSCRIPT_HIDE,
            'collapsed' => SPEAKUP_PROMPTTRANSCRIPT_COLLAPSED, 'collapse' => SPEAKUP_PROMPTTRANSCRIPT_COLLAPSED,
            'expanded' => SPEAKUP_PROMPTTRANSCRIPT_EXPANDED, 'expand' => SPEAKUP_PROMPTTRANSCRIPT_EXPANDED,
            'show' => SPEAKUP_PROMPTTRANSCRIPT_EXPANDED, 'yes' => SPEAKUP_PROMPTTRANSCRIPT_EXPANDED,
        ];
    }

    /**
     * The "Show ..." settings, by the names used in GIFT's SHOW setting.
     *
     * @return array name => field
     */
    protected static function get_gift_show_fields(): array {
        return [
            'calculation' => 'showcalculation',
            'phrases' => 'showphrases',
            'items' => 'showitems',
            'comment' => 'showcomment',
            'transcript' => 'showtranscript',
        ];
    }

    /**
     * Escape GIFT's special characters in a value, and write line breaks as "\n".
     *
     * @param string $text
     * @return string
     */
    protected static function gift_escape(string $text): string {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        return str_replace(['\\', '#', '=', '~', '{', '}', ':', "\n"],
                ['\\\\', '\#', '\=', '\~', '\{', '\}', '\:', '\n'], $text);
    }

    /**
     * Restore the characters that qformat_gift replaced with placeholders
     * while it parsed the question (see qformat_gift::escapedchar_pre()).
     *
     * @param string $text
     * @return string
     */
    protected static function gift_unescape(string $text): string {
        return str_replace(['&&058;', '&&035;', '&&061;', '&&123;', '&&125;', '&&126;', '&&010'],
                [':', '#', '=', '{', '}', '~', "\n"], $text);
    }

    /**
     * Export a question to GIFT.
     *
     * The settings go inside the braces, one per line, starting with the
     * audio prompt transcript ("AUDIO: ...") or, if there is none, "SPEAKUP".
     * The audio prompt file cannot be exported: on import, it is generated
     * again from the transcript.
     *
     * @param stdClass $question the question data, with ->options.
     * @param qformat_gift $format
     * @param mixed $extra
     * @return string
     */
    public function export_to_gift($question, $format, $extra = null) {
        global $DB;
        $o = $question->options;
        $output = '';

        $transcript = trim((string) ($o->audioprompttranscript ?? ''));
        $hasfile = !get_file_storage()->is_area_empty($question->contextid, 'qtype_speakup', 'audioprompt',
                $question->id, false);
        if ($hasfile && $transcript === '') {
            $output .= '// ' . get_string('giftnoaudioprompt', 'qtype_speakup') . "\n";
        }

        $output .= $format->write_name($question->name);
        $output .= $format->write_questiontext($question->questiontext, $question->questiontextformat);
        $output .= "{\n";

        $lines = [];
        if ($transcript === '') {
            $lines[] = 'SPEAKUP';
        } else {
            $voice = trim((string) ($o->audiopromptvoice ?? ''));
            $lines[] = 'AUDIO' . (($voice === '') ? '' : '(voice=' . self::gift_escape($voice) . ')') .
                    ': ' . self::gift_escape($transcript);
        }

        $yesno = fn($value) => empty($value) ? 'no' : 'yes';
        $showwords = array_flip(array_reverse(self::get_gift_show_words(), true));
        $transcriptwords = array_flip(array_reverse(self::get_gift_transcript_words(), true));

        $settings = [
            'TIMELIMIT' => (int) $o->timelimitinseconds,
            'MEDIA' => $o->mediatype,
            'RESPONSE' => $o->responsemethod,
            'LANGUAGE' => $o->sttlanguage,
            'PAUSE' => $yesno($o->allowpause),
            'RECORDAFTERPROMPT' => $yesno($o->recordafterprompt),
            'MAXPROMPTPLAYS' => (int) $o->maxpromptplays,
            'MAXRESPONSES' => (int) $o->maxresponses,
            'SILENCESTOP' => (int) $o->silencestop,
            'PROMPTTRANSCRIPT' => $transcriptwords[(int) $o->prompttranscriptdisplay] ?? 'collapsed',
            'TRANSCRIPTPENALTY' => (int) $o->transcriptpenalty . '%',
            'GRADING' => $o->gradingmethod,
            'FEEDBACK' => $o->feedbackmode,
        ];
        if ($o->mediatype === SPEAKUP_MEDIA_IMAGE) {
            // Images are always uploaded, and have no length.
            unset($settings['TIMELIMIT'], $settings['RESPONSE'], $settings['PAUSE'], $settings['RECORDAFTERPROMPT'],
                    $settings['MAXRESPONSES'], $settings['SILENCESTOP']);
            $settings = ['MAXFILES' => (int) ($o->maxfiles ?? 1)] + $settings;
        }

        $providername = '';
        if (!empty($o->aiproviderid)) {
            $providername = (string) $DB->get_field('qtype_speakup_ai_providers', 'name', ['id' => $o->aiproviderid]);
        }
        if (!empty($o->aiassessment)) {
            $settings['AI'] = (($providername === '') ? 'yes' : $providername) . ', ' . (int) $o->aiweighting . '%';
        } else {
            $settings['AI'] = 'no';
            if ($providername !== '') {
                // The provider that transcribes uploaded files.
                $settings['PROVIDER'] = $providername;
            }
        }
        foreach ($settings as $name => $value) {
            $lines[] = $name . '=' . self::gift_escape((string) $value);
        }

        if (trim((string) $o->airubric) !== '') {
            $lines[] = 'RUBRIC=' . self::gift_escape(trim($o->airubric));
        }

        foreach ($o->targetitems ?? [] as $item) {
            $lines[] = 'TARGETITEM=(' . self::gift_escape($item->itemtype) . ', ' . (float) $item->itemcount . ', ' .
                    (int) $item->percent . '%, x' . (int) $item->multiplier . ')';
        }
        foreach ($o->expectedanswers ?? [] as $phrase) {
            $text = str_replace(['\\', '"'], ['\\\\', '\\"'], $phrase->answertext);
            $lines[] = 'TARGETPHRASE=' . self::gift_escape('("' . $text . '", ' . (int) $phrase->percent . '%, /' .
                    (int) $phrase->divisor . ', ' . (empty($phrase->fullmatch) ? 'partial' : 'full') . ', ' .
                    (empty($phrase->casesensitive) ? 'nocase' : 'case') . ')');
        }

        $show = [];
        foreach (self::get_gift_show_fields() as $name => $field) {
            $show[$name] = $showwords[(int) $o->$field] ?? 'none';
        }
        if (count(array_unique($show)) == 1) {
            $lines[] = 'SHOW=' . reset($show);
        } else {
            $lines[] = 'SHOW=' . implode(', ', array_map(fn($name, $value) => "$name:$value",
                    array_keys($show), $show));
        }

        $stats = $yesno($o->showstats);
        if ($o->statsitems !== null) {
            $stats .= '; ' . (($o->statsitems === '') ? 'none' : str_replace(',', ', ', $o->statsitems));
        }
        $lines[] = 'STATS=' . self::gift_escape($stats);

        $output .= implode("\n", $lines) . "\n";
        $generalfeedback = $format->write_general_feedback($question, '');
        if ($generalfeedback !== '') {
            $output .= $generalfeedback . "\n";
        }
        $output .= "}\n";
        return $output;
    }

    /**
     * Import a question from GIFT (see export_to_gift()).
     *
     * The question is a SpeakUp question only if the text in its braces
     * starts with "AUDIO:" or "SPEAKUP". Settings that are left out get
     * their default values. Unrecognised settings and values are reported
     * as warnings, and skipped.
     *
     * @param array $lines the lines of this question.
     * @param stdClass $question the question, as parsed so far by qformat_gift.
     * @param qformat_gift $format
     * @param string|null $extra the text in the question's braces.
     * @return stdClass|false the question, or false if it is not a SpeakUp question.
     */
    public function import_from_gift($lines, $question, $format, $extra = null) {
        if ($extra === null || !is_object($question)) {
            return false;
        }
        $rows = preg_split('/\r?\n/', trim($extra));

        // The first line must be "AUDIO: text", "AUDIO(voice=name): text" or "SPEAKUP".
        $first = trim((string) array_shift($rows));
        if (preg_match('/^AUDIO\s*(?:\(\s*(?:voice\s*=\s*)?([^)]*)\))?\s*:\s*(.*)$/is', $first, $matches)) {
            $entries = [['AUDIO', $matches[2]]];
            if (trim($matches[1]) !== '') {
                $entries[] = ['VOICE', $matches[1]];
            }
        } else if (preg_match('/^SPEAKUP\s*:?\s*$/i', $first)) {
            $entries = [];
        } else {
            return false;
        }

        // Each setting is "NAME=value". A value carries on over the following
        // lines, until the next line that starts with "NAME=".
        $current = count($entries) ? 0 : null;
        foreach ($rows as $row) {
            if (preg_match('/^\s*([A-Za-z][A-Za-z0-9_]*)\s*=\s*(.*)$/s', $row, $matches)) {
                $entries[] = [strtoupper($matches[1]), $matches[2]];
                $current = count($entries) - 1;
            } else if ($current !== null) {
                $entries[$current][1] .= "\n" . $row;
            } else if (trim($row) !== '') {
                $this->gift_warning($question, get_string('giftunknownline', 'qtype_speakup', self::gift_unescape(trim($row))));
            }
        }

        $question->qtype = $this->name();
        $question->itemtype = [];
        $question->itemcount = [];
        $question->itempercent = [];
        $question->itemmultiplier = [];
        $question->phrasematch = [];
        $question->phrasepercent = [];
        $question->phrasedivisor = [];
        $question->phrasefullmatch = [];
        $question->phrasecasesensitive = [];

        $names = $this->get_gift_names();
        foreach ($entries as [$name, $value]) {
            $value = trim(self::gift_unescape($value));
            if (!isset($names[$name])) {
                $this->gift_warning($question, get_string('giftunknownsetting', 'qtype_speakup', $name));
                continue;
            }
            $error = $this->import_gift_setting($question, $names[$name], $value);
            if ($error !== null) {
                $this->gift_warning($question, get_string('giftbadvalue', 'qtype_speakup',
                        (object) ['name' => $name, 'value' => $value, 'error' => $error]));
            }
        }

        // Settings that were left out get their defaults, as on the question
        // form (whose default grading method differs from the database's).
        $formdefaults = ['gradingmethod' => SPEAKUP_GRADING_AUTO, 'aiproviderid' => null];
        $fields = $this->extra_question_fields();
        array_shift($fields); // Omit the table name.
        foreach ($fields as $field) {
            if (!property_exists($question, $field)) {
                $question->$field = array_key_exists($field, $formdefaults)
                        ? $formdefaults[$field] : $this->get_field_default($field);
            }
        }
        // AI assessment needs a provider: if none was named, use the first one.
        if (!empty($question->aiassessment) && empty($question->aiproviderid)) {
            $menu = \qtype_speakup\ai_provider::get_menu(true);
            $question->aiproviderid = $menu ? (int) array_key_first($menu) : null;
        }
        return $question;
    }

    /**
     * Set one GIFT setting on a question being imported.
     *
     * @param stdClass $question
     * @param string $field the field, or special setting, it sets (see get_gift_names()).
     * @param string $value
     * @return string|null an explanation, if the value is not valid; otherwise null.
     */
    protected function import_gift_setting(stdClass $question, string $field, string $value): ?string {
        $plugin = 'qtype_speakup';
        $word = strtolower($value);

        // Yes/no settings.
        $yesno = ['yes' => 1, 'y' => 1, 'true' => 1, 'on' => 1, '1' => 1, 'no' => 0, 'n' => 0, 'false' => 0, 'off' => 0, '0' => 0];
        // Choices from a list.
        $choices = [
            'mediatype' => [SPEAKUP_MEDIA_AUDIO => SPEAKUP_MEDIA_AUDIO, SPEAKUP_MEDIA_VIDEO => SPEAKUP_MEDIA_VIDEO,
                    SPEAKUP_MEDIA_IMAGE => SPEAKUP_MEDIA_IMAGE, 'images' => SPEAKUP_MEDIA_IMAGE,
                    'handwriting' => SPEAKUP_MEDIA_IMAGE],
            'responsemethod' => [SPEAKUP_RESPONSE_RECORD => SPEAKUP_RESPONSE_RECORD,
                    SPEAKUP_RESPONSE_UPLOAD => SPEAKUP_RESPONSE_UPLOAD],
            'gradingmethod' => [SPEAKUP_GRADING_AUTO => SPEAKUP_GRADING_AUTO, 'automatic' => SPEAKUP_GRADING_AUTO,
                    'automated' => SPEAKUP_GRADING_AUTO, SPEAKUP_GRADING_MANUAL => SPEAKUP_GRADING_MANUAL],
            'feedbackmode' => [SPEAKUP_FEEDBACK_FORMATIVE => SPEAKUP_FEEDBACK_FORMATIVE,
                    SPEAKUP_FEEDBACK_SUMMATIVE => SPEAKUP_FEEDBACK_SUMMATIVE],
            'prompttranscriptdisplay' => self::get_gift_transcript_words(),
        ];
        foreach (self::get_gift_show_fields() as $showfield) {
            $choices[$showfield] = self::get_gift_show_words();
        }
        // Whole numbers: field => [min, max, allowed values (or null for any)].
        $numbers = [
            'timelimitinseconds' => [0, 3600, null],
            'aiweighting' => [0, 100, null],
            'transcriptpenalty' => [0, 100, null],
            'maxpromptplays' => [0, 10, null],
            'maxresponses' => [0, 10, null],
            'silencestop' => [0, 15, [0, 2, 3, 4, 5, 7, 10, 15]],
            'maxfiles' => [1, SPEAKUP_MAX_IMAGE_FILES, null],
        ];

        if (in_array($field, ['allowpause', 'recordafterprompt', 'aiassessment', 'showstats'])) {
            if (!array_key_exists($word, $yesno)) {
                return get_string('giftexpectyesno', $plugin);
            }
            $question->$field = $yesno[$word];
            return null;
        }

        if (isset($choices[$field])) {
            if (is_numeric($word) && in_array((int) $word, $choices[$field], true)) {
                $question->$field = (int) $word;
                return null;
            }
            if (!isset($choices[$field][$word])) {
                return get_string('giftexpectoneof', $plugin, implode(', ', array_unique(array_keys($choices[$field]))));
            }
            $question->$field = $choices[$field][$word];
            return null;
        }

        if (isset($numbers[$field])) {
            [$min, $max, $allowed] = $numbers[$field];
            // A time limit may be given in minutes, e.g. "2m" or "2 minutes".
            if ($field === 'timelimitinseconds' && preg_match('/^(\d+)\s*(m|min|mins|minutes?)$/', $word, $matches)) {
                $word = (string) ($matches[1] * 60);
            }
            $word = preg_replace('/\s*(%|s|sec|secs|seconds?)$/', '', $word);
            if (!preg_match('/^\d+$/', $word) || $word < $min || $word > $max) {
                return get_string('giftexpectnumber', $plugin, (object) ['min' => $min, 'max' => $max]);
            }
            $number = (int) $word;
            if ($allowed !== null && !in_array($number, $allowed)) {
                // Use the nearest allowed value.
                usort($allowed, fn($a, $b) => abs($a - $number) <=> abs($b - $number));
                $question->$field = $allowed[0];
                return get_string('giftnearestvalue', $plugin, $allowed[0]);
            }
            $question->$field = $number;
            return null;
        }

        switch ($field) {
            case 'sttlanguage':
                if (!preg_match('/^[a-z]{2,3}([_-][A-Za-z0-9]{2,8})*$/i', $value)) {
                    return get_string('giftexpectlanguage', $plugin);
                }
                $question->sttlanguage = str_replace('_', '-', $value);
                return null;

            case 'airubric':
            case 'audioprompttranscript':
            case 'audiopromptvoice':
                $question->$field = $value;
                return null;

            case 'aiprovider':
                return $this->import_gift_provider($question, $value);

            case 'ai':
                // "no", "yes", "yes, 50%", "Gemini", "Gemini, 100%" ...
                if (isset($yesno[$word]) && !$yesno[$word]) {
                    $question->aiassessment = 0;
                    return null;
                }
                $question->aiassessment = 1;
                $names = [];
                foreach (array_map('trim', explode(',', $value)) as $part) {
                    if (preg_match('/^(\d+)\s*%$/', $part, $matches)) {
                        $question->aiweighting = min(100, (int) $matches[1]);
                    } else if ($part !== '' && !isset($yesno[strtolower($part)])) {
                        $names[] = $part;
                    }
                }
                return $names ? $this->import_gift_provider($question, implode(', ', $names)) : null;

            case 'show':
                // "both", or "calculation:both, phrases:students, ...".
                $showwords = self::get_gift_show_words();
                $showfields = self::get_gift_show_fields();
                if (isset($showwords[$word])) {
                    foreach ($showfields as $showfield) {
                        $question->$showfield = $showwords[$word];
                    }
                    return null;
                }
                $errors = [];
                foreach (array_filter(array_map('trim', explode(',', $word))) as $part) {
                    [$name, $setting] = array_map('trim', explode(':', $part, 2)) + [1 => ''];
                    if (!isset($showfields[$name]) || !isset($showwords[$setting])) {
                        $errors[] = $part;
                        continue;
                    }
                    $question->{$showfields[$name]} = $showwords[$setting];
                }
                return $errors ? get_string('giftbadparts', $plugin, implode(', ', $errors)) : null;

            case 'statsitems':
            case 'stats':
                // STATS: "yes", "no; words, longwords", "words, longwords" or "none".
                $parts = array_map('trim', explode(';', $word, 2));
                if ($field === 'stats' && isset($yesno[$parts[0]])) {
                    $question->showstats = $yesno[$parts[0]];
                    if (count($parts) == 1) {
                        return null;
                    }
                    $word = $parts[1];
                }
                $allowed = array_merge(\qtype_speakup\text_stats::get_stat_types(false),
                        \qtype_speakup\text_stats::get_stat_types(true));
                $items = array_filter(array_map('trim', preg_split('/[,\s]+/', $word)));
                if ($items == ['none']) {
                    $question->statsitems = '';
                    return null;
                }
                $unknown = array_diff($items, $allowed);
                $question->statsitems = implode(',', array_intersect($allowed, $items));
                return $unknown ? get_string('giftbadparts', $plugin, implode(', ', $unknown)) : null;

            case 'targetitem':
                // One or more "(itemtype, target, percent%, xmultiplier)".
                $pattern = '/\(\s*([a-z]+)\s*,\s*(\d+(?:\.\d+)?)\s*(?:,\s*(\d+)\s*%?)?\s*(?:,\s*[x*]?\s*(\d+))?\s*\)/i';
                if (!preg_match_all($pattern, $value, $matches, PREG_SET_ORDER)) {
                    return get_string('giftexpecttargetitem', $plugin);
                }
                $errors = [];
                foreach ($matches as $match) {
                    $itemtype = strtolower($match[1]);
                    if (!in_array($itemtype, \qtype_speakup\text_stats::get_item_types())) {
                        $errors[] = $itemtype;
                        continue;
                    }
                    $question->itemtype[] = $itemtype;
                    $question->itemcount[] = $match[2];
                    $question->itempercent[] = ($match[3] ?? '') === '' ? 100 : (int) $match[3];
                    $question->itemmultiplier[] = ($match[4] ?? '') === '' ? 1 : (int) $match[4];
                }
                return $errors ? get_string('giftbadparts', $plugin, implode(', ', $errors)) : null;

            case 'targetphrase':
                // One or more ("phrase", percent%, /divisor, full|partial, case|nocase).
                $pattern = '/\(\s*"((?:[^"\\\\]|\\\\.)*)"\s*(?:,\s*(\d+)\s*%?)?\s*(?:,\s*\/?\s*(\d+))?' .
                        '\s*(?:,\s*([a-z01]+))?\s*(?:,\s*([a-z01]+))?\s*\)/i';
                if (!preg_match_all($pattern, $value, $matches, PREG_SET_ORDER)) {
                    return get_string('giftexpecttargetphrase', $plugin);
                }
                $flags = ['full' => 1, 'fullmatch' => 1, '1' => 1, 'partial' => 0, 'part' => 0, '0' => 0,
                        'case' => 1, 'casesensitive' => 1, 'nocase' => 0, 'caseinsensitive' => 0];
                $errors = [];
                foreach ($matches as $match) {
                    $full = strtolower($match[4] ?? '');
                    $case = strtolower($match[5] ?? '');
                    if (($full !== '' && !isset($flags[$full])) || ($case !== '' && !isset($flags[$case]))) {
                        $errors[] = trim($full . ' ' . $case);
                    }
                    $question->phrasematch[] = preg_replace('/\\\\(.)/', '$1', $match[1]);
                    $question->phrasepercent[] = ($match[2] ?? '') === '' ? 100 : (int) $match[2];
                    $question->phrasedivisor[] = ($match[3] ?? '') === '' ? 1 : (int) $match[3];
                    $question->phrasefullmatch[] = $flags[$full] ?? 0;
                    $question->phrasecasesensitive[] = $flags[$case] ?? 0;
                }
                return $errors ? get_string('giftbadparts', $plugin, implode(', ', $errors)) : null;
        }
        return get_string('giftunknownsetting', $plugin, $field);
    }

    /**
     * Set the AI provider of a question being imported, from its name.
     *
     * @param stdClass $question
     * @param string $name
     * @return string|null an explanation, if there is no provider with this name.
     */
    protected function import_gift_provider(stdClass $question, string $name): ?string {
        global $DB;
        $id = $DB->get_field('qtype_speakup_ai_providers', 'id', ['name' => $name], IGNORE_MULTIPLE);
        if (!$id) {
            return get_string('giftnoprovider', 'qtype_speakup', $name);
        }
        $question->aiproviderid = (int) $id;
        return null;
    }

    /**
     * Show a warning while importing a question from GIFT.
     *
     * @param stdClass $question
     * @param string $message
     */
    protected function gift_warning(stdClass $question, string $message): void {
        global $OUTPUT;
        $a = (object) ['name' => s($question->name ?? ''), 'message' => s($message)];
        echo $OUTPUT->notification(get_string('giftwarning', 'qtype_speakup', $a), 'warning');
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
