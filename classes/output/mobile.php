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

namespace qtype_speakup\output;

use qtype_speakup\mobile_support;

/**
 * The methods the Moodle app calls for SpeakUp questions (see db/mobile.php).
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mobile {

    /**
     * Called when the app starts: switch SpeakUp off in the app if this site
     * does not meet the conditions for it, or the app is too old. The app
     * then treats SpeakUp as not supported, and offers to open the quiz in
     * the browser instead.
     *
     * @param array $args from the app, including appversioncode.
     * @return array
     */
    public static function mobile_init($args): array {
        $appversion = isset($args['appversioncode']) ? (int) $args['appversioncode'] : null;
        return [
            'templates' => [],
            'javascript' => '',
            'disabled' => !mobile_support::is_supported($appversion),
        ];
    }

    /**
     * Get the template and JavaScript that show SpeakUp questions in the app.
     *
     * The question text, and the review, are shown by the app. While the
     * question is being attempted, the response (the recorder or uploader,
     * and the audio prompt) is shown in a frame, from respond.php, which
     * sends the response's values back to the app (see mobile/mobile.js).
     *
     * @param array $args from the app.
     * @return array
     */
    public static function mobile_get_speakup($args): array {
        global $CFG;
        $folder = $CFG->dirroot . '/question/type/speakup/mobile';
        return [
            'templates' => [
                [
                    'id' => 'main',
                    'html' => file_get_contents($folder . '/qtype_speakup.html'),
                ],
            ],
            'javascript' => file_get_contents($folder . '/mobile.js'),
        ];
    }
}
