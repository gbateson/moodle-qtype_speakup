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

namespace qtype_speakup\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use question_bank;
use question_engine;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/type/speakup/tests/helper.php');

/**
 * Tests for the privacy provider: the transcripts and AI feedback stored
 * for each response.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \qtype_speakup\privacy\provider
 */
final class provider_test extends \core_privacy\tests\provider_testcase {
    /**
     * Make a user attempt a question in a context, and store a result for it.
     *
     * @param \stdClass $user
     * @param \context $context
     * @param int $questionid
     * @param string $transcript
     * @return int the id of the stored result.
     */
    protected function make_response(\stdClass $user, \context $context, int $questionid, string $transcript): int {
        global $DB;
        $this->setUser($user);
        $quba = question_engine::make_questions_usage_by_activity('core_question_preview', $context);
        $quba->set_preferred_behaviour('deferredfeedback');
        $slot = $quba->add_question(question_bank::load_question($questionid), 1);
        $quba->start_all_questions();
        question_engine::save_questions_usage_by_activity($quba);
        $qaid = $quba->get_question_attempt($slot)->get_database_id();
        return $DB->insert_record('qtype_speakup_response', [
            'questionattemptid' => $qaid,
            'transcript' => $transcript,
            'transcriptstatus' => 'done',
            'aifeedback' => 'Good.',
            'aiscore' => 0.8,
            'aistatus' => 'done',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * Set up two users, each with a response in two courses.
     *
     * @return array [user1, user2, context1, context2]
     */
    protected function setup_responses(): array {
        $generator = $this->getDataGenerator();
        /** @var \core_question_generator $qgenerator */
        $qgenerator = $generator->get_plugin_generator('core_question');
        $category = $qgenerator->create_question_category();
        $questionid = $qgenerator->create_question('speakup', 'manual', ['category' => $category->id])->id;

        $user1 = $generator->create_user();
        $user2 = $generator->create_user();
        $context1 = \context_course::instance($generator->create_course()->id);
        $context2 = \context_course::instance($generator->create_course()->id);
        $this->make_response($user1, $context1, $questionid, 'User 1 in course 1');
        $this->make_response($user1, $context2, $questionid, 'User 1 in course 2');
        $this->make_response($user2, $context1, $questionid, 'User 2 in course 1');
        return [$user1, $user2, $context1, $context2];
    }

    /**
     * Test the metadata.
     */
    public function test_get_metadata(): void {
        $collection = provider::get_metadata(new collection('qtype_speakup'));
        $names = array_map(fn($item) => $item->get_name(), $collection->get_collection());
        $this->assertContains('qtype_speakup_response', $names);
        $this->assertContains('qtype_speakup_presets', $names);
        $this->assertContains('ai_provider', $names);
    }

    /**
     * Test finding the contexts with a user's data.
     */
    public function test_get_contexts_for_userid(): void {
        $this->resetAfterTest();
        [$user1, $user2, $context1, $context2] = $this->setup_responses();
        $this->assertEqualsCanonicalizing(
            [$context1->id, $context2->id],
            provider::get_contexts_for_userid($user1->id)->get_contextids()
        );
        $this->assertEquals([$context1->id], provider::get_contexts_for_userid($user2->id)->get_contextids());
    }

    /**
     * Test exporting a user's data.
     */
    public function test_export_user_data(): void {
        global $DB;
        $this->resetAfterTest();
        [$user1, , $context1] = $this->setup_responses();
        provider::export_user_data(new approved_contextlist($user1, 'qtype_speakup', [$context1->id]));

        $writer = writer::with_context($context1);
        $this->assertTrue($writer->has_any_data());
        foreach ($DB->get_records('qtype_speakup_response') as $record) {
            $data = $writer->get_data([get_string('pluginname', 'qtype_speakup'), $record->questionattemptid]);
            if ($record->transcript === 'User 1 in course 1') {
                $this->assertSame('User 1 in course 1', $data->transcript);
                $this->assertSame('Good.', $data->aifeedback);
            } else {
                // Other users' data, and other contexts, are not exported here.
                $this->assertEmpty($data);
            }
        }
    }

    /**
     * Test deleting one user's data.
     */
    public function test_delete_data_for_user(): void {
        global $DB;
        $this->resetAfterTest();
        [$user1, , $context1] = $this->setup_responses();
        provider::delete_data_for_user(new approved_contextlist($user1, 'qtype_speakup', [$context1->id]));
        $transcripts = $DB->get_fieldset_select('qtype_speakup_response', 'transcript', '1 = 1');
        $this->assertEqualsCanonicalizing(['User 1 in course 2', 'User 2 in course 1'], $transcripts);
    }

    /**
     * Test deleting everyone's data in a context.
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;
        $this->resetAfterTest();
        [, , $context1] = $this->setup_responses();
        provider::delete_data_for_all_users_in_context($context1);
        $transcripts = $DB->get_fieldset_select('qtype_speakup_response', 'transcript', '1 = 1');
        $this->assertSame(['User 1 in course 2'], array_values($transcripts));
    }

    /**
     * Test finding the users with data in a context.
     */
    public function test_get_users_in_context(): void {
        $this->resetAfterTest();
        [$user1, $user2, $context1, $context2] = $this->setup_responses();
        $userlist = new userlist($context1, 'qtype_speakup');
        provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing([$user1->id, $user2->id], $userlist->get_userids());
        $userlist = new userlist($context2, 'qtype_speakup');
        provider::get_users_in_context($userlist);
        $this->assertEquals([$user1->id], $userlist->get_userids());
    }

    /**
     * Test deleting some users' data in a context.
     */
    public function test_delete_data_for_users(): void {
        global $DB;
        $this->resetAfterTest();
        [$user1, , $context1] = $this->setup_responses();
        provider::delete_data_for_users(new approved_userlist($context1, 'qtype_speakup', [$user1->id]));
        $transcripts = $DB->get_fieldset_select('qtype_speakup_response', 'transcript', '1 = 1');
        $this->assertEqualsCanonicalizing(['User 1 in course 2', 'User 2 in course 1'], $transcripts);
    }

    /**
     * Set up two teachers, each with a preset of their own, and one shared
     * in a course.
     *
     * @return array [teacher1, teacher2, usercontext1, coursecontext, private1, shared1, shared2]
     */
    protected function setup_presets(): array {
        $generator = $this->getDataGenerator();
        $speakup = $generator->get_plugin_generator('qtype_speakup');
        $teacher1 = $generator->create_user();
        $teacher2 = $generator->create_user();
        $usercontext1 = \context_user::instance($teacher1->id);
        $coursecontext = \context_course::instance($generator->create_course()->id);
        $private1 = $speakup->create_preset(['name' => 'Mine', 'shortname' => 'mine',
            'contextid' => $usercontext1->id, 'userid' => $teacher1->id, 'timelimitinseconds' => 40]);
        $speakup->create_preset(['name' => 'Theirs', 'shortname' => 'theirs',
            'contextid' => \context_user::instance($teacher2->id)->id, 'userid' => $teacher2->id]);
        $shared1 = $speakup->create_preset(['name' => 'Shared', 'shortname' => 'shared',
            'contextid' => $coursecontext->id, 'userid' => $teacher1->id]);
        $shared2 = $speakup->create_preset(['name' => 'Also shared', 'shortname' => 'alsoshared',
            'contextid' => $coursecontext->id, 'userid' => $teacher2->id]);
        return [$teacher1, $teacher2, $usercontext1, $coursecontext, $private1, $shared1, $shared2];
    }

    /**
     * The presets a teacher owns are their data, where the presets are kept.
     */
    public function test_presets_contexts_and_users(): void {
        $this->resetAfterTest();
        [$teacher1, $teacher2, $usercontext1, $coursecontext] = $this->setup_presets();
        $this->assertEqualsCanonicalizing(
            [$usercontext1->id, $coursecontext->id],
            provider::get_contexts_for_userid($teacher1->id)->get_contextids()
        );

        $userlist = new userlist($coursecontext, 'qtype_speakup');
        provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing([$teacher1->id, $teacher2->id], $userlist->get_userids());

        // The site's presets, owned by the admin, are the admin's.
        $userlist = new userlist(\context_system::instance(), 'qtype_speakup');
        provider::get_users_in_context($userlist);
        $this->assertEquals([get_admin()->id], $userlist->get_userids());
    }

    /**
     * A teacher's presets are exported where they are kept.
     */
    public function test_export_presets(): void {
        $this->resetAfterTest();
        [$teacher1, , $usercontext1, $coursecontext] = $this->setup_presets();
        provider::export_user_data(new approved_contextlist($teacher1, 'qtype_speakup', [
            $usercontext1->id, $coursecontext->id,
        ]));
        $presets = get_string('presets', 'qtype_speakup');
        $data = writer::with_context($usercontext1)->get_data([get_string('pluginname', 'qtype_speakup'), $presets, 'mine']);
        $this->assertSame('Mine', $data->name);
        $this->assertEquals(40, $data->settings['timelimitinseconds']);
        $data = writer::with_context($coursecontext)->get_data([get_string('pluginname', 'qtype_speakup'), $presets, 'shared']);
        $this->assertSame('Shared', $data->name);
        $this->assertEmpty(writer::with_context($coursecontext)->get_data(
            [get_string('pluginname', 'qtype_speakup'), $presets, 'alsoshared']
        ));
    }

    /**
     * Deleting a teacher's data deletes their own presets, and keeps those
     * they shared, without an owner.
     */
    public function test_delete_presets_for_user(): void {
        $this->resetAfterTest();
        [$teacher1, $teacher2, $usercontext1, $coursecontext, $private1, $shared1, $shared2] = $this->setup_presets();
        provider::delete_data_for_user(new approved_contextlist($teacher1, 'qtype_speakup', [
            $usercontext1->id, $coursecontext->id,
        ]));
        $this->assertNull(\qtype_speakup\preset::get($private1->id));
        $this->assertSame(0, \qtype_speakup\preset::get($shared1->id)->userid);
        $this->assertSame((int) $teacher2->id, \qtype_speakup\preset::get($shared2->id)->userid);

        provider::delete_data_for_users(new approved_userlist($coursecontext, 'qtype_speakup', [$teacher2->id]));
        $this->assertSame(0, \qtype_speakup\preset::get($shared2->id)->userid);
    }

    /**
     * Deleting all the data in a context deletes the presets in a user
     * context, and keeps those in a course, without owners.
     */
    public function test_delete_presets_for_all_users_in_context(): void {
        $this->resetAfterTest();
        [, , $usercontext1, $coursecontext, $private1, $shared1, $shared2] = $this->setup_presets();
        provider::delete_data_for_all_users_in_context($usercontext1);
        $this->assertNull(\qtype_speakup\preset::get($private1->id));
        provider::delete_data_for_all_users_in_context($coursecontext);
        $this->assertSame(0, \qtype_speakup\preset::get($shared1->id)->userid);
        $this->assertSame(0, \qtype_speakup\preset::get($shared2->id)->userid);
    }
}
