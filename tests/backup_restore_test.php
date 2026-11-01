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

use backup;
use backup_controller;
use question_bank;
use restore_controller;
use restore_dbops;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/type/speakup/tests/helper.php');

/**
 * Tests for backing up and restoring SpeakUp questions.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \backup_qtype_speakup_plugin
 * @covers    \restore_qtype_speakup_plugin
 */
final class backup_restore_test extends \advanced_testcase {

    /**
     * Back up a course.
     *
     * @param int $courseid
     * @return string the backup id.
     */
    protected function backup(int $courseid): string {
        global $USER;
        $bc = new backup_controller(backup::TYPE_1COURSE, $courseid, backup::FORMAT_MOODLE, backup::INTERACTIVE_NO,
                backup::MODE_IMPORT, $USER->id);
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();
        return $backupid;
    }

    /**
     * Restore a backup into a course.
     *
     * @param string $backupid
     * @param int $courseid
     * @param int $target one of the backup::TARGET_ constants.
     */
    protected function restore(string $backupid, int $courseid, int $target): void {
        global $USER;
        $rc = new restore_controller($backupid, $courseid, backup::INTERACTIVE_NO, backup::MODE_IMPORT, $USER->id, $target);
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();
    }

    /**
     * Get the SpeakUp questions in a course's question bank.
     *
     * @param int $courseid
     * @return \stdClass[] question data.
     */
    protected function get_course_questions(int $courseid): array {
        global $DB;
        $ids = $DB->get_fieldset_sql("
                SELECT q.id
                  FROM {question} q
                  JOIN {question_versions} qv ON qv.questionid = q.id
                  JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                  JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                 WHERE qc.contextid = ? AND q.qtype = 'speakup'
              ORDER BY q.id", [\context_course::instance($courseid)->id]);
        return array_map(fn($id) => question_bank::load_question_data($id), $ids);
    }

    /**
     * A restored question has the same settings, phrases, items, audio
     * prompt and provider (matched by name), and the provider's
     * credentials are not in the backup.
     */
    public function test_backup_and_restore(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->setAdminUser();
        $CFG->keeptempdirectoriesonbackup = true;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $provider = \qtype_speakup_test_helper::create_provider(['name' => 'Gemini', 'apikey' => 'very-secret-key']);
        /** @var \core_question_generator $qgenerator */
        $qgenerator = $generator->get_plugin_generator('core_question');
        $category = $qgenerator->create_question_category(['contextid' => \context_course::instance($course->id)->id]);
        $question = $qgenerator->create_question('speakup', 'prompt', [
            'category' => $category->id,
            'aiassessment' => 1,
            'aiproviderid' => $provider->id,
            'audiopromptvoice' => 'Kore',
        ]);
        $original = question_bank::load_question_data($question->id);
        get_file_storage()->create_file_from_string(['contextid' => $original->contextid, 'component' => 'qtype_speakup',
                'filearea' => 'audioprompt', 'itemid' => $original->id, 'filepath' => '/', 'filename' => 'audioprompt.mp3'],
                'ID3 prompt audio');

        $backupid = $this->backup($course->id);
        $xml = file_get_contents($CFG->tempdir . '/backup/' . $backupid . '/questions.xml');
        $this->assertStringContainsString('<aiprovidername>Gemini</aiprovidername>', $xml);
        $this->assertStringNotContainsString('very-secret-key', $xml);
        $this->assertStringNotContainsString('api.example.com', $xml);

        $newcourseid = restore_dbops::create_new_course('Restored', 'RESTORED', $course->category);
        $this->restore($backupid, $newcourseid, backup::TARGET_NEW_COURSE);

        $restored = $this->get_course_questions($newcourseid);
        $this->assertCount(1, $restored);
        $restored = $restored[0];
        foreach (['gradingmethod', 'audioprompttranscript', 'audiopromptvoice', 'transcriptpenalty', 'recordafterprompt',
                'maxpromptplays', 'maxresponses', 'aiassessment', 'aiweighting'] as $field) {
            $this->assertEquals($original->options->$field, $restored->options->$field, "Field $field");
        }
        $this->assertEquals($provider->id, $restored->options->aiproviderid);
        $this->assertSame(array_column($original->options->expectedanswers, 'answertext'),
                array_column($restored->options->expectedanswers, 'answertext'));
        $this->assertSame(array_column($original->options->targetitems, 'itemtype'),
                array_column($restored->options->targetitems, 'itemtype'));

        $files = get_file_storage()->get_area_files($restored->contextid, 'qtype_speakup', 'audioprompt', $restored->id,
                'filename', false);
        $this->assertSame(['audioprompt.mp3'], array_values(array_map(fn($f) => $f->get_filename(), $files)));
    }

    /**
     * If no provider has the same name, the restored question has none.
     */
    public function test_restore_unknown_provider(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $provider = \qtype_speakup_test_helper::create_provider(['name' => 'Gemini']);
        /** @var \core_question_generator $qgenerator */
        $qgenerator = $generator->get_plugin_generator('core_question');
        $category = $qgenerator->create_question_category(['contextid' => \context_course::instance($course->id)->id]);
        $qgenerator->create_question('speakup', 'basic', [
            'category' => $category->id, 'aiassessment' => 1, 'aiproviderid' => $provider->id,
        ]);

        $backupid = $this->backup($course->id);
        $DB->set_field('qtype_speakup_ai_providers', 'name', 'Renamed', ['id' => $provider->id]);

        $newcourseid = restore_dbops::create_new_course('Restored', 'RESTORED', $course->category);
        $this->restore($backupid, $newcourseid, backup::TARGET_NEW_COURSE);
        $restored = $this->get_course_questions($newcourseid);
        $this->assertCount(1, $restored);
        $this->assertNull($restored[0]->options->aiproviderid);
    }

    /**
     * Restoring into the same course reuses the existing questions,
     * rather than duplicating them.
     */
    public function test_restore_same_course_no_duplicates(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        /** @var \core_question_generator $qgenerator */
        $qgenerator = $generator->get_plugin_generator('core_question');
        $category = $qgenerator->create_question_category(['contextid' => \context_course::instance($course->id)->id]);
        $qgenerator->create_question('speakup', 'basic', ['category' => $category->id]);
        $qgenerator->create_question('speakup', 'prompt', ['category' => $category->id]);
        $this->assertCount(2, $this->get_course_questions($course->id));

        $backupid = $this->backup($course->id);
        $this->restore($backupid, $course->id, backup::TARGET_CURRENT_ADDING);
        $this->assertCount(2, $this->get_course_questions($course->id));
    }
}
