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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/type/speakup/questiontype.php');

/**
 * Tests for the recorder, player and uploader widgets.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \qtype_speakup\output\recorder_base
 * @covers    \qtype_speakup\output\audio_recorder
 * @covers    \qtype_speakup\output\video_recorder
 * @covers    \qtype_speakup\output\playback_base
 * @covers    \qtype_speakup\output\file_uploader
 */
final class recorder_test extends \advanced_testcase {

    /**
     * The audio recorder's template data.
     */
    public function test_audio_recorder(): void {
        global $PAGE;
        $url = new \moodle_url('/pluginfile.php/1/question/response_recording/1/recording.mp3');
        $recorder = new audio_recorder('recording.ogg', 60, true, $url, false);
        $data = $recorder->export_for_template($PAGE->get_renderer('core'));
        $this->assertSame('recording.ogg', $data['filename']);
        $this->assertSame(60, $data['maxrecordingduration']);
        $this->assertTrue($data['allowpausing']);
        $this->assertTrue($data['hasrecording']);
        $this->assertSame($url->out(false), $data['recordingurl']);
        $this->assertFalse($data['candownload']);

        $data = (new audio_recorder('recording.ogg', 30, false, null, true))->export_for_template($PAGE->get_renderer('core'));
        $this->assertFalse($data['hasrecording']);
        $this->assertSame('', $data['recordingurl']);
    }

    /**
     * The video recorder adds the width and aspect ratio.
     */
    public function test_video_recorder(): void {
        global $PAGE;
        $data = (new video_recorder('recording.webm', 60, false, null, true))->export_for_template($PAGE->get_renderer('core'));
        $this->assertSame(SPEAKUP_VIDEO_WIDTH, $data['width']);
        $this->assertContains($data['aspectratio'], ['4x3', '16x9']);
        $this->assertSame(['width' => SPEAKUP_VIDEO_WIDTH, 'aspectratio' => '4x3'],
                video_recorder::add_width_and_aspect([]));
    }

    /**
     * The players' and uploader's template data.
     */
    public function test_playback_and_uploader(): void {
        global $PAGE;
        $output = $PAGE->get_renderer('core');
        $data = (new audio_playback('recording.mp3', 'https://example.com/a.mp3', true))->export_for_template($output);
        $this->assertIsArray($data);
        $data = (new video_playback('recording.webm', null, false))->export_for_template($output);
        $this->assertIsArray($data);
        $data = (new file_uploader(null, 'audio/mpeg', 'audio/*', true))->export_for_template($output);
        $this->assertIsArray($data);
    }
}
