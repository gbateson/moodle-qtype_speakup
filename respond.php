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
 * The response part of a SpeakUp question (the audio prompt, and the
 * recorder or uploader), shown in a frame in the Moodle app (see
 * mobile/mobile.js). It works just as it does in a browser: the response
 * goes into the app's draft file area, and its other values are sent to the
 * app (see amd/src/mobilebridge.js), which sends them with the quiz.
 *
 * Phones may not keep cookies in frames, so this page has no session. It is
 * opened with a single-use key from the app (see
 * \qtype_speakup\external\get_response_key), and its JavaScript then uses the
 * app's web service token, which the app sends to it (see
 * amd/src/draftfiles.js).
 *
 * Only the student's own quiz attempt, still in progress, can be shown.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// No session: the key identifies the student.
define('NO_MOODLE_COOKIES', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/questionlib.php');

$key = required_param('key', PARAM_ALPHANUM);
$usageid = required_param('usage', PARAM_INT);
$slot = required_param('slot', PARAM_INT);
$draftitemid = required_param('draft', PARAM_INT);

$PAGE->set_url('/question/type/speakup/respond.php', ['usage' => $usageid, 'slot' => $slot, 'draft' => $draftitemid]);
$PAGE->set_pagelayout('embedded');
$PAGE->set_title(get_string('pluginname', 'qtype_speakup'));
$PAGE->add_body_class('qtype_speakup-mobile-response');

$contextset = false;
try {
    $user = \qtype_speakup\mobile_support::use_response_key($key, $usageid);
    \core\session\manager::set_user($user);
    // Check access directly, rather than with require_login() for the
    // course and quiz, which would add the course page's JavaScript. That
    // needs a session, and sends the page to the login form without one.
    $attempt = $DB->get_record('quiz_attempts', ['uniqueid' => $usageid], '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('quiz', $attempt->quiz, 0, false, MUST_EXIST);
    $modcontext = context_module::instance($cm->id);
    if (!\core_availability\info_module::is_user_visible($cm, $user->id, false)
            || !has_capability('mod/quiz:attempt', $modcontext, $user)) {
        throw new moodle_exception('mobilenoattempt', 'qtype_speakup');
    }
    [$qa, $context] = \qtype_speakup\mobile_support::get_question_attempt($usageid, $slot);
    $PAGE->set_context($context);
    $contextset = true;
} catch (Exception $e) {
    // A message is clearer than an error page in the app's frame.
    if (!$contextset) {
        $PAGE->set_context(context_system::instance());
    }
    echo $OUTPUT->header();
    echo $OUTPUT->notification($e->getMessage(), \core\output\notification::NOTIFY_ERROR);
    echo $OUTPUT->footer();
    exit;
}

$options = new question_display_options();
$options->context = $context;
$options->readonly = false;
$options->flags = question_display_options::HIDDEN;

/** @var qtype_speakup_renderer $renderer */
$renderer = $PAGE->get_renderer('qtype_speakup');
$html = $renderer->mobile_response($qa, $options, $draftitemid);

// Tells the JavaScript to use the app's token, for files in the user's draft area.
$html = html_writer::div('', 'd-none', ['id' => 'qtype_speakup-mobile',
        'data-usercontextid' => context_user::instance($user->id)->id]) . $html;

// Get the app's token, and send the response's values, and the page's height, to the app.
$PAGE->requires->js_call_amd('qtype_speakup/mobilebridge', 'init',
        [$qa->get_outer_question_div_unique_id(), $qa->get_field_prefix()]);

echo $OUTPUT->header();
echo $html;
echo $OUTPUT->footer();
