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
 * Tests for reading the length of a recording.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \qtype_speakup\media_info
 */
final class media_info_test extends \advanced_testcase {

    /**
     * Save some content as a file.
     *
     * @param string $filename
     * @param string $content
     * @return \stored_file
     */
    protected function make_file(string $filename, string $content): \stored_file {
        return get_file_storage()->create_file_from_string(['contextid' => \context_system::instance()->id,
                'component' => 'qtype_speakup', 'filearea' => 'test', 'itemid' => 0, 'filepath' => '/',
                'filename' => $filename], $content);
    }

    /**
     * The length of a WAV file is read from the file.
     */
    public function test_get_duration_wav(): void {
        $this->resetAfterTest();
        // 2.5 seconds of 16-bit mono samples at 16 kHz.
        $wav = tts::pcm_to_wav(str_repeat("\0\0", 40000), 16000);
        $this->assertSame(2.5, media_info::get_duration($this->make_file('a.wav', $wav)));
    }

    /**
     * The length is unknown without a file, or for a file that is not audio.
     */
    public function test_get_duration_unknown(): void {
        $this->resetAfterTest();
        $this->assertNull(media_info::get_duration(null));
        $this->assertNull(media_info::get_duration($this->make_file('a.txt', 'Not audio.')));
    }
}
