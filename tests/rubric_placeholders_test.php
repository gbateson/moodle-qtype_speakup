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
 * Tests for the {{questiontext...}} placeholders in AI rubrics.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \qtype_speakup\rubric_placeholders
 */
final class rubric_placeholders_test extends \basic_testcase {

    /** @var string a question text with several parts. */
    const QUESTIONTEXT = '<p>Listen and answer.</p>' .
            '<div class="rubric d-none"><p>Score <b>fluency</b>.</p></div>' .
            '<p id="task">Describe your town.</p>' .
            '<p><span lang="en" class="multilang">Hello</span><span lang="ja" class="multilang">こんにちは</span></p>' .
            '<audio src="x.mp3">Audio</audio>' .
            '<details><summary>Show more</summary>Hidden text</details>';

    /**
     * A rubric without placeholders is unchanged.
     */
    public function test_no_placeholders(): void {
        $this->assertSame('Just a rubric.', rubric_placeholders::expand('Just a rubric.', self::QUESTIONTEXT));
    }

    /**
     * Data for test_expand().
     *
     * @return array
     */
    public static function expand_provider(): array {
        return [
            'class' => ['Task: {{questiontext.rubric}}', 'Task: Score fluency.'],
            'id' => ['{{questiontext#task}}', 'Describe your town.'],
            'tag and class' => ['{{ questiontext div.rubric }}', 'Score fluency.'],
            'no match' => ['[{{questiontext.missing}}]', '[]'],
            'unsupported selector' => ['[{{questiontext div > p}}]', '[]'],
        ];
    }

    /**
     * Test expanding placeholders with selectors.
     *
     * @dataProvider expand_provider
     * @param string $rubric
     * @param string $expected
     */
    public function test_expand(string $rubric, string $expected): void {
        $this->assertSame($expected, rubric_placeholders::expand($rubric, self::QUESTIONTEXT));
    }

    /**
     * The whole question text leaves out media players and summary labels,
     * and keeps multilang text in English unless another language is given.
     */
    public function test_whole_question_text(): void {
        $text = rubric_placeholders::expand('{{questiontext}}', self::QUESTIONTEXT);
        $this->assertStringContainsString('Listen and answer.', $text);
        $this->assertStringContainsString('Hello', $text);
        $this->assertStringContainsString('Hidden text', $text);
        $this->assertStringNotContainsString('こんにちは', $text);
        $this->assertStringNotContainsString('Audio', $text);
        $this->assertStringNotContainsString('Show more', $text);

        $text = rubric_placeholders::expand('{{questiontext:ja}}', self::QUESTIONTEXT);
        $this->assertStringContainsString('こんにちは', $text);
        $this->assertStringNotContainsString('Hello', $text);
    }

    /**
     * An empty question text gives an empty string.
     */
    public function test_extract_empty(): void {
        $this->assertSame('', rubric_placeholders::extract('', ''));
    }
}
