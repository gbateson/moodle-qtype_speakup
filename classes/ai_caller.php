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
 * - Only inline_base64 file upload is implemented for multimodal
 *   external providers. upload_reference (a genuine two-step upload-then-
 *   reference flow) is provider-specific enough (upload endpoint, its own
 *   response shape, how the returned id gets referenced in the follow-up
 *   request) that it is deliberately deferred rather than half-built -
 *   choosing it currently throws a clear, caught error rather than
 *   silently sending no file.
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
        if ($file && $provider->capability === ai_provider::CAPABILITY_MULTIMODAL) {
            if ($provider->uploadstyle === ai_provider::UPLOAD_REFERENCE) {
                throw new \moodle_exception('erroruploadreferencenotsupported', 'qtype_speakup');
            }
            if ($provider->uploadstyle === ai_provider::UPLOAD_INLINE_BASE64) {
                $audiobase64 = base64_encode($file->get_content());
                $audiomimetype = $file->get_mimetype();
            }
        }

        $model = (string) ($provider->model ?? '');
        $apikey = (string) ($provider->apikey ?? '');

        // The URL and headers are not JSON, so {{MODEL}} / {{APIKEY}} are
        // substituted there as plain text - e.g. a provider that puts the
        // model in its URL path (rather than the request body), or an
        // API-key header convention other than the Bearer-token fallback
        // below (see e.g. Gemini's x-goog-api-key header).
        $url = str_replace(['{{MODEL}}', '{{APIKEY}}'], [$model, $apikey], $provider->url);
        $headerstemplate = str_replace(['{{MODEL}}', '{{APIKEY}}'], [$model, $apikey], (string) $provider->headers);

        // The request body IS JSON, so RUBRIC/TRANSCRIPT/MODEL/APIKEY are
        // JSON-string-escaped before substitution (in case any contain
        // quotes, newlines, etc). AUDIO_BASE64/AUDIO_MIMETYPE are left
        // as-is, since base64 and MIME-type strings never need escaping.
        $body = str_replace(
                ['{{RUBRIC}}', '{{TRANSCRIPT}}', '{{MODEL}}', '{{APIKEY}}', '{{AUDIO_BASE64}}', '{{AUDIO_MIMETYPE}}'],
                [self::json_string_escape($fullrubric), self::json_string_escape($transcript),
                        self::json_string_escape($model), self::json_string_escape($apikey),
                        $audiobase64, $audiomimetype],
                $provider->requesttemplate);

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

        // Log exactly what is about to be sent, via mtrace() - this only
        // ever appears in cron/CLI output (admin-only), never shown to
        // students, so it is safe to include the real request here. The
        // API key is redacted wherever it appears (URL, headers, or body),
        // since logs can end up copy-pasted/stored elsewhere even when the
        // immediate audience is trusted.
        $redact = function (string $text) use ($apikey): string {
            return $apikey !== '' ? str_replace($apikey, '***REDACTED***', $text) : $text;
        };
        mtrace('qtype_speakup: AI request URL -> ' . $redact($url));
        mtrace('qtype_speakup: AI request headers -> ' . $redact(implode(' | ', $headers)));
        mtrace('qtype_speakup: AI request body -> ' . $redact($body));

        $curl = new \curl();
        $curl->setHeader($headers);
        $rawresponse = $curl->post($url, $body);

        $info = $curl->get_info();
        mtrace('qtype_speakup: AI response (HTTP ' . ($info['http_code'] ?? '?') . ') -> ' . $redact((string) $rawresponse));

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
