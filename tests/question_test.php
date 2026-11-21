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

use question_state;
use test_question_maker;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');
require_once($CFG->dirroot . '/question/type/speakup/tests/helper.php');

/**
 * Tests for the SpeakUp question definition: grading from target phrases,
 * target items and the AI's score, and the transcript penalty.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \qtype_speakup_question
 */
final class question_test extends \advanced_testcase {
    /**
     * Make a test question.
     *
     * @param string $which
     * @return \qtype_speakup_question
     */
    protected function make(string $which = 'basic'): \qtype_speakup_question {
        return test_question_maker::make_question('speakup', $which);
    }

    /**
     * Data for test_phrase_results().
     *
     * @return array
     */
    public static function phrase_results_provider(): array {
        // The basic question's phrases are: "guitar" (40%, full match),
        // "I have played" (30%) and /\bviolins?\b/i (20% divided by 2).
        return [
            'none used' => ['I like music.', [false, false, false]],
            'full match' => ['I play the guitar.', [true, false, false]],
            'full match fails inside a word' => ['I play guitars.', [false, false, false]],
            'case insensitive' => ['i HAVE played the piano', [false, true, false]],
            'spaces match any run of spaces' => ['I have   played', [false, true, false]],
            'regex' => ['Two violins.', [false, false, true]],
            'all' => ['I have played guitar and violin.', [true, true, true]],
            'empty' => ['', [false, false, false]],
        ];
    }

    /**
     * Test which target phrases are found in a transcript.
     *
     * @dataProvider phrase_results_provider
     * @param string $transcript
     * @param bool[] $expected
     */
    public function test_phrase_results(string $transcript, array $expected): void {
        $results = $this->make()->phrase_results($transcript);
        $this->assertSame($expected, array_column($results, 'used'));
        $this->assertEquals([40, 30, 10], array_column($results, 'percent'));
    }

    /**
     * The phrase results include the words that matched, and the labels.
     */
    public function test_phrase_results_matched(): void {
        $question = $this->make();
        $question->phrases[2]->phraselabel = ' Violin ';
        $results = $question->phrase_results('I Have  played two Violins.');
        $this->assertSame([null, 'I Have  played', 'Violins'], array_column($results, 'matched'));
        $this->assertSame(['', '', 'Violin'], array_column($results, 'label'));
        $this->assertSame([false, false, true], array_column($results, 'isregex'));
        $this->assertSame([null, null, null], array_column($question->phrase_results(''), 'matched'));
    }

    /**
     * The item results include the target and the most the item awards.
     */
    public function test_item_results(): void {
        // The basic question's item is: 20 words, up to 30%.
        $question = $this->make();
        $question->targetitems[0]->multiplier = 2;
        [$result] = $question->item_results('one two three four five', null);
        $this->assertSame('words', $result->itemtype);
        $this->assertEquals(5, $result->value);
        $this->assertEquals(20, $result->target);
        $this->assertEquals(0, $result->lower);
        $this->assertEquals(5, $result->progress);
        $this->assertEqualsWithDelta(15, $result->percent, 0.0001);
        $this->assertEqualsWithDelta(60, $result->maxpercent, 0.0001);
    }

    /**
     * A case sensitive phrase only matches with the same case.
     */
    public function test_case_sensitive_phrase(): void {
        $question = $this->make();
        $question->phrases[1]->casesensitive = 1;
        $this->assertTrue($question->phrase_results('I have played')[1]->used);
        $this->assertFalse($question->phrase_results('i have played')[1]->used);
    }

    /**
     * Test the target phrase score.
     */
    public function test_grade_transcript(): void {
        $question = $this->make();
        [$fraction, $feedback] = $question->grade_transcript('I have played the guitar.');
        $this->assertEqualsWithDelta(0.7, $fraction, 0.0001);
        $this->assertNotEmpty($feedback);

        // No transcript is a lack of data, not a wrong answer.
        [$fraction] = $question->grade_transcript('');
        $this->assertNull($fraction);

        // Manually graded questions are not scored.
        $this->assertSame([null, ''], $this->make('manual')->grade_transcript('I have played the guitar.'));
    }

    /**
     * The phrase scores are capped at 100%.
     */
    public function test_phrase_score_cap(): void {
        $question = $this->make();
        foreach ($question->phrases as $phrase) {
            $phrase->percent = 60;
            $phrase->divisor = 1;
        }
        [$fraction] = $question->grade_transcript('I have played guitar and violin.');
        $this->assertEquals(1.0, $fraction);
    }

    /**
     * Test the target item score: in proportion to the target, up to the full award.
     */
    public function test_grade_by_items(): void {
        // The basic question's item is: 20 words, up to 30%.
        $question = $this->make();
        [$fraction] = $question->grade_by_items('one two three four five six seven eight nine ten', null);
        $this->assertEqualsWithDelta(0.15, $fraction, 0.0001);

        [$fraction] = $question->grade_by_items(str_repeat('word ', 40), null);
        $this->assertEqualsWithDelta(0.3, $fraction, 0.0001);

        [$fraction, $feedback] = $question->grade_by_items('', null);
        $this->assertNull($fraction);
        $this->assertNotEmpty($feedback);
    }

    /**
     * Test the share of the grade that one item awards in full.
     */
    public function test_item_award(): void {
        $item = (object) ['itemtype' => 'words', 'itemtarget' => 50, 'percent' => 20, 'multiplier' => 2];
        $this->assertEquals(40.0, \qtype_speakup_question::item_award($item));
        $item->multiplier = 0;
        $this->assertEquals(20.0, \qtype_speakup_question::item_award($item));
    }

    /**
     * Data for test_item_bands().
     *
     * @return array
     */
    public static function item_bands_provider(): array {
        // Bands: words 0-100 for 15%, and 100-130 for 5%.
        return [
            'part of the first band' => [83, [83, 0], [12.45, 0]],
            'all of the first band' => [100, [100, 0], [15, 0]],
            'part of the second band' => [106, [100, 6], [15, 1]],
            'all the bands' => [150, [100, 30], [15, 5]],
        ];
    }

    /**
     * Several targets for the same item are grade bands, as in qtype_essayautograde.
     *
     * @dataProvider item_bands_provider
     * @param int $words
     * @param float[] $progress how much of each band the response covers.
     * @param float[] $percent what each band earns.
     */
    public function test_item_bands(int $words, array $progress, array $percent): void {
        $question = $this->make();
        // In any order: the bands are sorted by their targets.
        $question->targetitems = [
            (object) ['itemtype' => 'words', 'itemtarget' => '130.00', 'percent' => 5, 'multiplier' => 1],
            (object) ['itemtype' => 'speechrate', 'itemtarget' => '100.00', 'percent' => 10, 'multiplier' => 1],
            (object) ['itemtype' => 'words', 'itemtarget' => '100.00', 'percent' => 15, 'multiplier' => 1],
        ];
        $results = $question->item_results(trim(str_repeat('word ', $words)), 60.0);
        $this->assertSame(['words', 'words', 'speechrate'], array_column($results, 'itemtype'));
        $this->assertEquals([0, 100, 0], array_column($results, 'lower'));
        $this->assertEquals([100, 130, 100], array_column($results, 'target'));
        $this->assertEquals([15, 5, 10], array_column($results, 'maxpercent'));
        $this->assertEqualsWithDelta($progress, array_slice(array_column($results, 'progress'), 0, 2), 0.0001);
        $this->assertEqualsWithDelta($percent, array_slice(array_column($results, 'percent'), 0, 2), 0.0001);

        // Bands of one item do not affect another: words per minute in 60 seconds.
        $this->assertEqualsWithDelta(10 * min($words, 100) / 100, $results[2]->percent, 0.0001);

        [$fraction] = $question->grade_by_items(trim(str_repeat('word ', $words)), 60.0);
        $this->assertEqualsWithDelta((array_sum($percent) + $results[2]->percent) / 100, $fraction, 0.0001);
    }

    /**
     * Speech rate needs the length of the recording.
     */
    public function test_needs_duration(): void {
        $question = $this->make();
        $this->assertFalse($question->needs_duration());
        $question->targetitems[0]->itemtype = text_stats::ITEM_SPEECHRATE;
        $this->assertTrue($question->needs_duration());
    }

    /**
     * Test combining the phrase, item and AI scores.
     */
    public function test_combine_fractions(): void {
        $question = $this->make();
        $question->aiweighting = 50;
        $this->assertNull($question->combine_fractions(null, null, null));
        $this->assertEqualsWithDelta(0.3, $question->combine_fractions(0.2, 0.1, null), 0.0001);
        $this->assertEqualsWithDelta(0.6, $question->combine_fractions(0.2, null, 0.8), 0.0001);
        $this->assertEquals(1.0, $question->combine_fractions(0.7, 0.3, 1.0));
        $this->assertEquals(0.0, $question->combine_fractions(0.0, null, null));
    }

    /**
     * Test the penalty for showing the audio prompt transcript.
     */
    public function test_transcript_penalty(): void {
        $question = $this->make('prompt');
        $this->assertTrue($question->has_transcript_penalty());
        $this->assertTrue($question->is_graded_later());
        $this->assertTrue($question->transcript_penalty_applies(true, false));
        $this->assertFalse($question->transcript_penalty_applies(false, false));
        $this->assertFalse($question->transcript_penalty_applies(true, true));
        $this->assertEqualsWithDelta(0.5, $question->apply_transcript_penalty(0.8), 0.0001);
        $this->assertEquals(0.0, $question->apply_transcript_penalty(0.1));
        $this->assertNull($question->apply_transcript_penalty(null));

        // Only the collapsed transcript has a penalty.
        $question->prompttranscriptdisplay = SPEAKUP_PROMPTTRANSCRIPT_EXPANDED;
        $this->assertFalse($question->has_transcript_penalty());

        // No transcript, no penalty.
        $question = $this->make('prompt');
        $question->prompttranscript = '';
        $this->assertFalse($question->has_transcript_penalty());
    }

    /**
     * Test which questions are graded straight away, and which later.
     */
    public function test_grading_modes(): void {
        $question = $this->make();
        $this->assertTrue($question->is_automated());
        $this->assertFalse($question->uses_ai());
        $this->assertFalse($question->is_graded_later());

        $question->aiassessment = 1;
        $this->assertTrue($question->uses_ai());
        $this->assertTrue($question->is_graded_later());

        $question = $this->make();
        $question->responsemethod = SPEAKUP_RESPONSE_UPLOAD;
        $this->assertTrue($question->is_upload());
        $this->assertTrue($question->is_graded_later());

        $question = $this->make('manual');
        $question->aiassessment = 1;
        $this->assertFalse($question->is_automated());
        $this->assertFalse($question->uses_ai());
    }

    /**
     * Questions graded straight away are graded from the live transcript.
     */
    public function test_grade_response(): void {
        $question = $this->make();
        [$fraction, $state] = $question->grade_response(['recording' => 'x', 'browsertranscript' => 'I play the guitar.']);
        $this->assertEqualsWithDelta(0.4 + 0.3 * 4 / 20, $fraction, 0.0001);
        $this->assertEquals(question_state::$gradedpartial, $state);

        // Without a transcript, a teacher must grade it.
        [$fraction, $state] = $question->grade_response(['recording' => 'x', 'browsertranscript' => '']);
        $this->assertNull($fraction);
        $this->assertEquals(question_state::$needsgrading, $state);

        [$fraction] = $this->make('manual')->grade_response(['recording' => 'x', 'browsertranscript' => 'guitar']);
        $this->assertNull($fraction);
    }

    /**
     * Test whether responses are complete.
     */
    public function test_response_checks(): void {
        $question = $this->make();
        $this->assertFalse($question->is_complete_response([]));
        $this->assertFalse($question->is_gradable_response(['recording' => '']));
        // A saved recording's value is not always a file object.
        $this->assertTrue($question->is_complete_response(['recording' => 'abc123']));
        $this->assertSame('', $question->get_validation_error(['recording' => 'abc123']));
        $this->assertNotEmpty($question->get_validation_error([]));
        $this->assertSame(get_string('norecording', 'qtype_speakup'), $question->summarise_response([]));
        $this->assertTrue($question->is_same_response(['recording' => 'a'], ['recording' => 'a']));
        $this->assertFalse($question->is_same_response(['recording' => 'a'], ['recording' => 'b']));
        $this->assertNull($question->get_correct_response());
    }

    /**
     * The expected data includes the recording and the prompt counters.
     */
    public function test_get_expected_data(): void {
        $expected = $this->make()->get_expected_data();
        foreach (
            ['recording', 'browsertranscript', 'browserduration', 'prompttranscriptviewed', 'promptplays',
                'responsecount'] as $name
        ) {
            $this->assertArrayHasKey($name, $expected);
        }
    }

    /**
     * Test the time limit check, which allows a few seconds' leeway.
     */
    public function test_is_too_long(): void {
        $question = $this->make();
        $this->assertFalse($question->is_too_long(null));
        $this->assertFalse($question->is_too_long(60 + \qtype_speakup_question::DURATION_LEEWAY_SECONDS));
        $this->assertTrue($question->is_too_long(61 + \qtype_speakup_question::DURATION_LEEWAY_SECONDS));
        $question->timelimitinseconds = 0;
        $this->assertFalse($question->is_too_long(1000));
    }

    /**
     * Test the language name sent to the AI.
     */
    public function test_get_language_name(): void {
        $question = $this->make();
        $this->assertSame('English (en-US)', $question->get_language_name());
        $question->sttlanguage = 'ja-JP';
        $this->assertSame('Japanese (ja-JP)', $question->get_language_name());
        $question->sttlanguage = 'xx-YY';
        $this->assertSame('xx-YY', $question->get_language_name());
    }

    /**
     * The browser's measurement is used if the file's length is unknown.
     */
    public function test_best_duration(): void {
        $this->assertSame(12.3, \qtype_speakup_question::best_duration(null, '12.34'));
        $this->assertNull(\qtype_speakup_question::best_duration(null, ''));
        $this->assertNull(\qtype_speakup_question::best_duration(null, '-1'));
    }

    /**
     * Image responses are uploaded, have no length, and take images or PDFs.
     */
    public function test_image_responses(): void {
        $question = $this->make('image');
        $this->assertTrue($question->is_image());
        $this->assertTrue($question->is_upload());
        $this->assertTrue($question->is_graded_later());
        $this->assertSame(3, $question->get_max_files());
        $question->maxfiles = 99;
        $this->assertSame(SPEAKUP_MAX_IMAGE_FILES, $question->get_max_files());
        $question->maxfiles = 0;
        $this->assertSame(1, $question->get_max_files());

        $this->assertTrue($question->is_allowed_file_type('image/jpeg'));
        $this->assertTrue($question->is_allowed_file_type('image/png'));
        $this->assertTrue($question->is_allowed_file_type('application/pdf'));
        $this->assertFalse($question->is_allowed_file_type('audio/mp3'));
        $this->assertFalse($question->is_allowed_file_type('text/plain'));

        // Speech rate needs a recording.
        $question->targetitems[0]->itemtype = text_stats::ITEM_SPEECHRATE;
        $this->assertFalse($question->needs_duration());

        // Audio and video questions take one recording, and no images.
        $question = $this->make();
        $this->assertFalse($question->is_image());
        $this->assertSame(1, $question->get_max_files());
        $this->assertTrue($question->is_allowed_file_type('audio/mp3'));
        $this->assertFalse($question->is_allowed_file_type('image/png'));
    }

    /**
     * The question's settings are given in English words, for the
     * placeholders in the AI rubric.
     */
    public function test_rubric_values(): void {
        $question = $this->make();
        $question->name = 'Favorite season';
        $question->prompttranscript = " What's your favorite season? ";
        $question->timelimitinseconds = 30;
        $question->maxpromptplays = 1;
        $question->maxresponses = 0;
        $question->sttlanguage = 'en-US';
        $question->targetitems = [
            (object) ['itemtype' => 'words', 'itemtarget' => '30.00', 'percent' => 25, 'multiplier' => 1],
            (object) ['itemtype' => 'words', 'itemtarget' => '45.00', 'percent' => 5, 'multiplier' => 1],
            (object) ['itemtype' => 'lexicaldensity', 'itemtarget' => '42.5', 'percent' => 10, 'multiplier' => 1],
        ];
        $values = $question->get_rubric_values();
        $this->assertSame('Favorite season', $values['questionname']);
        $this->assertSame("What's your favorite season?", $values['audioprompt']);
        $this->assertSame('30 seconds', $values['timelimit']);
        $this->assertSame('once', $values['promptplays']);
        $this->assertSame('any number of responses', $values['responses']);
        $this->assertSame('English (en-US)', $values['language']);
        // The highest target, i.e. the top of the highest grade band.
        $this->assertSame('45 words', $values['target.words']);
        $this->assertSame('a lexical density of 42.5%', $values['target.lexicaldensity']);

        $expected = [
            [90, 2, 3, '1 minute 30 seconds', 'twice', '3 responses'],
            [120, 3, 1, '2 minutes', '3 times', '1 response'],
            [61, 0, 2, '1 minute 1 second', 'as many times as they liked', '2 responses'],
            [0, 1, 1, 'no time limit', 'once', '1 response'],
        ];
        foreach ($expected as [$seconds, $plays, $responses, $time, $playwords, $responsewords]) {
            $question->timelimitinseconds = $seconds;
            $question->maxpromptplays = $plays;
            $question->maxresponses = $responses;
            $values = $question->get_rubric_values();
            $this->assertSame($time, $values['timelimit']);
            $this->assertSame($playwords, $values['promptplays']);
            $this->assertSame($responsewords, $values['responses']);
        }
    }
}
