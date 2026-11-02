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
 * them, and upload a file to it. Used by the student's upload widgets
 * (qtype_speakup/fileupload and qtype_speakup/imageupload), the recorder
 * (qtype_speakup/avrecording) and the audio prompt widget on the question
 * form (qtype_speakup/audioprompt).
 *
 * In a browser, these use the student's session. In the Moodle app, the
 * response is shown in a frame (respond.php) that has no session: there,
 * these use the app's web service token instead, which the app sends to
 * the frame (see qtype_speakup/mobilebridge).
 *
 * @module     qtype_speakup/draftfiles
 * @copyright  2026 Gordon Bateson and contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** @var {Object|null} the app's token and the user's context id, once the app has sent them. */
let mobile = null;

/** @var {Function[]} functions waiting for the app's token. */
let waiting = [];

/**
 * Is this the app's frame, which uses the app's token rather than a session?
 * (respond.php adds an element with the user's context id.)
 *
 * @returns {boolean}
 */
export const isTokenMode = () => !!document.getElementById('qtype_speakup-mobile');

/**
 * Store the app's web service token (see qtype_speakup/mobilebridge).
 *
 * @param {string} token
 */
export const setToken = (token) => {
    const element = document.getElementById('qtype_speakup-mobile');
    mobile = {token: token, userContextId: element ? element.dataset.usercontextid : ''};
    waiting.forEach((resolve) => resolve(mobile));
    waiting = [];
};

/**
 * Wait for the app's token, if this is the app's frame.
 *
 * @returns {Promise<Object|null>} the token and user context id, or null in a browser.
 */
export const getToken = () => {
    if (!isTokenMode()) {
        return Promise.resolve(null);
    }
    if (mobile) {
        return Promise.resolve(mobile);
    }
    return new Promise((resolve) => waiting.push(resolve));
};

/**
 * Turn a site file URL (pluginfile.php or draftfile.php) into one that
 * works with the app's token, in the app's frame.
 *
 * @param {string} url
 * @returns {string} the URL, unchanged in a browser or if it is not a site file.
 */
export const tokenFileUrl = (url) => {
    if (!mobile || !url) {
        return url;
    }
    const root = M.cfg.wwwroot;
    let result = url;
    ['pluginfile.php', 'draftfile.php'].forEach((script) => {
        if (result.indexOf(root + '/' + script + '/') === 0) {
            result = root + '/webservice/' + script + result.substring(root.length + script.length + 1);
        }
    });
    if (result === url) {
        return url;
    }
    return result + (result.indexOf('?') >= 0 ? '&' : '?') + 'token=' + encodeURIComponent(mobile.token);
};

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
 * Call a web service function with the app's token.
 *
 * @param {string} name e.g. "core_files_get_files"
 * @param {Object} params the function's parameters, already flattened (e.g. "files[0][filename]").
 * @returns {Promise<Object>}
 */
const callWebService = async(name, params) => {
    const info = await getToken();
    const body = new URLSearchParams(Object.assign({wstoken: info.token, wsfunction: name, moodlewsrestformat: 'json'}, params));
    const response = await fetch(M.cfg.wwwroot + '/webservice/rest/server.php', {method: 'POST', body: body});
    const result = await response.json();
    if (result && result.exception) {
        throw new Error(result.message || result.errorcode);
    }
    return result;
};

/**
 * List the files in a draft file area.
 *
 * @param {number} draftItemId
 * @returns {Promise<Object[]>} each with filename, filepath and url.
 */
export const listFiles = async(draftItemId) => {
    if (isTokenMode()) {
        const info = await getToken();
        const result = await callWebService('core_files_get_files', {
            contextid: info.userContextId, component: 'user', filearea: 'draft',
            itemid: draftItemId, filepath: '/', filename: '',
        });
        return (result.files || []).filter((file) => !file.isdir).map((file) => ({
            filename: file.filename,
            filepath: file.filepath || '/',
            url: tokenFileUrl(M.cfg.wwwroot + '/draftfile.php/' + info.userContextId + '/user/draft/' + draftItemId +
                (file.filepath || '/') + encodeURIComponent(file.filename)),
        }));
    }
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
 */
export const deleteAllFiles = async(draftItemId) => {
    for (const file of await listFiles(draftItemId)) {
        await deleteFile(draftItemId, file.filename, file.filepath);
    }
};

/**
 * Delete one file from a draft file area.
 *
 * @param {number} draftItemId
 * @param {string} filename
 * @param {string} [filepath]
 * @returns {Promise}
 */
export const deleteFile = (draftItemId, filename, filepath) => {
    if (isTokenMode()) {
        return callWebService('core_files_delete_draft_files', {
            draftitemid: draftItemId,
            'files[0][filepath]': filepath || '/',
            'files[0][filename]': filename,
        });
    }
    return postForm('/repository/draftfiles_ajax.php?action=delete', {
        itemid: draftItemId,
        filepath: filepath || '/',
        filename: filename,
    });
};

/**
 * Get the extension of a file name, in lower case, e.g. ".mp3".
 *
 * @param {string} filename
 * @returns {string}
 */
export const getExtension = (filename) => {
    const name = filename.toLowerCase();
    const dot = name.lastIndexOf('.');
    return (dot >= 0) ? name.substring(dot) : '';
};

/**
 * Get the URL and form data for uploading a file to a draft file area. In
 * the app's frame, a file with the same name is deleted first, since the
 * web service upload does not replace files.
 *
 * @param {Blob} file
 * @param {string} filename
 * @param {Object} settings with uploadRepositoryId, draftItemId, contextId and acceptedTypes.
 * @returns {Promise<Object>} with url and formData.
 */
export const getUploadRequest = async(file, filename, settings) => {
    const formData = new FormData();
    if (isTokenMode()) {
        const info = await getToken();
        const existing = await listFiles(settings.draftItemId);
        if (existing.some((f) => f.filename === filename)) {
            await deleteFile(settings.draftItemId, filename, '/');
        }
        formData.append('token', info.token);
        formData.append('itemid', settings.draftItemId);
        formData.append('filepath', '/');
        formData.append('file_1', file, filename);
        return {url: M.cfg.wwwroot + '/webservice/upload.php', formData: formData};
    }
    formData.append('repo_upload_file', file, filename);
    formData.append('sesskey', M.cfg.sesskey);
    formData.append('repo_id', settings.uploadRepositoryId);
    formData.append('itemid', settings.draftItemId);
    formData.append('savepath', '/');
    formData.append('ctx_id', settings.contextId);
    formData.append('title', filename);
    formData.append('overwrite', '1');
    (settings.acceptedTypes || []).forEach((type) => formData.append('accepted_types[]', type));
    return {url: M.cfg.wwwroot + '/repository/repository_ajax.php?action=upload', formData: formData};
};

/**
 * Did an upload fail? Moodle sends errors with a 200 status code, and the
 * web service upload gives each file its own error.
 *
 * @param {*} response the JSON reply.
 * @returns {boolean}
 */
export const isUploadError = (response) => {
    if (!response || response.error || response.errorcode) {
        return true;
    }
    return Array.isArray(response) && (response.length === 0 || response.some((file) => file.error));
};

/**
 * Upload a file to a draft file area.
 *
 * @param {Blob} file
 * @param {string} filename
 * @param {Object} settings with uploadRepositoryId, draftItemId, contextId and acceptedTypes.
 * @param {Function} [onProgress] called with the percent uploaded.
 * @returns {Promise<Object>} the reply.
 */
export const uploadFile = async(file, filename, settings, onProgress) => {
    const upload = await getUploadRequest(file, filename, settings);
    return new Promise((resolve, reject) => {
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
            if (request.status === 200 && !isUploadError(response)) {
                resolve(response);
            } else {
                reject(new Error('Upload failed'));
            }
        });
        request.addEventListener('error', () => reject(new Error('Upload failed')));
        request.addEventListener('abort', () => reject(new Error('Upload aborted')));
        request.open('POST', upload.url);
        request.send(upload.formData);
    });
};
