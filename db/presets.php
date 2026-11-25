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
 * The presets that SpeakUp adds to the site when it is installed: settings
 * that new questions can start from.
 *
 * They are added once, in the system context, owned by the main admin
 * (see \qtype_speakup\preset::seed()). After that, they are ordinary presets,
 * which admins can change or delete. A preset added here in a later version
 * is added to the site when SpeakUp is upgraded; a preset that is already on
 * the site, or that was added before and then deleted, is left alone.
 *
 * The name and description are the names of strings in
 * lang/en/qtype_speakup.php, stored in the language of the site.
 *
 * Settings that are not given here get the defaults of the question form.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$presets = [
    [
        // A short spoken answer, recorded as soon as the prompt has been
        // heard once, e.g. a quick reply to a question in a conversation.
        'shortname' => 'instant-response',
        'name' => 'presetinstantresponse',
        'description' => 'presetinstantresponse_desc',
        'settings' => [
            'mediatype' => 'audio',
            'responsemethod' => 'record',
            'promptpause' => 0,
            'maxpromptplays' => 1,
            'autorecord' => 1,
            'timelimitinseconds' => 30,
            'responsepause' => 0,
            'silencestop' => 3,
            'maxresponses' => 1,
        ],
        'items' => [
            ['itemtype' => 'words', 'itemtarget' => 20, 'percent' => 100, 'multiplier' => 1],
        ],
    ],
    [
        // A longer talk on video, e.g. a self-introduction or a short
        // presentation, assessed by AI from its sound.
        'shortname' => 'video-monologue',
        'name' => 'presetvideomonologue',
        'description' => 'presetvideomonologue_desc',
        'settings' => [
            'mediatype' => 'video',
            'responsemethod' => 'record',
            'timelimitinseconds' => 180,
            'responsepause' => 0,
            'maxresponses' => 2,
            'aiassessment' => 1,
            'aiweighting' => 70,
            'aiaudioonly' => 1,
            'showstats' => 1,
        ],
        'items' => [
            ['itemtype' => 'words', 'itemtarget' => 250, 'percent' => 30, 'multiplier' => 1],
        ],
    ],
    [
        // A photo or scan of handwriting, transcribed and assessed by an AI
        // provider that receives the image itself.
        'shortname' => 'handwriting-sample',
        'name' => 'presethandwritingsample',
        'description' => 'presethandwritingsample_desc',
        'settings' => [
            'mediatype' => 'image',
            'responsemethod' => 'upload',
            'maxfiles' => 2,
            'maxresponses' => 1,
            'aiassessment' => 1,
            'aiweighting' => 80,
            'showstats' => 1,
        ],
        'items' => [
            ['itemtype' => 'words', 'itemtarget' => 100, 'percent' => 20, 'multiplier' => 1],
        ],
    ],
];
