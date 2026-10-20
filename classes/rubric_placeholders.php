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
 * Replaces {{questiontext...}} placeholders in a question's AI rubric
 * with all, or part, of the question text, as plain text.
 *
 * - {{questiontext}}            the whole question text
 * - {{questiontext.rubric}}     the first element with class="rubric"
 * - {{questiontext#task}}       the element with id="task"
 * - {{questiontext div.x}}      the first <div> with class="x"
 * - {{questiontext.rubric:ja}}  as above, but in Japanese
 *
 * The selector after "questiontext" is a simple CSS selector: a tag name,
 * .class, #id, or a tag followed by .class or #id.
 *
 * Multilang content (lang="xx") is kept only in one language: English,
 * unless another language is given after a colon. Using one fixed
 * language means every student is graded against the same rubric,
 * whatever their own Moodle language is.
 *
 * Based on GET_INTRO() in datafield_report.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rubric_placeholders {

    /** Matches {{questiontext<selector>:<lang>}}, where selector and lang are optional. */
    const PATTERN = '/\{\{\s*questiontext\s*([^}:]*?)\s*(?::\s*([a-z_-]*)\s*)?\}\}/i';

    /** Language used when a placeholder doesn't give one. */
    const DEFAULT_LANG = 'en';

    /**
     * Replace every {{questiontext...}} placeholder in $rubric.
     *
     * @param string $rubric the rubric, as entered by the teacher.
     * @param string $questiontext the question text (HTML).
     * @return string
     */
    public static function expand(string $rubric, string $questiontext): string {
        if (stripos($rubric, '{{') === false) {
            return $rubric;
        }
        return preg_replace_callback(self::PATTERN, function (array $match) use ($questiontext): string {
            $lang = ($match[2] ?? '') !== '' ? $match[2] : self::DEFAULT_LANG;
            return self::extract($questiontext, $match[1], $lang);
        }, $rubric);
    }

    /**
     * Get the plain text of the part of $html that matches $selector.
     *
     * @param string $html
     * @param string $selector simple CSS selector, or '' for all of $html.
     * @param string $lang
     * @return string plain text, or '' if nothing matches.
     */
    public static function extract(string $html, string $selector, string $lang = ''): string {
        if (trim($html) === '') {
            return '';
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        // Wrap in a <div>, so a question text with several top-level
        // elements still loads as a single tree.
        $dom->loadHTML('<?xml encoding="utf-8" ?><div>' . $html . '</div>',
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new \DOMXPath($dom);

        if ($lang !== '') {
            // Remove multilang content in other languages. Matches on the
            // main language only, so lang="en" is kept for en_us.
            $lang = strtolower(strtok(str_replace('-', '_', $lang), '_'));
            $nodes = iterator_to_array($xpath->query('//*[@lang]'));
            foreach ($nodes as $node) {
                $nodelang = strtolower(strtok(str_replace('-', '_', $node->getAttribute('lang')), '_'));
                if ($nodelang !== $lang && $node->parentNode) {
                    $node->parentNode->removeChild($node);
                }
            }
        }

        // Remove media players and scripts, whose fallback text (often a
        // file URL) means nothing to the AI, and <summary> labels such as
        // "Show rubric", which are only there for students.
        $nodes = iterator_to_array($xpath->query(
                '//audio|//video|//object|//embed|//iframe|//script|//style|//summary'));
        foreach ($nodes as $node) {
            if ($node->parentNode) {
                $node->parentNode->removeChild($node);
            }
        }

        if ($selector === '') {
            $node = $dom->documentElement;
        } else {
            $query = self::css_to_xpath($selector);
            if ($query === null) {
                return '';
            }
            $nodes = $xpath->query($query);
            if (!$nodes || !$nodes->length) {
                return '';
            }
            $node = $nodes->item(0);
        }

        // Convert the element's inner HTML to plain text, keeping line
        // breaks between paragraphs and list items (DOM textContent
        // would run them all together).
        $inner = '';
        foreach ($node->childNodes as $child) {
            $inner .= $dom->saveHTML($child);
        }
        // html_to_text() writes bold text, headings and table headers in
        // CAPITALS, which would change the teacher's wording, so turn
        // those into plain tags first.
        $inner = preg_replace('#</?(b|strong)\b[^>]*>#i', '', $inner);
        $inner = preg_replace('#<(/?)h[1-6]\b#i', '<$1p', $inner);
        $inner = preg_replace('#<(/?)th\b#i', '<$1td', $inner);
        return trim(html_to_text($inner, 0, false), " \r\n");
    }

    /**
     * Convert a simple CSS selector to an XPath query.
     *
     * @param string $selector e.g. "div", ".rubric", "#task", "div.rubric", "p#task".
     * @return string|null null if the selector is not supported.
     */
    protected static function css_to_xpath(string $selector): ?string {
        if (!preg_match('/^([a-z][a-z0-9]*)?(?:([.#])([a-z0-9_-]+))?$/i', trim($selector), $match)) {
            return null;
        }
        $tag = ($match[1] ?? '') !== '' ? strtolower($match[1]) : '*';
        $type = $match[2] ?? '';
        $name = $match[3] ?? '';
        if ($type === '.') {
            return "/*//{$tag}[contains(concat(' ', normalize-space(@class), ' '), ' {$name} ')]";
        }
        if ($type === '#') {
            return "/*//{$tag}[@id='{$name}']";
        }
        if ($tag === '*') {
            return null;
        }
        return "/*//{$tag}";
    }
}
