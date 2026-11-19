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
 * Stops students leaving the page while a recording or file is being
 * saved, which would lose it, e.g. by clicking "Next page" just after
 * recording.
 *
 * While anything is being saved, a click on a link or a submit button, or
 * submitting a form, is held back: the page is covered by a "Saving..."
 * layer, and once everything is saved, the click is made again, so the
 * student goes where they meant to. If saving fails, the layer goes, and
 * the student stays on the page, to see the message and try again. Leaving
 * in other ways (e.g. closing the tab) asks the browser to confirm.
 *
 * Each upload calls begin() when it starts (for a recording, as soon as
 * recording stops, since it is converted before it is uploaded), and end()
 * when it has finished.
 *
 * @module     qtype_speakup/uploadguard
 * @copyright  2026 Gordon Bateson and contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** @var {Set<string>} the uploads in progress. */
const active = new Set();

/** @var {boolean} whether any of the current uploads failed. */
let failed = false;

/** @var {HTMLElement|null} the link or button that was clicked (or the form that was submitted) while saving. */
let held = null;

/** @var {HTMLElement|null} the layer over the page. */
let overlay = null;

/** @var {boolean} whether the listeners have been added. */
let installed = false;

/** @var {number} for making ids. */
let counter = 0;

/**
 * Make an id for an upload.
 *
 * @param {string} prefix
 * @return {string}
 */
export const makeId = (prefix) => prefix + '-' + (++counter);

/**
 * Is anything being saved?
 *
 * @return {boolean}
 */
export const isBusy = () => active.size > 0;

/**
 * An upload has started.
 *
 * @param {string} id
 */
export const begin = (id) => {
    install();
    active.add(id);
};

/**
 * An upload has finished. If it was the last one, and a click was held
 * back, it is made again (unless an upload failed).
 *
 * @param {string} id
 * @param {boolean} ok whether it was saved.
 */
export const end = (id, ok = true) => {
    if (!active.delete(id)) {
        return;
    }
    if (!ok) {
        failed = true;
    }
    if (active.size > 0) {
        return;
    }
    const target = held;
    const resume = !failed;
    held = null;
    failed = false;
    hideOverlay();
    if (target && resume) {
        if (target.tagName === 'FORM') {
            target.requestSubmit();
        } else {
            target.click();
        }
    }
};

/**
 * Add the listeners, once.
 */
const install = () => {
    if (installed) {
        return;
    }
    installed = true;
    // In the capture phase, so that clicks are held before anything else
    // (e.g. the quiz navigation) acts on them.
    document.addEventListener('click', handleClick, true);
    document.addEventListener('submit', handleSubmit, true);
    window.addEventListener('beforeunload', (e) => {
        if (isBusy()) {
            e.preventDefault();
            e.returnValue = '';
        }
    });
};

/**
 * Hold back a click that would leave the page.
 *
 * @param {MouseEvent} e
 */
const handleClick = (e) => {
    if (!isBusy() || !e.target.closest) {
        return;
    }
    const target = e.target.closest('a[href], button[type="submit"], input[type="submit"], form button:not([type])');
    if (!target || target.closest('.qtype_speakup-upload-overlay')) {
        return;
    }
    if (target.tagName === 'A') {
        const href = target.getAttribute('href');
        if (href.startsWith('#') || href.startsWith('javascript:') || target.target === '_blank') {
            return;
        }
    }
    e.preventDefault();
    e.stopImmediatePropagation();
    hold(target);
};

/**
 * Hold back a form that is submitted in another way, e.g. by pressing Enter.
 *
 * @param {SubmitEvent} e
 */
const handleSubmit = (e) => {
    if (!isBusy()) {
        return;
    }
    e.preventDefault();
    e.stopImmediatePropagation();
    hold(e.submitter || e.target);
};

/**
 * Remember what was clicked, and cover the page until saving has finished.
 *
 * @param {HTMLElement} target
 */
const hold = (target) => {
    held = target;
    showOverlay();
};

/**
 * Cover the page with a layer that says it is saving.
 */
const showOverlay = () => {
    if (overlay) {
        return;
    }
    overlay = document.createElement('div');
    overlay.className = 'qtype_speakup-upload-overlay';
    overlay.setAttribute('role', 'alert');
    overlay.setAttribute('aria-busy', 'true');
    const box = document.createElement('div');
    box.className = 'qtype_speakup-upload-overlay-box';
    const spinner = document.createElement('div');
    spinner.className = 'spinner-border text-primary';
    spinner.setAttribute('aria-hidden', 'true');
    const text = document.createElement('div');
    text.className = 'mt-2';
    text.textContent = M.util.get_string('uploadwait', 'qtype_speakup');
    box.append(spinner, text);
    overlay.append(box);
    document.body.append(overlay);
};

/**
 * Remove the layer.
 */
const hideOverlay = () => {
    if (overlay) {
        overlay.remove();
        overlay = null;
    }
};
