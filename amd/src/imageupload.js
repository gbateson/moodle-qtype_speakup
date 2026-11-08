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
 * order as pages, each with a "Remove" button, and images with buttons to
 * rotate them. Files are uploaded as they are, unless they are bigger than
 * the upload limit: then photos are made smaller until they fit, and other
 * files get a warning. HEIC photos (from iPhones) are always turned into
 * JPEG, since most browsers cannot show them. The files go into the
 * response's draft file area, and are named so that they sort in the order
 * they were uploaded, e.g. "01-scan.jpg".
 *
 * @module     qtype_speakup/imageupload
 * @copyright  2026 Gordon Bateson and contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {deleteFile, getExtension, listFiles, uploadFile} from 'qtype_speakup/draftfiles';

/** Image types that can be made smaller or rotated. (Not GIF, which may be animated, or SVG.) */
const SHRINKABLE = ['image/jpeg', 'image/png', 'image/webp', 'image/bmp', 'image/heic', 'image/heif'];

/** Image types that most browsers cannot show, so are always turned into JPEG. */
const CONVERT = ['image/heic', 'image/heif'];

/** Uploaded files that can be rotated, by extension. */
const ROTATABLE = ['.jpg', '.jpeg', '.png', '.webp', '.bmp'];

/**
 * The longest side, in pixels, to try when making a photo smaller, largest
 * first. (Phones cannot draw much more than 4096 x 4096 pixels.)
 */
const SIDES = [4096, 3200, 2400, 2000, 1600, 1280, 1024];

/** The JPEG qualities to try at each size. */
const QUALITIES = [0.85, 0.7];

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
        const rotate = e.target.closest('.qtype_speakup-image-rotate');
        if (rotate && !rotate.disabled) {
            rotateFile(rotate.dataset.filename, parseInt(rotate.dataset.degrees, 10));
        }
    });

    // When a page was last changed, so that its new picture is shown, not the browser's saved copy.
    const versions = {};

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
                image.src = versions[file.filename] ? addParam(file.url, 'v', versions[file.filename]) : file.url;
                image.alt = label;
                link.appendChild(image);
            }
            item.appendChild(link);

            if (ROTATABLE.indexOf(getExtension(file.filename)) >= 0 && window.createImageBitmap) {
                const rotate = document.createElement('div');
                rotate.className = 'qtype_speakup-image-rotate-buttons';
                [[-90, 'left'], [90, 'right']].forEach(([degrees, direction]) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'qtype_speakup-image-rotate btn btn-link btn-sm p-0 mx-1';
                    button.dataset.filename = file.filename;
                    button.dataset.degrees = degrees;
                    button.title = getString('rotate' + direction);
                    button.setAttribute('aria-label', getString('rotate' + direction + 'x', index + 1));
                    const icon = document.createElement('i');
                    icon.className = 'icon fa fa-fw fa-rotate-' + direction + ' m-0';
                    icon.setAttribute('aria-hidden', 'true');
                    button.appendChild(icon);
                    rotate.appendChild(button);
                });
                item.appendChild(rotate);
            }

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
        const notices = [];
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
                const original = file;
                file = await fitImage(file, settings.maxUploadSize);
                if (settings.maxUploadSize >= 0 && file.size > settings.maxUploadSize) {
                    const a = {name: original.name, size: displaySize(original.size), limit: displaySize(settings.maxUploadSize)};
                    warnings.push(getString(SHRINKABLE.indexOf(original.type) >= 0 ? 'imagetoobig' : 'uploadtoobigpdf', a));
                    continue;
                }
                if (file !== original && original.size > settings.maxUploadSize && settings.maxUploadSize >= 0) {
                    notices.push(getString('imageshrunk', {name: original.name, before: displaySize(original.size),
                        after: displaySize(file.size)}));
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
                showMessage(notices.concat(warnings).join(' '), 'text-danger');
            } else if (notices.length) {
                showMessage(notices.join(' '), 'text-success');
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
     * Rotate one page, and upload it in place of the original.
     *
     * @param {string} filename
     * @param {number} degrees 90 (clockwise) or -90.
     */
    async function rotateFile(filename, degrees) {
        const file = files.find((f) => f.filename === filename);
        if (!file) {
            return;
        }
        M.util.js_pending(pendingKey + '-rotate');
        list.querySelectorAll('.qtype_speakup-image-rotate').forEach((button) => {
            button.disabled = true;
        });
        try {
            const response = await fetch(addParam(file.url, 'v', Date.now()), {credentials: 'same-origin'});
            if (!response.ok) {
                throw new Error('Could not load the picture');
            }
            let rotated = await rotateImage(await response.blob(), degrees, filename);
            rotated = await fitImage(rotated, settings.maxUploadSize);
            if (settings.maxUploadSize >= 0 && rotated.size > settings.maxUploadSize) {
                throw new Error('Too big');
            }
            // A PNG stays a PNG, and anything else becomes a JPEG.
            const newname = rotated.name;
            await uploadFile(rotated, newname, settings);
            if (newname !== filename) {
                await deleteFile(settings.draftItemId, filename);
            }
            versions[newname] = Date.now();
            widget.dispatchEvent(new CustomEvent('qtype_speakup:responsesaved', {bubbles: true}));
            showMessage(getString('pagerotated'), 'text-success');
        } catch (error) {
            showMessage(getString('uploadfailed'), 'text-danger');
        } finally {
            await refresh();
            M.util.js_complete(pendingKey + '-rotate');
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
 * Draw an image on a canvas, the right way up (as phones record it), with
 * its longest side at most a number of pixels, on a white background (for
 * images with transparent parts).
 *
 * @param {ImageBitmap} bitmap
 * @param {number} side the longest side, in pixels.
 * @param {number} [degrees] 0, 90 or -90.
 * @returns {HTMLCanvasElement}
 */
const drawImage = (bitmap, side, degrees = 0) => {
    const scale = Math.min(1, side / Math.max(bitmap.width, bitmap.height));
    const width = Math.max(1, Math.round(bitmap.width * scale));
    const height = Math.max(1, Math.round(bitmap.height * scale));
    const turned = (degrees % 180 !== 0);
    const canvas = document.createElement('canvas');
    canvas.width = turned ? height : width;
    canvas.height = turned ? width : height;
    const context = canvas.getContext('2d');
    context.fillStyle = '#ffffff';
    context.fillRect(0, 0, canvas.width, canvas.height);
    context.translate(canvas.width / 2, canvas.height / 2);
    context.rotate(degrees * Math.PI / 180);
    context.drawImage(bitmap, -width / 2, -height / 2, width, height);
    return canvas;
};

/**
 * Save a canvas as a file.
 *
 * @param {HTMLCanvasElement} canvas
 * @param {string} type "image/jpeg" or "image/png".
 * @param {number} quality for JPEG, 0 to 1.
 * @param {string} name the file's name, whose extension is changed to suit the type.
 * @returns {Promise<File|null>} null if the browser could not do it.
 */
const saveCanvas = async(canvas, type, quality, name) => {
    const blob = await new Promise((resolve) => canvas.toBlob(resolve, type, quality));
    if (!blob) {
        return null;
    }
    const extension = (type === 'image/png') ? '.png' : '.jpg';
    return new File([blob], name.replace(/\.[^.]*$/, '') + extension, {type: type});
};

/**
 * Read an image, the right way up.
 *
 * @param {Blob} blob
 * @returns {Promise<ImageBitmap|null>} null if the browser cannot read it (e.g. HEIC outside Safari).
 */
const readImage = async(blob) => {
    if (!window.createImageBitmap) {
        return null;
    }
    try {
        return await createImageBitmap(blob, {imageOrientation: 'from-image'});
    } catch (e) {
        return null;
    }
};

/**
 * Make a photo small enough to upload, if it is bigger than the upload
 * limit, by making it smaller, then saving it as a JPEG of lower quality,
 * until it fits. HEIC photos are always turned into JPEG. Other files, and
 * photos that are small enough, are returned as they are, as is the
 * original if the browser cannot read it, or cannot make it small enough.
 *
 * @param {File} file
 * @param {number} maxBytes the upload limit, or -1 for no limit.
 * @returns {Promise<File>}
 */
const fitImage = async(file, maxBytes) => {
    const tooBig = (maxBytes >= 0 && file.size > maxBytes);
    const convert = (CONVERT.indexOf(file.type) >= 0);
    if (SHRINKABLE.indexOf(file.type) < 0 || (!tooBig && !convert)) {
        return file;
    }
    const bitmap = await readImage(file);
    if (!bitmap) {
        return file;
    }
    try {
        const longest = Math.max(bitmap.width, bitmap.height);
        const sides = [Math.min(longest, SIDES[0])].concat(SIDES.filter((side) => side < Math.min(longest, SIDES[0])));
        for (const side of sides) {
            const canvas = drawImage(bitmap, side);
            for (const quality of QUALITIES) {
                const result = await saveCanvas(canvas, 'image/jpeg', quality, file.name);
                if (result && (maxBytes < 0 || result.size <= maxBytes)) {
                    return result;
                }
            }
        }
        return file;
    } finally {
        bitmap.close();
    }
};

/**
 * Rotate an image by a quarter turn.
 *
 * @param {Blob} blob
 * @param {number} degrees 90 (clockwise) or -90.
 * @param {string} name the file's name.
 * @returns {Promise<File>} a PNG if the image was a PNG, otherwise a JPEG.
 */
const rotateImage = async(blob, degrees, name) => {
    const bitmap = await readImage(blob);
    if (!bitmap) {
        throw new Error('Could not read the picture');
    }
    try {
        const canvas = drawImage(bitmap, Math.min(Math.max(bitmap.width, bitmap.height), SIDES[0]), degrees);
        const type = (blob.type === 'image/png' || getExtension(name) === '.png') ? 'image/png' : 'image/jpeg';
        const result = await saveCanvas(canvas, type, 0.92, name);
        if (!result) {
            throw new Error('Could not save the picture');
        }
        return result;
    } finally {
        bitmap.close();
    }
};

/**
 * Add a parameter to a URL, e.g. so that the browser fetches a file again.
 *
 * @param {string} url
 * @param {string} name
 * @param {*} value
 * @returns {string}
 */
const addParam = (url, name, value) => url + (url.indexOf('?') >= 0 ? '&' : '?') + name + '=' + encodeURIComponent(value);

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
const displaySize = (bytes) => {
    if (bytes >= 10 * 1048576) {
        return Math.round(bytes / 1048576) + ' MB';
    }
    if (bytes >= 1048576) {
        return (Math.round(bytes / 104857.6) / 10) + ' MB';
    }
    if (bytes >= 1024) {
        return Math.round(bytes / 1024) + ' KB';
    }
    return bytes + ' bytes';
};

/**
 * Get a string from this plugin's language pack.
 *
 * @param {string} name
 * @param {*} [a]
 * @returns {string}
 */
const getString = (name, a) => M.util.get_string(name, 'qtype_speakup', a);
