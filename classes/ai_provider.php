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
 * CRUD helper for the admin-managed AI provider list.
 *
 * Credentials (apikey, headers) only ever flow through here and the admin
 * management page - the question editing form only ever sees provider
 * ids and display names, never the secrets themselves.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ai_provider {

    /** Moodle's own AI subsystem (\core_ai\manager). Text-only: it has no action that accepts files. */
    const TYPE_CORE = 'core';
    /** An arbitrary external REST API, configured by URL/key/headers. */
    const TYPE_EXTERNAL = 'external';

    /** Provider can only accept a text transcript. */
    const CAPABILITY_TEXT = 'text';
    /** Provider can accept the actual audio/video file. */
    const CAPABILITY_MULTIMODAL = 'multimodal';
    /** Provider turns text into speech (text-to-speech), e.g. for audio prompts. */
    const CAPABILITY_SPEECH = 'speech';

    /** No file is ever sent (text-only providers). */
    const UPLOAD_NONE = 'none';
    /** The file is base64-encoded directly into the request body. */
    const UPLOAD_INLINE_BASE64 = 'inline_base64';
    /** The file is uploaded first, then referenced by an id/URL in a second request. */
    const UPLOAD_REFERENCE = 'upload_reference';

    /**
     * Get every configured provider, most recently added last unless a
     * custom sortorder has been set.
     *
     * @param bool $enabledonly if true, only return providers with enabled=1.
     * @return \stdClass[] keyed by id.
     */
    public static function get_all(bool $enabledonly = false): array {
        global $DB;
        $conditions = $enabledonly ? ['enabled' => 1] : [];
        return $DB->get_records('qtype_speakup_ai_providers', $conditions, 'sortorder ASC, id ASC');
    }

    /**
     * @param int $id
     * @return \stdClass|false
     */
    public static function get(int $id) {
        global $DB;
        return $DB->get_record('qtype_speakup_ai_providers', ['id' => $id]);
    }

    /**
     * Options array suitable for a mform select element: id => name.
     * Deliberately excludes every other field - this is the only thing
     * the question editing form should ever see.
     *
     * Speech (text-to-speech) providers are left out, since they cannot
     * assess or transcribe responses.
     *
     * @param bool $enabledonly
     * @return array
     */
    public static function get_menu(bool $enabledonly = true): array {
        $menu = [];
        foreach (self::get_all($enabledonly) as $provider) {
            if ($provider->capability === self::CAPABILITY_SPEECH) {
                continue;
            }
            $label = $provider->name;
            if ($provider->providertype === self::TYPE_CORE) {
                $label .= ' (' . get_string('aiprovidertype_core', 'qtype_speakup') . ')';
            }
            $menu[$provider->id] = $label;
        }
        return $menu;
    }

    /**
     * Options for choosing a speech (text-to-speech) provider: id => name.
     *
     * @param bool $enabledonly
     * @return array
     */
    public static function get_speech_menu(bool $enabledonly = true): array {
        $menu = [];
        foreach (self::get_all($enabledonly) as $provider) {
            if ($provider->providertype === self::TYPE_EXTERNAL && $provider->capability === self::CAPABILITY_SPEECH) {
                $menu[$provider->id] = $provider->name;
            }
        }
        return $menu;
    }

    /**
     * Get the speech provider chosen in the site settings, for generating
     * audio prompts from their transcripts.
     *
     * @return \stdClass|null the provider, or null if none is chosen, or
     *      it no longer exists, is disabled or is not a speech provider.
     */
    public static function get_tts_provider(): ?\stdClass {
        $id = (int) get_config('qtype_speakup', 'ttsproviderid');
        if (!$id) {
            return null;
        }
        $provider = self::get($id);
        if (!$provider || empty($provider->enabled) || $provider->providertype !== self::TYPE_EXTERNAL
                || $provider->capability !== self::CAPABILITY_SPEECH) {
            return null;
        }
        return $provider;
    }

    /**
     * Insert or update a provider record.
     *
     * @param \stdClass $data as gathered from provider_form - must already
     *      have providertype/capability/uploadstyle validated by the form.
     * @return int the id of the saved record.
     */
    public static function save(\stdClass $data): int {
        global $DB;

        // Upload settings only apply to external multimodal providers. The
        // form merely hides these fields otherwise, so their old values
        // would still be submitted - clear them, so the stored record
        // always matches what the admin actually sees on the form.
        $providertype = $data->providertype ?? self::TYPE_EXTERNAL;
        $capability = $data->capability ?? self::CAPABILITY_TEXT;
        $uploadstyle = $data->uploadstyle ?? self::UPLOAD_NONE;
        if ($providertype !== self::TYPE_EXTERNAL || $capability !== self::CAPABILITY_MULTIMODAL) {
            $uploadstyle = self::UPLOAD_NONE;
        }
        // Core AI can only generate text.
        if ($providertype === self::TYPE_CORE) {
            $data->capability = self::CAPABILITY_TEXT;
        }
        $data->uploadstyle = $uploadstyle;
        if ($uploadstyle !== self::UPLOAD_REFERENCE) {
            $data->uploadprotocol = null;
            $data->uploadurl = null;
        }

        $data->timemodified = time();

        if (!empty($data->id)) {
            $DB->update_record('qtype_speakup_ai_providers', $data);
            return $data->id;
        }

        $data->timecreated = $data->timemodified;
        return $DB->insert_record('qtype_speakup_ai_providers', $data);
    }

    /**
     * @param int $id
     */
    public static function delete(int $id): void {
        global $DB;
        // Note: questions referencing this provider id are not cascaded or
        // blocked here. A dangling reference just means that question's AI
        // grading will fail gracefully at grade time (see #4 of the
        // AI-design discussion: unavailable/broken provider -> drop to
        // manual grading), which is preferable to silently blocking
        // deletion of a provider an admin has legitimate reason to remove.
        $DB->delete_records('qtype_speakup_ai_providers', ['id' => $id]);
    }
}
