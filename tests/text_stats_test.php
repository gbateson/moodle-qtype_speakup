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

/**
 * Tests for the text statistics.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \qtype_speakup\text_stats
 */
final class text_stats_test extends \basic_testcase {

    /**
     * Data for test_get_value().
     *
     * @return array
     */
    public static function get_value_provider(): array {
        $text = "I have played the guitar for six years. I practise every day!\nMy teacher is wonderful.";
        return [
            'chars' => [text_stats::ITEM_CHARS, $text, null, 68.0],
            'words' => [text_stats::ITEM_WORDS, $text, null, 16.0],
            'sentences' => [text_stats::ITEM_SENTENCES, $text, null, 3.0],
            'unique words' => [text_stats::ITEM_UNIQUEWORDS, $text, null, 15.0],
            'long words' => [text_stats::ITEM_LONGWORDS, $text, null, 2.0],
            'paragraphs' => [text_stats::ITEM_PARAGRAPHS, $text, null, 2.0],
            'words per sentence' => [text_stats::ITEM_WORDSPERSENTENCE, $text, null, 16 / 3],
            'sentences per paragraph' => [text_stats::ITEM_SENTENCESPERPARAGRAPH, $text, null, 1.5],
            'lexical density' => [text_stats::ITEM_LEXICALDENSITY, $text, null, 15 / 16 * 100],
            'speech rate' => [text_stats::ITEM_SPEECHRATE, $text, 30.0, 32.0],
            'speech rate, no duration' => [text_stats::ITEM_SPEECHRATE, $text, null, null],
            'words, empty' => [text_stats::ITEM_WORDS, '', null, 0.0],
            'lexical density, empty' => [text_stats::ITEM_LEXICALDENSITY, '', null, null],
            'fog index, empty' => [text_stats::ITEM_FOGINDEX, '', null, null],
            'number is not a sentence end' => [text_stats::ITEM_SENTENCES, 'It costs 3.5 dollars. Yes.', null, 2.0],
            'punctuation inside words' => [text_stats::ITEM_WORDS, "Don't stop - it's well-known.", null, 4.0],
            'unknown type' => ['nosuchtype', $text, null, null],
        ];
    }

    /**
     * Test the value of each statistic.
     *
     * @dataProvider get_value_provider
     * @param string $type
     * @param string $text
     * @param float|null $duration
     * @param float|null $expected
     */
    public function test_get_value(string $type, string $text, ?float $duration, ?float $expected): void {
        $value = text_stats::get_value($type, $text, $duration);
        if ($expected === null) {
            $this->assertNull($value);
        } else {
            $this->assertEqualsWithDelta($expected, $value, 0.001);
        }
    }

    /**
     * Data for test_count_syllables().
     *
     * @return array
     */
    public static function count_syllables_provider(): array {
        return [
            ['cat', 1], ['make', 1], ['table', 2], ['agree', 2], ['jumped', 1], ['wanted', 2],
            ['wonderful', 3], ['practise', 2], ['instrument', 3], ['', 0], ['123', 0],
        ];
    }

    /**
     * Test the syllable estimates.
     *
     * @dataProvider count_syllables_provider
     * @param string $word
     * @param int $expected
     */
    public function test_count_syllables(string $word, int $expected): void {
        $this->assertSame($expected, text_stats::count_syllables($word));
    }

    /**
     * Test the item and statistic types.
     */
    public function test_types(): void {
        $this->assertContains(text_stats::ITEM_SPEECHRATE, text_stats::get_item_types());
        $this->assertNotContains(text_stats::ITEM_SENTENCES, text_stats::get_item_types());
        $this->assertContains(text_stats::ITEM_SENTENCES, text_stats::get_stat_types(true));
        $this->assertNotContains(text_stats::ITEM_SENTENCES, text_stats::get_stat_types(false));
        $this->assertTrue(text_stats::needs_sentences(text_stats::ITEM_FOGINDEX));
        $this->assertFalse(text_stats::needs_sentences(text_stats::ITEM_WORDS));
    }

    /**
     * Test the defaults for languages with and without spaces between words.
     */
    public function test_language_defaults(): void {
        $this->assertTrue(text_stats::uses_spaces('en-US'));
        $this->assertFalse(text_stats::uses_spaces('ja-JP'));
        $this->assertFalse(text_stats::uses_spaces('zh_CN'));
        $this->assertSame(text_stats::ITEM_WORDS, text_stats::get_default_item_type('en'));
        $this->assertSame(text_stats::ITEM_CHARS, text_stats::get_default_item_type('ja'));
        $this->assertSame([text_stats::ITEM_CHARS], text_stats::get_default_stat_types('ko'));
        $defaults = text_stats::get_default_stat_types('en-GB');
        $this->assertContains(text_stats::ITEM_WORDS, $defaults);
        $this->assertNotContains(text_stats::ITEM_CHARS, $defaults);
    }
}
