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
 * The AI provider form: choosing a preset (e.g. Gemini, ChatGPT or Claude)
 * fills in the form's settings for that provider. The API key is left as it
 * is, and anything can be changed afterwards.
 *
 * @module     qtype_speakup/providerpresets
 * @copyright  2026 Gordon Bateson and contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Set up the preset menu. Its data-presets attribute has the presets: preset
 * name => form field name => value (see classes/provider_presets.php).
 */
export const init = () => {
    const menu = document.querySelector('select[name="preset"]');
    if (!menu) {
        return;
    }
    const presets = JSON.parse(menu.dataset.presets || '{}');
    menu.addEventListener('change', () => {
        const preset = presets[menu.value];
        if (!preset) {
            return;
        }
        Object.entries(preset).forEach(([name, value]) => {
            const field = menu.form.elements.namedItem(name);
            if (!field || name === 'label') {
                return;
            }
            if (name === 'name' && field.value.trim() !== '') {
                return; // Keep a name the administrator has typed.
            }
            field.value = value;
            // Let the form show and hide the fields that depend on this one.
            field.dispatchEvent(new Event('change', {bubbles: true}));
        });
    });
};
