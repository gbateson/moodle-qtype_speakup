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

/**
 * Manually queue AI assessment for every SpeakUp AI-mode question in a
 * given quiz attempt.
 *
 * This does exactly what \qtype_speakup\observer::quiz_attempt_submitted()
 * does, but triggered directly from the command line rather than by the
 * mod_quiz attempt_submitted event - useful for testing/debugging the
 * AI-calling pipeline independently of whether the event observer itself
 * is firing, and as a general "re-run AI assessment for this attempt"
 * admin tool.
 *
 * Usage: php cli/queue_ai_assessment.php --attemptid=123 [--run]
 *   --attemptid  the quiz_attempts.id to (re-)queue AI assessment for.
 *   --run        also execute the queued task(s) immediately, rather than
 *                waiting for the next cron run.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognised] = cli_get_params(
        ['attemptid' => 0, 'run' => false, 'help' => false],
        ['h' => 'help']
);

if ($unrecognised) {
    $unrecognised = implode("\n  ", $unrecognised);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognised));
}

if ($options['help'] || empty($options['attemptid'])) {
    echo "Manually queue AI assessment for a quiz attempt's SpeakUp questions.

Usage:
    php cli/queue_ai_assessment.php --attemptid=123 [--run]

Options:
    --attemptid=N   The quiz_attempts.id to queue AI assessment for. Required.
    --run           Also execute the queued task(s) immediately (bypasses cron).
    -h, --help      Print this help.
";
    exit($options['help'] ? 0 : 1);
}

$attemptid = (int) $options['attemptid'];
$attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid], '*', MUST_EXIST);

mtrace("Loading question usage {$attempt->uniqueid} for quiz attempt $attemptid (user {$attempt->userid})...");
$quba = question_engine::load_questions_usage_by_activity($attempt->uniqueid);

$cm = get_coursemodule_from_instance('quiz', $attempt->quiz, 0, false, MUST_EXIST);
$context = context_module::instance($cm->id);

$queued = 0;
foreach ($quba->get_slots() as $slot) {
    $question = $quba->get_question($slot);
    if (!($question instanceof qtype_speakup_question)) {
        continue;
    }
    mtrace("Slot $slot: " . $question->name . ' (gradingmethod=' . $question->gradingmethod . ')');

    if ($question->gradingmethod !== SPEAKUP_GRADING_AI) {
        mtrace("  Skipping - not an AI-graded question.");
        continue;
    }

    $qa = $quba->get_question_attempt($slot);
    if (!$question->is_complete_response($qa->get_last_qt_data())) {
        mtrace("  Skipping - no recording submitted for this slot.");
        continue;
    }

    $task = new \qtype_speakup\task\assess_response();
    $task->set_custom_data([
        'usageid' => $attempt->uniqueid,
        'slot' => $slot,
        'userid' => $attempt->userid,
        'contextid' => $context->id,
    ]);
    $taskid = \core\task\manager::queue_adhoc_task($task);
    mtrace("  Queued adhoc task id $taskid.");
    $queued++;
}

if ($queued === 0) {
    mtrace("Nothing queued - no AI-graded SpeakUp questions with a recording were found in this attempt.");
    exit(0);
}

if (!empty($options['run'])) {
    mtrace("\nRunning queued adhoc tasks now...\n");
    // Delegate to core's own adhoc task runner rather than re-implementing
    // task locking/execution here.
    system(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($CFG->dirroot . '/admin/cli/adhoc_task.php') .
            ' --execute', $exitcode);
    exit($exitcode);
}

mtrace("\nDone. Run cron (or admin/cli/adhoc_task.php --execute) to process the queued task(s).");
