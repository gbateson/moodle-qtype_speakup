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
use qtype_speakup\tts;

/**
 * Generates a question's audio prompt from its transcript, using the speech
 * provider chosen in the site settings.
 *
 * It is queued when a question is saved or imported with a transcript but
 * no audio prompt. If, by the time it runs, the question has an audio
 * prompt, or its transcript has changed, it does nothing.
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
        static $pending = null;
        if ($pending === null) {
            $pending = [];
            foreach (\core\task\manager::get_adhoc_tasks(self::class) as $task) {
                $data = $task->get_custom_data();
                if (!empty($data->questionid)) {
                    $pending[(int) $data->questionid] = true;
                }
            }
        }
        return isset($pending[$questionid]);
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
        // Any failure throws an exception, so that the task is tried again later.
        [$audio, $extension, $mimetype] = tts::generate($provider, $transcript, $voice, (string) ($options->sttlanguage ?? ''));

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
