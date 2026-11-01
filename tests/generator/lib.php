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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/type/speakup/tests/helper.php');

/**
 * Data generator for qtype_speakup: AI providers.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_speakup_generator extends component_generator_base {

    /**
     * Create an AI provider.
     *
     * A speech provider ("capability" = "speech") gets a speech request
     * template, unless one is given. With "tts" = 1, it is also chosen as
     * the site's speech provider.
     *
     * @param array $data provider fields, e.g. name, capability, providertype.
     * @return stdClass the provider.
     */
    public function create_provider(array $data): stdClass {
        $tts = !empty($data['tts']);
        unset($data['tts']);
        if (($data['capability'] ?? '') === \qtype_speakup\ai_provider::CAPABILITY_SPEECH) {
            $provider = qtype_speakup_test_helper::create_speech_provider($data);
            if (!$tts) {
                set_config('ttsproviderid', 0, 'qtype_speakup');
            }
            return $provider;
        }
        return qtype_speakup_test_helper::create_provider($data);
    }
}
