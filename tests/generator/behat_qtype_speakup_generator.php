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
 * Behat data generator for qtype_speakup, e.g.
 *
 *     Given the following "qtype_speakup > providers" exist:
 *       | name   | capability | tts |
 *       | Gemini | text       | 0   |
 *       | Voices | speech     | 1   |
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_qtype_speakup_generator extends behat_generator_base {

    #[\Override]
    protected function get_creatable_entities(): array {
        return [
            'providers' => [
                'singular' => 'provider',
                'datagenerator' => 'provider',
                'required' => ['name'],
            ],
        ];
    }
}
