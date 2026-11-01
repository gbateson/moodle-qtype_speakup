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

use question_bank;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/type/speakup/tests/helper.php');

/**
 * Tests for generating audio prompts from their transcripts.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \qtype_speakup\task\generate_audio_prompt
 */
final class generate_audio_prompt_test extends \advanced_testcase {

    /** @var string the start of an MP3 file. */
    const MP3 = "ID3\x04\x00\x00\x00\x00\x00\x00audio";

    /**
     * Create a question with an audio prompt transcript, which queues the task.
     *
     * @param array $overrides
     * @return \stdClass the question data.
     */
    protected function create_question(array $overrides = []): \stdClass {
        /** @var \core_question_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category();
        $question = $generator->create_question('speakup', 'prompt', ['category' => $category->id] + $overrides);
        return question_bank::load_question_data($question->id);
    }

    /**
     * Run the queued tasks for a question.
     *
     * @param int $questionid
     */
    protected function run_tasks(int $questionid): void {
        foreach (\core\task\manager::get_adhoc_tasks(generate_audio_prompt::class) as $task) {
            if ((int) $task->get_custom_data()->questionid === $questionid) {
                ob_start();
                try {
                    $task->execute();
                } finally {
                    ob_end_clean();
                }
            }
        }
    }

    /**
     * Get the names of the files in a question's audio prompt area.
     *
     * @param \stdClass $qdata
     * @return string[]
     */
    protected function get_prompt_files(\stdClass $qdata): array {
        $files = get_file_storage()->get_area_files($qdata->contextid, 'qtype_speakup', 'audioprompt', $qdata->id,
                'filename', false);
        return array_values(array_map(fn($file) => $file->get_filename(), $files));
    }

    /**
     * The task is only queued when there is a transcript, no audio prompt and a speech provider.
     */
    public function test_queue_if_needed(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $context = \context_system::instance();

        $this->assertFalse(generate_audio_prompt::queue_if_needed(1, $context->id, 'Hello.'));

        \qtype_speakup_test_helper::create_speech_provider();
        $this->assertFalse(generate_audio_prompt::queue_if_needed(1, $context->id, '  '));
        $this->assertTrue(generate_audio_prompt::queue_if_needed(1, $context->id, 'Hello.'));
        $this->assertTrue(generate_audio_prompt::is_pending(1));
        $this->assertFalse(generate_audio_prompt::is_pending(2));

        get_file_storage()->create_file_from_string(['contextid' => $context->id, 'component' => 'qtype_speakup',
                'filearea' => 'audioprompt', 'itemid' => 3, 'filepath' => '/', 'filename' => 'a.mp3'], self::MP3);
        $this->assertTrue(generate_audio_prompt::has_audio_prompt(3, $context->id));
        $this->assertFalse(generate_audio_prompt::queue_if_needed(3, $context->id, 'Hello.'));
    }

    /**
     * The task saves the generated audio as the question's audio prompt.
     */
    public function test_execute(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        \qtype_speakup_test_helper::allow_any_curl_url();
        \qtype_speakup_test_helper::create_speech_provider();
        $qdata = $this->create_question();
        $this->assertTrue(generate_audio_prompt::is_pending($qdata->id));

        \curl::mock_response(self::MP3);
        $this->run_tasks($qdata->id);

        $this->assertSame(['audioprompt.mp3'], $this->get_prompt_files($qdata));
        $file = get_file_storage()->get_file($qdata->contextid, 'qtype_speakup', 'audioprompt', $qdata->id, '/',
                'audioprompt.mp3');
        $this->assertSame(self::MP3, $file->get_content());
        $this->assertSame('audio/mpeg', $file->get_mimetype());
    }

    /**
     * The task does nothing if the transcript changed, or the question
     * already has an audio prompt, or there is no speech provider any more.
     */
    public function test_execute_skips(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $provider = \qtype_speakup_test_helper::create_speech_provider();

        // Transcript changed.
        $qdata = $this->create_question();
        $DB->set_field('qtype_speakup_options', 'audioprompttranscript', 'Something else.', ['questionid' => $qdata->id]);
        $this->run_tasks($qdata->id);
        $this->assertSame([], $this->get_prompt_files($qdata));

        // Already has an audio prompt.
        $qdata = $this->create_question();
        get_file_storage()->create_file_from_string(['contextid' => $qdata->contextid, 'component' => 'qtype_speakup',
                'filearea' => 'audioprompt', 'itemid' => $qdata->id, 'filepath' => '/', 'filename' => 'mine.mp3'], self::MP3);
        $this->run_tasks($qdata->id);
        $this->assertSame(['mine.mp3'], $this->get_prompt_files($qdata));

        // No speech provider any more.
        $qdata = $this->create_question();
        set_config('ttsproviderid', 0, 'qtype_speakup');
        $this->run_tasks($qdata->id);
        $this->assertSame([], $this->get_prompt_files($qdata));

        // Question deleted.
        set_config('ttsproviderid', $provider->id, 'qtype_speakup');
        $qdata = $this->create_question();
        $DB->delete_records('qtype_speakup_options', ['questionid' => $qdata->id]);
        $this->run_tasks($qdata->id);
        $this->assertSame([], $this->get_prompt_files($qdata));
    }

    /**
     * A failed request throws an exception, so that the task is tried again later.
     */
    public function test_execute_failure(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        \qtype_speakup_test_helper::allow_any_curl_url();
        \qtype_speakup_test_helper::create_speech_provider();
        $qdata = $this->create_question();

        \curl::mock_response('<html>Server error</html>');
        $this->expectException(\moodle_exception::class);
        $this->run_tasks($qdata->id);
    }

    /**
     * The question's voice is sent to the provider, and the language is
     * used to choose the site's voice.
     */
    public function test_voice(): void {
        $this->resetAfterTest();
        set_config('ttsvoices', 'en-GB=fable', 'qtype_speakup');
        $this->assertSame('fable', \qtype_speakup\tts::choose_voice('', 'en-GB'));
        $this->assertSame('Kore', \qtype_speakup\tts::choose_voice('Kore', 'en-GB'));
    }
}
