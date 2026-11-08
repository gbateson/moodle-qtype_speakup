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
 * - {{xyz/questiontext.rubric}} as above, but from the question text of
 *                               another question, whose ID number is "xyz"
 *
 * Another question is found by its ID number: in this question's category,
 * or else the category above it, and so on up to the top of the question
 * bank. So a rubric that many questions share, e.g. in a Description
 * question, can be written once, and refined in one place. Its latest
 * "Ready" version is used.
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

    /** Matches {{<idnumber>/questiontext<selector>:<lang>}}, where idnumber, selector and lang are optional. */
    const PATTERN = '/\{\{\s*(?:([^{}\/]+?)\s*\/\s*)?questiontext\s*([^}:]*?)\s*(?::\s*([a-z_-]*)\s*)?\}\}/i';

    /** The most levels of categories to look up, in case of a loop in bad data. */
    const MAX_DEPTH = 50;

    /** Language used when a placeholder doesn't give one. */
    const DEFAULT_LANG = 'en';

    /**
     * Replace every {{questiontext...}} placeholder in $rubric.
     *
     * @param string $rubric the rubric, as entered by the teacher.
     * @param string $questiontext the question text (HTML).
     * @param int $categoryid the question's category, where other questions
     *      named in placeholders are looked for (and the categories above it).
     * @return string
     * @throws \moodle_exception if another question named in a placeholder cannot be found.
     */
    public static function expand(string $rubric, string $questiontext, int $categoryid = 0): string {
        if (stripos($rubric, '{{') === false) {
            return $rubric;
        }
        return preg_replace_callback(self::PATTERN, function (array $match) use ($questiontext, $categoryid): string {
            $idnumber = $match[1] ?? '';
            if ($idnumber !== '') {
                $questiontext = self::find_question_text($idnumber, $categoryid);
                if ($questiontext === null) {
                    throw new \moodle_exception('errorrubricquestion', 'qtype_speakup', '', $idnumber);
                }
            }
            $lang = ($match[3] ?? '') !== '' ? $match[3] : self::DEFAULT_LANG;
            return self::extract($questiontext, $match[2], $lang);
        }, $rubric);
    }

    /**
     * Get the ID numbers of other questions named in a rubric's placeholders
     * that cannot be found, e.g. to warn the teacher on the question form.
     *
     * @param string $rubric
     * @param int $categoryid the question's category.
     * @return string[]
     */
    public static function get_missing_questions(string $rubric, int $categoryid): array {
        $missing = [];
        if (preg_match_all(self::PATTERN, $rubric, $matches)) {
            foreach (array_unique(array_filter($matches[1], 'strlen')) as $idnumber) {
                if (self::find_question_text($idnumber, $categoryid) === null) {
                    $missing[] = $idnumber;
                }
            }
        }
        return $missing;
    }

    /**
     * Find the question text of the question with an ID number: in a
     * category, or else the category above it, and so on up to the top of
     * the question bank. The latest "Ready" version is used.
     *
     * @param string $idnumber
     * @param int $categoryid
     * @return string|null the question text (HTML), or null if there is no such question.
     */
    public static function find_question_text(string $idnumber, int $categoryid): ?string {
        global $DB;
        $sql = 'SELECT q.questiontext
                  FROM {question_bank_entries} qbe
                  JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                  JOIN {question} q ON q.id = qv.questionid
                 WHERE qbe.questioncategoryid = :categoryid AND qbe.idnumber = :idnumber AND qv.status = :status
              ORDER BY qv.version DESC';
        $status = \core_question\local\bank\question_version_status::QUESTION_STATUS_READY;
        for ($depth = 0; $categoryid && $depth < self::MAX_DEPTH; $depth++) {
            $records = $DB->get_records_sql($sql,
                    ['categoryid' => $categoryid, 'idnumber' => $idnumber, 'status' => $status], 0, 1);
            if ($records) {
                return (string) reset($records)->questiontext;
            }
            $categoryid = (int) $DB->get_field('question_categories', 'parent', ['id' => $categoryid]);
        }
        return null;
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
