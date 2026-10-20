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

    /**
     * Assess a response, dispatching to core AI or an external provider.
     *
     * @param \stdClass $provider a row from qtype_speakup_ai_providers.
     * @param int $contextid context to run the AI action in (core AI only).
     * @param int $userid the user the action is performed as (core AI only).
     * @param string $rubric assessment instructions set on the question.
     * @param string $transcript text transcript (may be empty if a
     *      multimodal provider will be sent the file directly instead).
     * @param \stored_file|null $file the audio/video recording, for
     *      multimodal external providers.
     * @return array [float $fraction 0..1, string $feedback]
     * @throws \moodle_exception on any failure.
     */
    public static function assess(\stdClass $provider, int $contextid, int $userid,
            string $rubric, string $transcript, ?\stored_file $file = null): array {
        if ($provider->providertype === ai_provider::TYPE_CORE) {
            return self::assess_via_core($contextid, $userid, $rubric, $transcript);
        }
        return self::assess_via_external($provider, $rubric, $transcript, $file);
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
            throw new \moodle_exception('erroraiprovider', 'qtype_speakup', '', $response->get_errormessage());
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
     * @param \stored_file|null $file
     * @return array [float $fraction, string $feedback]
     */
    protected static function assess_via_external(\stdClass $provider, string $rubric,
            string $transcript, ?\stored_file $file = null): array {
        if (empty($provider->url) || empty($provider->requesttemplate)) {
            throw new \moodle_exception('errorproviderconfig', 'qtype_speakup');
        }

        // The {{RUBRIC}} token must carry the same "always end with SCORE: N"
        // instruction the core-AI path gets via build_prompt() below -
        // otherwise an external provider is never actually told what format
        // to reply in, and every response fails to parse regardless of how
        // well the student answered. This was a real gap: it worked
        // correctly for core AI (which always went through build_prompt())
        // but silently never worked for any external provider until fixed
        // here.
        $fullrubric = self::build_rubric_with_instructions($rubric);

        $audiobase64 = '';
        $audiomimetype = '';
        $extratokens = [];
        if ($file && $provider->capability === ai_provider::CAPABILITY_MULTIMODAL) {
            // Fail loudly if the template has no slot for the recording.
            // Otherwise the token is simply never substituted (or replaced
            // with an empty string) and some providers - Gemini included -
            // quietly accept the request and grade the transcript alone,
            // which looks like success but isn't. Checked before uploading,
            // so a misconfigured provider doesn't upload files for nothing.
            $requiredtoken = [
                ai_provider::UPLOAD_REFERENCE => '{{AUDIO_FILE_URI}}',
                ai_provider::UPLOAD_INLINE_BASE64 => '{{AUDIO_BASE64}}',
            ][$provider->uploadstyle] ?? null;
            if ($requiredtoken !== null && strpos($provider->requesttemplate, $requiredtoken) === false) {
                throw new \moodle_exception('errortemplatemissingaudio', 'qtype_speakup', '', $requiredtoken);
            }

            if ($provider->uploadstyle === ai_provider::UPLOAD_REFERENCE) {
                // A genuine upload-then-reference flow is provider-specific
                // in ways plain JSON templating can't express (see the
                // class docblock above and classes/upload/uploader.php) -
                // delegate to whichever protocol implementation this
                // provider is configured to use, and merge in whatever
                // extra tokens it returns (e.g. {{AUDIO_FILE_URI}}) below.
                $uploader = \qtype_speakup\upload\uploader_factory::create((string) $provider->uploadprotocol);
                $result = $uploader->upload($file, $provider);
                // AUDIO_MIMETYPE is always treated as a "raw" (unescaped)
                // token alongside AUDIO_BASE64 below, regardless of which
                // upload style produced it - pull it out here so both
                // paths end up in exactly the same place.
                $audiomimetype = $result['AUDIO_MIMETYPE'] ?? '';
                unset($result['AUDIO_MIMETYPE']);
                $extratokens = $result;
            } else if ($provider->uploadstyle === ai_provider::UPLOAD_INLINE_BASE64) {
                $audiobase64 = base64_encode($file->get_content());
                $audiomimetype = $file->get_mimetype();
            }
        }

        $model = (string) ($provider->model ?? '');
        $apikey = (string) ($provider->apikey ?? '');

        // The URL and headers are not JSON, so every token is substituted
        // there as plain text - e.g. a provider that puts the model in
        // the URL path, or an API-key header convention other than the
        // automatic Bearer-token fallback below (see e.g. Gemini's
        // x-goog-api-key header).
        $plaintokens = array_merge(['MODEL' => $model, 'APIKEY' => $apikey], $extratokens);
        $url = self::substitute_plain($provider->url, $plaintokens);
        $headerstemplate = self::substitute_plain((string) $provider->headers, $plaintokens);

        // The request body IS JSON. RUBRIC/TRANSCRIPT/MODEL/APIKEY, and
        // any extra tokens an uploader returned (e.g. AUDIO_FILE_URI), are
        // JSON-string-escaped, since they could in principle contain
        // quotes, newlines, etc. AUDIO_BASE64/AUDIO_MIMETYPE never need
        // escaping (a base64 string and a MIME type never contain
        // characters JSON needs escaped), so they are substituted as-is
        // regardless of which upload style produced them.
        $escapedtokens = array_merge(
                ['RUBRIC' => $fullrubric, 'TRANSCRIPT' => $transcript, 'MODEL' => $model, 'APIKEY' => $apikey],
                $extratokens);
        $body = self::substitute_escaped($provider->requesttemplate, $escapedtokens);
        $body = self::substitute_plain($body, ['AUDIO_BASE64' => $audiobase64, 'AUDIO_MIMETYPE' => $audiomimetype]);

        json_decode($body);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // The template + substitutions no longer form valid JSON -
            // most likely a template authoring mistake, not a runtime
            // fluke, so this is worth surfacing distinctly.
            throw new \moodle_exception('errorrequestnotvalidjson', 'qtype_speakup');
        }

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
        $rawresponse = $curl->post($url, $body);

        $info = $curl->get_info();

        if (!empty($info['http_code']) && $info['http_code'] >= 400) {
            // Try to surface the provider's own error message from the
            // response body, not just the HTTP status - most JSON APIs
            // (including Gemini's) put a genuinely useful explanation
            // there (e.g. "model not found for API version v1beta"),
            // which is far more actionable than "HTTP 404" alone.
            $detail = 'HTTP ' . $info['http_code'];
            $errorbody = json_decode($rawresponse, true);
            if (is_array($errorbody)) {
                if (isset($errorbody['error']['message'])) {
                    $detail .= ': ' . $errorbody['error']['message'];
                } else if (isset($errorbody['error']) && is_string($errorbody['error'])) {
                    $detail .= ': ' . $errorbody['error'];
                }
            }
            throw new \moodle_exception('erroraiprovider', 'qtype_speakup', '', $detail);
        }

        $decoded = json_decode($rawresponse, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \moodle_exception('erroraiproviderresponse', 'qtype_speakup');
        }

        $content = self::extract_by_path($decoded, $provider->returnpath);
        if ($content === null) {
            throw new \moodle_exception('erroraiproviderresponse', 'qtype_speakup');
        }

        return self::parse_score_and_feedback((string) $content);
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
            $fraction = $score / 100;
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
