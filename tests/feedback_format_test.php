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
 * Tests for detecting and tidying Markdown in AI feedback.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \qtype_speakup\feedback_format
 */
final class feedback_format_test extends \basic_testcase {

    /**
     * Data for test_detect().
     *
     * @return array
     */
    public static function detect_provider(): array {
        return [
            'plain' => ['Good answer. Try to speak more slowly.', FORMAT_PLAIN],
            'asterisk in maths' => ['2 * 3 = 6, and 4 * 5 = 20.', FORMAT_PLAIN],
            'bullets' => ["Well done.\n* Speak slowly\n* Use linking words", FORMAT_MARKDOWN],
            'numbered' => ["1. First point\n2. Second point", FORMAT_MARKDOWN],
            'heading' => ["## Feedback\nGood.", FORMAT_MARKDOWN],
            'bold' => ['This was **very** good.', FORMAT_MARKDOWN],
            'italic' => ['This was *very* good.', FORMAT_MARKDOWN],
            'code' => ['Say `have played`.', FORMAT_MARKDOWN],
            'link' => ['See [this page](https://example.com).', FORMAT_MARKDOWN],
        ];
    }

    /**
     * Test detecting Markdown.
     *
     * @dataProvider detect_provider
     * @param string $text
     * @param int|string $expected one of the FORMAT_ constants.
     */
    public function test_detect(string $text, $expected): void {
        $this->assertEquals($expected, feedback_format::detect($text));
    }

    /**
     * A list straight after a line of text gets a blank line before it, so
     * that Markdown shows it as a list.
     */
    public function test_tidy_markdown(): void {
        $this->assertSame("Suggestions:\n\n* One\n* Two", feedback_format::tidy_markdown("Suggestions:\r\n* One\r\n* Two"));
        $this->assertSame("Text\n\n1. One", feedback_format::tidy_markdown("Text\n1. One"));
        // Already separated: unchanged.
        $this->assertSame("Text\n\n* One", feedback_format::tidy_markdown("Text\n\n* One"));
    }
}
