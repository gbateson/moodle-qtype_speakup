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
 * An AI request failed. It says whether trying again later might work.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ai_request_exception extends \moodle_exception {
    /** @var bool whether trying again later might work. */
    public $temporary;

    /** @var int the HTTP status code, or 0 if the request got no reply. */
    public $httpcode;

    /** @var string the AI bridge's error code, e.g. "policynotaccepted". */
    public $bridgeerror;

    /**
     * Constructor.
     *
     * @param string $detail what went wrong, e.g. "HTTP 503: The model is overloaded".
     * @param int $httpcode the HTTP status code, or 0 if the request got no reply.
     * @param bool|null $temporary whether trying again later might work, or null to decide from the HTTP code.
     * @param string $bridgeerror the AI bridge's error code.
     */
    public function __construct(string $detail, int $httpcode = 0, ?bool $temporary = null, string $bridgeerror = '') {
        $this->httpcode = $httpcode;
        if ($temporary === null) {
            $temporary = ($httpcode === 0 || $httpcode === 408 || $httpcode === 429 || $httpcode >= 500);
        }
        $this->temporary = $temporary;
        $this->bridgeerror = $bridgeerror;
        parent::__construct('erroraiprovider', 'qtype_speakup', '', $detail);
    }

    /**
     * Make an exception from a failed response from the AI bridge.
     *
     * @param \tool_aibridge\response $response
     * @return ai_request_exception
     */
    public static function from_response(\tool_aibridge\response $response): ai_request_exception {
        $errorcode = $response->get_errorcode();
        if ($errorcode === 'policynotaccepted') {
            // The bridge's message speaks to the user, but teachers read this one.
            $detail = get_string('errorpolicynotaccepted', 'qtype_speakup');
        } else if ($errorcode === 'noproviders') {
            $detail = get_string('errornoproviders', 'qtype_speakup');
        } else {
            $detail = $response->get_error();
            if ($response->get_providername() !== '') {
                $detail = $response->get_providername() . ': ' . $detail;
            }
        }
        return new ai_request_exception($detail, $response->get_httpcode(), $response->is_temporary(), $errorcode);
    }

    /**
     * Might trying again later work, after this error?
     *
     * Replies that cannot be read, or have no score, are counted as
     * temporary too, since AI providers sometimes reply oddly.
     *
     * @param \Throwable $error
     * @return bool
     */
    public static function is_temporary(\Throwable $error): bool {
        if ($error instanceof self) {
            return $error->temporary;
        }
        if ($error instanceof \dml_exception) {
            return true;
        }
        return ($error instanceof \moodle_exception)
                && in_array($error->errorcode, ['erroraiscoreunparseable']);
    }
}
