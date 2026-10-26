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

namespace qtype_speakup;

/**
 * Reads information from audio and video files, using the getID3 library
 * (vendor/getID3). Based on get_duration() in datafield_report.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class media_info {

    /**
     * Get the length of an audio or video file, in seconds.
     *
     * This is a best effort: getID3 often can't read the length of WebM
     * files made by browsers, because they don't store it.
     *
     * @param \stored_file|null $file
     * @return float|null the length in seconds, or null if it is unknown.
     */
    public static function get_duration(?\stored_file $file): ?float {
        global $CFG;

        if (empty($file)) {
            return null;
        }

        require_once($CFG->dirroot . '/question/type/speakup/vendor/getID3/getid3/getid3.php');

        $tempfile = $file->copy_content_to_temp();
        if (!$tempfile) {
            return null;
        }
        try {
            $getid3 = new \getID3();
            $info = $getid3->analyze($tempfile);
        } catch (\Throwable $e) {
            $info = [];
        } finally {
            @unlink($tempfile);
        }

        if (!empty($info['playtime_seconds']) && $info['playtime_seconds'] > 0) {
            return round((float) $info['playtime_seconds'], 1);
        }
        return null;
    }
}
