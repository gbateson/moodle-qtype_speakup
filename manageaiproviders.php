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
 * Manage the site-wide list of AI providers for qtype_speakup.
 *
 * Admin-only (qtype/speakup:manageaiproviders) - this page is the only
 * place credentials (apikey, headers) are ever entered or shown; the
 * question editing form only ever sees a provider's id and display name.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/question/type/speakup/classes/form/provider_form.php');

use qtype_speakup\ai_provider;
use qtype_speakup\form\provider_form;

$id = optional_param('id', 0, PARAM_INT);
$delete = optional_param('delete', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

$url = new moodle_url('/question/type/speakup/manageaiproviders.php');

// admin_externalpage_setup() handles require_login(), context, $PAGE setup,
// and the capability check (against whatever capability this page is
// registered with in settings.php) all in one call - deliberately not
// duplicating any of that manually here.
admin_externalpage_setup('qtype_speakup_manageaiproviders');

// Handle deletion, with a confirmation step.
if ($delete) {
    $provider = ai_provider::get($delete);
    if (!$provider) {
        throw new moodle_exception('invalidrecord', 'error');
    }
    if ($confirm && confirm_sesskey()) {
        ai_provider::delete($delete);
        redirect($url, get_string('providerdeleted', 'qtype_speakup', $provider->name));
    }
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(
        get_string('confirmdeleteprovider', 'qtype_speakup', $provider->name),
        new moodle_url($url, ['delete' => $delete, 'confirm' => 1, 'sesskey' => sesskey()]),
        $url
    );
    echo $OUTPUT->footer();
    exit;
}

// Handle add/edit form.
$provider = $id ? ai_provider::get($id) : null;
if ($id && !$provider) {
    throw new moodle_exception('invalidrecord', 'error');
}

$form = new provider_form($url);
if ($provider) {
    $form->set_data($provider);
}

if ($form->is_cancelled()) {
    redirect($url);
} else if ($data = $form->get_data()) {
    ai_provider::save($data);
    redirect($url, get_string('providersaved', 'qtype_speakup'));
}

echo $OUTPUT->header();

if ($id || optional_param('new', 0, PARAM_BOOL)) {
    echo $OUTPUT->heading($id ? get_string('editprovider', 'qtype_speakup') : get_string('addprovider', 'qtype_speakup'));
    $form->display();
} else {
    echo $OUTPUT->heading(get_string('manageaiproviders', 'qtype_speakup'));
    echo html_writer::tag('p', get_string('manageaiproviders_intro', 'qtype_speakup'));

    $providers = ai_provider::get_all();

    $table = new html_table();
    $table->head = [
        get_string('name', 'qtype_speakup'),
        get_string('providertype', 'qtype_speakup'),
        get_string('capability', 'qtype_speakup'),
        get_string('enabled', 'qtype_speakup'),
        '',
    ];

    foreach ($providers as $p) {
        $editurl = new moodle_url($url, ['id' => $p->id]);
        $deleteurl = new moodle_url($url, ['delete' => $p->id]);

        $typelabel = ($p->providertype === ai_provider::TYPE_CORE)
                ? get_string('aiprovidertype_core', 'qtype_speakup')
                : get_string('aiprovidertype_external', 'qtype_speakup');
        $capabilitylabel = ($p->capability === ai_provider::CAPABILITY_MULTIMODAL)
                ? get_string('aicapability_multimodal', 'qtype_speakup')
                : get_string('aicapability_text', 'qtype_speakup');
        $enabledlabel = $p->enabled ? get_string('yes') : get_string('no');

        $actions = html_writer::link($editurl, get_string('edit')) . ' | ' .
                html_writer::link($deleteurl, get_string('delete'));

        $table->data[] = [s($p->name), $typelabel, $capabilitylabel, $enabledlabel, $actions];
    }

    echo html_writer::table($table);
    echo $OUTPUT->single_button(new moodle_url($url, ['new' => 1]), get_string('addprovider', 'qtype_speakup'));
}

echo $OUTPUT->footer();
