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

namespace qtype_speakup\upload;

/**
 * Maps a provider's uploadprotocol setting (chosen explicitly by the
 * admin on the provider form, never guessed/sniffed from the URL) to the
 * class that actually implements it.
 *
 * Adding support for another provider's own upload-then-reference
 * protocol later means: write one new class implementing uploader,
 * add one PROTOCOL_xxx constant and one line in get_protocol_menu() and
 * create() below. Nothing else in the plugin - not ai_caller.php, not
 * the provider form, not the admin page - needs to change.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class uploader_factory {

    /** Google Gemini's resumable-upload protocol (see gemini_uploader). */
    const PROTOCOL_GEMINI = 'gemini';

    /**
     * Options array suitable for a mform select element.
     *
     * @return array protocol constant => display name.
     */
    public static function get_protocol_menu(): array {
        return [
            self::PROTOCOL_GEMINI => get_string('uploadprotocol_gemini', 'qtype_speakup'),
        ];
    }

    /**
     * @param string $protocol one of the PROTOCOL_xxx constants above.
     * @return uploader
     * @throws \moodle_exception if $protocol isn't a known/supported one.
     */
    public static function create(string $protocol): uploader {
        switch ($protocol) {
            case self::PROTOCOL_GEMINI:
                return new gemini_uploader();
            default:
                // Include the offending value, so cron output shows at a
                // glance whether it was empty (e.g. a provider saved before
                // the uploadprotocol field existed) or simply mistyped.
                throw new \moodle_exception('erroruploadprotocolunknown', 'qtype_speakup', '',
                        $protocol === '' ? '(empty)' : $protocol);
        }
    }
}
