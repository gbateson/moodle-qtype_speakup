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
 * The response part of a SpeakUp question, in a frame in the Moodle app
 * (see respond.php).
 *
 * The frame has no session (phones may not keep cookies in frames), so it
 * asks the app for its web service token, which it then uses to upload and
 * list files (see qtype_speakup/draftfiles) and to play the audio prompt
 * and earlier recordings. The token is only kept in this page.
 *
 * It sends the response's values (e.g. how many times the prompt was
 * played) to the app whenever they change, and the page's height, so that
 * the app can show the whole page. The app sends the values with the quiz
 * (see mobile/mobile.js). These are not secret, so they are posted to the
 * app whatever its address (which differs between Android, iOS and the
 * desktop app); the app checks that they come from this site.
 *
 * @module     qtype_speakup/mobilebridge
 * @copyright  2026 Gordon Bateson and contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {setToken, tokenFileUrl} from 'qtype_speakup/draftfiles';

/** How often to check the values, in milliseconds. */
const INTERVAL = 400;

/**
 * Start talking to the app.
 *
 * @param {string} questionId id of the outer question div.
 * @param {string} prefix the start of the response's field names, e.g. "q123:4_".
 */
export const init = (questionId, prefix) => {
    const questionDiv = document.getElementById(questionId);
    if (!questionDiv || window.parent === window) {
        return;
    }
    const sent = {};
    let sentHeight = 0;

    const send = (message) => {
        window.parent.postMessage(Object.assign({type: 'qtype_speakup', prefix: prefix}, message), '*');
    };

    // Receive the app's token. Only the app (this frame's parent) can send it.
    window.addEventListener('message', (e) => {
        const data = e.data;
        if (e.source !== window.parent || !data || data.type !== 'qtype_speakup_token' || data.prefix !== prefix
                || !data.token) {
            return;
        }
        setToken(String(data.token));
        useTokenForFiles(questionDiv);
    });

    const check = () => {
        const fields = {};
        let changed = false;
        questionDiv.querySelectorAll('input[type=hidden]').forEach((input) => {
            if (input.name.indexOf(prefix) === 0 && !input.name.endsWith(':sequencecheck') && sent[input.name] !== input.value) {
                fields[input.name] = input.value;
                sent[input.name] = input.value;
                changed = true;
            }
        });
        if (changed) {
            send({fields: fields});
        }
        const height = document.documentElement.scrollHeight;
        if (height !== sentHeight) {
            sentHeight = height;
            send({height: height});
        }
    };

    // Ask the app for its token, then keep it up to date.
    send({ready: true});
    check();
    setInterval(check, INTERVAL);
};

/**
 * Make the page's files (the audio prompt, and an earlier recording or
 * images) load with the app's token, since the frame has no session.
 *
 * @param {HTMLElement} questionDiv
 */
const useTokenForFiles = (questionDiv) => {
    questionDiv.querySelectorAll('audio[src], video[src], source[src], img[src]').forEach((element) => {
        const url = tokenFileUrl(element.getAttribute('src'));
        if (url !== element.getAttribute('src')) {
            element.setAttribute('src', url);
            const media = element.closest('audio, video');
            if (media) {
                media.load();
            }
        }
    });
    questionDiv.querySelectorAll('a[href]').forEach((link) => {
        link.setAttribute('href', tokenFileUrl(link.getAttribute('href')));
    });
};
