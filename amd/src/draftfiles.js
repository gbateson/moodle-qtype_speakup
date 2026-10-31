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
 * Helpers for a Moodle draft file area: list the files in it, delete
 * them, and upload a file to it. Used by the student's file upload widget
 * (qtype_speakup/fileupload) and the audio prompt widget on the question
 * form (qtype_speakup/audioprompt).
 *
 * @module     qtype_speakup/draftfiles
 * @copyright  2026 Gordon Bateson and contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * POST some fields (and the sesskey) to a Moodle script, and return the JSON reply.
 *
 * @param {string} path e.g. "/repository/draftfiles_ajax.php?action=list"
 * @param {Object} fields
 * @return {Promise<Object>}
 */
const postForm = async(path, fields) => {
    const body = new URLSearchParams(Object.assign({sesskey: M.cfg.sesskey}, fields));
    const response = await fetch(M.cfg.wwwroot + path, {method: 'POST', body: body, credentials: 'same-origin'});
    return response.json();
};

/**
 * Get the files in a draft file area.
 *
 * @param {number} draftItemId
 * @return {Promise<Object[]>} each with filename, filepath and url (among others).
 */
export const listFiles = async(draftItemId) => {
    const list = await postForm('/repository/draftfiles_ajax.php?action=list', {
        itemid: draftItemId,
        filepath: '/',
    });
    return ((list && list.list) ? list.list : []).filter((file) => file.type !== 'folder');
};

/**
 * Delete all the files in a draft file area.
 *
 * @param {number} draftItemId
 * @return {Promise}
 */
export const deleteAllFiles = async(draftItemId) => {
    for (const file of await listFiles(draftItemId)) {
        await postForm('/repository/draftfiles_ajax.php?action=delete', {
            itemid: draftItemId,
            filepath: file.filepath || '/',
            filename: file.filename,
        });
    }
};

/**
 * Get the extension of a file name, e.g. ".mp3".
 *
 * @param {string} filename
 * @return {string} the extension, in lower case, or "" if there is none.
 */
export const getExtension = (filename) => {
    const name = filename.toLowerCase();
    const dot = name.lastIndexOf('.');
    return (dot >= 0) ? name.substring(dot) : '';
};

/**
 * Upload a file to a draft file area, with Moodle's upload repository.
 *
 * @param {Blob} file the file to upload.
 * @param {string} filename the name to save it as.
 * @param {Object} settings with uploadRepositoryId, contextId, draftItemId,
 *      and acceptedTypes (file extensions such as ".mp3").
 * @param {Function} [onProgress] called with the percentage uploaded so far.
 * @return {Promise<Object>} Moodle's reply.
 */
export const uploadFile = (file, filename, settings, onProgress) => {
    return new Promise((resolve, reject) => {
        const formData = new FormData();
        formData.append('repo_upload_file', file, filename);
        formData.append('sesskey', M.cfg.sesskey);
        formData.append('repo_id', settings.uploadRepositoryId);
        formData.append('itemid', settings.draftItemId);
        formData.append('savepath', '/');
        formData.append('ctx_id', settings.contextId);
        formData.append('title', filename);
        formData.append('overwrite', '1');
        (settings.acceptedTypes || []).forEach((type) => formData.append('accepted_types[]', type));

        const request = new XMLHttpRequest();
        request.upload.addEventListener('progress', (e) => {
            if (onProgress && e.lengthComputable) {
                onProgress(Math.round(e.loaded / e.total * 100));
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
};
