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

use renderer_base;

/**
 * Represents the video recorder widget, for output.
 *
 * Video size is currently a fixed default (see SPEAKUP_VIDEO_WIDTH /
 * SPEAKUP_VIDEO_HEIGHT in questiontype.php) rather than an admin setting -
 * qtype_recordrtc (the basis for this class) reads it from get_config().
 *
 * @package   qtype_speakup
 * @copyright 2022 The Open University (original qtype_recordrtc implementation)
 * @copyright 2026 Gordon Bateson and contributors (qtype_speakup adaptation)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class video_recorder extends recorder_base {

    /**
     * Helper to add 'width' and 'aspectratio' to the given context.
     *
     * @param array $context being built for the template.
     * @return array augmented context.
     */
    public static function add_width_and_aspect(array $context): array {
        $videowidth = SPEAKUP_VIDEO_WIDTH;
        $videoheight = SPEAKUP_VIDEO_HEIGHT;
        $context['width'] = $videowidth;
        if ($videowidth / $videoheight > 1.5) {
            $context['aspectratio'] = '16x9';
        } else {
            $context['aspectratio'] = '4x3';
        }

        return $context;
    }

    #[\Override]
    public function export_for_template(renderer_base $output): array {
        $context = parent::export_for_template($output);
        return self::add_width_and_aspect($context);
    }
}
