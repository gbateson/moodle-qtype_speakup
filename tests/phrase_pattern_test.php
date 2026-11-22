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
 * Tests for target phrases written in plain English, e.g. "First AND (Then OR Next)".
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \qtype_speakup\phrase_pattern
 */
final class phrase_pattern_test extends \basic_testcase {
    /** A phrase that checks the sequencing words of a recipe. */
    const SEQUENCE = 'First AND (Then OR Next) AND (Finally OR In the end)';

    /**
     * Data for test_match().
     *
     * @return array [phrase, text, fullmatch, casesensitive, expected words, or null]
     */
    public static function match_provider(): array {
        return [
            'plain phrase' => ['I have played', 'Yes, I have played it.', false, false, 'I have played'],
            'spaces match any spaces' => ['I have played', "I have\n  played", false, false, "I have\n  played"],
            'not found' => ['I have played', 'I played', false, false, null],
            'case insensitive' => ['first', 'FIRST of all', false, false, 'FIRST'],
            'case sensitive' => ['first', 'First of all', false, true, null],
            'partial word' => ['first', 'Firstly, ...', false, false, 'First'],
            'full match' => ['first', 'Firstly, ...', true, false, null],
            'OR, first alternative' => ['Then OR Next', 'Then we wait.', false, false, 'Then'],
            'OR, second alternative' => ['Then OR Next', 'Next we wait.', false, false, 'Next'],
            'comma is OR' => ['Finally, In the end', 'In the end, we eat.', false, false, 'In the end'],
            'or in lower case is a word' => ['then or next', 'then or next', false, false, 'then or next'],
            'or in lower case is a word, not found' => ['then or next', 'next', false, false, null],
            'AND' => ['First AND Finally', 'First, boil it. Finally, eat it.', false, false, 'First … Finally'],
            'AND is in order' => ['First AND Finally', 'Finally, first.', false, false, null],
            'ANY is AND' => ['First ANY Finally', 'First, boil it. Finally, eat it.', false, false, 'First … Finally'],
            'AND across lines' => ['First AND Finally', "First, boil it.\nFinally, eat it.", false, false, 'First … Finally'],
            'brackets' => [self::SEQUENCE, 'First boil it, next cool it, and in the end eat it.', true, false,
                    'First … next … in the end'],
            'brackets, in order' => [self::SEQUENCE, 'Then boil it. Finally eat it. First of all, no.', true, false, null],
            'brackets, missing part' => [self::SEQUENCE, 'First boil it, and in the end eat it.', true, false, null],
            'AND binds more tightly than OR' => ['Hello OR Good AND bye', 'Good night.', false, false, null],
            'AND binds more tightly than OR, found' => ['Hello OR Good AND bye', 'Good, bye.', false, false, 'Good … bye'],
            'bracket next to words' => ['(big OR small) dog', 'a big  dog', false, false, 'big  dog'],
            'star' => ['walk*', 'We walked home.', true, false, 'walked'],
            'star, no letters' => ['walk*', 'We walk home.', true, false, 'walk'],
            'star as a word' => ['I * you', 'I love you.', false, false, 'I love you'],
            'question mark' => ['organi?e', 'organize it', true, false, 'organize'],
            'question mark, no letter' => ['colo?r', 'The color is red.', true, false, 'color'],
            'full match with letters outside a-z' => ['café', 'Un café crème', true, false, 'café'],
            'other characters are not special' => ['Mr. Smith', 'Mrs Smith', false, false, null],
            'regex' => ['/\bviolins?\b/i', 'Two Violins.', true, false, 'Violins'],
            'empty text' => ['first', '', false, false, null],
        ];
    }

    /**
     * Test finding phrases in a text, and the words that matched.
     *
     * @dataProvider match_provider
     * @param string $phrase
     * @param string $text
     * @param bool $fullmatch
     * @param bool $casesensitive
     * @param string|null $expected
     */
    public function test_match(string $phrase, string $text, bool $fullmatch, bool $casesensitive, ?string $expected): void {
        $this->assertSame($expected, phrase_pattern::match($phrase, $text, $fullmatch, $casesensitive));
    }

    /**
     * Data for test_get_error().
     *
     * @return array [phrase, the name of the expected error string, or null]
     */
    public static function get_error_provider(): array {
        return [
            'plain' => ['I have played', null],
            'sequence' => [self::SEQUENCE, null],
            'nested brackets' => ['((a OR b) AND c) OR d', null],
            'regex' => ['/\bgood\b/i', null],
            'bad regex' => ['/(good/i', 'err_invalidregex'],
            'unclosed bracket' => ['First AND (Then OR Next', 'err_phrasebrackets'],
            'unopened bracket' => ['First AND Then) OR Next', 'err_phrasebrackets'],
            'OR at the start' => ['OR Next', 'err_phraseoperator'],
            'AND at the end' => ['First AND', 'err_phraseoperator'],
            'two commas' => ['Then,, Next', 'err_phraseoperator'],
            'empty brackets' => ['First AND ()', 'err_phraseoperator'],
        ];
    }

    /**
     * Test checking phrases on the question form.
     *
     * @dataProvider get_error_provider
     * @param string $phrase
     * @param string|null $expected
     */
    public function test_get_error(string $phrase, ?string $expected): void {
        $error = phrase_pattern::get_error($phrase);
        if ($expected === null) {
            $this->assertNull($error);
        } else if ($expected === 'err_invalidregex') {
            $this->assertSame(get_string($expected, 'qtype_speakup', s($phrase)), $error);
        } else {
            $this->assertSame(get_string($expected, 'qtype_speakup'), $error);
        }
    }

    /**
     * A phrase that is not valid never matches.
     */
    public function test_invalid_phrase_does_not_match(): void {
        $this->assertNull(phrase_pattern::match('First AND', 'First and last', false, false));
        $this->assertNull(phrase_pattern::match('/(first/', 'First and last', false, false));
    }

    /**
     * Test recognising regular expressions.
     */
    public function test_is_regex(): void {
        $this->assertTrue(phrase_pattern::is_regex('/good/'));
        $this->assertTrue(phrase_pattern::is_regex(' /good/iu '));
        $this->assertFalse(phrase_pattern::is_regex('good'));
        $this->assertFalse(phrase_pattern::is_regex('and/or'));
    }
}
