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
 * Finds a target phrase in a transcript.
 *
 * A phrase written as /pattern/flags is a regular expression. Any other
 * phrase is written in plain English, as in qtype_essayautograde, e.g.
 * "First AND (Then OR Next) AND (Finally OR In the end)":
 *
 * - "OR", or a comma, separates alternatives.
 * - "AND", or "ANY", means that what follows comes later in the response.
 * - Brackets group parts of the phrase.
 * - In a word, "*" matches any number of characters (including none), and
 *   "?" matches one character (or none).
 * - Spaces between words match any spaces in the response.
 *
 * "AND" binds more tightly than "OR", so "A OR B AND C" means "A", or "B
 * and later C". OR, AND and ANY are only keywords in upper case.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class phrase_pattern {
    /** A letter or digit, which a word can not touch if "full match" is set. Unlike \b, this works for "café". */
    const WORDCHAR = '[\p{L}\p{N}]';

    /** @var string[] the tokens of the phrase: "(", ")", "OR", "AND" or a word. */
    protected $tokens = [];

    /** @var int the position of the next token. */
    protected $pos = 0;

    /** @var bool whether words must be whole words. */
    protected $fullmatch;

    /** @var string|null the name of the string that explains why the phrase is not valid, or null. */
    protected $error = null;

    /** @var array cache of compiled phrases: key => [regex or null, error or null]. */
    protected static $cache = [];

    /**
     * Constructor.
     *
     * @param string $phrase a plain-English phrase.
     * @param bool $fullmatch whether words must be whole words.
     */
    protected function __construct(string $phrase, bool $fullmatch) {
        $this->fullmatch = $fullmatch;
        preg_match_all('/[(),]|[^\s(),]+/u', $phrase, $matches);
        foreach ($matches[0] as $token) {
            switch ($token) {
                case ',':
                case 'OR':
                    $this->tokens[] = 'OR';
                    break;
                case 'AND':
                case 'ANY':
                    $this->tokens[] = 'AND';
                    break;
                default:
                    $this->tokens[] = $token;
            }
        }
    }

    /**
     * Is a phrase a regular expression, i.e. written as /pattern/flags?
     *
     * @param string $phrase
     * @return bool
     */
    public static function is_regex(string $phrase): bool {
        return (bool) preg_match('#^/.*/[a-zA-Z]*$#s', trim($phrase));
    }

    /**
     * Convert a plain-English phrase to a regular expression.
     *
     * Each run of words is a capture group, so that match() can say which
     * words matched each part of the phrase.
     *
     * @param string $phrase
     * @param bool $fullmatch whether words must be whole words.
     * @param bool $casesensitive
     * @return array [regex, or null if the phrase is not valid; the name of
     *      the string that explains why it is not valid, or null]
     */
    public static function compile(string $phrase, bool $fullmatch, bool $casesensitive): array {
        $key = (int) $fullmatch . (int) $casesensitive . $phrase;
        if (!isset(self::$cache[$key])) {
            $parser = new self($phrase, $fullmatch);
            $regex = $parser->parse_or();
            if ($parser->error === null && $parser->pos < count($parser->tokens)) {
                // The only token that parse_or() stops at is an unopened ")".
                $parser->error = 'err_phrasebrackets';
            }
            if ($parser->error !== null) {
                self::$cache[$key] = [null, $parser->error];
            } else {
                // Matching ignores line breaks ("s"), since a transcript's line breaks mean nothing.
                self::$cache[$key] = ['/' . $regex . '/us' . ($casesensitive ? '' : 'i'), null];
            }
        }
        return self::$cache[$key];
    }

    /**
     * Check that a phrase can be used.
     *
     * @param string $phrase a plain-English phrase, or a regular expression.
     * @return string|null an explanation, if it can not be used; otherwise null.
     */
    public static function get_error(string $phrase): ?string {
        $phrase = trim($phrase);
        if (self::is_regex($phrase)) {
            if (@preg_match($phrase, '') === false) {
                return get_string('err_invalidregex', 'qtype_speakup', s($phrase));
            }
            return null;
        }
        [, $error] = self::compile($phrase, false, false);
        return ($error === null) ? null : get_string($error, 'qtype_speakup');
    }

    /**
     * Find a phrase in a text.
     *
     * @param string $phrase a plain-English phrase, or a regular expression.
     * @param string $text
     * @param bool $fullmatch whether words must be whole words (not for a regular expression).
     * @param bool $casesensitive (not for a regular expression, which has its own flags).
     * @return string|null the words that matched, or null if the phrase was not
     *      found. For a phrase with AND, the words that matched each part,
     *      e.g. "First … then … in the end".
     */
    public static function match(string $phrase, string $text, bool $fullmatch, bool $casesensitive): ?string {
        $phrase = trim($phrase);
        if ($phrase === '' || trim($text) === '') {
            return null;
        }
        if (self::is_regex($phrase)) {
            return @preg_match($phrase, $text, $match) ? $match[0] : null;
        }
        [$regex] = self::compile($phrase, $fullmatch, $casesensitive);
        if ($regex === null || !preg_match($regex, $text, $match, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        // Join the words that matched each part of the phrase. Parts with only
        // spaces between them are shown as they are in the text.
        $words = '';
        $end = null;
        for ($i = 1; $i < count($match); $i++) {
            [$part, $offset] = $match[$i];
            if ($offset < 0 || $part === '') {
                continue;
            }
            if ($end === null) {
                $words = $part;
            } else if (trim(substr($text, $end, $offset - $end)) === '') {
                $words .= substr($text, $end, $offset + strlen($part) - $end);
            } else {
                $words .= ' … ' . $part;
            }
            $end = $offset + strlen($part);
        }
        return ($words === '') ? $match[0][0] : $words;
    }

    /**
     * The next token, or null at the end of the phrase.
     *
     * @return string|null
     */
    protected function peek(): ?string {
        return $this->tokens[$this->pos] ?? null;
    }

    /**
     * Parse alternatives: parts separated by OR.
     *
     * @return string regex
     */
    protected function parse_or(): string {
        $parts = [$this->parse_and()];
        while ($this->error === null && $this->peek() === 'OR') {
            $this->pos++;
            $parts[] = $this->parse_and();
        }
        return (count($parts) == 1) ? $parts[0] : '(?:' . implode('|', $parts) . ')';
    }

    /**
     * Parse a sequence of terms: each either straight after the one before
     * it (with spaces between), or, after AND, later in the text.
     *
     * @return string regex
     */
    protected function parse_and(): string {
        $regex = $this->parse_term();
        while ($this->error === null) {
            $token = $this->peek();
            if ($token === 'AND') {
                $this->pos++;
                $regex .= '.*?' . $this->parse_term();
            } else if ($token !== null && $token !== 'OR' && $token !== ')') {
                // A bracket after words, or words after a bracket, e.g. "(big OR small) dog".
                $regex .= '\s+' . $this->parse_term();
            } else {
                break;
            }
        }
        return $regex;
    }

    /**
     * Parse a term: a bracketed phrase, or a run of words.
     *
     * @return string regex
     */
    protected function parse_term(): string {
        $token = $this->peek();
        if ($token === '(') {
            $this->pos++;
            $regex = $this->parse_or();
            if ($this->error === null && $this->peek() !== ')') {
                $this->error = 'err_phrasebrackets';
            }
            $this->pos++;
            return '(?:' . $regex . ')';
        }
        if ($token === null || $token === 'OR' || $token === 'AND' || $token === ')') {
            // E.g. "OR" at the start, "AND" at the end, or "()".
            $this->error = 'err_phraseoperator';
            return '';
        }
        $words = [];
        while (($token = $this->peek()) !== null && !in_array($token, ['(', ')', 'OR', 'AND'], true)) {
            $words[] = $this->word_regex($token);
            $this->pos++;
        }
        $regex = '(' . implode('\s+', $words) . ')';
        if ($this->fullmatch) {
            $regex = '(?<!' . self::WORDCHAR . ')' . $regex . '(?!' . self::WORDCHAR . ')';
        }
        return $regex;
    }

    /**
     * Convert a word to a regex: its characters as they are, except the
     * wildcards "*" (any number of characters) and "?" (one or none).
     *
     * @param string $word
     * @return string regex
     */
    protected function word_regex(string $word): string {
        $regex = '';
        foreach (preg_split('/([*?])/', $word, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) as $part) {
            if ($part === '*') {
                $regex .= '\S*?';
            } else if ($part === '?') {
                $regex .= '\S?';
            } else {
                $regex .= preg_quote($part, '/');
            }
        }
        return $regex;
    }
}
