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

use question_state;
use test_question_maker;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');
require_once($CFG->dirroot . '/question/type/speakup/tests/helper.php');

/**
 * Walkthroughs of attempts at SpeakUp questions: recording a response,
 * finishing, grading, and what the question shows.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \qtype_speakup_question
 * @covers    \qtype_speakup_renderer
 */
final class walkthrough_test extends \qbehaviour_walkthrough_test_base {

    /**
     * Put a recording in a new draft file area.
     *
     * @return int the draft item id.
     */
    protected function make_recording(): int {
        global $USER;
        $draftid = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_string(['contextid' => \context_user::instance($USER->id)->id,
                'component' => 'user', 'filearea' => 'draft', 'itemid' => $draftid, 'filepath' => '/',
                'filename' => 'recording.mp3'], "ID3\x04\x00\x00\x00\x00\x00\x00audio");
        return $draftid;
    }

    /**
     * Give a question an audio prompt file.
     *
     * @param \qtype_speakup_question $question
     */
    protected function add_audio_prompt(\qtype_speakup_question $question): void {
        $question->id = 12345;
        $question->contextid = \context_system::instance()->id;
        get_file_storage()->create_file_from_string(['contextid' => $question->contextid, 'component' => 'qtype_speakup',
                'filearea' => 'audioprompt', 'itemid' => $question->id, 'filepath' => '/', 'filename' => 'audioprompt.mp3'],
                "ID3\x04\x00\x00\x00\x00\x00\x00prompt");
    }

    /**
     * An automatically graded question is graded from the live transcript
     * when the attempt is finished.
     */
    public function test_automatic_grading(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $question = test_question_maker::make_question('speakup', 'basic');
        $this->start_attempt_at_question($question, 'deferredfeedback', 1);

        $this->check_current_state(question_state::$todo);
        $this->render();
        $this->check_output_contains_lang_string('recordyouranswer', 'qtype_speakup');

        $this->process_submission(['recording' => $this->make_recording(),
                'browsertranscript' => 'I have played the guitar.']);
        $this->check_current_state(question_state::$complete);

        $this->finish();
        $this->check_current_state(question_state::$gradedpartial);
        // Phrases 40% + 30%, and 5 of 20 words for up to 30%.
        $this->check_current_mark(0.775);
    }

    /**
     * Without a recording, the response is not complete.
     */
    public function test_no_recording(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $question = test_question_maker::make_question('speakup', 'basic');
        $this->start_attempt_at_question($question, 'deferredfeedback', 1);
        $this->process_submission(['browsertranscript' => 'Hello']);
        $this->check_current_state(question_state::$todo);
        $this->finish();
        $this->check_current_state(question_state::$gaveup);
    }

    /**
     * A manually graded question waits for a teacher's grade.
     */
    public function test_manual_grading(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $question = test_question_maker::make_question('speakup', 'manual');
        $this->start_attempt_at_question($question, 'deferredfeedback', 1);

        $this->process_submission(['recording' => $this->make_recording(), 'browsertranscript' => 'Hello.']);
        $this->finish();
        $this->check_current_state(question_state::$needsgrading);
        $this->check_current_mark(null);

        $this->manual_grade('Well spoken.', 0.9, FORMAT_HTML);
        $this->check_current_state(question_state::$mangrpartial);
        $this->check_current_mark(0.9);
    }

    /**
     * The audio prompt is shown with its collapsed transcript, the penalty
     * for showing it, and the hidden fields for the prompt controls.
     */
    public function test_audio_prompt_display(): void {
        $this->resetAfterTest();
        // Not an admin, who would be exempt from the penalty.
        $this->setUser($this->getDataGenerator()->create_user());
        $question = test_question_maker::make_question('speakup', 'prompt');
        $this->add_audio_prompt($question);
        $this->start_attempt_at_question($question, 'deferredfeedback', 1);

        $this->render();
        $this->check_output_contains('audioprompt.mp3');
        $this->check_output_contains('Talk about a string instrument that you can play.');
        $this->check_output_contains_lang_string('showtranscriptpenalty', 'qtype_speakup', 30);
        $this->check_output_contains_hidden_input('prompttranscriptviewed', 0);
        $this->assertStringContainsString('promptplays', $this->currentoutput);
        $this->assertStringContainsString('responsecount', $this->currentoutput);
    }

    /**
     * An expanded transcript is shown straight away, with no penalty.
     */
    public function test_expanded_transcript(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $question = test_question_maker::make_question('speakup', 'prompt');
        $question->prompttranscriptdisplay = SPEAKUP_PROMPTTRANSCRIPT_EXPANDED;
        $this->add_audio_prompt($question);
        $this->start_attempt_at_question($question, 'deferredfeedback', 1);

        $this->render();
        $this->check_output_contains('Talk about a string instrument that you can play.');
        $this->assertStringNotContainsString('<details', $this->currentoutput);
    }

    /**
     * A hidden transcript is not shown.
     */
    public function test_hidden_transcript(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $question = test_question_maker::make_question('speakup', 'prompt');
        $question->prompttranscriptdisplay = SPEAKUP_PROMPTTRANSCRIPT_HIDE;
        $this->add_audio_prompt($question);
        $this->start_attempt_at_question($question, 'deferredfeedback', 1);

        $this->render();
        $this->check_output_contains('audioprompt.mp3');
        $this->assertStringNotContainsString('Talk about a string instrument', $this->currentoutput);
    }

    /**
     * Students see a notice while the audio prompt is being generated.
     */
    public function test_audio_prompt_pending(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        \qtype_speakup_test_helper::create_speech_provider();
        $question = test_question_maker::make_question('speakup', 'prompt');
        $question->id = 12345;
        $question->contextid = \context_system::instance()->id;
        task\generate_audio_prompt::queue_if_needed($question->id, $question->contextid, $question->audioprompttranscript);
        $this->start_attempt_at_question($question, 'deferredfeedback', 1);

        $this->render();
        $this->check_output_contains_lang_string('audiopromptnotready', 'qtype_speakup');
        $this->assertStringNotContainsString('qtype_speakup/audioprompt/', $this->currentoutput);
    }

    /**
     * Image responses show the upload widget, and the pages when reviewed.
     */
    public function test_image_response(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        // A question in the database, so the attempt can be saved and loaded again.
        /** @var \core_question_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category();
        $questiondata = $generator->create_question('speakup', 'image', ['category' => $category->id]);
        $question = \question_bank::load_question($questiondata->id);
        $this->start_attempt_at_question($question, 'deferredfeedback', 1);

        $this->render();
        $this->check_output_contains_lang_string('uploadimage', 'qtype_speakup');
        $this->check_output_contains_lang_string('maxfilesnote', 'qtype_speakup', 3);
        $this->assertStringContainsString('application/pdf', $this->currentoutput);
        $this->assertStringNotContainsString('qtype_speakup-audio-widget', $this->currentoutput);

        $draftid = file_get_unused_draft_itemid();
        foreach (['01-page.png', '02-page.pdf'] as $filename) {
            get_file_storage()->create_file_from_string(['contextid' => \context_user::instance($USER->id)->id,
                    'component' => 'user', 'filearea' => 'draft', 'itemid' => $draftid, 'filepath' => '/',
                    'filename' => $filename], 'content');
        }
        $this->process_submission(['recording' => $draftid]);
        $this->check_current_state(question_state::$complete);
        $this->finish();
        // The files are stored when the attempt is saved.
        $this->save_quba();
        $this->load_quba();
        $this->displayoptions->readonly = true;

        $this->render();
        $this->check_output_contains_lang_string('pagex', 'qtype_speakup', 1);
        $this->check_output_contains_lang_string('pagex', 'qtype_speakup', 2);
        $this->assertStringContainsString('01-page.png', $this->currentoutput);
        $this->assertStringContainsString('<img', $this->currentoutput);
        $this->assertStringContainsString('qtype_speakup-image-pdf', $this->currentoutput);
    }

    /**
     * An image response must be images or PDFs, and no more files than allowed.
     */
    public function test_image_response_checks(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $question = test_question_maker::make_question('speakup', 'image');
        $this->start_attempt_at_question($question, 'deferredfeedback', 1);

        $draftid = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_string(['contextid' => \context_user::instance($USER->id)->id,
                'component' => 'user', 'filearea' => 'draft', 'itemid' => $draftid, 'filepath' => '/',
                'filename' => 'notes.txt'], 'Not an image.');
        $this->process_submission(['recording' => $draftid]);
        $this->check_current_state(question_state::$invalid);
        $this->render();
        $this->check_output_contains_lang_string('notimagefile', 'qtype_speakup');

        $draftid = file_get_unused_draft_itemid();
        foreach (['01.png', '02.png', '03.png', '04.png'] as $filename) {
            get_file_storage()->create_file_from_string(['contextid' => \context_user::instance($USER->id)->id,
                    'component' => 'user', 'filearea' => 'draft', 'itemid' => $draftid, 'filepath' => '/',
                    'filename' => $filename], 'content');
        }
        $this->process_submission(['recording' => $draftid]);
        $this->check_current_state(question_state::$invalid);
        $this->render();
        $this->check_output_contains_lang_string('toomanyfiles', 'qtype_speakup', 3);
    }
}
