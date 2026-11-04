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
 * Calls an AI provider (Moodle's own core AI subsystem, or an admin-
 * configured external REST API) to assess a response, and parses a score
 * and feedback out of whatever text comes back.
 *
 * Design notes:
 * - For providertype=core, uses \core_ai\manager + the generate_text
 *   action - exact constructor/response shape verified against Moodle
 *   4.5 core source rather than assumed. This action is text-only (no
 *   file input), so this path always needs a transcript.
 * - For providertype=external, the request body is built from the
 *   provider's own requesttemplate (a JSON template with {{RUBRIC}},
 *   {{TRANSCRIPT}}, {{MODEL}}, {{APIKEY}}, {{AUDIO_BASE64}},
 *   {{AUDIO_MIMETYPE}} tokens), rather than a hard-coded shape for one
 *   vendor's API - this is what makes "any REST-based AI provider" actually
 *   work generically, following the spirit of the generic url/key/headers/
 *   postparams caller in Gordon Bateson's own moodle-datafield_report
 *   plugin. {{MODEL}} and {{APIKEY}} also substitute into the URL and the
 *   extra-headers field (as plain text, not JSON-escaped there), since some
 *   providers put the model in the URL path or want the key in a header
 *   other than the automatic Bearer-token fallback (e.g. Gemini's
 *   x-goog-api-key header) - see assess_via_external() below.
 * - Both inline_base64 and upload_reference file upload are supported for
 *   multimodal external providers. Unlike the main request above,
 *   upload_reference genuinely cannot be made generic across providers -
 *   each provider that offers a real "upload the file, then reference it"
 *   flow uses its own actual protocol (own endpoints, own non-JSON or
 *   multi-step handshakes, own way of identifying the uploaded file
 *   afterwards). So each supported protocol is a small dedicated class
 *   under classes/upload/ (see uploader_factory), which returns whatever
 *   extra {{...}} tokens (e.g. {{AUDIO_FILE_URI}}) the provider's own
 *   requesttemplate needs in place of {{AUDIO_BASE64}}. Adding another
 *   provider's own protocol means adding one new class there - this file
 *   never needs to know or care which protocol was actually used.
 * - Every failure path throws \moodle_exception. Callers (the adhoc task)
 *   are expected to catch these and treat them as "AI assessment failed"
 *   (per the design decision that a failed AI call drops the question to
 *   manual grading, never blocks or crashes anything for the student).
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ai_caller {

    /** The longest to wait to connect to a provider, in seconds. */
    const CONNECT_TIMEOUT = 20;

    /** The longest to wait for a provider's reply, in seconds. */
    const REQUEST_TIMEOUT = 300;

    /**
     * Assess a response, dispatching to core AI or an external provider.
     *
     * @param \stdClass $provider a row from qtype_speakup_ai_providers.
     * @param int $contextid context to run the AI action in (core AI only).
     * @param int $userid the user the action is performed as (core AI only).
     * @param string $rubric assessment instructions set on the question.
     * @param string $transcript text transcript (may be empty if a
     *      multimodal provider will be sent the file directly instead).
     * @param \stored_file|\stored_file[]|null $files the recording, or the
     *      images, for multimodal external providers.
     * @return array [float $fraction 0..1, string $feedback]
     * @throws \moodle_exception on any failure.
     */
    public static function assess(\stdClass $provider, int $contextid, int $userid,
            string $rubric, string $transcript, $files = null): array {
        if ($provider->providertype === ai_provider::TYPE_CORE) {
            return self::assess_via_core($contextid, $userid, $rubric, $transcript);
        }
        return self::assess_via_external($provider, $rubric, $transcript, $files);
    }

    /**
     * Assess via Moodle's own core AI subsystem. Text-only - the
     * generate_text action has no file input.
     *
     * @param int $contextid
     * @param int $userid
     * @param string $rubric
     * @param string $transcript
     * @return array [float $fraction, string $feedback]
     */
    protected static function assess_via_core(int $contextid, int $userid,
            string $rubric, string $transcript): array {
        if (!class_exists('\core_ai\manager')) {
            throw new \moodle_exception('errorcoreaiunavailable', 'qtype_speakup');
        }
        if (trim($transcript) === '') {
            throw new \moodle_exception('errornotranscript', 'qtype_speakup');
        }

        $prompt = self::build_prompt($rubric, $transcript);

        $manager = new \core_ai\manager();
        $action = new \core_ai\aiactions\generate_text($contextid, $userid, $prompt);
        $response = $manager->process_action($action);

        if (!$response->get_success()) {
            throw new ai_request_exception((string) $response->get_errormessage(), (int) $response->get_errorcode());
        }

        $content = $response->get_response_data()['generatedcontent'] ?? '';
        return self::parse_score_and_feedback((string) $content);
    }

    /**
     * Assess via an admin-configured external REST API.
     *
     * @param \stdClass $provider
     * @param string $rubric
     * @param string $transcript
     * @param \stored_file|\stored_file[]|null $files
     * @return array [float $fraction, string $feedback]
     */
    protected static function assess_via_external(\stdClass $provider, string $rubric,
            string $transcript, $files = null): array {
        // The {{RUBRIC}} token must carry the same "always end with SCORE: N"
        // instruction the core-AI path gets via build_prompt() below -
        // otherwise an external provider is never actually told what format
        // to reply in, and every response fails to parse regardless of how
        // well the student answered.
        $fullrubric = self::build_rubric_with_instructions($rubric);
        $content = self::request_external($provider, $fullrubric, $transcript, $files);
        return self::parse_score_and_feedback($content);
    }

    /**
     * Ask an external provider to transcribe a recording, or the handwriting
     * in some images, word for word.
     *
     * Used for uploaded files without AI assessment, so that the target
     * phrases and items can be checked in the transcript.
     *
     * @param \stdClass $provider a row from qtype_speakup_ai_providers. It
     *      must be an external provider that receives the files themselves.
     * @param \stored_file|\stored_file[] $files the audio or video file, or the images.
     * @param string $language the language the student is expected to use,
     *      e.g. "English (en-US)".
     * @param bool $isimage whether the files are images (or PDFs) of handwriting.
     * @return string the transcript.
     * @throws \moodle_exception on any failure.
     */
    public static function transcribe(\stdClass $provider, $files, string $language, bool $isimage = false): string {
        if ($provider->providertype === ai_provider::TYPE_CORE
                || $provider->capability !== ai_provider::CAPABILITY_MULTIMODAL) {
            throw new \moodle_exception('errorproviderconfig', 'qtype_speakup');
        }
        $prompt = get_string($isimage ? 'aiprompttranscribeonlyimage' : 'aiprompttranscribeonly', 'qtype_speakup', $language);
        $content = trim(self::request_external($provider, $prompt, '', $files));
        // Remove a "TRANSCRIPT:" label, if the AI added one anyway.
        return trim((string) preg_replace('/^[\s*#]*TRANSCRIPT\s*:[\s*]*/i', '', $content));
    }

    /**
     * Separate the transcript from the feedback in an AI reply that was
     * asked for both (see the aiprompttranscribe string): "TRANSCRIPT:"
     * followed by the transcript, then "FEEDBACK:" followed by the feedback.
     *
     * @param string $content the AI's reply, without its SCORE line.
     * @return array [string|null $transcript, string $feedback] - the
     *      transcript is null if the reply did not include one.
     */
    public static function split_transcript(string $content): array {
        // Allow for Markdown around the labels, e.g. "**TRANSCRIPT:**".
        // After each colon, only the closing "**" of a bold label is removed,
        // so that a bullet ("* ") at the start of the feedback is kept.
        $pattern = '/^[\s*#]*TRANSCRIPT\s*:\**(.*?)[\s*#]*FEEDBACK\s*:\**(.*)$/is';
        if (preg_match($pattern, $content, $matches)) {
            $transcript = trim($matches[1]);
            return [($transcript === '') ? null : $transcript, trim($matches[2])];
        }
        return [null, $content];
    }

    /**
     * Send a prompt (and, for a multimodal provider, the response's files)
     * to an admin-configured external REST API, and return the text it replies.
     *
     * Files are given to the request template in one of two ways:
     * - One file: {{AUDIO_BASE64}} or {{FILE_BASE64}} and {{AUDIO_MIMETYPE}}
     *   or {{FILE_MIMETYPE}} (or, if files are uploaded first,
     *   {{AUDIO_FILE_URI}} or {{FILE_URI}}).
     * - Any number of files: {{FILE_PARTS}}, which is replaced by the
     *   provider's file template once for each file, each followed by a
     *   comma (see build_file_parts()).
     *
     * @param \stdClass $provider a row from qtype_speakup_ai_providers.
     * @param string $prompt substituted for the {{RUBRIC}} token.
     * @param string $transcript substituted for the {{TRANSCRIPT}} token.
     * @param \stored_file|\stored_file[]|null $files the recording, or the
     *      images, for multimodal providers.
     * @return string the text of the reply.
     * @throws \moodle_exception on any failure.
     */
    protected static function request_external(\stdClass $provider, string $prompt,
            string $transcript, $files = null): string {
        if (empty($provider->url) || empty($provider->requesttemplate)) {
            throw new \moodle_exception('errorproviderconfig', 'qtype_speakup');
        }
        $files = self::normalise_files($files);
        $template = $provider->requesttemplate;
        $usesparts = (strpos($template, '{{FILE_PARTS}}') !== false);

        $model = (string) ($provider->model ?? '');
        $apikey = (string) ($provider->apikey ?? '');
        $plaintokens = ['MODEL' => $model, 'APIKEY' => $apikey];
        $escapedtokens = ['RUBRIC' => $prompt, 'TRANSCRIPT' => $transcript, 'MODEL' => $model, 'APIKEY' => $apikey];
        $fileparts = '';

        if ($files && $provider->capability === ai_provider::CAPABILITY_MULTIMODAL) {
            // Fail loudly if the template has no slot for the files.
            // Otherwise the token is simply never substituted and some
            // providers - Gemini included - quietly grade the transcript
            // alone, which looks like success but isn't. Checked before
            // uploading, so a misconfigured provider doesn't upload files
            // for nothing.
            $slots = ($provider->uploadstyle === ai_provider::UPLOAD_REFERENCE)
                    ? ['{{FILE_PARTS}}', '{{FILE_URI}}', '{{AUDIO_FILE_URI}}']
                    : ['{{FILE_PARTS}}', '{{FILE_BASE64}}', '{{AUDIO_BASE64}}'];
            $hasslot = false;
            foreach ($slots as $slot) {
                $hasslot = $hasslot || (strpos($template, $slot) !== false);
            }
            if (!$hasslot) {
                throw new \moodle_exception('errortemplatemissingaudio', 'qtype_speakup', '', implode(', ', $slots));
            }
            if (!$usesparts && count($files) > 1) {
                throw new \moodle_exception('errortemplatemultiplefiles', 'qtype_speakup');
            }
            if ($usesparts && trim((string) ($provider->filetemplate ?? '')) === '') {
                throw new \moodle_exception('errorfiletemplatemissing', 'qtype_speakup');
            }

            $filetokens = [];
            foreach ($files as $file) {
                $filetokens[] = self::get_file_tokens($provider, $file);
            }
            if ($usesparts) {
                $fileparts = self::build_file_parts((string) $provider->filetemplate, $files, $filetokens);
            } else {
                // One file: its tokens go straight into the request template.
                $escapedtokens = array_merge($escapedtokens, $filetokens[0]);
                $plaintokens = array_merge($plaintokens, $filetokens[0]);
            }
        }

        // The URL and headers are not JSON, so every token is substituted
        // there as plain text - e.g. a provider that puts the model in
        // the URL path, or an API-key header convention other than the
        // automatic Bearer-token fallback (see e.g. Gemini's
        // x-goog-api-key header).
        $url = self::substitute_plain($provider->url, $plaintokens);
        $headerstemplate = self::substitute_plain((string) $provider->headers, $plaintokens);

        // The request body IS JSON, so values are JSON-string-escaped.
        // The file parts are JSON already, so they are inserted as they are.
        $body = self::substitute_escaped($template, $escapedtokens);
        $body = self::substitute_plain($body, ['FILE_PARTS' => $fileparts]);
        // Tokens with no value, e.g. a file token when there is no file.
        $body = self::substitute_plain($body, ['AUDIO_BASE64' => '', 'AUDIO_MIMETYPE' => '', 'AUDIO_FILE_URI' => '',
                'FILE_BASE64' => '', 'FILE_MIMETYPE' => '', 'FILE_URI' => '', 'FILE_NAME' => '']);

        json_decode($body);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // The template + substitutions no longer form valid JSON -
            // most likely a template authoring mistake, not a runtime
            // fluke, so this is worth surfacing distinctly.
            throw new \moodle_exception('errorrequestnotvalidjson', 'qtype_speakup');
        }

        $rawresponse = self::send_request($url, $headerstemplate, $apikey, $body);

        $decoded = json_decode($rawresponse, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \moodle_exception('erroraiproviderresponse', 'qtype_speakup');
        }

        $content = self::extract_by_path($decoded, $provider->returnpath);
        if ($content === null) {
            throw new \moodle_exception('erroraiproviderresponse', 'qtype_speakup');
        }

        return (string) $content;
    }

    /**
     * Turn one file, several files or none into a list of files.
     *
     * @param \stored_file|\stored_file[]|null $files
     * @return \stored_file[]
     */
    protected static function normalise_files($files): array {
        if ($files instanceof \stored_file) {
            return [$files];
        }
        return is_array($files) ? array_values(array_filter($files)) : [];
    }

    /**
     * Get the tokens for one file: its content (base64) or, if files are
     * uploaded first, the reference the provider gave back; its MIME type;
     * and its name. Each is available as FILE_... and, for compatibility,
     * AUDIO_....
     *
     * @param \stdClass $provider
     * @param \stored_file $file
     * @return array token name => value.
     */
    protected static function get_file_tokens(\stdClass $provider, \stored_file $file): array {
        $tokens = ['FILE_NAME' => $file->get_filename()];
        if ($provider->uploadstyle === ai_provider::UPLOAD_REFERENCE) {
            // A genuine upload-then-reference flow is provider-specific in
            // ways JSON templating can't express (see classes/upload/uploader.php).
            $uploader = \qtype_speakup\upload\uploader_factory::create((string) $provider->uploadprotocol);
            $result = $uploader->upload($file, $provider);
            $mimetype = $result['AUDIO_MIMETYPE'] ?? $file->get_mimetype();
            $uri = $result['AUDIO_FILE_URI'] ?? '';
            unset($result['AUDIO_MIMETYPE']);
            $tokens += $result;
            $tokens += ['FILE_URI' => $uri, 'AUDIO_FILE_URI' => $uri];
        } else {
            $content = base64_encode($file->get_content());
            $mimetype = $file->get_mimetype();
            $tokens += ['FILE_BASE64' => $content, 'AUDIO_BASE64' => $content];
        }
        $tokens += ['FILE_MIMETYPE' => $mimetype, 'AUDIO_MIMETYPE' => $mimetype];
        return $tokens;
    }

    /**
     * Build the {{FILE_PARTS}} of a request: the file template once for
     * each file, with that file's tokens, each followed by a comma. So
     * {{FILE_PARTS}} goes just before another item in a JSON list, e.g.
     * "parts": [{{FILE_PARTS}} {"text": "{{RUBRIC}}"}], and with no files
     * the list is still valid.
     *
     * The file template is either one JSON part, or a JSON object of MIME
     * types => parts, for providers that need a different part for e.g.
     * images and PDFs: {"image/*": {...}, "application/pdf": {...}}. A key
     * of "*" matches any file.
     *
     * @param string $filetemplate
     * @param \stored_file[] $files
     * @param array[] $filetokens the tokens of each file.
     * @return string
     * @throws \moodle_exception if the template is not valid, or has no part for a file's type.
     */
    public static function build_file_parts(string $filetemplate, array $files, array $filetokens): string {
        $parts = '';
        foreach (array_values($files) as $i => $file) {
            $part = self::choose_file_part($filetemplate, $file->get_mimetype());
            $parts .= self::substitute_escaped($part, $filetokens[$i]) . ', ';
        }
        return $parts;
    }

    /**
     * Choose the file template part for a file's MIME type.
     *
     * @param string $filetemplate one JSON part, or an object of MIME types => parts.
     * @param string $mimetype
     * @return string the JSON part, with its tokens not yet substituted.
     * @throws \moodle_exception
     */
    public static function choose_file_part(string $filetemplate, string $mimetype): string {
        $decoded = json_decode($filetemplate, true);
        if (!is_array($decoded)) {
            throw new \moodle_exception('errorfiletemplatejson', 'qtype_speakup');
        }
        if (!self::is_mimetype_map($decoded)) {
            return trim($filetemplate);
        }
        $mimetype = strtolower($mimetype);
        $candidates = [$mimetype, strtok($mimetype, '/') . '/*', '*'];
        foreach ($candidates as $key) {
            foreach ($decoded as $pattern => $part) {
                if (strtolower(trim($pattern)) === $key) {
                    return json_encode($part, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                }
            }
        }
        throw new \moodle_exception('errorproviderfiletype', 'qtype_speakup', '', $mimetype);
    }

    /**
     * Is a decoded file template an object of MIME types => parts, rather than one part?
     *
     * @param array $decoded
     * @return bool
     */
    public static function is_mimetype_map(array $decoded): bool {
        if (!$decoded || array_is_list($decoded)) {
            return false;
        }
        foreach ($decoded as $key => $value) {
            if (!is_array($value) || ($key !== '*' && strpos((string) $key, '/') === false)) {
                return false;
            }
        }
        return true;
    }

    /**
     * POST a JSON request body to a provider, and return the raw response.
     *
     * @param string $url
     * @param string $headerstemplate extra headers as JSON, with tokens
     *      already substituted. If empty, the API key is sent as a Bearer token.
     * @param string $apikey
     * @param string $body the JSON request body.
     * @param array|null $info if given, set to the curl info, e.g. content_type.
     * @return string the raw response body.
     * @throws \moodle_exception if the provider returns an HTTP error.
     */
    protected static function send_request(string $url, string $headerstemplate, string $apikey,
            string $body, ?array &$info = null): string {
        $headers = ['Content-Type: application/json'];
        if (!empty($headerstemplate)) {
            $extra = json_decode($headerstemplate, true);
            if (is_array($extra)) {
                foreach ($extra as $key => $value) {
                    $headers[] = "$key: $value";
                }
            }
        } else if (!empty($apikey)) {
            // No explicit headers configured but a key was given - assume
            // the common bearer-token convention.
            $headers[] = 'Authorization: Bearer ' . $apikey;
        }

        $curl = new \curl();
        $curl->setHeader($headers);
        // Don't let a request that hangs hold up cron (and the other tasks) for ever.
        $rawresponse = (string) $curl->post($url, $body, [
            'CURLOPT_CONNECTTIMEOUT' => self::CONNECT_TIMEOUT,
            'CURLOPT_TIMEOUT' => self::REQUEST_TIMEOUT,
        ]);
        $info = $curl->get_info();

        // The request could not be sent, e.g. a blocked URL, no connection, or a timeout.
        if ($curl->get_errno() || (!empty($curl->error) && $rawresponse === $curl->error)) {
            throw new ai_request_exception(self::hide_key((string) $curl->error, $apikey), 0);
        }

        if (!empty($info['http_code']) && $info['http_code'] >= 400) {
            // Surface the provider's own error message from the response
            // body, not just the HTTP status - most JSON APIs (including
            // Gemini's) put a useful explanation there.
            $detail = 'HTTP ' . $info['http_code'];
            $errorbody = json_decode($rawresponse, true);
            if (is_array($errorbody)) {
                if (isset($errorbody['error']['message'])) {
                    $detail .= ': ' . $errorbody['error']['message'];
                } else if (isset($errorbody['error']) && is_string($errorbody['error'])) {
                    $detail .= ': ' . $errorbody['error'];
                }
            }
            throw new ai_request_exception(self::hide_key($detail, $apikey), (int) $info['http_code']);
        }
        return $rawresponse;
    }

    /**
     * Remove an API key from an error message, since the message is saved,
     * and shown to teachers.
     *
     * @param string $message
     * @param string $apikey
     * @return string
     */
    protected static function hide_key(string $message, string $apikey): string {
        return ($apikey === '') ? $message : str_replace($apikey, '********', $message);
    }

    /**
     * Build the actual prompt text sent to a text-based AI (core AI, or an
     * external provider's {{RUBRIC}}+{{TRANSCRIPT}} tokens combined this
     * way too, for consistency).
     *
     * @param string $rubric
     * @param string $transcript
     * @return string
     */
    protected static function build_prompt(string $rubric, string $transcript): string {
        return self::build_rubric_with_instructions($rubric) . "\n\n" .
                get_string('aiprompttranscriptlabel', 'qtype_speakup') . ' ' . $transcript;
    }

    /**
     * Append the mandatory "end with SCORE: N" scoring-format instruction
     * to a question's rubric. Shared by both the core-AI path (via
     * build_prompt(), which also appends the transcript) and the external-
     * provider path (which substitutes this directly into {{RUBRIC}}) -
     * both need the AI told the same thing about how to format its score,
     * regardless of which provider actually receives the prompt.
     *
     * @param string $rubric
     * @return string
     */
    protected static function build_rubric_with_instructions(string $rubric): string {
        return $rubric . "\n\n" . get_string('aipromptinstructions', 'qtype_speakup');
    }

    /**
     * Parse a numeric score and feedback text out of the AI's raw text
     * response. Expects a line such as "SCORE: 85" (out of 100),
     * per the instruction given in aipromptinstructions.
     *
     * @param string $content
     * @return array [float $fraction, string $feedback]
     * @throws \moodle_exception if no parseable score is found.
     */
    protected static function parse_score_and_feedback(string $content): array {
        if (preg_match('/SCORE:\s*(\d{1,3})/i', $content, $matches)) {
            $score = max(0, min(100, (int) $matches[1]));
            $fraction = $score / 100.0;
            $feedback = trim((string) preg_replace('/SCORE:\s*\d{1,3}\s*$/i', '', $content));
            return [$fraction, $feedback];
        }
        throw new \moodle_exception('erroraiscoreunparseable', 'qtype_speakup');
    }

    /**
     * Walk a comma-separated key path into a decoded JSON array/object
     * structure, e.g. path "choices,0,message,content".
     *
     * @param mixed $data
     * @param string|null $path
     * @return mixed|null the value found, or null if the path doesn't exist.
     */
    protected static function extract_by_path($data, ?string $path) {
        if (empty($path)) {
            return null;
        }
        $value = $data;
        foreach (explode(',', $path) as $key) {
            $key = trim($key);
            if (is_array($value) && array_key_exists($key, $value)) {
                $value = $value[$key];
            } else {
                return null;
            }
        }
        return $value;
    }

    /**
     * Substitute {{...}} tokens into a plain-text (non-JSON) string, e.g.
     * a URL or a headers template - values are inserted verbatim, with no
     * escaping of any kind.
     *
     * @param string $template
     * @param array $tokens token name (without braces) => value.
     * @return string
     */
    protected static function substitute_plain(string $template, array $tokens): string {
        $search = [];
        $replace = [];
        foreach ($tokens as $name => $value) {
            $search[] = '{{' . $name . '}}';
            $replace[] = (string) $value;
        }
        return str_replace($search, $replace, $template);
    }

    /**
     * Substitute {{...}} tokens into a JSON request body template - each
     * value is JSON-string-escaped first, since the template supplies the
     * surrounding quotes itself (e.g. "content": "{{TRANSCRIPT}}") and the
     * values may contain quotes, newlines, etc.
     *
     * @param string $template
     * @param array $tokens token name (without braces) => value.
     * @return string
     */
    protected static function substitute_escaped(string $template, array $tokens): string {
        $search = [];
        $replace = [];
        foreach ($tokens as $name => $value) {
            $search[] = '{{' . $name . '}}';
            $replace[] = self::json_string_escape((string) $value);
        }
        return str_replace($search, $replace, $template);
    }

    /**
     * Escape a plain string for embedding inside a JSON string literal in
     * a request template (the template itself supplies the surrounding
     * quotes, e.g. "content": "{{TRANSCRIPT}}").
     *
     * @param string $text
     * @return string
     */
    protected static function json_string_escape(string $text): string {
        return substr(json_encode($text), 1, -1);
    }
}
