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
 * While a response is waiting to be assessed in the background, check now
 * and then whether it has been, and reload the page when it has, so that
 * the grade and feedback appear. (The Moodle app does the same in
 * mobile/mobile.js.)
 *
 * @module     qtype_speakup/assessmentstatus
 * @copyright  2026 Gordon Bateson and contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';

/** The seconds to wait before each check; the last one is repeated. */
const DELAYS = [10, 15, 20, 30, 45, 60];

/** Stop checking after this many minutes: the page can still be reloaded by hand. */
const MAX_MINUTES = 15;

/** Whether the page is already being reloaded, e.g. for another question. */
let reloading = false;

/**
 * Check whether a response has been assessed, now and then, and reload the
 * page when it has.
 *
 * @param {number} questionattemptid
 */
export const init = (questionattemptid) => {
    const stop = Date.now() + MAX_MINUTES * 60 * 1000;
    let count = 0;

    const check = () => {
        if (reloading) {
            return;
        }
        if (document.hidden) {
            // Check again when the page is shown, rather than in the background.
            document.addEventListener('visibilitychange', check, {once: true});
            return;
        }
        Ajax.call([{
            methodname: 'qtype_speakup_get_assessment_status',
            args: {questionattemptid: questionattemptid},
        }])[0].then((result) => {
            if (result.status !== 'pending') {
                reloading = true;
                window.location.reload();
            } else {
                wait();
            }
            return result;
        }).catch(() => {
            // E.g. the session has ended: stop checking.
        });
    };

    const wait = () => {
        if (Date.now() < stop) {
            const delay = DELAYS[Math.min(count++, DELAYS.length - 1)];
            setTimeout(check, delay * 1000);
        }
    };

    wait();
};
