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

namespace qtype_speakup;

use question_bank;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->libdir . '/xmlize.php');
require_once($CFG->dirroot . '/question/format.php');
require_once($CFG->dirroot . '/question/format/gift/format.php');
require_once($CFG->dirroot . '/question/format/xml/format.php');
require_once($CFG->dirroot . '/question/type/speakup/tests/helper.php');

/**
 * Tests for the SpeakUp question type: saving and loading questions,
 * GIFT and XML import and export, and generating audio prompts.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \qtype_speakup
 */
final class questiontype_test extends \advanced_testcase {

    /** @var array the settings compared after import. */
    const FIELDS = ['timelimitinseconds', 'mediatype', 'responsemethod', 'gradingmethod', 'sttlanguage', 'allowpause',
            'feedbackmode', 'airubric', 'aiproviderid', 'aiassessment', 'aiweighting', 'showcalculation', 'showphrases',
            'showitems', 'showcomment', 'showtranscript', 'showstats', 'statsitems', 'audioprompttranscript',
            'audiopromptvoice', 'prompttranscriptdisplay', 'transcriptpenalty', 'recordafterprompt', 'maxpromptplays',
            'maxresponses', 'silencestop'];

    /**
     * Create a test question in a new category.
     *
     * @param string $which
     * @param array $overrides
     * @return \stdClass the question data, as loaded from the database.
     */
    protected function create_question(string $which = 'basic', array $overrides = []): \stdClass {
        /** @var \core_question_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category();
        $question = $generator->create_question('speakup', $which, ['category' => $category->id] + $overrides);
        return question_bank::load_question_data($question->id);
    }

    /**
     * Parse some GIFT, as qformat_gift does when importing a file.
     *
     * @param string $gift
     * @return array the questions.
     */
    protected function read_gift(string $gift): array {
        $format = new \qformat_gift();
        $method = new \ReflectionMethod($format, 'readquestions');
        return $method->invoke($format, preg_split('/\r?\n/', $gift));
    }

    /**
     * Test the question type's name and capabilities.
     */
    public function test_basics(): void {
        $qtype = question_bank::get_qtype('speakup');
        $this->assertSame('speakup', $qtype->name());
        $this->assertSame('questionid', $qtype->questionid_column_name());
        $fields = $qtype->extra_question_fields();
        $this->assertSame('qtype_speakup_options', array_shift($fields));
        $this->assertEqualsCanonicalizing(self::FIELDS, $fields);
    }

    /**
     * Test saving and loading a question, with its target phrases and items.
     */
    public function test_save_and_load(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $qdata = $this->create_question();

        $this->assertSame(SPEAKUP_GRADING_AUTO, $qdata->options->gradingmethod);
        $this->assertEquals(60, $qdata->options->timelimitinseconds);
        $this->assertSame('', (string) $qdata->options->statsitems);

        $phrases = $qdata->options->expectedanswers;
        $this->assertCount(3, $phrases);
        $this->assertSame('guitar', $phrases[0]->answertext);
        $this->assertEquals(1, $phrases[0]->fullmatch);
        $this->assertEquals(0, $phrases[0]->isregex);
        $this->assertEquals(1, $phrases[2]->isregex);
        $this->assertEquals(2, $phrases[2]->divisor);

        $items = $qdata->options->targetitems;
        $this->assertCount(1, $items);
        $this->assertSame('words', $items[0]->itemtype);
        $this->assertEquals(20, $items[0]->itemcount);

        // The question definition gets the phrases and items too.
        $question = question_bank::load_question($qdata->id);
        $this->assertCount(3, $question->expectedanswers);
        $this->assertCount(1, $question->targetitems);
    }

    /**
     * Phrases that award nothing, and items without a target, are not saved.
     */
    public function test_save_skips_empty_rows(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $qdata = $this->create_question('basic', [
            'phrasematch' => ['kept', '', 'zero'],
            'phrasepercent' => [10, 50, 0],
            'phrasedivisor' => [1, 1, 1],
            'phrasefullmatch' => [0, 0, 0],
            'phrasecasesensitive' => [0, 0, 0],
            'itemtype' => ['words', 'chars', 'nosuchtype'],
            'itemcount' => [10, 0, 5],
            'itempercent' => [20, 20, 20],
            'itemmultiplier' => [1, 1, 1],
        ]);
        $this->assertSame(['kept'], array_column($qdata->options->expectedanswers, 'answertext'));
        $this->assertSame(['words'], array_column($qdata->options->targetitems, 'itemtype'));
    }

    /**
     * The statistics checkboxes are saved as a list.
     */
    public function test_save_statsitems(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $qdata = $this->create_question('basic', [
            'statsitems' => ['words' => 1, 'chars' => 0, 'uniquewords' => 1, 'nosuchstat' => 1],
        ]);
        $this->assertSame('words,uniquewords', $qdata->options->statsitems);
    }

    /**
     * Deleting a question deletes its phrases, items and audio prompt.
     */
    public function test_delete(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $qdata = $this->create_question();
        get_file_storage()->create_file_from_string(['contextid' => $qdata->contextid, 'component' => 'qtype_speakup',
                'filearea' => 'audioprompt', 'itemid' => $qdata->id, 'filepath' => '/', 'filename' => 'a.mp3'], 'ID3');

        question_delete_question($qdata->id);
        $this->assertFalse($DB->record_exists('qtype_speakup_options', ['questionid' => $qdata->id]));
        $this->assertFalse($DB->record_exists('qtype_speakup_answers', ['questionid' => $qdata->id]));
        $this->assertFalse($DB->record_exists('qtype_speakup_items', ['questionid' => $qdata->id]));
        $this->assertTrue(get_file_storage()->is_area_empty($qdata->contextid, 'qtype_speakup', 'audioprompt', $qdata->id));
    }

    /**
     * A question exported to GIFT and imported again has the same settings.
     */
    public function test_gift_round_trip(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $provider = \qtype_speakup_test_helper::create_provider(['name' => 'Gemini']);
        $qdata = $this->create_question('prompt', [
            'aiassessment' => 1,
            'aiproviderid' => $provider->id,
            'aiweighting' => 60,
            'airubric' => "Line one.\nLine two: {braces} = #hash ~tilde",
            'audiopromptvoice' => 'Kore',
            'showcomment' => SPEAKUP_SHOW_STUDENTS_ONLY,
            'showstats' => 1,
            'statsitems' => ['words' => 1, 'lexicaldensity' => 1],
            'phrasematch' => ['say "hello"', '/colou?r/i', 'café'],
            'phrasepercent' => [25, 10, 5],
            'phrasedivisor' => [1, 3, 1],
            'phrasefullmatch' => [1, 0, 0],
            'phrasecasesensitive' => [1, 0, 0],
        ]);

        $gift = (new \qformat_gift())->writequestion($qdata);
        $this->assertStringContainsString('AUDIO(voice=Kore): Talk about a string instrument', $gift);
        $this->assertStringContainsString('AI=Gemini, 60%', $gift);

        $questions = $this->read_gift($gift);
        $this->assertCount(1, $questions);
        $imported = $questions[0];
        $this->assertSame('speakup', $imported->qtype);
        foreach (self::FIELDS as $field) {
            $this->assertEquals($qdata->options->$field, $imported->$field, "Field $field");
        }
        $this->assertSame(['say "hello"', '/colou?r/i', 'café'], $imported->phrasematch);
        $this->assertEquals([25, 10, 5], $imported->phrasepercent);
        $this->assertEquals([1, 3, 1], $imported->phrasedivisor);
        $this->assertEquals([1, 0, 0], $imported->phrasefullmatch);
        $this->assertEquals([1, 0, 0], $imported->phrasecasesensitive);
        $this->assertSame(['words'], $imported->itemtype);
        $this->assertEquals([20], $imported->itemcount);
        $this->assertSame($qdata->questiontext, $imported->questiontext);
        $this->assertSame($qdata->generalfeedback, $imported->generalfeedback);
    }

    /**
     * Hand-written GIFT can use short names, and leave out settings.
     */
    public function test_gift_import_short_names(): void {
        $this->resetAfterTest();
        $provider = \qtype_speakup_test_helper::create_provider(['name' => 'Gemini']);
        $gift = "::Weekend::Listen and answer.{AUDIO: What did you do last weekend?}\n\n" .
                "::Hobby::Listen.{\nAUDIO(voice=Kore): Tell me about your hobby.\nIt can be anything.\n" .
                "TIMELIMIT=2m\nLANG=en_GB\nPAUSE=yes\nPLAYS=2\nTRANSCRIPT=expanded\nAI=Gemini, 50%\n" .
                "RUBRIC=One.\\nTwo\\: three \\{x\\}.\nITEMS=(words, 40, 30%, x2)(longwords, 5)\n" .
                "PHRASE=(\"I like\", 20%, /2, full, case)\nPHRASE=(\"because\")\n" .
                "SHOW=calculation:teachers, comment:students\nSTATS=yes; words, uniquewords\n#### Well done!\n}\n\n" .
                "::NoPrompt::Say something.{\nSPEAKUP\nGRADING=manual\n}\n\n" .
                "::Essay::Not SpeakUp.{}\n";
        $questions = $this->read_gift($gift);
        $this->assertSame(['speakup', 'speakup', 'speakup', 'essay'], array_column($questions, 'qtype'));

        [$weekend, $hobby, $noprompt] = $questions;
        $this->assertSame('What did you do last weekend?', $weekend->audioprompttranscript);
        // Left out: the question form's defaults.
        $this->assertSame(SPEAKUP_GRADING_AUTO, $weekend->gradingmethod);
        $this->assertEquals(60, $weekend->timelimitinseconds);
        $this->assertNull($weekend->aiproviderid);

        $this->assertSame("Tell me about your hobby.\nIt can be anything.", $hobby->audioprompttranscript);
        $this->assertSame('Kore', $hobby->audiopromptvoice);
        $this->assertSame(120, $hobby->timelimitinseconds);
        $this->assertSame('en-GB', $hobby->sttlanguage);
        $this->assertSame(1, $hobby->allowpause);
        $this->assertSame(2, $hobby->maxpromptplays);
        $this->assertSame(SPEAKUP_PROMPTTRANSCRIPT_EXPANDED, $hobby->prompttranscriptdisplay);
        $this->assertSame(1, $hobby->aiassessment);
        $this->assertSame(50, $hobby->aiweighting);
        $this->assertEquals($provider->id, $hobby->aiproviderid);
        $this->assertSame("One.\nTwo: three {x}.", $hobby->airubric);
        $this->assertSame(['words', 'longwords'], $hobby->itemtype);
        $this->assertEquals([40, 5], $hobby->itemcount);
        $this->assertEquals([30, 100], $hobby->itempercent);
        $this->assertEquals([2, 1], $hobby->itemmultiplier);
        $this->assertSame(['I like', 'because'], $hobby->phrasematch);
        $this->assertEquals([20, 100], $hobby->phrasepercent);
        $this->assertEquals([2, 1], $hobby->phrasedivisor);
        $this->assertEquals([1, 0], $hobby->phrasefullmatch);
        $this->assertEquals([1, 0], $hobby->phrasecasesensitive);
        $this->assertSame(SPEAKUP_SHOW_TEACHERS_ONLY, $hobby->showcalculation);
        $this->assertSame(SPEAKUP_SHOW_STUDENTS_ONLY, $hobby->showcomment);
        $this->assertEquals(SPEAKUP_SHOW_TEACHERS_AND_STUDENTS, $hobby->showphrases);
        $this->assertSame(1, $hobby->showstats);
        $this->assertSame('words,uniquewords', $hobby->statsitems);
        $this->assertSame('Well done!', $hobby->generalfeedback);

        $this->assertNull($noprompt->audioprompttranscript);
        $this->assertSame(SPEAKUP_GRADING_MANUAL, $noprompt->gradingmethod);
    }

    /**
     * Unrecognised settings and values are skipped, with a warning.
     */
    public function test_gift_import_warnings(): void {
        $this->resetAfterTest();
        $gift = "::Warn::Listen.{\nAUDIO: Hello.\nCOLOUR=blue\nMEDIA=hologram\nSILENCE=6\nPAUSE=maybe\n" .
                "AI=Nobody, 40%\nITEMS=(nosuchtype, 5)\n}\n";
        ob_start();
        $questions = $this->read_gift($gift);
        $output = ob_get_clean();

        $this->assertStringContainsString('COLOUR', $output);
        $this->assertStringContainsString('MEDIA=hologram', $output);
        $this->assertStringContainsString('SILENCE=6', $output);
        $this->assertStringContainsString('PAUSE=maybe', $output);
        $this->assertStringContainsString('Nobody', $output);
        $this->assertStringContainsString('nosuchtype', $output);

        $question = $questions[0];
        $this->assertEquals(SPEAKUP_MEDIA_AUDIO, $question->mediatype);
        $this->assertSame(5, $question->silencestop);
        $this->assertEquals(0, $question->allowpause);
        $this->assertSame(40, $question->aiweighting);
        $this->assertSame([], $question->itemtype);
    }

    /**
     * A question exported to Moodle XML and imported again has the same
     * settings, phrases, items and audio prompt.
     */
    public function test_xml_round_trip(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $provider = \qtype_speakup_test_helper::create_provider(['name' => 'Gemini']);
        $qdata = $this->create_question('prompt', ['aiassessment' => 1, 'aiproviderid' => $provider->id]);
        get_file_storage()->create_file_from_string(['contextid' => $qdata->contextid, 'component' => 'qtype_speakup',
                'filearea' => 'audioprompt', 'itemid' => $qdata->id, 'filepath' => '/', 'filename' => 'audioprompt.mp3'],
                'ID3 prompt audio');

        $exporter = new \qformat_xml();
        $xml = $exporter->writequestion($qdata);
        $this->assertStringContainsString('<aiprovider>Gemini</aiprovider>', $xml);
        $this->assertStringNotContainsString('secret-key', $xml);
        $this->assertStringContainsString('<file name="audioprompt.mp3"', $xml);

        $importer = new \qformat_xml();
        $data = xmlize($xml);
        $imported = $importer->try_importing_using_qtypes($data['question'], null, null, 'speakup');
        $this->assertSame('speakup', $imported->qtype);
        foreach (self::FIELDS as $field) {
            $this->assertEquals($qdata->options->$field, $imported->$field, "Field $field");
        }
        $this->assertSame(['guitar', 'I have played', '/\bviolins?\b/i'], $imported->phrasematch);
        $this->assertSame(['words'], $imported->itemtype);

        $files = file_get_drafarea_files($imported->audioprompt);
        $this->assertSame(['audioprompt.mp3'], array_column($files->list, 'filename'));
    }

    /**
     * Saving a question with a transcript but no audio prompt queues the
     * audio prompt to be generated, if a speech provider is set up.
     */
    public function test_save_queues_audio_generation(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        // No speech provider: nothing queued.
        $qdata = $this->create_question('prompt');
        $this->assertFalse(task\generate_audio_prompt::is_pending($qdata->id));

        \qtype_speakup_test_helper::create_speech_provider();
        $qdata = $this->create_question('prompt');
        $this->assertTrue(task\generate_audio_prompt::is_pending($qdata->id));

        // No transcript: nothing queued.
        $qdata = $this->create_question('basic');
        $this->assertFalse(task\generate_audio_prompt::is_pending($qdata->id));
    }
}
