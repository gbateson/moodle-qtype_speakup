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

use qtype_speakup\task\assess_response;
use qtype_speakup\task\generate_audio_prompt;
use qtype_speakup\task\reduce_response_files;

/**
 * Remove queued background tasks that are no longer needed, e.g. because
 * the attempt or question they are for was deleted (see observer).
 *
 * Not every deletion fires an event (e.g. previews, or deleting all of a
 * quiz's attempts), so the tasks also check, when they run, that what they
 * are for still exists.
 *
 * Tasks that are already running are left alone.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class task_cleanup {

    /**
     * Remove the tasks that assess the responses in a question usage, or make their files smaller.
     *
     * @param int $usageid
     * @return int how many tasks were removed.
     */
    public static function remove_assessments(int $usageid): int {
        $match = function(\stdClass $data) use ($usageid): bool {
            return (int) ($data->usageid ?? 0) === $usageid;
        };
        return self::remove(assess_response::class, $match) + self::remove(reduce_response_files::class, $match);
    }

    /**
     * Remove the tasks that generate a question's audio prompt.
     *
     * @param int $questionid
     * @return int how many tasks were removed.
     */
    public static function remove_audio_prompts(int $questionid): int {
        return self::remove(generate_audio_prompt::class, function(\stdClass $data) use ($questionid): bool {
            return (int) ($data->questionid ?? 0) === $questionid;
        });
    }

    /**
     * Can a response still be loaded, to be assessed or have its files made
     * smaller? Its question usage, its slot, and the questions in the usage
     * must all still exist.
     *
     * Moodle core should delete a question's previews with the question,
     * but in Moodle 4.5 it does not, so a preview can outlive its question.
     * Such a usage cannot be loaded.
     *
     * @param int $usageid
     * @param int $slot
     * @return bool
     */
    public static function response_exists(int $usageid, int $slot): bool {
        global $DB;
        $exists = $DB->record_exists_sql('
                SELECT 1
                  FROM {question_attempts} qa
                  JOIN {question} q ON q.id = qa.questionid
                 WHERE qa.questionusageid = ? AND qa.slot = ?', [$usageid, $slot]);
        $missing = $DB->record_exists_sql('
                SELECT 1
                  FROM {question_attempts} qa
             LEFT JOIN {question} q ON q.id = qa.questionid
                 WHERE qa.questionusageid = ? AND q.id IS NULL', [$usageid]);
        return $exists && !$missing;
    }

    /**
     * Remove the tasks whose question usage or question no longer exists,
     * e.g. after many attempts or questions were deleted at once.
     *
     * @return int how many tasks were removed.
     */
    public static function remove_orphans(): int {
        global $DB;
        $nousage = function(\stdClass $data): bool {
            return !self::response_exists((int) ($data->usageid ?? 0), (int) ($data->slot ?? 0));
        };
        $count = self::remove(assess_response::class, $nousage) + self::remove(reduce_response_files::class, $nousage);
        $count += self::remove(generate_audio_prompt::class, function(\stdClass $data) use ($DB): bool {
            return !$DB->record_exists('question', ['id' => (int) ($data->questionid ?? 0)]);
        });
        return $count;
    }

    /**
     * Remove the queued tasks of a class that match a test.
     *
     * @param string $classname
     * @param callable $match given a task's custom data, returns true to remove the task.
     * @return int how many tasks were removed.
     */
    protected static function remove(string $classname, callable $match): int {
        global $DB;
        $count = 0;
        // Moodle has no function to remove one queued task, so remove its record.
        foreach (\core\task\manager::get_adhoc_tasks($classname, false, true) as $task) {
            $data = $task->get_custom_data();
            if (is_object($data) && $match($data)) {
                $DB->delete_records('task_adhoc', ['id' => $task->get_id(), 'timestarted' => null]);
                $count++;
            }
        }
        return $count;
    }
}
