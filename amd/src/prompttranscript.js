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
 * Records that a student showed the collapsed transcript of the audio
 * prompt, by setting the question's hidden "prompttranscriptviewed" field.
 * The field is saved with the response, and the penalty for showing the
 * transcript (if any) is taken off when the response is graded.
 *
 * If the penalty applies, the student is asked to confirm before the
 * transcript is shown the first time.
 *
 * @module     qtype_speakup/prompttranscript
 * @copyright  2026 Gordon Bateson and contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {saveCancelPromise} from 'core/notification';

/**
 * Initialise the transcript in one question.
 *
 * @param {string} questionId id of the outer question div.
 * @param {number} penalty the percent taken off for showing the transcript,
 *      or 0 if there is no penalty for this student (or they have already
 *      shown it), in which case no confirmation is needed.
 */
export const init = (questionId, penalty) => {
    const questionDiv = document.getElementById(questionId);
    if (!questionDiv) {
        return;
    }
    const details = questionDiv.querySelector('details.qtype_speakup-prompt-transcript');
    const field = questionDiv.querySelector('.qtype_speakup-prompttranscriptviewed-field');
    if (!details || !field) {
        return;
    }
    details.addEventListener('toggle', () => {
        if (details.open) {
            field.value = '1';
        }
    });

    // Ask before showing the transcript, if that has a penalty. Once shown,
    // it is not asked again: the penalty is only taken once.
    const summary = details.querySelector('summary');
    let confirmed = !penalty;
    summary.addEventListener('click', (e) => {
        if (confirmed || details.open) {
            return;
        }
        e.preventDefault();
        saveCancelPromise(
            getString('confirmtranscripttitle'),
            getString('confirmtranscript', penalty),
            getString('showtranscript')
        ).then(() => {
            confirmed = true;
            details.open = true;
            return true;
        }).catch(() => {
            // Cancelled: leave the transcript hidden.
            return false;
        });
    });
};

/**
 * Get a string from this plugin's language pack.
 *
 * @param {string} name
 * @param {*} [a]
 * @return {string}
 */
const getString = (name, a) => M.util.get_string(name, 'qtype_speakup', a);
