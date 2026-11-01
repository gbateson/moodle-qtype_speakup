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
        $this->assertContains('ai_provider', $names);
    }

    /**
     * Test finding the contexts with a user's data.
     */
    public function test_get_contexts_for_userid(): void {
        $this->resetAfterTest();
        [$user1, $user2, $context1, $context2] = $this->setup_responses();
        $this->assertEqualsCanonicalizing([$context1->id, $context2->id],
                provider::get_contexts_for_userid($user1->id)->get_contextids());
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
}
