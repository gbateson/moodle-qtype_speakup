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
 * Support for SpeakUp questions in the Moodle app.
 *
 * In the app, the response (the recorder, uploader and prompt) is shown in
 * an iframe, from respond.php on this site, so that it works just as it does
 * in a browser. The frame has no session, since phones may not keep cookies
 * in frames: it is opened with a single-use key (see create_response_key()),
 * and then uses the app's web service token. That needs some site settings:
 * see get_conditions(). If any is not met, SpeakUp tells the app it is not
 * supported (see output\mobile::mobile_init()), and the app offers to open
 * the quiz in the browser instead.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mobile_support {

    /** The oldest Moodle app version supported: 4.2.0. */
    const MIN_APP_VERSION = 42000;

    /** The "script" of the single-use keys for respond.php, in the user_private_key table. */
    const KEY_SCRIPT = 'qtype_speakup_respond';

    /** How long a key for respond.php can be used, in seconds. */
    const KEY_LIFETIME = 300;

    /**
     * Check each condition for SpeakUp questions to work in the Moodle app.
     *
     * - mobileapp: the administrator allows SpeakUp in the app (a setting of this plugin).
     * - mobileservice: web services for mobile devices are enabled.
     * - framembedding: pages from this site can be shown in frames, as the
     *   app does with the response.
     *
     * @return bool[] condition name => whether it is met.
     */
    public static function get_conditions(): array {
        global $CFG;
        return [
            'mobileapp' => (bool) get_config('qtype_speakup', 'mobileapp'),
            'mobileservice' => !empty($CFG->enablewebservices) && !empty($CFG->enablemobilewebservice),
            'framembedding' => !empty($CFG->allowframembedding),
        ];
    }

    /**
     * Do SpeakUp questions work in the Moodle app on this site?
     *
     * @param int|null $appversion the app's version code, e.g. 44000 for 4.4.0, if known.
     * @return bool
     */
    public static function is_supported(?int $appversion = null): bool {
        if ($appversion !== null && $appversion > 0 && $appversion < self::MIN_APP_VERSION) {
            return false;
        }
        return !in_array(false, self::get_conditions(), true);
    }

    /**
     * Describe the conditions, for the plugin's settings page.
     *
     * @return string HTML
     */
    public static function get_status_html(): string {
        $items = '';
        foreach (self::get_conditions() as $name => $met) {
            $icon = $met ? '✔' : '✘';
            $class = $met ? 'text-success' : 'text-danger';
            $items .= \html_writer::tag('li', \html_writer::span($icon, $class . ' me-1') .
                    get_string('mobilecondition_' . $name, 'qtype_speakup'));
        }
        $summary = get_string(self::is_supported() ? 'mobilesupported' : 'mobilenotsupported', 'qtype_speakup');
        return \html_writer::tag('p', $summary) . \html_writer::tag('ul', $items, ['class' => 'list-unstyled']);
    }

    /**
     * Get the question attempt that the current user may respond to in the
     * app's frame: their own quiz attempt, still in progress.
     *
     * @param int $usageid
     * @param int $slot
     * @return array [\question_attempt, \context] the attempt, and the context it is in.
     * @throws \moodle_exception if there is no such attempt, or it is not the user's, or it is finished.
     */
    public static function get_question_attempt(int $usageid, int $slot): array {
        global $CFG, $DB, $USER;
        require_once($CFG->libdir . '/questionlib.php');

        $attempt = $DB->get_record('quiz_attempts', ['uniqueid' => $usageid]);
        if (!$attempt || (int) $attempt->userid !== (int) $USER->id || $attempt->state !== 'inprogress') {
            throw new \moodle_exception('mobilenoattempt', 'qtype_speakup');
        }
        $quba = \question_engine::load_questions_usage_by_activity($usageid);
        if (!in_array($slot, $quba->get_slots())) {
            throw new \moodle_exception('mobilenoattempt', 'qtype_speakup');
        }
        $qa = $quba->get_question_attempt($slot);
        if (!($qa->get_question() instanceof \qtype_speakup_question) || $qa->get_state()->is_finished()) {
            throw new \moodle_exception('mobilenoattempt', 'qtype_speakup');
        }
        return [$qa, $quba->get_owning_context()];
    }

    /**
     * Create a single-use key that lets a user open respond.php for one of
     * their quiz attempts, within a few minutes.
     *
     * @param int $userid
     * @param int $usageid the question usage id of the attempt.
     * @return string the key.
     */
    public static function create_response_key(int $userid, int $usageid): string {
        global $DB;
        // Remove the user's keys that have expired.
        $DB->delete_records_select('user_private_key', 'script = ? AND userid = ? AND validuntil < ?',
                [self::KEY_SCRIPT, $userid, time()]);
        return create_user_key(self::KEY_SCRIPT, $userid, $usageid, null, time() + self::KEY_LIFETIME);
    }

    /**
     * Use a key from create_response_key(): check it, and delete it so that
     * it cannot be used again.
     *
     * @param string $key
     * @param int $usageid the question usage id it must be for.
     * @return \stdClass the user it is for.
     * @throws \moodle_exception if the key is not valid, has expired, or the user cannot log in.
     */
    public static function use_response_key(string $key, int $usageid): \stdClass {
        global $DB;
        $record = $DB->get_record('user_private_key', ['script' => self::KEY_SCRIPT, 'value' => $key]);
        if ($record) {
            $DB->delete_records('user_private_key', ['id' => $record->id]);
        }
        if (!$record || (int) $record->instance !== $usageid || $record->validuntil < time()) {
            throw new \moodle_exception('mobilebadkey', 'qtype_speakup');
        }
        $user = $DB->get_record('user', ['id' => $record->userid, 'deleted' => 0, 'suspended' => 0]);
        if (!$user) {
            throw new \moodle_exception('mobilebadkey', 'qtype_speakup');
        }
        return $user;
    }
}
