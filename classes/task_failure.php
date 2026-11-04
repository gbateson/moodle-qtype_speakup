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

/**
 * What to do when a background task fails: decide whether to try again
 * later, describe the error, and tell the people who need to know.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class task_failure {

    /**
     * How many times to try a task. Moodle waits 1, 2, 4, 8 ... minutes
     * between tries, so 8 tries take about two hours.
     */
    const MAX_TRIES = 8;

    /** The longest error message to save, in characters. */
    const MAX_LENGTH = 1000;

    /**
     * Should a task that failed with this error be tried again later?
     *
     * @param \Throwable $error
     * @param \core\task\adhoc_task $task
     * @return bool
     */
    public static function retry(\Throwable $error, \core\task\adhoc_task $task): bool {
        // Moodle counts down the tries left after each failure, so 1 means
        // this is the last one.
        return ai_request_exception::is_temporary($error) && $task->get_attempts_available() > 1;
    }

    /**
     * Describe an error, for the log and for teachers.
     *
     * @param \Throwable $error
     * @return string
     */
    public static function describe(\Throwable $error): string {
        $message = trim($error->getMessage());
        if (!($error instanceof \moodle_exception)) {
            $message = get_class($error) . ': ' . $message;
        }
        return \core_text::substr($message, 0, self::MAX_LENGTH);
    }

    /**
     * Tell the teachers that a response could not be assessed, and so needs
     * grading by hand. For a preview, tell the person previewing.
     *
     * @param \question_attempt $qa
     * @param int $usageid
     * @param int $slot
     * @param int $userid the student, or the person previewing.
     * @param string $reason why it could not be assessed.
     * @return int how many people were told.
     */
    public static function notify_assessment_failed(\question_attempt $qa, int $usageid, int $slot,
            int $userid, string $reason): int {
        global $DB;

        $a = (object) [
            'question' => format_string($qa->get_question(false)->name),
            'reason' => $reason,
        ];
        $courseid = SITEID;
        $url = null;
        $attempt = $DB->get_record('quiz_attempts', ['uniqueid' => $usageid]);
        $cm = $attempt ? get_coursemodule_from_instance('quiz', $attempt->quiz) : false;
        if ($attempt && $cm) {
            $context = \context_module::instance($cm->id);
            $courseid = (int) $cm->course;
            $student = \core_user::get_user((int) $attempt->userid);
            $a->student = $student ? fullname($student) : '';
            $a->quiz = format_string($cm->name, true, ['context' => $context]);
            $a->url = (new \moodle_url('/mod/quiz/comment.php', ['attempt' => $attempt->id, 'slot' => $slot]))->out(false);
            $url = $a->url;
            // Active teachers in the course who can grade the quiz.
            $recipients = get_enrolled_users($context, 'mod/quiz:grade', 0, 'u.*', null, 0, 0, true);
            $body = get_string('assessmentfailedmessage', 'qtype_speakup', $a);
        } else {
            // A preview: tell the person previewing.
            $user = \core_user::get_user($userid);
            $recipients = $user ? [$user] : [];
            $body = get_string('assessmentfailedpreviewmessage', 'qtype_speakup', $a);
        }

        return self::send('assessmentfailed', $recipients, $courseid,
                get_string('assessmentfailedsubject', 'qtype_speakup'), $body, $url);
    }

    /**
     * Tell a question's author that its audio prompt could not be generated.
     *
     * @param int $questionid
     * @param int $userid the person who saved the question, if known.
     * @param string $reason why it could not be generated.
     * @return int how many people were told.
     */
    public static function notify_audio_prompt_failed(int $questionid, int $userid, string $reason): int {
        global $DB;

        $question = $DB->get_record('question', ['id' => $questionid], 'id, name, createdby, modifiedby');
        if (!$question) {
            return 0;
        }
        $userid = $userid ?: (int) ($question->modifiedby ?: $question->createdby);
        $user = $userid ? \core_user::get_user($userid) : false;
        if (!$user || $user->deleted || $user->suspended) {
            return 0;
        }
        $a = (object) ['question' => format_string($question->name), 'reason' => $reason];
        return self::send('audiopromptfailed', [$user], SITEID,
                get_string('audiopromptfailedsubject', 'qtype_speakup'),
                get_string('audiopromptfailedmessage', 'qtype_speakup', $a), null);
    }

    /**
     * Send a message.
     *
     * @param string $name the message provider.
     * @param \stdClass[] $recipients
     * @param int $courseid
     * @param string $subject
     * @param string $body plain text.
     * @param string|null $url a link to where to fix the problem.
     * @return int how many people were sent it.
     */
    protected static function send(string $name, array $recipients, int $courseid, string $subject,
            string $body, ?string $url): int {
        $count = 0;
        foreach ($recipients as $recipient) {
            $message = new \core\message\message();
            $message->component = 'qtype_speakup';
            $message->name = $name;
            $message->userfrom = \core_user::get_noreply_user();
            $message->userto = $recipient;
            $message->subject = $subject;
            $message->fullmessage = $body;
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = text_to_html(s($body), false, false, true);
            $message->smallmessage = $subject;
            $message->notification = 1;
            $message->courseid = $courseid;
            if ($url) {
                $message->contexturl = $url;
                $message->contexturlname = get_string('gradeitnow', 'qtype_speakup');
            }
            try {
                if (message_send($message)) {
                    $count++;
                }
            } catch (\Throwable $e) {
                // Not being able to send a message must not stop the task.
                mtrace('qtype_speakup: could not send a message to user ' . $recipient->id . ': ' . $e->getMessage());
            }
        }
        return $count;
    }
}
