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

use qtype_speakup\ai_caller;
use qtype_speakup\ai_provider;
use qtype_speakup\rubric_placeholders;

/**
 * Adhoc task: assess one SpeakUp question's response with AI, then post the
 * result the same way a human teacher's manual grade is posted.
 *
 * Queued by \qtype_speakup\observer::quiz_attempt_submitted() - runs via
 * cron rather than inline in the submit request, since an AI provider call
 * is slow and can fail transiently; it should never block the student's
 * submit action.
 *
 * custom_data required: usageid, slot (identify the question_attempt to
 * assess), userid and contextid (passed through to the core AI subsystem,
 * if that is the configured provider type).
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assess_response extends \core\task\adhoc_task {

    #[\Override]
    public function execute() {
        global $CFG, $DB;

        // Cron doesn't load the question engine, so load it here.
        require_once($CFG->libdir . '/questionlib.php');

        $data = $this->get_custom_data();
        $usageid = $data->usageid;
        $slot = $data->slot;

        $quba = \question_engine::load_questions_usage_by_activity($usageid);
        $qa = $quba->get_question_attempt($slot);
        /** @var \qtype_speakup_question $question */
        $question = $quba->get_question($slot);

        if (!($question instanceof \qtype_speakup_question) || !$question->is_graded_later()) {
            // Question was changed (e.g. its grading settings edited) since
            // this task was queued - nothing to do.
            mtrace('qtype_speakup: question at usage ' . $usageid . ' slot ' . $slot .
                    ' is no longer graded in the background, skipping.');
            return;
        }

        $qadatabaseid = $qa->get_database_id();
        $record = $DB->get_record('qtype_speakup_response', ['questionattemptid' => $qadatabaseid]);
        if (!$record) {
            $record = new \stdClass();
            $record->questionattemptid = $qadatabaseid;
            $record->timecreated = time();
            $record->timemodified = $record->timecreated;
            $record->id = $DB->insert_record('qtype_speakup_response', $record);
        }

        $success = false;
        $fraction = null;
        $feedback = '';
        try {
            $transcript = (string) $qa->get_last_qt_var('browsertranscript', '');
            $files = $qa->get_last_qt_files('recording', $data->contextid);
            $file = reset($files) ?: null;
            $duration = \qtype_speakup_question::best_duration($file, $qa->get_last_qt_var('browserduration'));
            $aifraction = null;
            $aitranscript = null;

            if ($question->uses_ai()) {
                $provider = $question->aiproviderid ? ai_provider::get((int) $question->aiproviderid) : false;
                if (!$provider) {
                    throw new \moodle_exception('errorproviderconfig', 'qtype_speakup');
                }
                // Only a multimodal provider receives the recording itself,
                // and so can transcribe it.
                $multimodal = ($file && $provider->capability === ai_provider::CAPABILITY_MULTIMODAL);

                // Fill in any {{questiontext...}} placeholders, and add any
                // notes and instructions the AI needs.
                $rubric = rubric_placeholders::expand((string) $question->airubric,
                        (string) $question->questiontext);
                if ($question->is_too_long($duration)) {
                    $rubric .= "\n\n" . get_string('aipromptoverlong', 'qtype_speakup', (object) [
                        'duration' => round($duration),
                        'limit' => (int) $question->timelimitinseconds,
                    ]);
                }
                if ($multimodal) {
                    $rubric .= "\n\n" . get_string('aiprompttranscribe', 'qtype_speakup', $question->get_language_name());
                }

                [$aifraction, $feedback] = ai_caller::assess(
                        $provider, (int) $data->contextid, (int) $data->userid,
                        $rubric, $transcript, $multimodal ? $file : null);

                if ($multimodal) {
                    [$aitranscript, $feedback] = ai_caller::split_transcript($feedback);
                }

                // The AI may reply in Markdown, even when told not to.
                if (\qtype_speakup\feedback_format::detect($feedback) == FORMAT_MARKDOWN) {
                    $feedback = \qtype_speakup\feedback_format::tidy_markdown($feedback);
                }
            } else if ($question->is_upload() && $file) {
                // Without AI assessment, an uploaded file still needs a
                // transcript, for the target phrases and items. The
                // transcription provider is stored in aiproviderid.
                $provider = $question->aiproviderid ? ai_provider::get((int) $question->aiproviderid) : false;
                if (!$provider) {
                    throw new \moodle_exception('errorproviderconfig', 'qtype_speakup');
                }
                $aitranscript = ai_caller::transcribe($provider, $file, $question->get_language_name());
            }

            // The grade combines the target phrases and items with the AI's
            // score. Phrases and items are checked in the AI's transcript if
            // there is one, since it is usually more accurate than the live
            // transcript, and has punctuation for counting sentences.
            $gradingtranscript = ($aitranscript !== null && $aitranscript !== '') ? $aitranscript : $transcript;
            [$phrasefraction, ] = $question->grade_transcript($gradingtranscript);
            [$itemfraction, ] = $question->grade_by_items($gradingtranscript, $duration);
            $fraction = $question->combine_fractions($phrasefraction, $itemfraction, $aifraction);

            // Take off the penalty for showing the audio prompt transcript,
            // unless the student is exempt (e.g. they cannot hear the prompt).
            $viewed = !empty($qa->get_last_qt_var('prompttranscriptviewed'));
            $exempt = \qtype_speakup_question::is_exempt_from_transcript_penalty((int) $data->userid,
                    \context::instance_by_id((int) $data->contextid));
            if ($question->transcript_penalty_applies($viewed, $exempt)) {
                $fraction = $question->apply_transcript_penalty($fraction);
            }

            $record->transcript = $transcript;
            $record->aitranscript = $aitranscript;
            $record->transcriptstatus = ($gradingtranscript !== '') ? 'done' : 'failed';
            $record->aifeedback = $feedback;
            $record->aiscore = $aifraction; // Stored as a 0..1 fraction, not a 0..100 percentage.
            $record->aistatus = 'done';
            $success = true;

        } catch (\Throwable $e) {
            // Any failure here (missing/misconfigured provider, network
            // error, unparseable AI response, etc.) leaves this question
            // on manual grading rather than propagating the error - the
            // renderer shows an explicit "AI assessment failed" message
            // by checking $record->aistatus directly (see renderer.php).
            mtrace('qtype_speakup: AI assessment failed for usage ' . $usageid . ' slot ' . $slot .
                    ': ' . $e->getMessage());
            $record->aistatus = 'failed';
        }

        $record->timemodified = time();
        $DB->update_record('qtype_speakup_response', $record);

        // Post the result, unless there is neither a grade nor a comment.
        if ($success && ($fraction !== null || $feedback !== '')) {
            // Post the AI's result the same way a human teacher's grading
            // action would - manual_grade() works via process_action(),
            // the same mechanism the grading UI itself uses, so a teacher
            // can subsequently override it in the completely ordinary way,
            // with full grading history. This sidesteps a real limitation
            // of grade_response(): it is only ever given $response, never
            // the question_attempt itself or its database id, so it has
            // no way to look up a per-attempt stored AI result - posting
            // via manual_grade() here, where $qa is directly available,
            // avoids needing that at all.
            // If there is nothing to grade from (e.g. AI weighting 0% and no
            // target phrases), only the comment is posted, and a teacher
            // gives the grade.
            $mark = ($fraction === null) ? null : $fraction * $qa->get_max_mark();
            $qa->manual_grade($feedback, $mark, \qtype_speakup\feedback_format::detect($feedback), time());
            \question_engine::save_questions_usage_by_activity($quba);

            // manual_grade() only updates the question engine's own data
            // (the per-question mark, visible immediately) - it does NOT
            // touch mod_quiz's own separate, cached attempt-level and
            // gradebook-facing totals (quiz_attempts.sumgrades, and the
            // grade shown in the gradebook). Those are normally kept in
            // sync by mod_quiz's own code paths (e.g. the manual-grading
            // comment form, or the student finishing their attempt) -
            // since we are updating a grade from a background task,
            // bypassing all of that, we need to explicitly ask mod_quiz to
            // resync them, via its own public grade_calculator class.
            $attemptrecord = $DB->get_record('quiz_attempts', ['uniqueid' => $usageid]);
            if ($attemptrecord) {
                $quizobj = \mod_quiz\quiz_settings::create((int) $attemptrecord->quiz);
                $gradecalculator = $quizobj->get_grade_calculator();
                $gradecalculator->recompute_all_attempt_sumgrades();
                $gradecalculator->recompute_final_grade((int) $attemptrecord->userid);
            } else {
                // Not actually a quiz attempt (shouldn't happen given this
                // task is only ever queued from the quiz observer, but
                // defend against it rather than fatal-erroring here).
                mtrace('qtype_speakup: no quiz_attempts row found for usage ' . $usageid .
                        ' - skipping quiz grade resync.');
            }
        }
        // On failure, deliberately leave the attempt exactly as it was
        // (still "not yet graded", via manualgraded behaviour) - the
        // renderer shows an explicit "AI assessment failed" message by
        // checking $record->aistatus directly, and a teacher can grade it
        // manually like any other manualgraded question. No regrade is
        // needed for that to work correctly.
    }
}
