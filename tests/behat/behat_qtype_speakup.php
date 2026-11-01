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
}
