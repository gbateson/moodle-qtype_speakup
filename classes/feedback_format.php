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
 * Decides whether AI feedback is Markdown or plain text.
 *
 * AI models often reply in Markdown (bullet lists, *emphasis*, etc.)
 * even when told not to. Shown as plain text, students would see the
 * raw asterisks. So if the feedback looks like Markdown, it is saved as
 * a Markdown grading comment, which Moodle converts to HTML when showing
 * it. Otherwise it is saved as plain text.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class feedback_format {

    /** Patterns that are common in Markdown but rare in plain text. */
    const MARKDOWN_PATTERNS = [
        '/^ {0,3}[*+-][ \t]+\S/m',                       // Bullet list item.
        '/^ {0,3}\d{1,3}[.)][ \t]+\S/m',                 // Numbered list item.
        '/^ {0,3}#{1,6}[ \t]+\S/m',                      // Heading.
        '/\*\*[^*\n]+\*\*/',                             // **Bold**.
        '/__[^_\n]+__/',                                 // __Bold__.
        '/(?<![*\w])\*(?![\s*])[^*\n]+(?<![\s*])\*(?![*\w])/', // *Italic*.
        '/`[^`\n]+`/',                                   // `Code`.
        '/\[[^\]\n]+\]\([^)\s]+\)/',                     // [Link](url).
    ];

    /** A Markdown list item: a bullet or a number, then a space. */
    const LIST_ITEM = ' {0,3}(?:[*+-]|\d{1,3}[.)])[ \t]';

    /**
     * Add the blank line Markdown needs between a paragraph and a list.
     *
     * AI models often start a list on the line straight after a
     * paragraph. Moodle's Markdown converter then treats the list items
     * as part of the paragraph, and shows the raw "*" bullets.
     *
     * @param string $text
     * @return string
     */
    public static function tidy_markdown(string $text): string {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $item = self::LIST_ITEM;
        return preg_replace("/^(?!{$item})(.*\S.*)\n({$item})/m", "$1\n\n$2", $text);
    }

    /**
     * Get the Moodle text format to use for this feedback.
     *
     * @param string $text
     * @return int FORMAT_MARKDOWN or FORMAT_PLAIN.
     */
    public static function detect(string $text): int {
        foreach (self::MARKDOWN_PATTERNS as $pattern) {
            if (preg_match($pattern, $text)) {
                return FORMAT_MARKDOWN;
            }
        }
        return FORMAT_PLAIN;
    }
}
