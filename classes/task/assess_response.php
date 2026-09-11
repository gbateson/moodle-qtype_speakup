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

/**
 * Adhoc task: transcribe a submitted recording, then (if the question uses
 * AI grading) send the transcript to an AI assessment engine, and store the
 * result so it can be picked up next time the attempt is graded/rendered.
 *
 * This runs via cron rather than inline in the web request, because both
 * transcription and AI assessment calls are slow and can fail transiently -
 * they should never block the student's submit action.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assess_response extends \core\task\adhoc_task {

    #[\Override]
    public function execute() {
        global $DB;

        $data = $this->get_custom_data();
        $questionattemptid = $data->questionattemptid;
        $fileid = $data->fileid;

        $record = $DB->get_record('qtype_speakup_response', ['questionattemptid' => $questionattemptid]);
        if (!$record) {
            mtrace("qtype_speakup: no response row for question_attempt $questionattemptid, skipping.");
            return;
        }

        // Step 1: transcription. Swap in a real provider here (e.g. Whisper
        // API, or a self-hosted whisper.cpp endpoint for institutions with
        // data-residency requirements).
        try {
            $transcript = $this->transcribe(\get_file_storage()->get_file_by_id($fileid));
            $record->transcript = $transcript;
            $record->transcriptstatus = 'done';
        } catch (\Throwable $e) {
            mtrace('qtype_speakup: transcription failed: ' . $e->getMessage());
            $record->transcriptstatus = 'failed';
            $record->timemodified = time();
            $DB->update_record('qtype_speakup_response', $record);
            return;
        }

        // Step 2: AI assessment, only if this question is configured for it.
        if (!empty($data->gradingmethod) && $data->gradingmethod === \SPEAKUP_GRADING_AI) {
            try {
                [$score, $feedback] = $this->assess(
                    $record->transcript,
                    $data->airubric ?? ''
                );
                $record->aiscore = $score;
                $record->aifeedback = $feedback;
                $record->aistatus = 'done';
            } catch (\Throwable $e) {
                mtrace('qtype_speakup: AI assessment failed: ' . $e->getMessage());
                $record->aistatus = 'failed';
            }
        } else {
            $record->aistatus = 'notapplicable';
        }

        $record->timemodified = time();
        $DB->update_record('qtype_speakup_response', $record);

        // TODO: once the grade/feedback is available, this should trigger
        // updating the question_attempt / question_usage grade, e.g. via
        // question_engine::load_questions_usage_by_activity() + regrade,
        // or by marking the attempt as needing regrading on next view.
    }

    /**
     * Transcribe an audio file to text. Placeholder - wire up a real
     * transcription provider (Whisper API / self-hosted whisper.cpp / etc).
     *
     * @param \stored_file $file
     * @return string
     */
    protected function transcribe(\stored_file $file): string {
        throw new \coding_exception('transcribe() is not implemented yet - configure a transcription provider.');
    }

    /**
     * Send a transcript + rubric to the configured AI assessment engine.
     * Placeholder - wire up a real provider behind a pluggable interface
     * (see \qtype_speakup\ai\assessor_interface, to be added) so
     * institutions can choose their own backend.
     *
     * @param string $transcript
     * @param string $rubric
     * @return array [float $score 0..1, string $feedbackhtml]
     */
    protected function assess(string $transcript, string $rubric): array {
        throw new \coding_exception('assess() is not implemented yet - configure an AI assessment provider.');
    }
}
