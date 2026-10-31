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
 * Controls the audio prompt and the responses in one question, while it is
 * being attempted:
 *
 * - Starts recording when the prompt finishes, if the question says so.
 * - Limits how many times the prompt can be played.
 * - Limits how many responses (recordings or uploads) can be given.
 *
 * The prompt is the audio prompt, if the question has one, or else the
 * first audio player in the question text.
 *
 * The counts are saved with the response, in hidden fields, and also kept
 * in the browser's storage, so that reloading the page doesn't reset them.
 *
 * @module     qtype_speakup/promptcontrol
 * @copyright  2026 Gordon Bateson and contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Initialise the prompt and response controls in one question.
 *
 * @param {string} questionId id of the outer question div.
 * @param {Object} settings with recordAfterPrompt (boolean), and
 *      maxPromptPlays and maxResponses (0 for no limit).
 */
export const init = (questionId, settings) => {
    const questionDiv = document.getElementById(questionId);
    if (!questionDiv) {
        return;
    }
    const playsField = questionDiv.querySelector('.qtype_speakup-promptplays-field');
    const responsesField = questionDiv.querySelector('.qtype_speakup-responsecount-field');
    const message = questionDiv.querySelector('.qtype_speakup-prompt-message');
    const storageKey = 'qtype_speakup-' + questionId;

    // The counts so far: the larger of the saved value and the stored one.
    const stored = readStorage(storageKey);
    let plays = Math.max(parseInt(playsField?.value || '0', 10), stored.plays || 0);
    let responses = Math.max(parseInt(responsesField?.value || '0', 10), stored.responses || 0);
    saveCounts();

    const prompt = questionDiv.querySelector('.qtype_speakup-audio-prompt audio') ||
        questionDiv.querySelector('.qtext audio');

    if (prompt) {
        let midPlay = false;
        prompt.addEventListener('play', () => {
            if (midPlay) {
                return; // Resuming after a pause: still the same play.
            }
            if (settings.maxPromptPlays && plays >= settings.maxPromptPlays) {
                prompt.pause();
                lockPrompt();
                return;
            }
            midPlay = true;
            plays++;
            saveCounts();
        });
        prompt.addEventListener('ended', () => {
            midPlay = false;
            if (settings.maxPromptPlays && plays >= settings.maxPromptPlays) {
                lockPrompt();
            }
            if (settings.recordAfterPrompt) {
                startRecording();
            }
        });
        if (settings.maxPromptPlays && plays >= settings.maxPromptPlays) {
            lockPrompt();
        }
    }

    questionDiv.addEventListener('qtype_speakup:responsesaved', () => {
        responses++;
        saveCounts();
        if (settings.maxResponses && responses >= settings.maxResponses) {
            lockResponses();
        }
    });
    if (settings.maxResponses && responses >= settings.maxResponses) {
        lockResponses();
    }

    /**
     * Start recording, as if the student had clicked the record button. For
     * video, the first click shows the camera, and a second click starts
     * the recording, so that is clicked too, as soon as the camera is ready.
     */
    function startRecording() {
        const widget = questionDiv.querySelector('.qtype_speakup-audio-widget, .qtype_speakup-video-widget');
        const button = widget ? widget.querySelector('button.qtype_speakup-main-button') : null;
        if (!button || button.disabled || button.classList.contains('hide')) {
            return;
        }
        if (widget.dataset.state !== 'new' && widget.dataset.state !== 'recorded') {
            return; // Already recording.
        }
        if (widget.dataset.mediaType === 'video') {
            const observer = new MutationObserver(() => {
                if (widget.dataset.state === 'starting') {
                    observer.disconnect();
                    button.click();
                } else if (widget.dataset.state !== 'new' && widget.dataset.state !== 'recorded') {
                    observer.disconnect();
                }
            });
            observer.observe(widget, {attributes: true, attributeFilter: ['data-state']});
        }
        button.click();
    }

    /**
     * Stop the prompt being played again, and say why.
     */
    function lockPrompt() {
        prompt.controls = false;
        prompt.classList.add('qtype_speakup-prompt-locked');
        showMessage(getString('promptplaysused'));
    }

    /**
     * Stop any more responses being given, and say why.
     */
    function lockResponses() {
        questionDiv.querySelectorAll('button.qtype_speakup-main-button, .qtype_speakup-upload-button')
            .forEach((button) => button.classList.add('hide'));
        showMessage(getString('responsesused'));
    }

    /**
     * Save the counts in the hidden fields and the browser's storage.
     */
    function saveCounts() {
        if (playsField) {
            playsField.value = plays;
        }
        if (responsesField) {
            responsesField.value = responses;
        }
        try {
            window.localStorage.setItem(storageKey, JSON.stringify({plays: plays, responses: responses}));
        } catch (e) {
            // Storage may be unavailable, e.g. in a private window. The hidden fields still work.
        }
    }

    /**
     * Show a message under the prompt.
     *
     * @param {string} text
     */
    function showMessage(text) {
        if (message) {
            message.textContent = text;
            message.classList.remove('hide');
        }
    }
};

/**
 * Read the counts kept in the browser's storage.
 *
 * @param {string} key
 * @return {Object} with plays and responses, if stored.
 */
const readStorage = (key) => {
    try {
        return JSON.parse(window.localStorage.getItem(key) || '{}') || {};
    } catch (e) {
        return {};
    }
};

/**
 * Get a string from this plugin's language pack.
 *
 * @param {string} name
 * @return {string}
 */
const getString = (name) => M.util.get_string(name, 'qtype_speakup');
