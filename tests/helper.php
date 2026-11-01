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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');
require_once($CFG->dirroot . '/question/type/speakup/questiontype.php');

/**
 * Test helper for SpeakUp questions.
 *
 * The test questions are:
 * - basic: graded automatically, from target phrases and a target item.
 * - manual: graded by a teacher.
 * - prompt: graded automatically, with an audio prompt transcript and a
 *   penalty for showing it.
 *
 * These are used by the PHPUnit tests, and by Behat's
 * "the following questions exist" step (with "template").
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_speakup_test_helper extends question_test_helper {

    #[\Override]
    public function get_test_questions() {
        return ['basic', 'manual', 'prompt'];
    }

    /**
     * The settings shared by every test question, as stored in qtype_speakup_options.
     *
     * @return array
     */
    protected static function get_default_options(): array {
        return [
            'timelimitinseconds' => 60,
            'mediatype' => SPEAKUP_MEDIA_AUDIO,
            'responsemethod' => SPEAKUP_RESPONSE_RECORD,
            'gradingmethod' => SPEAKUP_GRADING_AUTO,
            'sttlanguage' => 'en-US',
            'allowpause' => 0,
            'feedbackmode' => SPEAKUP_FEEDBACK_FORMATIVE,
            'airubric' => '',
            'aiproviderid' => null,
            'aiassessment' => 0,
            'aiweighting' => 100,
            'showcalculation' => SPEAKUP_SHOW_TEACHERS_AND_STUDENTS,
            'showphrases' => SPEAKUP_SHOW_TEACHERS_AND_STUDENTS,
            'showitems' => SPEAKUP_SHOW_TEACHERS_AND_STUDENTS,
            'showcomment' => SPEAKUP_SHOW_TEACHERS_AND_STUDENTS,
            'showtranscript' => SPEAKUP_SHOW_TEACHERS_AND_STUDENTS,
            'showstats' => 0,
            'statsitems' => null,
            'audioprompttranscript' => '',
            'audiopromptvoice' => null,
            'prompttranscriptdisplay' => SPEAKUP_PROMPTTRANSCRIPT_COLLAPSED,
            'transcriptpenalty' => 0,
            'recordafterprompt' => 0,
            'maxpromptplays' => 0,
            'maxresponses' => 0,
            'silencestop' => 0,
        ];
    }

    /**
     * The settings of each test question that differ from the defaults.
     *
     * @param string $which
     * @return array
     */
    protected static function get_options(string $which): array {
        $options = self::get_default_options();
        switch ($which) {
            case 'manual':
                $options['gradingmethod'] = SPEAKUP_GRADING_MANUAL;
                break;
            case 'prompt':
                $options['audioprompttranscript'] = 'Talk about a string instrument that you can play.';
                $options['transcriptpenalty'] = 30;
                $options['recordafterprompt'] = 1;
                $options['maxpromptplays'] = 2;
                $options['maxresponses'] = 1;
                break;
        }
        return $options;
    }

    /**
     * The target phrases of each test question.
     *
     * @param string $which
     * @return array of [answertext, percent, divisor, fullmatch, casesensitive]
     */
    protected static function get_phrases(string $which): array {
        if ($which === 'manual') {
            return [];
        }
        return [
            ['guitar', 40, 1, 1, 0],
            ['I have played', 30, 1, 0, 0],
            ['/\bviolins?\b/i', 20, 2, 0, 0],
        ];
    }

    /**
     * The target items of each test question.
     *
     * @param string $which
     * @return array of [itemtype, itemcount, percent, multiplier]
     */
    protected static function get_items(string $which): array {
        if ($which === 'manual') {
            return [];
        }
        return [
            ['words', 20, 30, 1],
        ];
    }

    /**
     * Make a question definition object.
     *
     * @param string $which
     * @return qtype_speakup_question
     */
    protected function make_question(string $which): qtype_speakup_question {
        question_bank::load_question_definition_classes('speakup');
        $q = new qtype_speakup_question();
        test_question_maker::initialise_a_question($q);
        $q->name = 'SpeakUp question (' . $which . ')';
        $q->questiontext = 'Talk about a musical instrument.';
        $q->generalfeedback = 'Thank you.';
        $q->qtype = question_bank::get_qtype('speakup');
        foreach (self::get_options($which) as $field => $value) {
            $q->$field = $value;
        }
        $q->expectedanswers = [];
        foreach (self::get_phrases($which) as $i => [$text, $percent, $divisor, $full, $case]) {
            $q->expectedanswers[] = (object) ['id' => $i + 1, 'answertext' => $text,
                    'isregex' => (int) preg_match('#^/.*/[a-zA-Z]*$#', $text), 'percent' => $percent,
                    'divisor' => $divisor, 'fullmatch' => $full, 'casesensitive' => $case];
        }
        $q->targetitems = [];
        foreach (self::get_items($which) as $i => [$type, $count, $percent, $multiplier]) {
            $q->targetitems[] = (object) ['id' => $i + 1, 'itemtype' => $type, 'itemcount' => $count,
                    'percent' => $percent, 'multiplier' => $multiplier];
        }
        return $q;
    }

    /**
     * @return qtype_speakup_question
     */
    public function make_speakup_question_basic() {
        return $this->make_question('basic');
    }

    /**
     * @return qtype_speakup_question
     */
    public function make_speakup_question_manual() {
        return $this->make_question('manual');
    }

    /**
     * @return qtype_speakup_question
     */
    public function make_speakup_question_prompt() {
        return $this->make_question('prompt');
    }

    /**
     * Get the data that the question form would return.
     *
     * @param string $which
     * @return stdClass
     */
    protected function get_form_data(string $which): stdClass {
        $form = new stdClass();
        $form->name = 'SpeakUp question (' . $which . ')';
        $form->questiontext = ['text' => 'Talk about a musical instrument.', 'format' => FORMAT_HTML];
        $form->generalfeedback = ['text' => 'Thank you.', 'format' => FORMAT_HTML];
        $form->defaultmark = 1.0;
        $form->penalty = 0;
        $form->status = \core_question\local\bank\question_version_status::QUESTION_STATUS_READY;
        foreach (self::get_options($which) as $field => $value) {
            $form->$field = $value;
        }
        $form->statsitems = [];
        foreach (self::get_phrases($which) as [$text, $percent, $divisor, $full, $case]) {
            $form->phrasematch[] = $text;
            $form->phrasepercent[] = $percent;
            $form->phrasedivisor[] = $divisor;
            $form->phrasefullmatch[] = $full;
            $form->phrasecasesensitive[] = $case;
        }
        foreach (self::get_items($which) as [$type, $count, $percent, $multiplier]) {
            $form->itemtype[] = $type;
            $form->itemcount[] = $count;
            $form->itempercent[] = $percent;
            $form->itemmultiplier[] = $multiplier;
        }
        return $form;
    }

    /**
     * @return stdClass
     */
    public function get_speakup_question_form_data_basic() {
        return $this->get_form_data('basic');
    }

    /**
     * @return stdClass
     */
    public function get_speakup_question_form_data_manual() {
        return $this->get_form_data('manual');
    }

    /**
     * @return stdClass
     */
    public function get_speakup_question_form_data_prompt() {
        return $this->get_form_data('prompt');
    }

    /**
     * Get the question data, as returned by question_bank::load_question_data().
     *
     * @param string $which
     * @return stdClass
     */
    protected function get_question_data(string $which): stdClass {
        $qdata = new stdClass();
        test_question_maker::initialise_question_data($qdata);
        $qdata->qtype = 'speakup';
        $qdata->name = 'SpeakUp question (' . $which . ')';
        $qdata->questiontext = 'Talk about a musical instrument.';
        $qdata->generalfeedback = 'Thank you.';
        $qdata->options = (object) self::get_options($which);
        $qdata->options->expectedanswers = [];
        foreach (self::get_phrases($which) as $i => [$text, $percent, $divisor, $full, $case]) {
            $qdata->options->expectedanswers[] = (object) ['id' => $i + 1, 'answertext' => $text,
                    'isregex' => (int) preg_match('#^/.*/[a-zA-Z]*$#', $text), 'percent' => $percent,
                    'divisor' => $divisor, 'fullmatch' => $full, 'casesensitive' => $case];
        }
        $qdata->options->targetitems = [];
        foreach (self::get_items($which) as $i => [$type, $count, $percent, $multiplier]) {
            $qdata->options->targetitems[] = (object) ['id' => $i + 1, 'itemtype' => $type,
                    'itemcount' => $count, 'percent' => $percent, 'multiplier' => $multiplier];
        }
        return $qdata;
    }

    /**
     * @return stdClass
     */
    public function get_speakup_question_data_basic() {
        return $this->get_question_data('basic');
    }

    /**
     * @return stdClass
     */
    public function get_speakup_question_data_manual() {
        return $this->get_question_data('manual');
    }

    /**
     * @return stdClass
     */
    public function get_speakup_question_data_prompt() {
        return $this->get_question_data('prompt');
    }

    /**
     * Create an AI provider, for tests.
     *
     * @param array $data fields that differ from a text provider called "Test AI".
     * @return stdClass the provider record.
     */
    public static function create_provider(array $data = []): stdClass {
        $data = (object) array_merge([
            'name' => 'Test AI',
            'providertype' => \qtype_speakup\ai_provider::TYPE_EXTERNAL,
            'capability' => \qtype_speakup\ai_provider::CAPABILITY_TEXT,
            'url' => 'https://api.example.com/v1/chat',
            'apikey' => 'secret-key',
            'headers' => '',
            'model' => 'test-model',
            'returnpath' => 'choices,0,message,content',
            'requesttemplate' => '{"model": "{{MODEL}}", "messages": [{"role": "system", "content": "{{RUBRIC}}"}, ' .
                    '{"role": "user", "content": "{{TRANSCRIPT}}"}]}',
            'uploadstyle' => \qtype_speakup\ai_provider::UPLOAD_NONE,
            'enabled' => 1,
            'sortorder' => 0,
        ], $data);
        $id = \qtype_speakup\ai_provider::save($data);
        return \qtype_speakup\ai_provider::get($id);
    }

    /**
     * Create a speech (text-to-speech) provider, and choose it in the site settings.
     *
     * @param array $data fields that differ from the defaults.
     * @return stdClass the provider record.
     */
    public static function create_speech_provider(array $data = []): stdClass {
        $provider = self::create_provider(array_merge([
            'name' => 'Test speech',
            'capability' => \qtype_speakup\ai_provider::CAPABILITY_SPEECH,
            'url' => 'https://api.example.com/v1/audio/speech',
            'returnpath' => '',
            'requesttemplate' => '{"model": "{{MODEL}}", "input": "{{TEXT}}", "voice": "{{VOICE}}"}',
        ], $data));
        set_config('ttsproviderid', $provider->id, 'qtype_speakup');
        return $provider;
    }

    /**
     * Turn off Moodle's checks of curl URLs, so that mocked responses work
     * for any test URL.
     */
    public static function allow_any_curl_url(): void {
        set_config('curlsecurityblockedhosts', '');
        set_config('curlsecurityallowedport', '');
    }
}
