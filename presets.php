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
 * Manage the presets that new SpeakUp questions can start from: those that
 * can be used in a question bank (the site's, a course's or a category's).
 * Presets are added by saving a question as a preset.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use qtype_speakup\local\presets_page;
use qtype_speakup\preset;

$contextid = optional_param('contextid', SYSCONTEXTID, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);

$plugin = 'qtype_speakup';
$context = context::instance_by_id($contextid);
require_login();
$title = get_string('presets', $plugin);
presets_page::setup('/question/type/speakup/presets.php', ['contextid' => $contextid], $context, $title);
$baseurl = presets_page::get_url($context);

if ($id && $action) {
    $preset = preset::get($id);
    if (!$preset || !$preset->can_edit((int) $USER->id)) {
        throw new required_capability_exception($context, 'qtype/speakup:sharepresets', 'nopermissions', '');
    }
    require_sesskey();
    switch ($action) {
        case 'moveup':
        case 'movedown':
            $preset->move($action === 'moveup' ? -1 : 1);
            redirect($baseurl);
            break;
        case 'delete':
            $name = format_string($preset->name);
            if (optional_param('confirm', 0, PARAM_BOOL)) {
                $preset->delete();
                redirect($baseurl, get_string('presetdeleted', $plugin, $name), null, \core\output\notification::NOTIFY_SUCCESS);
            }
            echo $OUTPUT->header();
            $confirmurl = new moodle_url($baseurl, ['action' => 'delete', 'id' => $id, 'confirm' => 1]);
            echo $OUTPUT->confirm(get_string('presetdeleteconfirm', $plugin, $name), $confirmurl, $baseurl);
            echo $OUTPUT->footer();
            die;
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading($title);
echo html_writer::tag('p', get_string('presetsintro', $plugin));

$presets = preset::get_visible($context, (int) $USER->id);
if (!$presets) {
    echo $OUTPUT->notification(get_string('nopresets', $plugin), 'info');
    echo $OUTPUT->footer();
    die;
}

// The first and last preset in each context cannot move further up or down.
$first = [];
$last = [];
foreach ($presets as $preset) {
    $first[$preset->contextid] = $first[$preset->contextid] ?? $preset->id;
    $last[$preset->contextid] = $preset->id;
}

$table = new html_table();
$table->head = [
    get_string('presetname', $plugin),
    get_string('presetshortname', $plugin),
    get_string('presetcontextid', $plugin),
    get_string('presetowner', $plugin),
    get_string('lastmodified'),
    get_string('actions'),
];
$table->attributes['class'] = 'generaltable';
$table->colclasses = [5 => 'text-nowrap'];
$owners = [];
foreach ($presets as $preset) {
    $name = html_writer::div(format_string($preset->name, true, ['context' => $context]));
    if ($preset->description !== '') {
        $name .= html_writer::div(format_text($preset->description, FORMAT_PLAIN), 'small text-muted');
    }

    if (!array_key_exists($preset->userid, $owners)) {
        $owner = $preset->userid ? core_user::get_user($preset->userid) : null;
        $owners[$preset->userid] = ($owner && empty($owner->deleted)) ? fullname($owner) : '-';
    }

    $actions = [];
    if ($preset->can_edit((int) $USER->id)) {
        $params = ['id' => $preset->id, 'sesskey' => sesskey()];
        $actions[] = $OUTPUT->action_icon(
            new moodle_url('/question/type/speakup/presetedit.php', ['contextid' => $contextid, 'id' => $preset->id]),
            new pix_icon('t/edit', get_string('presetedit', $plugin, format_string($preset->name)))
        );
        if ($preset->id != $first[$preset->contextid]) {
            $actions[] = $OUTPUT->action_icon(
                new moodle_url($baseurl, ['action' => 'moveup'] + $params),
                new pix_icon('t/up', get_string('moveup'))
            );
        }
        if ($preset->id != $last[$preset->contextid]) {
            $actions[] = $OUTPUT->action_icon(
                new moodle_url($baseurl, ['action' => 'movedown'] + $params),
                new pix_icon('t/down', get_string('movedown'))
            );
        }
        $actions[] = $OUTPUT->action_icon(
            new moodle_url($baseurl, ['action' => 'delete'] + $params),
            new pix_icon('t/delete', get_string('presetdelete', $plugin, format_string($preset->name)))
        );
    }

    $table->data[] = [
        $name,
        s($preset->shortname),
        s($preset->get_users_label()),
        s($owners[$preset->userid]),
        userdate($preset->timemodified, get_string('strftimedatetimeshort', 'langconfig')),
        implode(' ', $actions),
    ];
}
echo html_writer::table($table);

echo $OUTPUT->footer();
