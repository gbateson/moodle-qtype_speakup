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
    #[\Override]
    public static function setUpBeforeClass(): void {
        global $CFG;
        parent::setUpBeforeClass();
        require_once($CFG->dirroot . '/question/type/speakup/tests/helper.php');
    }

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

    public function test_seeded_on_install(): void {
        global $DB;
        $this->resetAfterTest();
        $syscontext = \context_system::instance();
        $records = $DB->get_records(preset::TABLE, ['contextid' => $syscontext->id], 'sortorder ASC');
        $this->assertSame(
            ['instant-response', 'video-monologue', 'handwriting-sample'],
            array_values(array_column($records, 'shortname'))
        );

        $preset = new preset(reset($records));
        $this->assertSame((int) get_admin()->id, $preset->userid);
        $this->assertSame('Instant spoken response', $preset->name);

        // Every setting is given, with the question form's defaults for
        // those that db/presets.php leaves out.
        $fields = array_merge(array_diff(preset::get_setting_fields(), ['aiproviderid']), ['aiprovider']);
        sort($fields);
        $keys = array_keys($preset->settings);
        sort($keys);
        $this->assertSame($fields, $keys);
        $this->assertSame(1, $preset->settings['autorecord']);
        $this->assertSame(30, $preset->settings['timelimitinseconds']);
        $this->assertSame('auto', $preset->settings['gradingmethod']);
        $this->assertSame('en-US', $preset->settings['sttlanguage']);
        $this->assertSame('', $preset->settings['aiprovider']);
        $this->assertSame('words', $preset->items[0]['itemtype']);
    }

    public function test_seed_only_once(): void {
        global $DB;
        $this->resetAfterTest();
        $syscontext = \context_system::instance();

        // A seed preset that was deleted is not added again, and one that
        // was changed is not changed back.
        preset::find('instant-response', $syscontext, 0)->delete();
        $video = preset::find('video-monologue', $syscontext, 0);
        $video->name = 'Talk to the camera';
        $video->save();
        preset::seed();
        $this->assertNull(preset::find('instant-response', $syscontext, 0));
        $this->assertSame('Talk to the camera', preset::find('video-monologue', $syscontext, 0)->name);
        $this->assertSame(2, $DB->count_records(preset::TABLE));

        // A seed preset that was not added before (e.g. one added to
        // db/presets.php in a later version) is added.
        set_config(preset::SEEDED_CONFIG, 'instant-response,video-monologue', 'qtype_speakup');
        preset::seed();
        $this->assertSame(2, $DB->count_records(preset::TABLE));
        $DB->delete_records(preset::TABLE, ['shortname' => 'handwriting-sample']);
        set_config(preset::SEEDED_CONFIG, 'instant-response,video-monologue', 'qtype_speakup');
        preset::seed();
        $this->assertNotNull(preset::find('handwriting-sample', $syscontext, 0));
        $this->assertSame(
            'instant-response,video-monologue,handwriting-sample',
            get_config('qtype_speakup', preset::SEEDED_CONFIG)
        );
    }

    /**
     * Make and save a preset.
     *
     * @param \context $context
     * @param string $shortname
     * @param int $userid
     * @param array $settings
     * @return preset
     */
    protected function make_preset(\context $context, string $shortname, int $userid = 0, array $settings = []): preset {
        $preset = new preset();
        $preset->contextid = $context->id;
        $preset->userid = $userid;
        $preset->shortname = $shortname;
        $preset->name = ucfirst($shortname);
        $preset->settings = $settings;
        $preset->save();
        return $preset;
    }

    public function test_get_visible_and_find(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $user = $generator->create_user();
        $other = $generator->create_user();
        $category = $generator->create_category();
        $course = $generator->create_course(['category' => $category->id]);
        $coursecontext = \context_course::instance($course->id);
        $catcontext = \context_coursecat::instance($category->id);
        $syscontext = \context_system::instance();

        $site = preset::find('instant-response', $syscontext, 0);
        $mine = $this->make_preset(\context_user::instance($user->id), 'mine', $user->id);
        $theirs = $this->make_preset(\context_user::instance($other->id), 'theirs', $other->id);
        $incourse = $this->make_preset($coursecontext, 'instant-response', $other->id, ['timelimitinseconds' => 10]);
        $incategory = $this->make_preset($catcontext, 'incategory', $other->id);
        $othercourse = $this->make_preset(\context_course::instance($generator->create_course()->id), 'elsewhere');

        // The user's own first, then the nearest context, then the site's in their sort order.
        $visible = preset::get_visible($coursecontext, $user->id);
        $this->assertSame(
            [$mine->id, $incourse->id, $incategory->id, $site->id],
            array_slice(array_keys($visible), 0, 4)
        );
        $this->assertCount(6, $visible);
        $this->assertArrayNotHasKey($theirs->id, $visible);
        $this->assertArrayNotHasKey($othercourse->id, $visible);

        // The nearest preset with a shortname wins.
        $this->assertSame($incourse->id, preset::find('instant-response', $coursecontext, $user->id)->id);
        $this->assertSame($site->id, preset::find(' instant-response ', $catcontext, $user->id)->id);
        $this->assertNull(preset::find('mine', $coursecontext, $other->id));
        $this->assertNull(preset::find('nosuchpreset', $coursecontext, $user->id));
    }

    public function test_from_question_and_apply_to(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $provider = \qtype_speakup_test_helper::create_provider(['name' => 'Marker']);
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category();
        $question = $generator->create_question('speakup', 'prompt', [
            'category' => $category->id,
            'aiassessment' => 1,
            'aiproviderid' => $provider->id,
        ]);

        // The preset keeps the settings, target items and target phrases,
        // but not the transcript of the audio prompt; the provider by name.
        $preset = preset::from_question($question->id);
        $this->assertArrayNotHasKey('prompttranscript', $preset->settings);
        $this->assertArrayNotHasKey('aiproviderid', $preset->settings);
        $this->assertSame('Marker', $preset->settings['aiprovider']);
        $this->assertCount(1, $preset->items);
        $this->assertCount(3, $preset->phrases);
        $this->assertSame('guitar', $preset->phrases[0]['phrasetext']);

        // It is the same after saving and loading.
        $preset->contextid = \context_system::instance()->id;
        $preset->shortname = 'guitar';
        $preset->name = 'Guitar';
        $preset->save();
        $loaded = preset::get($preset->id);
        $this->assertEquals($preset->settings, $loaded->settings);
        $this->assertEquals($preset->items, $loaded->items);
        $this->assertEquals($preset->phrases, $loaded->phrases);

        // A new question takes the settings, with the provider's id on this site.
        $new = (object) ['name' => 'New'];
        $loaded->apply_to($new, \context::instance_by_id($category->contextid));
        $this->assertEquals($provider->id, $new->aiproviderid);
        $this->assertEquals(1, $new->autorecord);
        $this->assertEquals(2, $new->maxpromptplays);
        $this->assertObjectNotHasProperty('prompttranscript', $new);
        $this->assertSame(['words'], $new->itemtype);
        $this->assertCount(3, $new->phrasematch);

        // A provider that is not on this site leaves the choice to the AI bridge.
        $loaded->settings['aiprovider'] = 'Gone';
        $new = (object) [];
        $loaded->apply_to($new);
        $this->assertNull($new->aiproviderid);
    }

    public function test_can_save_in_and_can_edit(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $teacher = $generator->create_user();
        $student = $generator->create_user();
        $course = $generator->create_course();
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');
        $generator->enrol_user($student->id, $course->id, 'student');
        $coursecontext = \context_course::instance($course->id);
        $syscontext = \context_system::instance();
        $adminid = (int) get_admin()->id;

        $this->assertTrue(preset::can_save_in(\context_user::instance($teacher->id), $teacher->id));
        $this->assertFalse(preset::can_save_in(\context_user::instance($teacher->id), $student->id));
        $this->assertTrue(preset::can_save_in($coursecontext, $teacher->id));
        $this->assertFalse(preset::can_save_in($coursecontext, $student->id));
        $this->assertFalse(preset::can_save_in($syscontext, $teacher->id));
        $this->assertTrue(preset::can_save_in($syscontext, $adminid));
        $this->assertFalse(preset::can_save_in($coursecontext, 0));

        // Only those who could save a preset where it is can change it.
        $shared = $this->make_preset($coursecontext, 'shared', $student->id);
        $this->assertTrue($shared->can_edit($teacher->id));
        $this->assertFalse($shared->can_edit($student->id));
        $site = preset::find('video-monologue', $syscontext, 0);
        $this->assertFalse($site->can_edit($teacher->id));
        $this->assertTrue($site->can_edit($adminid));
    }

    public function test_display_name(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['firstname' => 'émile', 'lastname' => 'Zola']);
        $preset = $this->make_preset(\context_user::instance($user->id), 'novel', $user->id);
        $this->assertSame('ÉZ', $preset->get_owner_initials());
        $this->assertSame('Novel (ÉZ)', $preset->get_display_name());

        $preset->userid = 0;
        $this->assertSame('', $preset->get_owner_initials());
        $this->assertSame('Novel', $preset->get_display_name());
    }

    public function test_rename_settings(): void {
        $this->resetAfterTest();
        $syscontext = \context_system::instance();
        $old = $this->make_preset($syscontext, 'old', 0, ['allowpausing' => 1, 'autorecord' => 0]);
        $both = $this->make_preset($syscontext, 'both', 0, ['allowpausing' => 1, 'responsepause' => 0]);

        preset::rename_settings(['allowpause' => 'responsepause', 'allowpausing' => 'responsepause']);
        $this->assertSame(['autorecord' => 0, 'responsepause' => 1], preset::get($old->id)->settings);
        // A setting that already has its new name keeps its value.
        $this->assertSame(['responsepause' => 0], preset::get($both->id)->settings);
    }

    public function test_delete_user(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $private = $this->make_preset(\context_user::instance($user->id), 'private', $user->id);
        $shared = $this->make_preset(\context_course::instance($course->id), 'shared', $user->id);

        preset::delete_user($user->id);
        $this->assertNull(preset::get($private->id));
        $this->assertSame(0, preset::get($shared->id)->userid);
    }

    public function test_is_valid_shortname(): void {
        $this->assertTrue(preset::is_valid_shortname('instant-response'));
        $this->assertTrue(preset::is_valid_shortname(' Video_2 '));
        $this->assertFalse(preset::is_valid_shortname(''));
        $this->assertFalse(preset::is_valid_shortname('-dash-first'));
        $this->assertFalse(preset::is_valid_shortname('two words'));
        $this->assertFalse(preset::is_valid_shortname(str_repeat('a', 101)));
        $this->assertSame('video_2', preset::clean_shortname(' Video_2 '));
    }

    public function test_get_save_contexts(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $teacher = $generator->create_user();
        $category = $generator->create_category(['name' => 'Languages']);
        $course = $generator->create_course(['category' => $category->id, 'fullname' => 'French 1']);
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');
        $coursecontext = \context_course::instance($course->id);
        $usercontext = \context_user::instance($teacher->id);

        $this->assertSame([
            $usercontext->id => 'Only me',
            $coursecontext->id => 'Teachers in the course: French 1',
        ], preset::get_save_contexts($coursecontext, $teacher->id));

        $admin = get_admin();
        $menu = preset::get_save_contexts($coursecontext, $admin->id);
        $this->assertSame([
            \context_user::instance($admin->id)->id => 'Only me',
            $coursecontext->id => 'Teachers in the course: French 1',
            \context_coursecat::instance($category->id)->id => 'Teachers in the category: Languages',
            \context_system::instance()->id => 'Everyone on this site',
        ], $menu);
    }

    /**
     * Save a SpeakUp question, as from the question form.
     *
     * @param int $categoryid
     * @param array $data fields of the form that differ from the "prompt" test question's.
     * @return stdClass the question.
     */
    protected function save_question(int $categoryid, array $data): \stdClass {
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        return $generator->create_question('speakup', 'prompt', array_merge(['category' => $categoryid], $data));
    }

    public function test_save_from_question(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $teacher = $generator->create_user(['firstname' => 'Tess', 'lastname' => 'Teacher']);
        $course = $generator->create_course();
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');
        $coursecontext = \context_course::instance($course->id);
        $usercontext = \context_user::instance($teacher->id);
        $category = $generator->get_plugin_generator('core_question')->create_question_category([
            'contextid' => $coursecontext->id,
        ]);
        $this->setUser($teacher);
        $count = $DB->count_records(preset::TABLE);

        // A question saved without asking for a preset saves none.
        $this->save_question($category->id, []);
        $this->assertSame($count, $DB->count_records(preset::TABLE));

        // A preset for the teacher alone.
        $question = $this->save_question($category->id, [
            'savepreset' => 1,
            'presetname' => 'Strings',
            'presetshortname' => 'Strings',
            'presetdescription' => 'Talk about a string instrument.',
            'presetcontextid' => $usercontext->id,
            'timelimitinseconds' => 45,
        ]);
        $preset = preset::find('strings', $coursecontext, $teacher->id);
        $this->assertNotNull($preset);
        $this->assertSame($usercontext->id, $preset->contextid);
        $this->assertSame((int) $teacher->id, $preset->userid);
        $this->assertSame('strings', $preset->shortname);
        $this->assertSame('Strings (TT)', $preset->get_display_name());
        $this->assertSame('Talk about a string instrument.', $preset->description);
        $this->assertEquals(45, $preset->settings['timelimitinseconds']);
        $this->assertEquals(2, $preset->settings['maxpromptplays']);
        $this->assertArrayNotHasKey('prompttranscript', $preset->settings);
        $this->assertCount(1, $preset->items);
        $this->assertCount(3, $preset->phrases);
        $this->assertEquals(preset::from_question($question->id), (function () use ($preset) {
            $copy = new preset();
            $copy->settings = $preset->settings;
            $copy->items = $preset->items;
            $copy->phrases = $preset->phrases;
            return $copy;
        })());

        // The same short name again is refused, unless it replaces the preset.
        $data = [
            'savepreset' => 1,
            'presetname' => 'Strings, short',
            'presetshortname' => 'strings',
            'presetcontextid' => $usercontext->id,
            'timelimitinseconds' => 20,
        ];
        try {
            $this->save_question($category->id, $data);
            $this->fail('A preset with the same short name should not be saved.');
        } catch (\moodle_exception $e) {
            $this->assertSame('err_presetexists', $e->errorcode);
        }
        $this->save_question($category->id, $data + ['presetreplace' => 1]);
        $replaced = preset::find('strings', $coursecontext, $teacher->id);
        $this->assertSame($preset->id, $replaced->id);
        $this->assertSame('Strings, short', $replaced->name);
        $this->assertEquals(20, $replaced->settings['timelimitinseconds']);

        // Shared in the course, where the teacher can share presets.
        $this->save_question($category->id, [
            'savepreset' => 1,
            'presetname' => 'Strings for all',
            'presetshortname' => 'strings',
            'presetcontextid' => $coursecontext->id,
        ]);
        $this->assertTrue($DB->record_exists(preset::TABLE, ['contextid' => $coursecontext->id, 'shortname' => 'strings']));

        // Not on the whole site, where they cannot.
        try {
            $this->save_question($category->id, [
                'savepreset' => 1,
                'presetname' => 'Strings for everyone',
                'presetshortname' => 'strings',
                'presetcontextid' => \context_system::instance()->id,
            ]);
            $this->fail('The teacher should not be able to share a preset with the whole site.');
        } catch (\moodle_exception $e) {
            $this->assertSame('nopermissions', $e->errorcode);
        }
    }

    public function test_move(): void {
        $this->resetAfterTest();
        $syscontext = \context_system::instance();
        $names = fn() => array_values(array_map(
            fn($preset) => $preset->shortname,
            array_filter(preset::get_visible($syscontext, 0), fn($preset) => $preset->contextid == $syscontext->id)
        ));
        $this->assertSame(['instant-response', 'video-monologue', 'handwriting-sample'], $names());

        $handwriting = preset::find('handwriting-sample', $syscontext, 0);
        $this->assertTrue($handwriting->move(-1));
        $this->assertSame(['instant-response', 'handwriting-sample', 'video-monologue'], $names());
        $this->assertTrue($handwriting->move(-1));
        $this->assertSame(['handwriting-sample', 'instant-response', 'video-monologue'], $names());
        $this->assertFalse($handwriting->move(-1));
        $this->assertTrue($handwriting->move(1));
        $this->assertSame(['instant-response', 'handwriting-sample', 'video-monologue'], $names());
        $this->assertFalse(preset::find('video-monologue', $syscontext, 0)->move(1));
    }

    public function test_get_users_label(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $user = $generator->create_user();
        $category = $generator->create_category(['name' => 'Languages']);
        $course = $generator->create_course(['fullname' => 'French 1', 'category' => $category->id]);
        $speakup = $generator->get_plugin_generator('qtype_speakup');
        $labels = [];
        foreach (
            [
            \context_user::instance($user->id),
            \context_course::instance($course->id),
            \context_coursecat::instance($category->id),
            \context_system::instance(),
            ] as $i => $context
        ) {
            $labels[] = $speakup->create_preset([
                'name' => 'Preset ' . $i,
                'shortname' => 'preset' . $i,
                'contextid' => $context->id,
            ])->get_users_label();
        }
        $this->assertSame([
            'Only me',
            'Teachers in the course: French 1',
            'Teachers in the category: Languages',
            'Everyone on this site',
        ], $labels);
    }

    /**
     * When a user, a course or a category is deleted, so are its presets.
     * A deleted user's shared presets are kept, without an owner.
     *
     * @covers \qtype_speakup\observer
     */
    public function test_deleted_owner_or_context(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $speakup = $generator->get_plugin_generator('qtype_speakup');
        $user = $generator->create_user();
        $category = $generator->create_category();
        $course = $generator->create_course(['category' => $category->id]);
        $private = $speakup->create_preset(['name' => 'Private', 'shortname' => 'private',
            'contextid' => \context_user::instance($user->id)->id, 'userid' => $user->id]);
        $incourse = $speakup->create_preset(['name' => 'Course', 'shortname' => 'course',
            'contextid' => \context_course::instance($course->id)->id, 'userid' => $user->id]);
        $incategory = $speakup->create_preset(['name' => 'Category', 'shortname' => 'category',
            'contextid' => \context_coursecat::instance($category->id)->id, 'userid' => $user->id]);

        delete_user($user);
        $this->assertNull(preset::get($private->id));
        $this->assertSame(0, preset::get($incourse->id)->userid);

        delete_course($course, false);
        $this->assertNull(preset::get($incourse->id));
        $this->assertNotNull(preset::get($incategory->id));

        \core_course_category::get($category->id)->delete_full(false);
        $this->assertNull(preset::get($incategory->id));
    }
}
