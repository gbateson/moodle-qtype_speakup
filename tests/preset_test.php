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
 * Tests for the settings that a new question can start from.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \qtype_speakup\preset
 */
final class preset_test extends \advanced_testcase {
    /** @var array the settings of a base. */
    const SETTINGS = [
        'mediatype' => 'video',
        'timelimitinseconds' => 180,
        'autorecord' => 1,
        'prompttranscript' => 'Not copied.',
        'nosuchfield' => 'Ignored.',
    ];

    /** @var array the target items of a base. */
    const ITEMS = [
        ['itemtype' => 'words', 'itemtarget' => 100, 'percent' => 50, 'multiplier' => 1],
    ];

    /** @var array the target phrases of a base. */
    const PHRASES = [
        [
            'phrasetext' => 'First AND Then', 'phraselabel' => null, 'percent' => 50,
            'divisor' => 1, 'fullmatch' => 0, 'casesensitive' => 0,
        ],
    ];

    public function test_get_setting_fields(): void {
        $fields = preset::get_setting_fields();
        $this->assertContains('mediatype', $fields);
        $this->assertContains('statstypes', $fields);
        $this->assertNotContains('prompttranscript', $fields);
        $this->assertNotContains('qtype_speakup_options', $fields);
    }

    public function test_apply_base_to_empty_question(): void {
        $question = (object) ['name' => 'Mine', 'prompttranscript' => 'My prompt.'];
        preset::apply_base($question, (object) self::SETTINGS, self::ITEMS, self::PHRASES);

        $this->assertSame('Mine', $question->name);
        $this->assertSame('My prompt.', $question->prompttranscript);
        $this->assertSame('video', $question->mediatype);
        $this->assertSame(180, $question->timelimitinseconds);
        $this->assertSame(1, $question->autorecord);
        $this->assertObjectNotHasProperty('nosuchfield', $question);

        $this->assertSame(['words'], $question->itemtype);
        $this->assertSame([100], $question->itemtarget);
        $this->assertSame([50], $question->itempercent);
        $this->assertSame([1], $question->itemmultiplier);

        $this->assertSame(['First AND Then'], $question->phrasematch);
        $this->assertSame([''], $question->phraselabel);
        $this->assertSame([50], $question->phrasepercent);
        $this->assertSame([1], $question->phrasedivisor);
        $this->assertSame([0], $question->phrasefullmatch);
        $this->assertSame([0], $question->phrasecasesensitive);
    }

    public function test_apply_base_keeps_own_settings(): void {
        $question = (object) [
            'mediatype' => 'audio',
            'timelimitinseconds' => 30,
            'itemtype' => ['wpm'],
            'phrasematch' => ['Hello'],
        ];
        preset::apply_base($question, self::SETTINGS, self::ITEMS, self::PHRASES, ['mediatype']);

        // A setting it gives itself is kept; one it does not give is replaced.
        $this->assertSame('audio', $question->mediatype);
        $this->assertSame(180, $question->timelimitinseconds);

        // Its own target items and phrases are kept.
        $this->assertSame(['wpm'], $question->itemtype);
        $this->assertObjectNotHasProperty('itemtarget', $question);
        $this->assertSame(['Hello'], $question->phrasematch);
        $this->assertObjectNotHasProperty('phraselabel', $question);
    }
}
