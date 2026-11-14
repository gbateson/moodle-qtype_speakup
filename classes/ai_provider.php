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

use tool_aibridge\action\generate_speech;
use tool_aibridge\action\generate_text;
use tool_aibridge\core_bridge;
use tool_aibridge\manager;
use tool_aibridge\provider;

/**
 * The AI providers that SpeakUp questions can use.
 *
 * Providers are set up in the AI bridge (tool_aibridge), by the site, or by
 * teachers for their courses. A question can name one, or leave the choice
 * to the bridge ("Automatic", stored as an empty aiproviderid).
 *
 * The question form only offers providers that are shared where the
 * question is, and not teachers' personal providers, since responses are
 * assessed for students, who cannot use a teacher's personal provider.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ai_provider {
    /** Provider can only accept text. */
    const CAPABILITY_TEXT = provider::CAPABILITY_TEXT;

    /** Provider can accept the recording, video or images themselves. */
    const CAPABILITY_MULTIMODAL = provider::CAPABILITY_MULTIMODAL;

    /** Provider turns text into speech, e.g. for audio prompts. */
    const CAPABILITY_SPEECH = provider::CAPABILITY_SPEECH;

    /**
     * Get a provider.
     *
     * @param int $id
     * @return provider|null the provider, or null if there is none with this id.
     */
    public static function get(int $id): ?provider {
        global $DB;
        if ($id <= 0 || !$DB->record_exists(provider::TABLE, ['id' => $id])) {
            return null;
        }
        return provider::get($id);
    }

    /**
     * Get the name of a provider.
     *
     * @param int $id
     * @return string the name, or '' if there is no such provider.
     */
    public static function get_name(int $id): string {
        $provider = self::get($id);
        return $provider ? $provider->get_name() : '';
    }

    /**
     * Get the providers that can assess or transcribe responses, for a menu.
     *
     * @param \context $context where the question is.
     * @param bool $multimodalonly only providers that take files.
     * @return string[] provider id => name.
     */
    public static function get_menu(\context $context, bool $multimodalonly = false): array {
        $capabilities = $multimodalonly ? [self::CAPABILITY_MULTIMODAL] : [self::CAPABILITY_TEXT, self::CAPABILITY_MULTIMODAL];
        $menu = [];
        foreach (provider::get_shared($context, $capabilities) as $id => $provider) {
            $menu[$id] = $provider->get_name();
        }
        return $menu;
    }

    /**
     * Get the speech providers shared with the whole site, for a menu.
     *
     * @return string[] provider id => name.
     */
    public static function get_speech_menu(): array {
        $menu = [];
        foreach (provider::get_shared(\context_system::instance(), [self::CAPABILITY_SPEECH]) as $id => $provider) {
            $menu[$id] = $provider->get_name();
        }
        return $menu;
    }

    /**
     * Can responses be assessed with AI where a question is? This is so if a
     * provider is shared there, or if Moodle's own AI can generate text.
     *
     * @param \context $context
     * @param bool $multimodalonly only providers that take files.
     * @return bool
     */
    public static function is_available(\context $context, bool $multimodalonly = false): bool {
        if (!manager::is_enabled()) {
            return false;
        }
        if (manager::is_external_allowed() && self::get_menu($context, $multimodalonly)) {
            return true;
        }
        return !$multimodalonly && get_config('tool_aibridge', 'coreroute') !== core_bridge::ROUTE_NEVER
                && core_bridge::is_action_available('\\core_ai\\aiactions\\generate_text');
    }

    /**
     * Will a provider take the response's files? For "Automatic", this is so
     * if any provider that takes files can be used.
     *
     * @param int $providerid the question's provider, or 0 for "Automatic".
     * @param int $contextid where the response is assessed.
     * @param int $userid the student.
     * @param \stored_file[] $files
     * @return bool
     */
    public static function takes_files(int $providerid, int $contextid, int $userid, array $files): bool {
        if (empty($files)) {
            return false;
        }
        if ($providerid) {
            $provider = self::get($providerid);
            return $provider && $provider->get_capability() === self::CAPABILITY_MULTIMODAL;
        }
        $action = new generate_text($contextid, $userid, '-', '', $files);
        return (bool) manager::get_routes($action);
    }

    /**
     * Find a provider by its name, e.g. when a question is imported. Providers
     * shared where the question is come first, then any other provider that
     * is not personal.
     *
     * @param string $name
     * @param \context|null $context where the question is, if known.
     * @return int the provider id, or 0 if there is none.
     */
    public static function find_by_name(string $name, ?\context $context = null): int {
        global $DB;
        $name = trim($name);
        if ($name === '') {
            return 0;
        }
        if ($context) {
            foreach (self::get_menu($context) as $id => $providername) {
                if (\core_text::strtolower($providername) === \core_text::strtolower($name)) {
                    return $id;
                }
            }
        }
        $sql = "SELECT p.id
                  FROM {" . provider::TABLE . "} p
                  JOIN {context} ctx ON ctx.id = p.contextid
                 WHERE p.name = :name AND ctx.contextlevel <> :userlevel AND p.capability <> :speech
              ORDER BY ctx.depth, p.id";
        $params = ['name' => $name, 'userlevel' => CONTEXT_USER, 'speech' => self::CAPABILITY_SPEECH];
        $ids = $DB->get_fieldset_sql($sql, $params);
        return $ids ? (int) reset($ids) : 0;
    }

    /**
     * Get the speech provider chosen in the site settings, for audio prompts.
     *
     * @return int the provider id, 0 for any speech provider, or -1 for none (audio prompts are not made).
     */
    public static function get_tts_providerid(): int {
        return (int) get_config('qtype_speakup', 'ttsproviderid');
    }

    /**
     * Can audio prompts be made from transcripts, where a question is?
     *
     * @param \context $context
     * @param int $userid the teacher.
     * @return bool
     */
    public static function is_tts_available(\context $context, int $userid): bool {
        $providerid = self::get_tts_providerid();
        if ($providerid < 0) {
            return false;
        }
        $action = new generate_speech($context->id, $userid, '-');
        return (bool) manager::get_routes($action, $providerid);
    }

    /**
     * Get the URL of the AI bridge's providers page, for a course if the
     * context is in one, else for the site.
     *
     * @param \context $context
     * @return \moodle_url
     */
    public static function get_manage_url(\context $context): \moodle_url {
        $coursecontext = $context->get_course_context(false);
        $contextid = $coursecontext ? $coursecontext->id : SYSCONTEXTID;
        return new \moodle_url('/admin/tool/aibridge/index.php', ['contextid' => $contextid]);
    }
}
