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
 * Spoken English practice/assessment question type - version information.
 *
 * The audio-capture component of this plugin is based on, and reuses code
 * and ideas from, qtype_recordrtc by The Open University
 * (https://github.com/moodleou/moodle-qtype_recordrtc), used under GPL v3+.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2026091100;
$plugin->requires  = 2024042200; // Moodle 4.4+ / 5.0 branch, matching recordrtc's minimum.
$plugin->component = 'qtype_speakup';
$plugin->maturity  = MATURITY_ALPHA;
$plugin->release   = '0.1.0 (dev)';

$plugin->dependencies = [
    'qtype' => 2024042200,
];
