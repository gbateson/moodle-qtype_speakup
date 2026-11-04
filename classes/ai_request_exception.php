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
 * A request to an AI provider that failed, and whether trying again later
 * might work.
 *
 * Temporary: no connection, a timeout, or the provider being busy or down
 * (HTTP 408, 429 or 5xx). Not temporary: e.g. a wrong API key or URL, or a
 * request the provider does not accept (other HTTP 4xx).
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

    /**
     * Constructor.
     *
     * @param string $detail what went wrong, e.g. "HTTP 503: The model is overloaded".
     * @param int $httpcode the HTTP status code, or 0 if the request got no reply.
     */
    public function __construct(string $detail, int $httpcode = 0) {
        $this->httpcode = $httpcode;
        $this->temporary = ($httpcode === 0 || $httpcode === 408 || $httpcode === 429 || $httpcode >= 500);
        parent::__construct('erroraiprovider', 'qtype_speakup', '', $detail);
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
                && in_array($error->errorcode, ['erroraiproviderresponse', 'erroraiscoreunparseable']);
    }
}
