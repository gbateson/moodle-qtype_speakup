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

namespace qtype_speakup\task;

use qtype_speakup\ai_provider;
use qtype_speakup\task_failure;
use qtype_speakup\tts;

/**
 * Generates a question's audio prompt from its transcript, using the speech
 * provider chosen in the site settings.
 *
 * It is queued when a question is saved or imported with a transcript but
 * no audio prompt. If, by the time it runs, the question has an audio
 * prompt, or its transcript has changed, it does nothing.
 *
 * If the speech provider fails for a reason that may go away (e.g. no
 * connection), the task fails, and Moodle tries it again later. Otherwise,
 * or after the last try, the person who saved the question is sent a message.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generate_audio_prompt extends \core\task\adhoc_task {

    #[\Override]
    public function get_name() {
        return get_string('taskgenerateaudioprompt', 'qtype_speakup');
    }

    /**
     * Queue this task for a question, if it needs an audio prompt and a
     * speech provider is set up.
     *
     * @param int $questionid
     * @param int $contextid the question's context.
     * @param string|null $transcript the audio prompt transcript.
     * @return bool true if the task was queued.
     */
    public static function queue_if_needed(int $questionid, int $contextid, ?string $transcript): bool {
        global $USER;
        $transcript = trim((string) $transcript);
        if ($transcript === '' || !ai_provider::get_tts_provider()) {
            return false;
        }
        if (self::has_audio_prompt($questionid, $contextid)) {
            return false;
        }
        $task = new self();
        $task->set_attempts_available(\qtype_speakup\task_failure::MAX_TRIES);
        $task->set_custom_data((object) [
            'questionid' => $questionid,
            'contextid' => $contextid,
            'transcripthash' => sha1($transcript),
        ]);
        if (!empty($USER->id)) {
            $task->set_userid($USER->id);
        }
        return (bool) \core\task\manager::queue_adhoc_task($task, true);
    }

    /**
     * Is an audio prompt waiting to be generated for this question?
     *
     * @param int $questionid
     * @return bool
     */
    public static function is_pending(int $questionid): bool {
        foreach (\core\task\manager::get_adhoc_tasks(self::class) as $task) {
            if ((int) ($task->get_custom_data()->questionid ?? 0) === $questionid) {
                return true;
            }
        }
        return false;
    }

    /**
     * Does this question have an audio prompt file?
     *
     * @param int $questionid
     * @param int $contextid
     * @return bool
     */
    public static function has_audio_prompt(int $questionid, int $contextid): bool {
        return !get_file_storage()->is_area_empty($contextid, 'qtype_speakup', 'audioprompt', $questionid, false);
    }

    #[\Override]
    public function execute() {
        global $DB;

        $data = $this->get_custom_data();
        $questionid = (int) $data->questionid;
        $contextid = (int) $data->contextid;

        $options = $DB->get_record('qtype_speakup_options', ['questionid' => $questionid]);
        if (!$options) {
            mtrace("Question $questionid no longer exists.");
            return;
        }
        $transcript = trim((string) $options->audioprompttranscript);
        if ($transcript === '' || sha1($transcript) !== $data->transcripthash) {
            mtrace("The transcript of question $questionid has changed, so its audio prompt was not generated.");
            return;
        }
        if (self::has_audio_prompt($questionid, $contextid)) {
            mtrace("Question $questionid already has an audio prompt.");
            return;
        }
        $provider = ai_provider::get_tts_provider();
        if (!$provider) {
            mtrace('No speech provider is set up, so the audio prompt of question ' . $questionid . ' was not generated.');
            return;
        }

        $voice = tts::choose_voice($options->audiopromptvoice ?? '', $options->sttlanguage ?? '');
        try {
            [$audio, $extension, $mimetype] = tts::generate($provider, $transcript, $voice,
                    (string) ($options->sttlanguage ?? ''));
        } catch (\Throwable $e) {
            $reason = task_failure::describe($e);
            if (task_failure::retry($e, $this)) {
                // E.g. no connection, or the provider is busy: fail, so that Moodle tries again.
                mtrace("Generating the audio prompt of question $questionid failed, and will be tried again later (" .
                        ($this->get_attempts_available() - 1) . " tries left): $reason");
                throw $e;
            }
            // It won't work, or this was the last try: tell the question's author.
            mtrace("The audio prompt of question $questionid could not be generated: $reason");
            $count = task_failure::notify_audio_prompt_failed($questionid, (int) $this->get_userid(), $reason);
            mtrace("$count person(s) were sent a message about this.");
            return;
        }

        get_file_storage()->create_file_from_string([
            'contextid' => $contextid,
            'component' => 'qtype_speakup',
            'filearea' => 'audioprompt',
            'itemid' => $questionid,
            'filepath' => '/',
            'filename' => 'audioprompt.' . $extension,
            'mimetype' => $mimetype,
            'userid' => $this->get_userid() ?: null,
        ], $audio);
        mtrace("Generated the audio prompt of question $questionid (" . display_size(strlen($audio)) . ').');
    }
}
