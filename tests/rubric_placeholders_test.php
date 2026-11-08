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
final class rubric_placeholders_test extends \advanced_testcase {

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

    /**
     * Create a Description question, as a shared rubric.
     *
     * @param int $categoryid
     * @param string $idnumber
     * @param string $rubric the text of its rubric element.
     * @return \stdClass the question.
     */
    protected function create_rubric_question(int $categoryid, string $idnumber, string $rubric): \stdClass {
        /** @var \core_question_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        return $generator->create_question('description', 'info', [
            'category' => $categoryid,
            'idnumber' => $idnumber,
            'questiontext' => ['text' => '<p>Read this.</p><div class="rubric">' . $rubric . '</div>', 'format' => FORMAT_HTML],
        ]);
    }

    /**
     * Another question's text is found by its ID number, in the same
     * category, or the nearest category above it.
     */
    public function test_other_question(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        /** @var \core_question_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $top = $generator->create_question_category();
        $middle = $generator->create_question_category(['parent' => $top->id, 'contextid' => $top->contextid]);
        $bottom = $generator->create_question_category(['parent' => $middle->id, 'contextid' => $top->contextid]);
        $other = $generator->create_question_category();

        $this->create_rubric_question($top->id, 'shared', 'Top rubric.');
        $this->create_rubric_question($bottom->id, 'local', 'Bottom rubric.');
        $this->create_rubric_question($other->id, 'elsewhere', 'Other rubric.');

        $rubric = 'Use: {{shared/questiontext.rubric}}';
        $this->assertSame('Use: Top rubric.', rubric_placeholders::expand($rubric, self::QUESTIONTEXT, $bottom->id));
        $this->assertSame('Use: Top rubric.', rubric_placeholders::expand($rubric, self::QUESTIONTEXT, $top->id));
        $this->assertSame('Bottom rubric.',
                rubric_placeholders::expand('{{ local / questiontext.rubric }}', self::QUESTIONTEXT, $bottom->id));
        $this->assertStringContainsString('Read this.',
                rubric_placeholders::expand('{{local/questiontext}}', self::QUESTIONTEXT, $bottom->id));
        // The question's own text is still used without an ID number.
        $this->assertSame('Score fluency.',
                rubric_placeholders::expand('{{questiontext.rubric}}', self::QUESTIONTEXT, $bottom->id));

        // A nearer question with the same ID number is used instead.
        $this->create_rubric_question($middle->id, 'shared', 'Middle rubric.');
        $this->assertSame('Use: Middle rubric.', rubric_placeholders::expand($rubric, self::QUESTIONTEXT, $bottom->id));
        $this->assertSame('Use: Top rubric.', rubric_placeholders::expand($rubric, self::QUESTIONTEXT, $top->id));

        // Questions below, or in other category trees, are not found.
        $this->assertSame([], rubric_placeholders::get_missing_questions($rubric, $bottom->id));
        $this->assertSame(['local', 'elsewhere'], rubric_placeholders::get_missing_questions(
                '{{local/questiontext}} {{elsewhere/questiontext}} {{shared/questiontext.rubric:ja}}', $top->id));

        $this->expectException(\moodle_exception::class);
        rubric_placeholders::expand('{{elsewhere/questiontext.rubric}}', self::QUESTIONTEXT, $top->id);
    }

    /**
     * The latest "Ready" version of the other question is used.
     */
    public function test_other_question_versions(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        /** @var \core_question_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category();
        $question = $this->create_rubric_question($category->id, 'shared', 'First.');
        $rubric = '{{shared/questiontext.rubric}}';

        $generator->update_question($question, null,
                ['questiontext' => ['text' => '<div class="rubric">Second.</div>', 'format' => FORMAT_HTML]]);
        $this->assertSame('Second.', rubric_placeholders::expand($rubric, '', $category->id));

        $generator->update_question($question, null, [
            'questiontext' => ['text' => '<div class="rubric">Draft.</div>', 'format' => FORMAT_HTML],
            'status' => \core_question\local\bank\question_version_status::QUESTION_STATUS_DRAFT,
        ]);
        $this->assertSame('Second.', rubric_placeholders::expand($rubric, '', $category->id));
    }
}
