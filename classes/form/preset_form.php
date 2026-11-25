<?php
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

namespace qtype_speakup\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use qtype_speakup\preset;

/**
 * The form to change the name, short name, description and sharing of a
 * preset. Its settings are changed by saving a question as a preset, with
 * the same short name (see edit_speakup_form::add_save_preset()).
 *
 * Custom data: "preset" (the preset), "contexts" (context id => who can use
 * a preset there, for the "Who can use it" menu) and "contextid" (the
 * context of the question bank, for the page's URL).
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class preset_form extends \moodleform {
    #[\Override]
    protected function definition() {
        $mform = $this->_form;
        $plugin = 'qtype_speakup';

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'contextid');
        $mform->setType('contextid', PARAM_INT);

        $name = 'presetname';
        $mform->addElement('text', $name, get_string($name, $plugin), ['size' => 40]);
        $mform->setType($name, PARAM_TEXT);
        $mform->addRule($name, null, 'required', null, 'client');

        $name = 'presetshortname';
        $mform->addElement('text', $name, get_string($name, $plugin), ['size' => 20]);
        $mform->addHelpButton($name, $name, $plugin);
        $mform->setType($name, PARAM_RAW_TRIMMED);
        $mform->addRule($name, null, 'required', null, 'client');

        $name = 'presetdescription';
        $mform->addElement('textarea', $name, get_string($name, $plugin), ['rows' => 3, 'cols' => 60]);
        $mform->setType($name, PARAM_TEXT);

        $name = 'presetcontextid';
        $mform->addElement('select', $name, get_string($name, $plugin), $this->_customdata['contexts']);
        $mform->addHelpButton($name, $name, $plugin);
        $mform->setType($name, PARAM_INT);

        $mform->addElement('static', 'presetsettingsnote', '', get_string('presetsettingsnote', $plugin));

        $this->add_action_buttons();
    }

    #[\Override]
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $plugin = 'qtype_speakup';

        if (trim($data['presetname'] ?? '') === '') {
            $errors['presetname'] = get_string('required');
        }
        $shortname = (string) ($data['presetshortname'] ?? '');
        if (trim($shortname) === '') {
            $errors['presetshortname'] = get_string('required');
        } else if (!preset::is_valid_shortname($shortname)) {
            $errors['presetshortname'] = get_string('err_presetshortname', $plugin);
        }
        $contextid = (int) ($data['presetcontextid'] ?? 0);
        if (!isset($this->_customdata['contexts'][$contextid])) {
            $errors['presetcontextid'] = get_string('required');
        }

        // Another preset with the same short name there?
        if (!$errors) {
            $other = preset::get_in_context($shortname, $contextid);
            if ($other && $other->id != $this->_customdata['preset']->id) {
                $errors['presetshortname'] = get_string('err_presetexistsrename', $plugin, format_string($other->name));
            }
        }
        return $errors;
    }
}
