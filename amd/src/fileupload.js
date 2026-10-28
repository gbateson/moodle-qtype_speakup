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
 * File upload widget for SpeakUp questions whose response method is
 * "Upload" (see templates/file_uploader.mustache).
 *
 * The student chooses an audio or video file. This module checks the file
 * type and size, measures the file's length, and warns (but still uploads)
 * if it is longer than the time limit. It then removes any earlier file
 * from the draft file area and uploads the new one there, which is where
 * a live recording would go, so everything after that works the same.
 *
 * @module     qtype_speakup/fileupload
 * @copyright  2026 Gordon Bateson and contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Initialise the upload widget in one question.
 *
 * @param {string} questionId id of the outer question div.
 * @param {Object} settings with uploadRepositoryId, contextId, draftItemId,
 *      maxUploadSize (bytes, or -1 for no limit), acceptedTypes (file
 *      extensions such as ".mp3"), timeLimit and leeway (seconds).
 */
export const init = (questionId, settings) => {
    const questionDiv = document.getElementById(questionId);
    const widget = questionDiv ? questionDiv.querySelector('.qtype_speakup-upload-widget') : null;
    if (!widget) {
        return;
    }
    const input = widget.querySelector('.qtype_speakup-upload-input');
    const button = widget.querySelector('.qtype_speakup-upload-button');
    const placeholder = widget.querySelector('.qtype_speakup-no-recording-placeholder');
    const player = widget.querySelector('.qtype_speakup-upload-player');
    const audio = player.querySelector('audio');
    const video = player.querySelector('video');
    const message = widget.querySelector('.qtype_speakup-upload-message');
    const durationField = questionDiv.querySelector('.qtype_speakup-browserduration-field');

    button.addEventListener('click', () => input.click());
    input.addEventListener('change', () => {
        const file = input.files[0];
        // Clear the input, so choosing the same file again still triggers "change".
        input.value = '';
        if (file) {
            handleFile(file);
        }
    });

    /**
     * Check, measure and upload the chosen file.
     *
     * @param {File} file
     */
    async function handleFile(file) {
        if (!isMediaFile(file)) {
            showMessage(getString('uploadwrongtype'), 'text-danger');
            return;
        }
        if (settings.maxUploadSize >= 0 && file.size > settings.maxUploadSize) {
            showMessage(getString('uploadtoobig', displaySize(settings.maxUploadSize)), 'text-danger');
            return;
        }

        const pendingKey = 'qtype_speakup-upload-' + questionId;
        M.util.js_pending(pendingKey);
        button.disabled = true;
        try {
            const isVideo = file.type.indexOf('video/') === 0;
            const duration = await measureDuration(file, isVideo);
            let warning = '';
            if (duration !== null && settings.timeLimit > 0 && duration > settings.timeLimit + settings.leeway) {
                // Warn, but still upload: the review page and the AI are told too.
                warning = getString('uploadtoolong', {
                    duration: Math.round(duration),
                    limit: settings.timeLimit,
                });
            }

            await deleteExistingFiles();
            await uploadFile(file);

            showPlayer(file, isVideo);
            if (durationField) {
                durationField.value = (duration === null) ? '' : duration.toFixed(1);
            }
            widget.dataset.state = 'uploaded';
            button.textContent = getString('uploaddifferentfile');
            if (warning) {
                showMessage(warning, 'text-warning');
            } else {
                showMessage(getString('fileuploaded'), 'text-success');
            }
        } catch (error) {
            showMessage(getString('uploadfailed'), 'text-danger');
        } finally {
            button.disabled = false;
            M.util.js_complete(pendingKey);
        }
    }

    /**
     * Is this an audio or video file that Moodle recognises?
     *
     * @param {File} file
     * @return {boolean}
     */
    function isMediaFile(file) {
        const name = file.name.toLowerCase();
        const dot = name.lastIndexOf('.');
        const extension = (dot >= 0) ? name.substring(dot) : '';
        return settings.acceptedTypes.indexOf(extension) >= 0;
    }

    /**
     * Get the length of an audio or video file, in seconds.
     *
     * WebM files made by browsers often don't store their length, so the
     * browser reports it as Infinity. Jumping to the end makes the browser
     * work out the real length.
     *
     * @param {File} file
     * @param {boolean} isVideo
     * @return {Promise<number|null>} the length, or null if unknown.
     */
    function measureDuration(file, isVideo) {
        return new Promise((resolve) => {
            const element = document.createElement(isVideo ? 'video' : 'audio');
            const url = URL.createObjectURL(file);
            let finished = false;
            const finish = (value) => {
                if (!finished) {
                    finished = true;
                    URL.revokeObjectURL(url);
                    resolve((typeof value === 'number' && isFinite(value) && value > 0) ? value : null);
                }
            };
            element.preload = 'metadata';
            element.addEventListener('loadedmetadata', () => {
                if (isFinite(element.duration)) {
                    finish(element.duration);
                } else {
                    element.addEventListener('timeupdate', () => finish(element.duration), {once: true});
                    element.currentTime = Number.MAX_SAFE_INTEGER;
                }
            });
            element.addEventListener('error', () => finish(null));
            setTimeout(() => finish(null), 10000);
            element.src = url;
        });
    }

    /**
     * Remove any earlier file (a recording or an upload) from the draft
     * file area, so the new file is the only one there.
     *
     * @return {Promise}
     */
    async function deleteExistingFiles() {
        const list = await postForm('/repository/draftfiles_ajax.php?action=list', {
            itemid: settings.draftItemId,
            filepath: '/',
        });
        const files = (list && list.list) ? list.list : [];
        for (const file of files) {
            if (file.type === 'folder') {
                continue;
            }
            await postForm('/repository/draftfiles_ajax.php?action=delete', {
                itemid: settings.draftItemId,
                filepath: file.filepath || '/',
                filename: file.filename,
            });
        }
    }

    /**
     * Upload the file to the draft file area, showing the progress.
     *
     * @param {File} file
     * @return {Promise}
     */
    function uploadFile(file) {
        return new Promise((resolve, reject) => {
            const formData = new FormData();
            formData.append('repo_upload_file', file, file.name);
            formData.append('sesskey', M.cfg.sesskey);
            formData.append('repo_id', settings.uploadRepositoryId);
            formData.append('itemid', settings.draftItemId);
            formData.append('savepath', '/');
            formData.append('ctx_id', settings.contextId);
            formData.append('title', file.name);
            formData.append('overwrite', '1');
            settings.acceptedTypes.forEach((type) => formData.append('accepted_types[]', type));

            const request = new XMLHttpRequest();
            request.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) {
                    button.textContent = getString('uploadprogress', Math.round(e.loaded / e.total * 100) + '%');
                }
            });
            request.addEventListener('load', () => {
                let response = null;
                try {
                    response = JSON.parse(request.responseText);
                } catch (e) {
                    response = null;
                }
                // Moodle sends back errors with a 200 status code.
                if (request.status === 200 && response && !response.error && !response.errorcode) {
                    resolve(response);
                } else {
                    reject(new Error('Upload failed'));
                }
            });
            request.addEventListener('error', () => reject(new Error('Upload failed')));
            request.addEventListener('abort', () => reject(new Error('Upload aborted')));
            request.open('POST', M.cfg.wwwroot + '/repository/repository_ajax.php?action=upload');
            request.send(formData);
        });
    }

    /**
     * POST some fields (and the sesskey) to a Moodle script, and return the JSON reply.
     *
     * @param {string} path e.g. "/repository/draftfiles_ajax.php?action=list"
     * @param {Object} fields
     * @return {Promise<Object>}
     */
    async function postForm(path, fields) {
        const body = new URLSearchParams(Object.assign({sesskey: M.cfg.sesskey}, fields));
        const response = await fetch(M.cfg.wwwroot + path, {method: 'POST', body: body, credentials: 'same-origin'});
        return response.json();
    }

    /**
     * Show the uploaded file in the audio or video player.
     *
     * @param {File} file
     * @param {boolean} isVideo
     */
    function showPlayer(file, isVideo) {
        const url = URL.createObjectURL(file);
        audio.classList.toggle('hide', isVideo);
        video.classList.toggle('hide', !isVideo);
        (isVideo ? audio : video).removeAttribute('src');
        (isVideo ? video : audio).src = url;
        player.classList.remove('hide');
        placeholder.classList.add('hide');
    }

    /**
     * Show a message under the player.
     *
     * @param {string} text
     * @param {string} cssClass e.g. "text-danger"
     */
    function showMessage(text, cssClass) {
        message.textContent = text;
        message.className = 'qtype_speakup-upload-message small mb-1 ' + cssClass;
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

    /**
     * Show a number of bytes as e.g. "20 MB".
     *
     * @param {number} bytes
     * @return {string}
     */
    function displaySize(bytes) {
        if (bytes >= 1048576) {
            return Math.round(bytes / 1048576) + ' MB';
        }
        return Math.round(bytes / 1024) + ' KB';
    }
};
