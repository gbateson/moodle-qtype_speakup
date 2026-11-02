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

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../../lib/behat/behat_base.php');

use Behat\Mink\Exception\ExpectationException;

/**
 * Steps for SpeakUp questions: playing the audio prompt, and checking the
 * recorder.
 *
 * Recording needs a microphone. For Firefox, the Behat profile in
 * config.php can give it a fake one (see Readme.md).
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_qtype_speakup extends behat_base {

    /** @var string CSS selector for the audio prompt. */
    const PROMPT = '.qtype_speakup-audio-prompt audio';

    /** @var string CSS selector for the response recorder. */
    const RECORDER = '.que.speakup .qtype_speakup-audio-widget, .que.speakup .qtype_speakup-video-widget';

    /**
     * Play the audio prompt, and wait until it finishes, or is stopped
     * (e.g. because it has been played as many times as allowed).
     *
     * @When /^I play the SpeakUp audio prompt$/
     */
    public function i_play_the_speakup_audio_prompt(): void {
        $prompt = json_encode(self::PROMPT);
        $this->execute_script("
            (function() {
                window.qtypeSpeakupPromptDone = false;
                const audio = document.querySelector($prompt);
                const done = () => {
                    window.qtypeSpeakupPromptDone = true;
                };
                audio.addEventListener('ended', done, {once: true});
                audio.addEventListener('pause', done, {once: true});
                audio.currentTime = 0;
                audio.play().catch(done);
            })();
        ");
        $this->spin(function() {
            return $this->evaluate_script('return window.qtypeSpeakupPromptDone === true;');
        }, [], 30);
        // Let the question's own listeners react to the end of the prompt.
        $this->wait_for_pending_js();
    }

    /**
     * Check that the audio prompt can no longer be played.
     *
     * @Then /^the SpeakUp audio prompt should be locked$/
     */
    public function the_speakup_audio_prompt_should_be_locked(): void {
        $prompt = json_encode(self::PROMPT);
        $locked = $this->evaluate_script("return document.querySelector($prompt).controls === false;");
        if (!$locked) {
            throw new ExpectationException('The audio prompt can still be played.', $this->getSession());
        }
    }

    /**
     * Wait until the response recorder is in a state, e.g. "recording" or
     * "recorded" (see amd/src/avrecording.js).
     *
     * @Then /^the SpeakUp recorder should be "(?P<state>[a-z]+)"$/
     * @param string $state
     */
    public function the_speakup_recorder_should_be(string $state): void {
        $this->recorder_should_be(self::RECORDER, $state);
    }

    /**
     * Wait until the audio prompt recorder on the question form is in a state.
     *
     * @Then /^the SpeakUp audio prompt recorder should be "(?P<state>[a-z]+)"$/
     * @param string $state
     */
    public function the_speakup_audio_prompt_recorder_should_be(string $state): void {
        $this->recorder_should_be('#qtype_speakup-audioprompt .qtype_speakup-audio-widget', $state);
    }

    /**
     * Wait until a recorder is in a state.
     *
     * @param string $selector CSS selector for the recorder.
     * @param string $state
     */
    protected function recorder_should_be(string $selector, string $state): void {
        $selector = json_encode($selector);
        try {
            $this->spin(function() use ($selector, $state) {
                return $this->evaluate_script("return (function() {
                    const widget = document.querySelector($selector);
                    return !!widget && widget.dataset.state === " . json_encode($state) . ";
                })();");
            }, [], 30);
        } catch (Exception $e) {
            $actual = $this->evaluate_script("return (function() {
                const widget = document.querySelector($selector);
                return widget ? widget.dataset.state : 'missing';
            })();");
            throw new ExpectationException("The recorder is \"$actual\", not \"$state\".", $this->getSession());
        }
    }

    /**
     * Wait until a recording has been uploaded, so it will be saved with the
     * response. After the upload, the recorder enables its button again.
     *
     * @Then /^I wait until the SpeakUp recording is uploaded$/
     */
    public function i_wait_until_the_speakup_recording_is_uploaded(): void {
        $this->recording_uploaded(self::RECORDER);
    }

    /**
     * Wait until the audio prompt recorded on the question form has been uploaded.
     *
     * @Then /^I wait until the SpeakUp audio prompt is uploaded$/
     */
    public function i_wait_until_the_speakup_audio_prompt_is_uploaded(): void {
        $this->recording_uploaded('#qtype_speakup-audioprompt .qtype_speakup-audio-widget');
    }

    /**
     * Wait until a recorder has uploaded its recording.
     *
     * @param string $selector CSS selector for the recorder.
     */
    protected function recording_uploaded(string $selector): void {
        $selector = json_encode($selector);
        $this->spin(function() use ($selector) {
            return $this->evaluate_script("return (function() {
                const widget = document.querySelector($selector);
                const button = widget && widget.querySelector('button.qtype_speakup-main-button');
                return !!widget && widget.dataset.state === 'recorded' && !!button && !button.disabled;
            })();");
        }, [], 60);
        $this->wait_for_pending_js();
    }


    /**
     * Mute or unmute the microphone, e.g. to test stopping after a silence.
     * Muting before recording starts makes the recording start silent.
     *
     * @When /^I (?P<action>mute|unmute) the SpeakUp microphone$/
     * @param string $action
     */
    public function i_mute_the_speakup_microphone(string $action): void {
        $enabled = ($action === 'unmute') ? 'true' : 'false';
        $this->execute_script("
            (function() {
                window.qtypeSpeakupMicEnabled = $enabled;
                if (!window.qtypeSpeakupStreams) {
                    // Keep the streams the recorder gets, so they can be muted.
                    window.qtypeSpeakupStreams = [];
                    const getUserMedia = navigator.mediaDevices.getUserMedia.bind(navigator.mediaDevices);
                    navigator.mediaDevices.getUserMedia = (constraints) => getUserMedia(constraints).then((stream) => {
                        stream.getAudioTracks().forEach((track) => {
                            track.enabled = window.qtypeSpeakupMicEnabled;
                        });
                        window.qtypeSpeakupStreams.push(stream);
                        return stream;
                    });
                }
                window.qtypeSpeakupStreams.forEach((stream) => stream.getAudioTracks().forEach((track) => {
                    track.enabled = window.qtypeSpeakupMicEnabled;
                }));
            })();
        ");
    }

    /**
     * Upload a file as the response, to a question that asks for an uploaded file.
     *
     * @When /^I upload "(?P<filepath>[^"]+)" as the SpeakUp response$/
     * @param string $filepath relative to Moodle's root directory.
     */
    public function i_upload_as_the_speakup_response(string $filepath): void {
        global $CFG;
        $input = $this->find('css', '.que.speakup .qtype_speakup-upload-input');
        $input->attachFile($CFG->dirroot . '/' . $filepath);
        $this->spin(function() {
            return $this->evaluate_script("return !!document.querySelector(
                    '.que.speakup .qtype_speakup-upload-player:not(.hide)');");
        }, [], 60);
        $this->wait_for_pending_js();
    }

    /**
     * Compare the widths of two fields, e.g. to check a phrase box grows to fit its text.
     *
     * @Then /^the "(?P<first>[^"]+)" field should be wider than the "(?P<second>[^"]+)" field$/
     * @param string $first the id of the first field.
     * @param string $second the id of the second field.
     */
    public function the_field_should_be_wider_than(string $first, string $second): void {
        $widths = $this->evaluate_script('return [document.getElementById(' . json_encode($first) . ').offsetWidth, ' .
                'document.getElementById(' . json_encode($second) . ').offsetWidth];');
        if ($widths[0] <= $widths[1]) {
            throw new ExpectationException("\"$first\" is {$widths[0]}px wide, and \"$second\" is {$widths[1]}px wide.",
                    $this->getSession());
        }
    }

    /**
     * Check the height of an element, e.g. to check an editor grows to fit its content.
     *
     * @Then /^the "(?P<selector>(?:[^"]|\\")*)" "css_element" should be (?P<comparison>taller|shorter) than (?P<pixels>\d+) pixels$/
     * @param string $selector CSS selector.
     * @param string $comparison "taller" or "shorter".
     * @param int $pixels
     */
    public function the_element_should_be_taller_than(string $selector, string $comparison, int $pixels): void {
        $selector = json_encode(str_replace('\\"', '"', $selector));
        $this->spin(function() use ($selector, $comparison, $pixels) {
            $height = $this->evaluate_script("return (function() {
                const element = document.querySelector($selector);
                return element ? element.getBoundingClientRect().height : -1;
            })();");
            if ($height < 0) {
                throw new ExpectationException("There is no element $selector.", $this->getSession());
            }
            if (($comparison === 'taller') ? ($height > $pixels) : ($height < $pixels)) {
                return true;
            }
            throw new ExpectationException("$selector is {$height}px tall, not $comparison than {$pixels}px.",
                    $this->getSession());
        }, [], 10);
    }

    /**
     * Fill a text area with some lines of text, as if the user typed them.
     *
     * @When /^I fill the "(?P<id>[^"]+)" text area with (?P<lines>\d+) lines of text$/
     * @param string $id the id of the text area.
     * @param int $lines
     */
    public function i_fill_the_text_area_with_lines_of_text(string $id, int $lines): void {
        $this->execute_script("(function() {
            const textarea = document.getElementById(" . json_encode($id) . ");
            const lines = [];
            for (let i = 1; i <= $lines; i++) {
                lines.push('Line ' + i + ' of some text.');
            }
            textarea.value = lines.join(String.fromCharCode(10));
            textarea.dispatchEvent(new Event('input', {bubbles: true}));
        })();");
    }

    /**
     * Upload an image or PDF as a page of an image response.
     *
     * @When /^I upload "(?P<filepath>[^"]+)" as a SpeakUp page$/
     * @param string $filepath relative to Moodle's root directory.
     */
    public function i_upload_as_a_speakup_page(string $filepath): void {
        global $CFG;
        $count = 'return document.querySelectorAll(".que.speakup .qtype_speakup-image-item").length;';
        $before = (int) $this->evaluate_script($count);
        $input = $this->find('css', '.que.speakup .qtype_speakup-image-input');
        $input->attachFile($CFG->dirroot . '/' . $filepath);
        $this->spin(function() use ($count, $before) {
            return (int) $this->evaluate_script($count) > $before;
        }, [], 60);
        $this->wait_for_pending_js();
    }

    /**
     * Open the response page that the Moodle app shows in a frame (see
     * respond.php), for a SpeakUp question in a user's quiz attempt, with a
     * single-use key for a user (usually the same user), as the app does.
     *
     * @When /^I open the SpeakUp app response page for "(?P<question>[^"]+)" in quiz "(?P<quiz>[^"]+)" attempted by "(?P<username>[^"]+)" with a key for "(?P<keyuser>[^"]+)"$/
     * @param string $questionname
     * @param string $quizname
     * @param string $username the user who started the attempt.
     * @param string $keyusername the user the key is for.
     */
    public function i_open_the_speakup_app_response_page(string $questionname, string $quizname, string $username,
            string $keyusername): void {
        global $DB;
        $quiz = $DB->get_record('quiz', ['name' => $quizname], '*', MUST_EXIST);
        $user = $DB->get_record('user', ['username' => $username], '*', MUST_EXIST);
        $keyuser = $DB->get_record('user', ['username' => $keyusername], '*', MUST_EXIST);
        $attempts = $DB->get_records('quiz_attempts', ['quiz' => $quiz->id, 'userid' => $user->id], 'attempt DESC', '*', 0, 1);
        $attempt = reset($attempts);
        if (!$attempt) {
            throw new Exception("$username has not started an attempt at $quizname.");
        }
        $slot = $DB->get_field_sql("
                SELECT qa.slot
                  FROM {question_attempts} qa
                  JOIN {question} q ON q.id = qa.questionid
                 WHERE qa.questionusageid = ? AND q.name = ?", [$attempt->uniqueid, $questionname], MUST_EXIST);
        $key = \qtype_speakup\mobile_support::create_response_key($keyuser->id, $attempt->uniqueid);
        // Any draft item id: the response goes into the user's own draft area with this id.
        $url = new moodle_url('/question/type/speakup/respond.php',
                ['key' => $key, 'usage' => $attempt->uniqueid, 'slot' => $slot, 'draft' => 987654321]);
        $this->responseurl = $url;
        $this->execute('behat_general::i_visit', [$url->out_as_local_url(false)]);
    }

    /** @var moodle_url|null the last response page opened. */
    protected $responseurl = null;

    /**
     * Show the response page in a frame on another page, resized as the
     * Moodle app does (see mobile/mobile.js), to check how it behaves there.
     *
     * @When /^I open the SpeakUp app response page for "(?P<question>[^"]+)" in quiz "(?P<quiz>[^"]+)" attempted by "(?P<username>[^"]+)" in a frame$/
     * @param string $questionname
     * @param string $quizname
     * @param string $username
     */
    public function i_open_the_speakup_app_response_page_in_a_frame(string $questionname, string $quizname,
            string $username): void {
        // Get the response page's address (with a key), then show a plain page around it.
        $this->i_open_the_speakup_app_response_page($questionname, $quizname, $username, $username);
        $key = \qtype_speakup\mobile_support::create_response_key(
                (int) $this->get_user_id($username), (int) $this->responseurl->get_param('usage'));
        $this->responseurl->param('key', $key);
        $this->execute('behat_general::i_visit', ['/question/type/speakup/tests/fixtures/frame.html']);
        $this->execute_script("(function() {
            const iframe = document.createElement('iframe');
            iframe.id = 'qtype-speakup-test-frame';
            iframe.name = 'qtype-speakup-test-frame';
            iframe.style.width = '100%';
            iframe.style.border = '0';
            iframe.src = " . json_encode($this->responseurl->out(false)) . ";
            window.addEventListener('message', (e) => {
                if (e.data && e.data.type === 'qtype_speakup' && e.data.height) {
                    iframe.style.height = (Math.ceil(e.data.height) + 16) + 'px';
                }
            });
            document.body.appendChild(iframe);
        })();");
        $this->getSession()->switchToIFrame('qtype-speakup-test-frame');
        $this->wait_for_pending_js();
        $this->getSession()->switchToIFrame(null);
    }

    /**
     * Check that the response page's frame keeps the same height (it does
     * not keep growing as it is resized).
     *
     * @Then /^the SpeakUp app frame should keep the same height$/
     */
    public function the_speakup_app_frame_should_keep_the_same_height(): void {
        $height = 'return document.getElementById("qtype-speakup-test-frame").offsetHeight;';
        // Let the page report its height first.
        sleep(2);
        $first = (int) $this->evaluate_script($height);
        sleep(3);
        $second = (int) $this->evaluate_script($height);
        if ($first < 50 || $second !== $first) {
            throw new ExpectationException("The frame was {$first}px tall, and then {$second}px.", $this->getSession());
        }
    }

    /**
     * Get a user's id.
     *
     * @param string $username
     * @return int
     */
    protected function get_user_id(string $username): int {
        global $DB;
        return (int) $DB->get_field('user', 'id', ['username' => $username], MUST_EXIST);
    }

    /**
     * Give the response page the user's web service token, as the Moodle
     * app does (see mobile/mobile.js), so that it can upload files.
     *
     * @When /^the Moodle app gives the SpeakUp response page a token for "(?P<username>[^"]+)"$/
     * @param string $username
     */
    public function the_moodle_app_gives_the_speakup_response_page_a_token(string $username): void {
        global $CFG, $DB;
        // Turn on the web services that the app uses.
        set_config('enablewebservices', 1);
        set_config('enablemobilewebservice', 1);
        set_config('webserviceprotocols', 'rest');
        $DB->set_field('external_services', 'enabled', 1, ['shortname' => MOODLE_OFFICIAL_MOBILE_SERVICE]);
        assign_capability('webservice/rest:use', CAP_ALLOW, $CFG->defaultuserroleid, context_system::instance()->id, true);

        $user = $DB->get_record('user', ['username' => $username], '*', MUST_EXIST);
        $service = $DB->get_record('external_services', ['shortname' => MOODLE_OFFICIAL_MOBILE_SERVICE], '*', MUST_EXIST);
        $token = \core_external\util::generate_token(EXTERNAL_TOKEN_PERMANENT, $service, $user->id,
                context_system::instance());
        $this->execute_script("require(['qtype_speakup/draftfiles'], function(draftfiles) {
            draftfiles.setToken(" . json_encode($token) . ");
        });");
        $this->wait_for_pending_js();
    }

    /**
     * Check that nothing in the SpeakUp question goes past the right edge
     * of the window, e.g. on a phone in portrait.
     *
     * @Then /^nothing in the SpeakUp question should be wider than the window$/
     */
    public function nothing_in_the_speakup_question_should_be_wider_than_the_window(): void {
        $overflow = $this->evaluate_script("return (function() {
            const width = document.documentElement.clientWidth;
            const wide = [];
            document.querySelectorAll('.que.speakup *').forEach((element) => {
                const box = element.getBoundingClientRect();
                if (box.width > 0 && box.height > 0 && box.right > width + 1) {
                    wide.push(element.tagName.toLowerCase() + '.' + String(element.className).split(' ')[0] +
                        ' (right edge ' + Math.round(box.right) + 'px)');
                }
            });
            return wide.length ? 'Window ' + width + 'px wide: ' + wide.slice(0, 5).join(', ') : '';
        })();");
        if ($overflow) {
            throw new ExpectationException($overflow, $this->getSession());
        }
    }
}
