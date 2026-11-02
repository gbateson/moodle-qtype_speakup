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
 * The SpeakUp question form: the subtotals of the target items and
 * phrases, phrase boxes that grow to fit their text, scrolling to new rows
 * after "Add", and text areas and editors (TinyMCE, Atto or plain text)
 * that grow to fit their content. Based on qtype_essayautograde/form.
 *
 * @module     qtype_speakup/editform
 * @copyright  2026 Gordon Bateson and contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Set up the form.
 */
export const init = () => {
    const form = document.getElementById('speakup_edit_form');
    if (!form) {
        return;
    }
    initSubtotal(form, 'itemsheader', getItemsTotal);
    initSubtotal(form, 'phrasesheader', getPhrasesTotal);
    initPhraseBoxes(form);
    initAddButton(form, 'itemadd', 'itemrepeats', 'fgroup_id_itemgroup_', 'itemcount');
    initAddButton(form, 'phraseadd', 'phraserepeats', 'fgroup_id_phrasegroup_', 'phrasematch');
    scrollToNewRow();
    initTextAreas(form);
    initTinyEditors(form);
    initImageResponses(form);
};

/**
 * Images (e.g. scans of handwriting) are always uploaded. So when "Image"
 * is chosen as the response type, choose "Upload" as the response method,
 * so that the settings that depend on it are shown or hidden too.
 *
 * @param {HTMLFormElement} form
 */
const initImageResponses = (form) => {
    const mediatype = form.elements.namedItem('mediatype');
    const responsemethod = form.elements.namedItem('responsemethod');
    if (!mediatype || !responsemethod) {
        return;
    }
    const update = () => {
        if (mediatype.value === 'image' && responsemethod.value !== 'upload') {
            responsemethod.value = 'upload';
            responsemethod.dispatchEvent(new Event('change', {bubbles: true}));
        }
    };
    mediatype.addEventListener('change', update);
    update();
};

/**
 * Get the value of a form field, as a number.
 *
 * @param {HTMLFormElement} form
 * @param {string} name
 * @returns {number}
 */
const getNumber = (form, name) => {
    const field = form.elements.namedItem(name);
    const value = field ? parseFloat(field.value) : NaN;
    return isNaN(value) ? 0 : value;
};

/**
 * Get the number of rows of a repeated element, e.g. target phrases.
 *
 * @param {HTMLFormElement} form
 * @param {string} name e.g. "phraserepeats"
 * @returns {number}
 */
const getRepeats = (form, name) => getNumber(form, name);

/**
 * The most the target items can award, in percent: each item's percent
 * multiplied by its multiplier, for items with a target.
 *
 * @param {HTMLFormElement} form
 * @returns {number}
 */
const getItemsTotal = (form) => {
    let total = 0;
    for (let i = 0; i < getRepeats(form, 'itemrepeats'); i++) {
        if (getNumber(form, 'itemcount[' + i + ']') > 0) {
            total += getNumber(form, 'itempercent[' + i + ']') * Math.max(1, getNumber(form, 'itemmultiplier[' + i + ']'));
        }
    }
    return total;
};

/**
 * The most the target phrases can award, in percent: each phrase's
 * percent divided by its divisor, for phrases that are filled in.
 *
 * @param {HTMLFormElement} form
 * @returns {number}
 */
const getPhrasesTotal = (form) => {
    let total = 0;
    for (let i = 0; i < getRepeats(form, 'phraserepeats'); i++) {
        const phrase = form.elements.namedItem('phrasematch[' + i + ']');
        if (phrase && phrase.value.trim() !== '') {
            total += getNumber(form, 'phrasepercent[' + i + ']') / Math.max(1, getNumber(form, 'phrasedivisor[' + i + ']'));
        }
    }
    return total;
};

/**
 * Show the subtotal of a section next to its heading, and keep it up to date.
 *
 * @param {HTMLFormElement} form
 * @param {string} header the name of the section's header element.
 * @param {Function} getTotal
 */
const initSubtotal = (form, header, getTotal) => {
    const fieldset = document.getElementById('id_' + header);
    // The visible heading row: the toggle, the heading and the help icon.
    // (The fieldset's legend is only for screen readers.)
    const heading = fieldset ? fieldset.querySelector(':scope > div.d-flex') : null;
    if (!heading) {
        return;
    }
    const badge = document.createElement('span');
    badge.className = 'qtype_speakup-subtotal badge rounded-pill';
    badge.setAttribute('aria-live', 'polite');
    heading.appendChild(badge);

    const update = () => {
        const total = Math.round(getTotal(form) * 10) / 10;
        badge.textContent = M.util.get_string('subtotal', 'qtype_speakup', total);
        // More than 100% is allowed (the grade is capped at 100%), but worth noticing.
        badge.classList.toggle('bg-warning', total > 100);
        badge.classList.toggle('text-dark', total > 100);
        badge.classList.toggle('bg-secondary', total <= 100);
        badge.classList.toggle('d-none', total === 0);
    };
    fieldset.addEventListener('change', update);
    fieldset.addEventListener('input', update);
    update();
};

/**
 * Make the phrase boxes start small, and grow to fit their text.
 *
 * @param {HTMLFormElement} form
 */
const initPhraseBoxes = (form) => {
    form.querySelectorAll('input[name^="phrasematch["]').forEach((input) => {
        const minWidth = input.offsetWidth;
        const resize = () => {
            const style = window.getComputedStyle(input);
            const measure = document.createElement('span');
            measure.style.cssText = 'position:absolute;visibility:hidden;white-space:pre;';
            measure.style.font = style.font;
            measure.style.letterSpacing = style.letterSpacing;
            measure.textContent = input.value;
            document.body.appendChild(measure);
            const padding = parseFloat(style.paddingLeft) + parseFloat(style.paddingRight) +
                parseFloat(style.borderLeftWidth) + parseFloat(style.borderRightWidth);
            // A little extra room, so the text does not scroll while typing.
            const width = measure.offsetWidth + padding + 16;
            measure.remove();
            const maxWidth = input.parentElement ? input.parentElement.clientWidth : width;
            input.style.width = Math.min(Math.max(width, minWidth), maxWidth || width) + 'px';
        };
        input.addEventListener('input', resize);
        resize();
    });
};

/**
 * When "Add" reloads the form, go to the first new row.
 *
 * @param {HTMLFormElement} form
 * @param {string} button the name of the "Add" button.
 * @param {string} repeats the name of the field with the number of rows.
 * @param {string} rowid the start of the id of each row.
 * @param {string} field the field in each row to focus.
 */
const initAddButton = (form, button, repeats, rowid, field) => {
    const element = form.elements.namedItem(button);
    if (!element) {
        return;
    }
    element.addEventListener('click', () => {
        const url = form.action.replace(/#.*$/, '');
        const row = getRepeats(form, repeats);
        form.action = url + '#' + rowid + row + '|' + field + '[' + row + ']';
    });
};

/**
 * Scroll to the new row given in the URL by initAddButton(), and focus its field.
 */
const scrollToNewRow = () => {
    const match = window.location.hash.match(/^#(fgroup_id_[a-z]+_\d+)\|(.+)$/);
    if (!match) {
        return;
    }
    const row = document.getElementById(match[1]);
    if (!row) {
        return;
    }
    row.scrollIntoView({block: 'center'});
    const field = document.querySelector('[name="' + CSS.escape(decodeURIComponent(match[2])) + '"]');
    if (field) {
        field.focus({preventScroll: true});
    }
    // Remove the hash, so that reloading the page does not scroll again.
    window.history.replaceState(null, '', window.location.href.replace(/#.*$/, ''));
};

/** The tallest a text area or editor grows, as a fraction of the window's height. */
const MAX_HEIGHT = 0.8;

/**
 * Get the tallest a text area or editor may grow, in pixels.
 *
 * @returns {number}
 */
const getMaxHeight = () => Math.round(window.innerHeight * MAX_HEIGHT);

/**
 * Make the text areas grow to fit their text, e.g. the rubric, the audio
 * prompt transcript, and the question text if the plain text editor is used.
 * (The text areas behind TinyMCE and Atto are hidden, so they are left alone.
 * Atto's editing area grows by itself: see styles.css.)
 *
 * @param {HTMLFormElement} form
 */
const initTextAreas = (form) => {
    const textareas = Array.from(form.querySelectorAll('textarea'));
    const minHeights = new Map();

    const resize = (textarea) => {
        if (!textarea.offsetWidth) {
            return; // Hidden, e.g. in a collapsed section, or behind an editor.
        }
        if (!minHeights.has(textarea)) {
            minHeights.set(textarea, textarea.offsetHeight);
        }
        const style = window.getComputedStyle(textarea);
        const borders = parseFloat(style.borderTopWidth) + parseFloat(style.borderBottomWidth);
        textarea.style.height = 'auto';
        const height = Math.max(minHeights.get(textarea), Math.min(textarea.scrollHeight + borders, getMaxHeight()));
        textarea.style.height = height + 'px';
        textarea.style.overflowY = (textarea.scrollHeight + borders > height) ? 'auto' : 'hidden';
    };

    // Resize each text area when it is first shown (e.g. when its section
    // is expanded), and whenever its text changes.
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                resize(entry.target);
            }
        });
    });
    textareas.forEach((textarea) => {
        textarea.addEventListener('input', () => resize(textarea));
        observer.observe(textarea);
        resize(textarea);
    });
    window.addEventListener('resize', () => textareas.forEach(resize));
};

/**
 * Make the TinyMCE editors in the form grow to fit their content.
 * The editors load after the form, so look for them for a while.
 *
 * @param {HTMLFormElement} form
 */
const initTinyEditors = (form) => {
    const done = new Set();
    const ids = Array.from(form.querySelectorAll('textarea[id]')).map((textarea) => textarea.id);
    let tries = 0;
    const findEditors = () => {
        const tinymce = window.tinymce;
        if (tinymce) {
            ids.forEach((id) => {
                const editor = tinymce.get(id);
                if (editor && !done.has(id) && editor.initialized) {
                    done.add(id);
                    initTinyEditor(editor);
                }
            });
        }
        if (done.size < ids.length && ++tries < 60) {
            setTimeout(findEditors, 500);
        }
    };
    findEditors();
};

/**
 * Make one TinyMCE editor grow to fit its content.
 *
 * @param {Object} editor a TinyMCE editor.
 */
const initTinyEditor = (editor) => {
    const container = editor.getContainer();
    let minHeight = 0;

    const resize = () => {
        const area = editor.getContentAreaContainer();
        const body = editor.getBody();
        if (!container.offsetHeight || !area || !body) {
            return; // Hidden, e.g. in a collapsed section.
        }
        if (!minHeight) {
            minHeight = container.offsetHeight;
        }
        // The height of the content (not of the editing area, which is at
        // least as tall as the editor), plus the menus, toolbar and status bar.
        const style = editor.getWin().getComputedStyle(body);
        const content = body.offsetHeight + parseFloat(style.marginTop) + parseFloat(style.marginBottom);
        const chrome = container.offsetHeight - area.offsetHeight;
        const height = Math.max(minHeight, Math.min(content + chrome, getMaxHeight()));
        if (Math.abs(height - container.offsetHeight) > 1) {
            container.style.height = height + 'px';
        }
    };

    editor.on('input change keyup SetContent Undo Redo NodeChange', resize);
    const observer = new IntersectionObserver((entries) => {
        if (entries.some((entry) => entry.isIntersecting)) {
            resize();
        }
    });
    observer.observe(container);
    window.addEventListener('resize', resize);
    resize();
};
