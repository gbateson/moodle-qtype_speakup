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

/**
 * Change the name, short name, description and sharing of a preset.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use qtype_speakup\form\preset_form;
use qtype_speakup\local\presets_page;
use qtype_speakup\preset;

$contextid = required_param('contextid', PARAM_INT);
$id = required_param('id', PARAM_INT);

$plugin = 'qtype_speakup';
$context = context::instance_by_id($contextid);
require_login();
$preset = preset::get($id);
if (!$preset) {
    throw new moodle_exception('invalidrecord', 'error', '', preset::TABLE);
}
$title = get_string('presetedit', $plugin, format_string($preset->name, true, ['context' => $context]));
presets_page::setup('/question/type/speakup/presetedit.php', ['contextid' => $contextid, 'id' => $id], $context, $title);
$returnurl = presets_page::get_url($context);

// Only presets that can be used here, and that the user could save where they are.
if (!isset(preset::get_visible($context, (int) $USER->id)[$id]) || !$preset->can_edit((int) $USER->id)) {
    throw new required_capability_exception($context, 'qtype/speakup:sharepresets', 'nopermissions', '');
}

$contexts = preset::get_save_contexts($context, (int) $USER->id);
if (!isset($contexts[$preset->contextid])) {
    $contexts[$preset->contextid] = $preset->get_users_label();
}
$form = new preset_form(null, ['preset' => $preset, 'contexts' => $contexts]);
$form->set_data([
    'id' => $preset->id,
    'contextid' => $contextid,
    'presetname' => $preset->name,
    'presetshortname' => $preset->shortname,
    'presetdescription' => $preset->description,
    'presetcontextid' => $preset->contextid,
]);

if ($form->is_cancelled()) {
    redirect($returnurl);
}
if ($data = $form->get_data()) {
    if ($preset->contextid != $data->presetcontextid) {
        // Moved, e.g. from "Only me" to a course: it goes last there.
        $preset->contextid = (int) $data->presetcontextid;
        $preset->sortorder = 1 + (int) $DB->get_field(preset::TABLE, 'MAX(sortorder)', ['contextid' => $preset->contextid]);
    }
    $preset->name = trim($data->presetname);
    $preset->shortname = $data->presetshortname;
    $preset->description = trim($data->presetdescription ?? '');
    $preset->save();
    redirect(
        $returnurl,
        get_string('presetsaved', $plugin, format_string($preset->name)),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading($title);
$form->display();
echo $OUTPUT->footer();
