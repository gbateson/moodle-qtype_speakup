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
 * The audio prompt widget on the question form (see
 * templates/audioprompt_editor.mustache). The teacher can record the
 * prompt, upload an audio file, or delete the prompt. All three work on the
 * form's "audioprompt" draft file area, which holds at most one file and is
 * saved with the question, just as a file picker would be.
 *
 * While the teacher records, the browser's speech recognition (if any)
 * writes a transcript of the prompt into the "audioprompttranscript" box,
 * which the teacher can then correct.
 *
 * @module     qtype_speakup/audioprompt
 * @copyright  2026 Gordon Bateson and contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {initRecorder} from 'qtype_speakup/avrecording';
import {deleteAllFiles, getExtension, listFiles, uploadFile} from 'qtype_speakup/draftfiles';

/**
 * Initialise the audio prompt widget.
 *
 * @param {string} containerId id of the widget's outer element.
 * @param {Object} settings with audioBitRate, uploadRepositoryId,
 *      contextId, maxUploadSize and acceptedTypes (file extensions such as
 *      ".mp3"). The draft item id is read from the form's hidden
 *      "audioprompt" field, which is only filled in once the form has its data.
 */
export const init = async(containerId, settings) => {
    const container = document.getElementById(containerId);
    const form = container ? container.closest('form') : null;
    const draftField = form ? form.querySelector('input[name="audioprompt"]') : null;
    if (!container || !draftField) {
        return;
    }
    settings.draftItemId = draftField.value;

    const widget = container.querySelector('.qtype_speakup-audio-widget');
    const recordButton = widget.querySelector('button.qtype_speakup-main-button');
    const player = widget.querySelector('.qtype_speakup-media-player');
    const audio = player.querySelector('audio');
    const placeholder = widget.querySelector('.qtype_speakup-no-recording-placeholder');
    const uploadButton = container.querySelector('.qtype_speakup-audioprompt-upload');
    const deleteButton = container.querySelector('.qtype_speakup-audioprompt-delete');
    const input = container.querySelector('.qtype_speakup-audioprompt-input');
    const message = container.querySelector('.qtype_speakup-audioprompt-message');

    watchRecording(form, widget);

    initRecorder(widget, settings, {
        // A new recording replaces any earlier prompt, whatever its name.
        beforeUpload: () => deleteAllFiles(settings.draftItemId),
        onButtonStatesChanged: () => showPrompt(widget.dataset.state === 'recorded'),
    });

    uploadButton.addEventListener('click', () => input.click());
    input.addEventListener('change', () => {
        const file = input.files[0];
        // Clear the input, so choosing the same file again still triggers "change".
        input.value = '';
        if (file) {
            uploadPrompt(file);
        }
    });
    deleteButton.addEventListener('click', deletePrompt);

    // Show the prompt that is already in the draft area, if any: the saved
    // prompt, or one recorded or uploaded before the form was reloaded.
    const files = await listFiles(settings.draftItemId);
    if (files.length) {
        audio.src = files[0].url;
        showPrompt(true);
    }

    /**
     * Upload an audio file as the prompt.
     *
     * @param {File} file
     */
    async function uploadPrompt(file) {
        if (settings.acceptedTypes.indexOf(getExtension(file.name)) < 0) {
            showMessage(getString('uploadwrongtypeaudio'), 'text-danger');
            return;
        }
        if (settings.maxUploadSize >= 0 && file.size > settings.maxUploadSize) {
            showMessage(getString('uploadtoobig', Math.round(settings.maxUploadSize / 1048576) + ' MB'), 'text-danger');
            return;
        }
        const pendingKey = 'qtype_speakup-audioprompt-upload';
        M.util.js_pending(pendingKey);
        setButtonsDisabled(true);
        try {
            await deleteAllFiles(settings.draftItemId);
            await uploadFile(file, file.name, settings, (percent) => {
                uploadButton.textContent = getString('uploadprogress', percent + '%');
            });
            audio.src = URL.createObjectURL(file);
            showPrompt(true);
            showMessage(getString('fileuploaded'), 'text-success');
        } catch (error) {
            showMessage(getString('uploadfailed'), 'text-danger');
        } finally {
            uploadButton.textContent = getString('uploadfile');
            setButtonsDisabled(false);
            M.util.js_complete(pendingKey);
        }
    }

    /**
     * Delete the prompt.
     */
    async function deletePrompt() {
        const pendingKey = 'qtype_speakup-audioprompt-delete';
        M.util.js_pending(pendingKey);
        setButtonsDisabled(true);
        try {
            await deleteAllFiles(settings.draftItemId);
            audio.removeAttribute('src');
            audio.load();
            showPrompt(false);
            showMessage(getString('audiopromptdeleted'), 'text-success');
        } catch (error) {
            showMessage(getString('uploadfailed'), 'text-danger');
        } finally {
            setButtonsDisabled(false);
            M.util.js_complete(pendingKey);
        }
    }

    /**
     * Show whether there is a prompt: the player and "Delete" button, or
     * the "no recording" placeholder.
     *
     * @param {boolean} hasPrompt
     */
    function showPrompt(hasPrompt) {
        player.classList.toggle('hide', !hasPrompt);
        placeholder.classList.toggle('hide', hasPrompt);
        deleteButton.classList.toggle('hide', !hasPrompt);
        widget.dataset.state = hasPrompt ? 'recorded' : 'new';
        const label = hasPrompt ? 'recordagainx' : 'startrecordingx';
        recordButton.innerHTML = getString(label,
            '<span class="sr-only visually-hidden">&nbsp;' + widget.dataset.widgetName + '</span>');
    }

    /**
     * Disable or enable the widget's buttons, e.g. while uploading.
     *
     * @param {boolean} disabled
     */
    function setButtonsDisabled(disabled) {
        recordButton.disabled = disabled;
        uploadButton.disabled = disabled;
        deleteButton.disabled = disabled;
    }

    /**
     * Show a message under the buttons.
     *
     * @param {string} text
     * @param {string} cssClass e.g. "text-danger"
     */
    function showMessage(text, cssClass) {
        message.textContent = text;
        message.className = 'qtype_speakup-audioprompt-message small mt-1 ' + cssClass;
    }

    /**
     * Get a string from this plugin's language pack.
     *
     * @param {string} name
     * @param {*} [a]
     * @return {string}
     */
    function getString(name, a) {
        return M.util.get_string(name, 'qtype_speakup', a);
    }
};

/**
 * While the prompt is being recorded, transcribe it with the browser's
 * speech recognition (if the browser has it), into the form's
 * "audioprompttranscript" box. A new recording starts a new transcript; a
 * pause and resume carries on with the same one.
 *
 * @param {HTMLFormElement} form
 * @param {HTMLElement} widget the recorder widget, whose data-state changes
 *      as it records (see qtype_speakup/avrecording).
 */
const watchRecording = (form, widget) => {
    const SpeechRecognitionApi = window.SpeechRecognition || window.webkitSpeechRecognition;
    const box = form.querySelector('textarea[name="audioprompttranscript"]');
    if (!SpeechRecognitionApi || !box) {
        return;
    }
    const langField = form.querySelector('input[name="sttlanguage"]');
    let recognition = null;
    let finalText = '';
    let previousState = widget.dataset.state;

    const start = () => {
        recognition = new SpeechRecognitionApi();
        recognition.lang = (langField && langField.value) ? langField.value : 'en-US';
        recognition.continuous = true;
        recognition.interimResults = true;
        recognition.onresult = (event) => {
            let interim = '';
            for (let i = event.resultIndex; i < event.results.length; i++) {
                const text = event.results[i][0].transcript;
                if (event.results[i].isFinal) {
                    finalText += text.trim() + ' ';
                } else {
                    interim += text;
                }
            }
            box.value = (finalText + interim).trim();
        };
        // Browsers stop listening after a silence. Carry on while recording.
        recognition.onend = () => {
            if (recognition && widget.dataset.state === 'recording') {
                try {
                    recognition.start();
                } catch (e) {
                    recognition = null;
                }
            }
        };
        try {
            recognition.start();
        } catch (e) {
            recognition = null;
        }
    };

    const stop = () => {
        const current = recognition;
        recognition = null;
        if (current) {
            current.stop();
        }
    };

    new MutationObserver(() => {
        const state = widget.dataset.state;
        if (state === 'recording' && !recognition) {
            if (previousState !== 'paused') {
                // A new recording: start a new transcript.
                finalText = '';
                box.value = '';
            }
            start();
        } else if (state !== 'recording' && recognition) {
            stop();
        }
        previousState = state;
    }).observe(widget, {attributes: true, attributeFilter: ['data-state']});
};
