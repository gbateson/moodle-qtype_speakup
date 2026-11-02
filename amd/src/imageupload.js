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
 * Upload widget for image responses, e.g. scans of handwriting (see
 * templates/image_uploader.mustache).
 *
 * Students upload up to a set number of images or PDFs, which are shown in
 * order as pages, each with a "Remove" button. Large photos are made
 * smaller before they are uploaded, which is quicker and still easy to read.
 * The files go into the response's draft file area, and are named so that
 * they sort in the order they were uploaded, e.g. "01-scan.jpg".
 *
 * @module     qtype_speakup/imageupload
 * @copyright  2026 Gordon Bateson and contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {deleteFile, getExtension, listFiles, uploadFile} from 'qtype_speakup/draftfiles';

/** Photos wider or taller than this many pixels are made smaller. */
const MAX_DIMENSION = 2000;

/** Photos bigger than this many bytes are made smaller, even if they are not too wide or tall. */
const MAX_BYTES = 2 * 1024 * 1024;

/** Image types that can be made smaller. (Not GIF, which may be animated, or SVG.) */
const SHRINKABLE = ['image/jpeg', 'image/png', 'image/webp', 'image/bmp', 'image/heic', 'image/heif'];

/**
 * Initialise the upload widget in one question.
 *
 * @param {string} questionId id of the outer question div.
 * @param {Object} settings with uploadRepositoryId, contextId, draftItemId,
 *      maxUploadSize (bytes, or -1 for no limit), acceptedTypes (file
 *      extensions such as ".jpg") and maxFiles.
 */
export const init = (questionId, settings) => {
    const questionDiv = document.getElementById(questionId);
    const widget = questionDiv ? questionDiv.querySelector('.qtype_speakup-image-widget') : null;
    if (!widget) {
        return;
    }
    const input = widget.querySelector('.qtype_speakup-image-input');
    const button = widget.querySelector('.qtype_speakup-image-button');
    const list = widget.querySelector('.qtype_speakup-image-list');
    const placeholder = widget.querySelector('.qtype_speakup-no-recording-placeholder');
    const message = widget.querySelector('.qtype_speakup-image-message');
    const pendingKey = 'qtype_speakup-imageupload-' + questionId;
    let files = [];

    button.addEventListener('click', () => input.click());
    input.addEventListener('change', () => {
        const chosen = Array.from(input.files);
        // Clear the input, so choosing the same file again still triggers "change".
        input.value = '';
        if (chosen.length) {
            uploadFiles(chosen);
        }
    });
    list.addEventListener('click', (e) => {
        const remove = e.target.closest('.qtype_speakup-image-remove');
        if (remove) {
            removeFile(remove.dataset.filename);
        }
    });

    refresh();

    /**
     * Show the files in the draft area, in order.
     */
    async function refresh() {
        M.util.js_pending(pendingKey + '-list');
        try {
            files = (await listFiles(settings.draftItemId)).sort((a, b) => a.filename.localeCompare(b.filename));
            render();
        } finally {
            M.util.js_complete(pendingKey + '-list');
        }
    }

    /**
     * Show the list of pages, and the upload button if more can be added.
     */
    function render() {
        list.innerHTML = '';
        files.forEach((file, index) => {
            const label = getString('pagex', index + 1);
            const item = document.createElement('li');
            item.className = 'qtype_speakup-image-item card p-1 me-2 mb-2 text-center';
            item.dataset.filename = file.filename;

            const heading = document.createElement('div');
            heading.className = 'small fw-bold';
            heading.textContent = label;
            item.appendChild(heading);

            const link = document.createElement('a');
            link.href = file.url;
            link.target = '_blank';
            link.rel = 'noopener';
            if (getExtension(file.filename) === '.pdf') {
                link.className = 'qtype_speakup-image-pdf d-block py-3';
                link.textContent = displayName(file.filename);
            } else {
                const image = document.createElement('img');
                image.className = 'qtype_speakup-image-thumb';
                image.src = file.url;
                image.alt = label;
                link.appendChild(image);
            }
            item.appendChild(link);

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'qtype_speakup-image-remove btn btn-link btn-sm text-danger p-0';
            remove.dataset.filename = file.filename;
            remove.textContent = getString('removepage');
            remove.setAttribute('aria-label', getString('removepagex', index + 1));
            item.appendChild(remove);

            list.appendChild(item);
        });
        placeholder.classList.toggle('hide', files.length > 0);
        button.classList.toggle('d-none', files.length >= settings.maxFiles);
        button.textContent = getString(files.length ? 'uploadanotherimage' : 'uploadimage');
    }

    /**
     * Check, shrink and upload the chosen files, up to the most allowed.
     *
     * @param {File[]} chosen
     */
    async function uploadFiles(chosen) {
        const room = settings.maxFiles - files.length;
        const warnings = [];
        if (chosen.length > room) {
            warnings.push(getString('toomanyfiles', settings.maxFiles));
            chosen = chosen.slice(0, Math.max(0, room));
        }

        M.util.js_pending(pendingKey);
        button.disabled = true;
        let uploaded = 0;
        try {
            let number = nextNumber();
            for (let file of chosen) {
                if (settings.acceptedTypes.indexOf(getExtension(file.name)) < 0) {
                    warnings.push(getString('uploadwrongtypeimage') + ' (' + file.name + ')');
                    continue;
                }
                file = await shrinkImage(file);
                if (settings.maxUploadSize >= 0 && file.size > settings.maxUploadSize) {
                    warnings.push(getString('uploadtoobig', displaySize(settings.maxUploadSize)) + ' (' + file.name + ')');
                    continue;
                }
                const filename = String(number).padStart(2, '0') + '-' + safeName(file.name);
                await uploadFile(file, filename, settings, (percent) => {
                    button.textContent = getString('uploadprogress', percent + '%');
                });
                number++;
                uploaded++;
            }
            if (uploaded) {
                // Tell anything that counts responses (see qtype_speakup/promptcontrol).
                widget.dispatchEvent(new CustomEvent('qtype_speakup:responsesaved', {bubbles: true}));
            }
            if (warnings.length) {
                showMessage(warnings.join(' '), 'text-danger');
            } else if (uploaded) {
                showMessage(getString('fileuploaded'), 'text-success');
            }
        } catch (error) {
            showMessage(getString('uploadfailed'), 'text-danger');
        } finally {
            button.disabled = false;
            await refresh();
            M.util.js_complete(pendingKey);
        }
    }

    /**
     * Remove one page.
     *
     * @param {string} filename
     */
    async function removeFile(filename) {
        M.util.js_pending(pendingKey + '-remove');
        try {
            await deleteFile(settings.draftItemId, filename);
            showMessage(getString('pageremoved'), 'text-success');
        } catch (error) {
            showMessage(getString('uploadfailed'), 'text-danger');
        } finally {
            await refresh();
            M.util.js_complete(pendingKey + '-remove');
        }
    }

    /**
     * Get the number for the next file's name: one more than the highest so far.
     *
     * @returns {number}
     */
    function nextNumber() {
        let highest = 0;
        files.forEach((file) => {
            const match = file.filename.match(/^(\d+)-/);
            if (match) {
                highest = Math.max(highest, parseInt(match[1], 10));
            }
        });
        return highest + 1;
    }

    /**
     * Show a message under the pages.
     *
     * @param {string} text
     * @param {string} cssClass e.g. "text-danger"
     */
    function showMessage(text, cssClass) {
        message.textContent = text;
        message.className = 'qtype_speakup-image-message small mb-1 ' + cssClass;
    }
};

/**
 * Make a large photo smaller (as a JPEG), keeping it easy to read. Other
 * files, and photos that are already small, are returned as they are. If
 * the browser cannot read the image (e.g. HEIC outside Safari), the
 * original is returned too.
 *
 * @param {File} file
 * @returns {Promise<File>}
 */
const shrinkImage = async(file) => {
    if (SHRINKABLE.indexOf(file.type) < 0 || !window.createImageBitmap) {
        return file;
    }
    let bitmap;
    try {
        // Turn the photo the right way up, as phones record it.
        bitmap = await createImageBitmap(file, {imageOrientation: 'from-image'});
    } catch (e) {
        return file;
    }
    const scale = Math.min(1, MAX_DIMENSION / Math.max(bitmap.width, bitmap.height));
    if (scale === 1 && file.size <= MAX_BYTES && file.type !== 'image/heic' && file.type !== 'image/heif') {
        bitmap.close();
        return file;
    }
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(bitmap.width * scale);
    canvas.height = Math.round(bitmap.height * scale);
    const context = canvas.getContext('2d');
    // A white background, for images with transparent parts.
    context.fillStyle = '#ffffff';
    context.fillRect(0, 0, canvas.width, canvas.height);
    context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
    bitmap.close();
    const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.85));
    if (!blob || blob.size >= file.size) {
        return file;
    }
    const name = file.name.replace(/\.[^.]*$/, '') + '.jpg';
    return new File([blob], name, {type: 'image/jpeg'});
};

/**
 * Make a file name safe to use in Moodle's file storage.
 *
 * @param {string} name
 * @returns {string}
 */
const safeName = (name) => name.replace(/[^\w.-]+/g, '_').replace(/^_+/, '') || 'file';

/**
 * Show a file's name without the number added when it was uploaded.
 *
 * @param {string} filename
 * @returns {string}
 */
const displayName = (filename) => filename.replace(/^\d+-/, '');

/**
 * Show a number of bytes as e.g. "20 MB".
 *
 * @param {number} bytes
 * @returns {string}
 */
const displaySize = (bytes) => (bytes >= 1048576) ? Math.round(bytes / 1048576) + ' MB' : Math.round(bytes / 1024) + ' KB';

/**
 * Get a string from this plugin's language pack.
 *
 * @param {string} name
 * @param {*} [a]
 * @returns {string}
 */
const getString = (name, a) => M.util.get_string(name, 'qtype_speakup', a);
