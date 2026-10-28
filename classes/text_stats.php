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
 * Counts the "target items" in a transcript of a spoken response, e.g.
 * words, unique words or the speech rate.
 *
 * Based on the text statistics in qtype_essayautograde.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class text_stats {

    /** Letters and digits, not counting spaces or punctuation. */
    const ITEM_CHARS = 'chars';
    /** Words, separated by spaces. */
    const ITEM_WORDS = 'words';
    /** Sentences, ended by . ! ? (or 。！？). */
    const ITEM_SENTENCES = 'sentences';
    /** Different words, ignoring case. */
    const ITEM_UNIQUEWORDS = 'uniquewords';
    /** Words with three or more syllables. */
    const ITEM_LONGWORDS = 'longwords';
    /** Characters divided by sentences. */
    const ITEM_CHARSPERSENTENCE = 'charspersentence';
    /** Words divided by sentences. */
    const ITEM_WORDSPERSENTENCE = 'wordspersentence';
    /** Long words divided by sentences. */
    const ITEM_LONGWORDSPERSENTENCE = 'longwordspersentence';
    /** (Unique words / words) x 100. */
    const ITEM_LEXICALDENSITY = 'lexicaldensity';
    /** (Words / seconds of recording) x 60, i.e. words per minute. */
    const ITEM_SPEECHRATE = 'speechrate';
    /** Paragraphs, separated by line breaks. */
    const ITEM_PARAGRAPHS = 'paragraphs';
    /** Sentences divided by paragraphs. */
    const ITEM_SENTENCESPERPARAGRAPH = 'sentencesperparagraph';
    /** Gunning fog index: 0.4 x (words per sentence + 100 x long words / words). */
    const ITEM_FOGINDEX = 'fogindex';

    /**
     * Languages written without spaces between words, for which the default
     * target item is characters rather than words.
     */
    const NON_SPACE_LANGUAGES = ['ja', 'zh', 'ko', 'th', 'lo', 'km', 'my'];

    /**
     * Get the target item types that teachers can choose, in the order
     * they appear in menus.
     *
     * The sentence items (ITEM_SENTENCES and the "per sentence" items) can
     * be counted too, but are not offered for now.
     *
     * @return string[]
     */
    public static function get_item_types(): array {
        return [
            self::ITEM_CHARS,
            self::ITEM_WORDS,
            self::ITEM_LONGWORDS,
            self::ITEM_UNIQUEWORDS,
            self::ITEM_LEXICALDENSITY,
            self::ITEM_SPEECHRATE,
        ];
    }

    /**
     * Get the statistics that can be shown on the review page, in the
     * order they appear on the question form.
     *
     * @param bool $needsai true for the statistics that need sentences (and
     *      so a transcript with punctuation, made by AI), false for the rest.
     * @return string[]
     */
    public static function get_stat_types(bool $needsai): array {
        if ($needsai) {
            return [
                self::ITEM_SENTENCES,
                self::ITEM_PARAGRAPHS,
                self::ITEM_CHARSPERSENTENCE,
                self::ITEM_WORDSPERSENTENCE,
                self::ITEM_LONGWORDSPERSENTENCE,
                self::ITEM_SENTENCESPERPARAGRAPH,
                self::ITEM_FOGINDEX,
            ];
        }
        return [
            self::ITEM_CHARS,
            self::ITEM_WORDS,
            self::ITEM_LONGWORDS,
            self::ITEM_UNIQUEWORDS,
            self::ITEM_LEXICALDENSITY,
        ];
    }

    /**
     * Does this item need sentences? Those can only be counted in a
     * transcript with punctuation, i.e. one made by AI. The browser's live
     * transcript has no punctuation.
     *
     * @param string $type
     * @return bool
     */
    public static function needs_sentences(string $type): bool {
        return in_array($type, self::get_stat_types(true));
    }

    /**
     * Get the default target item for a language: characters for languages
     * written without spaces between words (e.g. Japanese), otherwise words.
     *
     * @param string $lang a language tag, e.g. "en-US" or "ja-JP".
     * @return string
     */
    public static function get_default_item_type(string $lang): string {
        return self::uses_spaces($lang) ? self::ITEM_WORDS : self::ITEM_CHARS;
    }

    /**
     * Is a language written with spaces between words? Japanese, Chinese,
     * Thai and some others are not (see NON_SPACE_LANGUAGES), so for them,
     * characters are counted rather than words.
     *
     * Moodle's language packs have no setting for this, and the language
     * pack for the response language may not even be installed, so the
     * list is kept here.
     *
     * @param string $lang a language tag, e.g. "en-US" or "ja-JP".
     * @return bool
     */
    public static function uses_spaces(string $lang): bool {
        $lang = strtolower(strtok(str_replace('_', '-', $lang), '-'));
        return !in_array($lang, self::NON_SPACE_LANGUAGES);
    }

    /**
     * Get the statistics that are ticked by default on the question form.
     *
     * For a language written without spaces, just characters; otherwise,
     * everything except characters. None of the statistics that need an
     * AI transcript are ticked by default.
     *
     * @param string $lang the response language, e.g. "en-US" or "ja-JP".
     * @return string[]
     */
    public static function get_default_stat_types(string $lang): array {
        if (!self::uses_spaces($lang)) {
            return [self::ITEM_CHARS];
        }
        return array_values(array_diff(self::get_stat_types(false), [self::ITEM_CHARS]));
    }

    /**
     * Get the value of one target item for a transcript.
     *
     * @param string $type one of the ITEM_ constants.
     * @param string $transcript
     * @param float|null $duration length of the recording in seconds, if known.
     * @return float|null the value, or null if it can't be worked out, e.g.
     *      the speech rate when the duration is unknown.
     */
    public static function get_value(string $type, string $transcript, ?float $duration = null): ?float {
        $words = self::get_words($transcript);
        $countwords = count($words);
        switch ($type) {
            case self::ITEM_CHARS:
                return (float) self::count_chars($transcript);
            case self::ITEM_WORDS:
                return (float) $countwords;
            case self::ITEM_SENTENCES:
                return (float) self::count_sentences($transcript);
            case self::ITEM_UNIQUEWORDS:
                return (float) count(self::get_unique_words($words));
            case self::ITEM_LONGWORDS:
                return (float) self::count_long_words($words);
            case self::ITEM_CHARSPERSENTENCE:
                return self::divide(self::count_chars($transcript), self::count_sentences($transcript));
            case self::ITEM_WORDSPERSENTENCE:
                return self::divide($countwords, self::count_sentences($transcript));
            case self::ITEM_LONGWORDSPERSENTENCE:
                return self::divide(self::count_long_words($words), self::count_sentences($transcript));
            case self::ITEM_LEXICALDENSITY:
                $density = self::divide(count(self::get_unique_words($words)), $countwords);
                return ($density === null) ? null : $density * 100;
            case self::ITEM_PARAGRAPHS:
                return (float) self::count_paragraphs($transcript);
            case self::ITEM_SENTENCESPERPARAGRAPH:
                return self::divide(self::count_sentences($transcript), self::count_paragraphs($transcript));
            case self::ITEM_FOGINDEX:
                $sentences = self::count_sentences($transcript);
                if (!$sentences || !$countwords) {
                    return null;
                }
                return 0.4 * (($countwords / $sentences) + (100 * self::count_long_words($words) / $countwords));
            case self::ITEM_SPEECHRATE:
                if (empty($duration) || $duration <= 0) {
                    return null;
                }
                return $countwords / $duration * 60;
        }
        return null;
    }

    /**
     * Divide two counts, or return null if there is nothing to divide by.
     *
     * @param int $count
     * @param int $by
     * @return float|null
     */
    protected static function divide(int $count, int $by): ?float {
        return $by ? $count / $by : null;
    }

    /**
     * Count the letters and digits, not spaces or punctuation.
     *
     * @param string $text
     * @return int
     */
    protected static function count_chars(string $text): int {
        return (int) preg_match_all('/[\p{L}\p{N}]/u', $text);
    }

    /**
     * Split a text into words, without the punctuation around them.
     *
     * @param string $text
     * @return string[]
     */
    protected static function get_words(string $text): array {
        $words = [];
        foreach (preg_split('/[\p{Z}\s—–]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) as $word) {
            // Trim punctuation from each end, but keep it inside a word,
            // e.g. "don't" or "well-known".
            $word = preg_replace('/^[^\p{L}\p{N}]+|[^\p{L}\p{N}]+$/u', '', $word);
            if ($word !== '') {
                $words[] = $word;
            }
        }
        return $words;
    }

    /**
     * Get the different words, ignoring case.
     *
     * @param string[] $words
     * @return string[]
     */
    protected static function get_unique_words(array $words): array {
        return array_unique(array_map(fn($word) => \core_text::strtolower($word), $words));
    }

    /**
     * Count the sentences. A sentence ends with . ! ? or 。！？, but not a
     * full stop in a number such as 3.5.
     *
     * @param string $text
     * @return int
     */
    protected static function count_sentences(string $text): int {
        $count = 0;
        foreach (preg_split('/[.!?。！？]+(?![0-9])/u', $text) as $sentence) {
            if (preg_match('/[\p{L}\p{N}]/u', $sentence)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Count the paragraphs: blocks of text separated by line breaks.
     *
     * @param string $text
     * @return int
     */
    protected static function count_paragraphs(string $text): int {
        $count = 0;
        foreach (preg_split('/\R+/u', $text) as $paragraph) {
            if (preg_match('/[\p{L}\p{N}]/u', $paragraph)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Count the words with three or more syllables.
     *
     * @param string[] $words
     * @return int
     */
    protected static function count_long_words(array $words): int {
        $count = 0;
        foreach ($words as $word) {
            if (self::count_syllables($word) >= 3) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Estimate the number of syllables in an English word, from its groups
     * of vowels. Like qtype_essayautograde's syllable counter, this is a
     * good estimate, not an exact count.
     *
     * @param string $word
     * @return int
     */
    public static function count_syllables(string $word): int {
        $word = preg_replace('/[^a-z]/', '', strtolower($word));
        if ($word === '') {
            return 0;
        }
        if (strlen($word) <= 3) {
            return 1;
        }
        $count = preg_match_all('/[aeiouy]+/', $word);
        // A final "e" is usually silent, as in "make", but not in "-le"
        // after a consonant, as in "table", or in "-ee", as in "agree".
        if (preg_match('/[^aeiouy]e$/', $word) && !preg_match('/[^aeiouy]le$/', $word)) {
            $count--;
        }
        // "-ed" is usually silent too, as in "jumped", but not after t or d,
        // as in "wanted".
        if (preg_match('/[^aeiouytd]ed$/', $word)) {
            $count--;
        }
        return max(1, $count);
    }
}
