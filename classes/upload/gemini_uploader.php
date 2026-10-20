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
 * Uploads a file using Google Gemini's resumable-upload protocol, then
 * returns a {{AUDIO_FILE_URI}} token referencing it.
 *
 * Protocol verified directly against Google's own current documentation
 * (ai.google.dev/gemini-api/docs/audio and /vision) rather than assumed:
 *
 * Step 1 - start the session:
 *   POST {provider->uploadurl} (e.g.
 *     https://generativelanguage.googleapis.com/upload/v1beta/files?key={{APIKEY}})
 *   Headers: X-Goog-Upload-Protocol: resumable
 *            X-Goog-Upload-Command: start
 *            X-Goog-Upload-Header-Content-Length: <bytes>
 *            X-Goog-Upload-Header-Content-Type: <mimetype>
 *   Body:    {"file": {"display_name": "..."}}
 *   The actual upload URL for step 2 comes back in a response HEADER,
 *   "X-Goog-Upload-URL" - not the body. Reading response headers (not
 *   just the body) via Moodle's own \curl class was verified against
 *   real core usage of curl::getResponse() (lib/boxlib.php), which
 *   returns the parsed headers as a lower-cased associative array.
 *
 * Step 2 - upload the actual bytes to that session URL:
 *   POST {session url from step 1}
 *   Headers: Content-Length: <bytes>
 *            X-Goog-Upload-Offset: 0
 *            X-Goog-Upload-Command: upload, finalize
 *   Body:    <raw file bytes>
 *   Response JSON contains {"file": {"uri": "...", ...}} - that uri is
 *   what gets referenced in the generateContent request afterwards, as
 *   {"file_data": {"mime_type": ..., "file_uri": ...}} instead of
 *   {"inline_data": {...}}.
 *
 * Step 3 - wait until the file is ready:
 *   The response from step 2 also has the file's "state". Video files
 *   in particular start as PROCESSING, and can't be used until they are
 *   ACTIVE. So GET {file uri}?key=... every few seconds until it is.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gemini_uploader extends uploader {

    /** Longest time to wait for Gemini to finish processing an upload. */
    const MAX_WAIT_SECONDS = 180;

    /** Time between checks on an upload's processing state. */
    const CHECK_INTERVAL_SECONDS = 3;

    #[\Override]
    public function upload(\stored_file $file, \stdClass $provider): array {
        if (empty($provider->uploadurl)) {
            throw new \moodle_exception('errorproviderconfig', 'qtype_speakup');
        }

        $apikey = (string) ($provider->apikey ?? '');
        $uploadurl = str_replace('{{APIKEY}}', $apikey, $provider->uploadurl);

        $content = $file->get_content();
        $numbytes = strlen($content);
        $mimetype = $file->get_mimetype();

        $sessionurl = $this->start_upload_session($uploadurl, $numbytes, $mimetype, $file->get_filename());
        $fileinfo = $this->upload_bytes($sessionurl, $content, $numbytes);
        $fileuri = $this->wait_until_active($fileinfo, $apikey);

        return [
            'AUDIO_FILE_URI' => $fileuri,
            'AUDIO_MIMETYPE' => $mimetype,
        ];
    }

    /**
     * Step 1: start a resumable upload session, returning the session URL
     * to upload the actual bytes to.
     *
     * @param string $uploadurl
     * @param int $numbytes
     * @param string $mimetype
     * @param string $displayname
     * @return string the session URL from the X-Goog-Upload-URL response header.
     */
    protected function start_upload_session(string $uploadurl, int $numbytes,
            string $mimetype, string $displayname): string {
        $headers = [
            'Content-Type: application/json',
            'X-Goog-Upload-Protocol: resumable',
            'X-Goog-Upload-Command: start',
            'X-Goog-Upload-Header-Content-Length: ' . $numbytes,
            'X-Goog-Upload-Header-Content-Type: ' . $mimetype,
        ];
        $body = json_encode(['file' => ['display_name' => $displayname]]);

        $curl = new \curl();
        $curl->setHeader($headers);
        $curl->post($uploadurl, $body);

        $info = $curl->get_info();
        if (!empty($info['http_code']) && $info['http_code'] >= 400) {
            throw new \moodle_exception('erroraiprovider', 'qtype_speakup', '',
                    'Gemini upload (start): HTTP ' . $info['http_code']);
        }

        $responseheaders = $curl->getResponse();
        $sessionurl = $responseheaders['x-goog-upload-url'] ?? null;
        if (empty($sessionurl)) {
            throw new \moodle_exception('erroraiproviderresponse', 'qtype_speakup');
        }

        return $sessionurl;
    }

    /**
     * Step 2: upload the actual file bytes to the session URL from step 1.
     *
     * @param string $sessionurl
     * @param string $content raw file bytes.
     * @param int $numbytes
     * @return array the uploaded file's details from the response JSON,
     *      including its "uri" and "state".
     */
    protected function upload_bytes(string $sessionurl, string $content, int $numbytes): array {
        $headers = [
            'Content-Length: ' . $numbytes,
            'X-Goog-Upload-Offset: 0',
            'X-Goog-Upload-Command: upload, finalize',
        ];

        $curl = new \curl();
        $curl->setHeader($headers);
        $rawresponse = $curl->post($sessionurl, $content);

        $info = $curl->get_info();
        if (!empty($info['http_code']) && $info['http_code'] >= 400) {
            throw new \moodle_exception('erroraiprovider', 'qtype_speakup', '',
                    'Gemini upload (finalize): HTTP ' . $info['http_code']);
        }

        $decoded = json_decode((string) $rawresponse, true);
        if (empty($decoded['file']['uri'])) {
            throw new \moodle_exception('erroraiproviderresponse', 'qtype_speakup');
        }

        return $decoded['file'];
    }

    /**
     * Step 3: wait until Gemini has finished processing the file.
     *
     * Gemini processes some uploads (video, in particular) before they
     * can be used. Until then the file's state is PROCESSING, and a
     * request that refers to it fails with "The File ... is not in an
     * ACTIVE state". So check the file's state every few seconds until
     * it is ACTIVE.
     *
     * @param array $fileinfo the file's details from upload_bytes().
     * @param string $apikey
     * @return string the file's uri, once it is ACTIVE.
     * @throws \moodle_exception if processing fails or takes too long.
     */
    protected function wait_until_active(array $fileinfo, string $apikey): string {
        $fileuri = $fileinfo['uri'];
        $state = $fileinfo['state'] ?? 'PROCESSING';

        // The file's uri is also the address for checking its details.
        $checkurl = $fileuri . (strpos($fileuri, '?') === false ? '?' : '&') . 'key=' . urlencode($apikey);
        $timeout = time() + self::MAX_WAIT_SECONDS;

        while ($state === 'PROCESSING') {
            if (time() >= $timeout) {
                throw new \moodle_exception('erroraiprovider', 'qtype_speakup', '',
                        'Gemini upload: file was still being processed after ' . self::MAX_WAIT_SECONDS . ' seconds');
            }
            sleep(self::CHECK_INTERVAL_SECONDS);

            $curl = new \curl();
            $rawresponse = $curl->get($checkurl);
            $info = $curl->get_info();
            if (!empty($info['http_code']) && $info['http_code'] >= 400) {
                throw new \moodle_exception('erroraiprovider', 'qtype_speakup', '',
                        'Gemini upload (check state): HTTP ' . $info['http_code']);
            }
            $decoded = json_decode((string) $rawresponse, true);
            $state = $decoded['state'] ?? '';
            mtrace('qtype_speakup: Gemini file state -> ' . ($state === '' ? 'unknown' : $state));
        }

        if ($state !== 'ACTIVE') {
            // E.g. FAILED, if Gemini could not process the file.
            throw new \moodle_exception('erroraiprovider', 'qtype_speakup', '',
                    'Gemini upload: file state is ' . ($state === '' ? 'unknown' : $state));
        }

        return $fileuri;
    }
}
