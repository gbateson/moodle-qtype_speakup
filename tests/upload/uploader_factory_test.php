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
 * Tests for choosing the protocol that uploads files to an AI provider.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \qtype_speakup\upload\uploader_factory
 */
final class uploader_factory_test extends \basic_testcase {

    /**
     * Each protocol in the menu can be created.
     */
    public function test_create(): void {
        $menu = uploader_factory::get_protocol_menu();
        $this->assertArrayHasKey(uploader_factory::PROTOCOL_GEMINI, $menu);
        foreach (array_keys($menu) as $protocol) {
            $this->assertInstanceOf(uploader::class, uploader_factory::create($protocol));
        }
        $this->assertInstanceOf(gemini_uploader::class, uploader_factory::create(uploader_factory::PROTOCOL_GEMINI));
    }

    /**
     * An unknown protocol is an error.
     */
    public function test_create_unknown(): void {
        $this->expectException(\moodle_exception::class);
        uploader_factory::create('nosuchprotocol');
    }
}
