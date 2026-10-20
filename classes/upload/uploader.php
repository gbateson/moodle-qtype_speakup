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
 * Base class for a provider-specific "upload the file first, then
 * reference it" implementation - used when a provider's uploadstyle is
 * ai_provider::UPLOAD_REFERENCE.
 *
 * Why this exists as a class hierarchy, rather than more if/else blocks
 * inside ai_caller.php (the way the main assess request is handled):
 * the main request genuinely is expressible generically across any REST
 * provider, because it is always "one JSON POST, built from a template
 * with {{...}} tokens" - only the field names and JSON shape differ
 * between providers, which requesttemplate already handles.
 *
 * A real upload-then-reference flow is not like that. Every provider
 * that offers one uses its own actual protocol: its own endpoint(s), its
 * own request/response shape (which may not even be JSON - Gemini's
 * upload step reads a session URL out of a response HEADER, then PUTs
 * raw bytes to it), and its own way of identifying the uploaded file
 * afterwards. None of that is expressible as JSON-template substitution.
 *
 * So each supported protocol gets its own small class here, all
 * implementing this same upload() contract. ai_caller.php never needs to
 * know or care which protocol was actually used - it just calls
 * upload() via uploader_factory, and merges whatever extra {{...}}
 * tokens come back into the same substitution mechanism it already uses
 * for {{AUDIO_BASE64}} etc. Adding support for another provider's own
 * upload protocol later means writing one new class here (see
 * uploader_factory::create()) - nothing else in the plugin changes.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class uploader {

    /**
     * Upload $file to the provider, returning extra {{...}} tokens for
     * the caller to substitute into the provider's requesttemplate, in
     * place of {{AUDIO_BASE64}}/{{AUDIO_MIMETYPE}} - typically something
     * like a {{AUDIO_FILE_URI}} token holding whatever reference the
     * provider gave back for the uploaded file.
     *
     * @param \stored_file $file the audio/video recording to upload.
     * @param \stdClass $provider a row from qtype_speakup_ai_providers.
     * @return array token name (without the surrounding {{ }}) => value.
     * @throws \moodle_exception on any failure - the same convention as
     *      ai_caller.php itself, so callers can catch this the same way.
     */
    abstract public function upload(\stored_file $file, \stdClass $provider): array;
}
