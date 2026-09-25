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
 * Admin settings for qtype_speakup.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // $settings is a pre-created admin_settingpage for this plugin, added
    // automatically under the "Question types" admin category.
    //
    // We register the management page below as a proper admin_externalpage
    // too (capability-gated, appears in admin search, etc), but that adds
    // it as a *separate, sibling* entry in the admin navigation tree - not
    // content embedded inside this page. To avoid that being easy to miss,
    // we also put a plain, unambiguous link to it directly here.
    $manageurl = new moodle_url('/question/type/speakup/manageaiproviders.php');
    $settings->add(new admin_setting_heading(
        'qtype_speakup/aiprovidersheading',
        get_string('aiprovidersheading', 'qtype_speakup'),
        get_string('aiprovidersheading_desc', 'qtype_speakup') . '<br /><br />' .
            html_writer::link($manageurl, get_string('manageaiproviders', 'qtype_speakup'),
                    ['class' => 'btn btn-secondary'])
    ));

    $ADMIN->add('qtypesettings', new admin_externalpage(
        'qtype_speakup_manageaiproviders',
        get_string('manageaiproviders', 'qtype_speakup'),
        $manageurl,
        'qtype/speakup:manageaiproviders'
    ));
}
