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

/**
 * Observers for assignment submission and comment notifications.
 *
 * @package    local_reminders
 * @copyright  2026 local_reminders contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_reminders;

defined('MOODLE_INTERNAL') || die();

/**
 * Forwards assignment events to the notifier.
 *
 * @package    local_reminders
 * @copyright  2026 local_reminders contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assign_observer {

    /**
     * A student submitted an assignment.
     *
     * @param \mod_assign\event\assessable_submitted $event
     * @return void
     */
    public static function assessable_submitted($event) {
        self::guard(function() use ($event) {
            assign_notifier::assessable_submitted($event);
        });
    }

    /**
     * A submission comment was added by a student or a grader.
     *
     * @param \assignsubmission_comments\event\comment_created $event
     * @return void
     */
    public static function submission_comment_created($event) {
        self::guard(function() use ($event) {
            assign_notifier::submission_comment_created($event);
        });
    }

    /**
     * A grade was saved. Feedback comments are emailed when their text changes.
     *
     * @param \mod_assign\event\submission_graded $event
     * @return void
     */
    public static function submission_graded($event) {
        self::guard(function() use ($event) {
            assign_notifier::submission_graded($event);
        });
    }

    /**
     * Run a notifier callback without breaking the assignment action.
     *
     * @param callable $callback
     * @return void
     */
    private static function guard($callback) {
        try {
            $callback();
        } catch (\Throwable $e) {
            if (defined('PHPUNIT_TEST') && PHPUNIT_TEST) {
                throw $e;
            }
            debugging('local_reminders assignment notification failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
