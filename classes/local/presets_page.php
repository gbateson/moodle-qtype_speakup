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

namespace qtype_speakup\local;

use context;
use moodle_url;

/**
 * Sets up the pages that manage presets (presets.php and presetedit.php).
 *
 * The pages are for a question bank: the site's (in the admin settings), a
 * course's, a category's, or (in Moodle 5.0 and later) a question bank
 * activity's. They show the presets that can be used there.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class presets_page {
    /** The name of the admin page, in settings.php. */
    const ADMIN_PAGE = 'qtype_speakup_presets';

    /**
     * Get the URL of the page that manages the presets for a question bank.
     *
     * @param context $context the context of the question bank.
     * @return moodle_url
     */
    public static function get_url(context $context): moodle_url {
        return new moodle_url('/question/type/speakup/presets.php', ['contextid' => $context->id]);
    }

    /**
     * Check the user can see the page, and set it up.
     *
     * In the system context, it is an admin page, which needs
     * qtype/speakup:sharepresets. Elsewhere, it needs moodle/question:add.
     *
     * @param string $path e.g. /question/type/speakup/presets.php
     * @param array $params the parameters of the page's URL.
     * @param context $context the context of the question bank.
     * @param string $title
     */
    public static function setup(string $path, array $params, context $context, string $title): void {
        global $CFG, $PAGE;
        $url = new moodle_url($path, $params);
        switch ($context->contextlevel) {
            case CONTEXT_SYSTEM:
                require_once($CFG->libdir . '/adminlib.php');
                admin_externalpage_setup(self::ADMIN_PAGE, '', null, $url);
                break;
            case CONTEXT_COURSE:
                require_login($context->instanceid, false);
                require_capability('moodle/question:add', $context);
                $PAGE->set_url($url);
                $PAGE->set_pagelayout('admin');
                break;
            case CONTEXT_MODULE:
                [$course, $cm] = get_course_and_cm_from_cmid($context->instanceid);
                require_login($course, false, $cm);
                require_capability('moodle/question:add', $context);
                $PAGE->set_url($url);
                $PAGE->set_pagelayout('admin');
                break;
            case CONTEXT_COURSECAT:
                require_login();
                require_capability('moodle/question:add', $context);
                $PAGE->set_context($context);
                $PAGE->set_url($url);
                $PAGE->set_pagelayout('admin');
                break;
            default:
                throw new \moodle_exception('invalidcontext');
        }
        $PAGE->set_title($title);
        $PAGE->set_heading($context->contextlevel == CONTEXT_SYSTEM ? $title : $context->get_context_name(false));
    }
}
