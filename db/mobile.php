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
 * Moodle app support for SpeakUp questions.
 *
 * The "init" method switches SpeakUp off in the app when this site does not
 * meet the conditions for it (see \qtype_speakup\mobile_support). The app
 * then treats SpeakUp as not supported, and offers to open the quiz in the
 * browser instead.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

$addons = [
    'qtype_speakup' => [
        'handlers' => [
            'speakup' => [
                'displaydata' => [
                    'title' => 'SpeakUp question',
                    'icon' => $CFG->wwwroot . '/question/type/speakup/pix/icon.svg',
                    'class' => '',
                ],
                'delegate' => 'CoreQuestionDelegate',
                'init' => 'mobile_init', // In classes/output/mobile.php.
                'method' => 'mobile_get_speakup', // In classes/output/mobile.php.
                'styles' => [
                    'url' => $CFG->wwwroot . '/question/type/speakup/mobile/styles_app.css',
                    'version' => '1.02',
                ],
            ],
        ],
        'lang' => [
            ['pluginname', 'qtype_speakup'],
            ['mobileloading', 'qtype_speakup'],
        ],
    ],
];
