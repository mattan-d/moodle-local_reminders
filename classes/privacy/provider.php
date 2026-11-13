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
 * Plugin privacy provider.
 *
 * @package    local_reminders
 * @copyright  2020 Catalyst AU
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_reminders\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for assignment feedback notification state.
 *
 * @package    local_reminders
 * @copyright  2020 Catalyst AU
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /**
     * Describe stored data.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_reminders_assignnote', [
            'gradeid' => 'privacy:metadata:local_reminders_assignnote:gradeid',
            'contenthash' => 'privacy:metadata:local_reminders_assignnote:contenthash',
            'timemodified' => 'privacy:metadata:local_reminders_assignnote:timemodified',
        ], 'privacy:metadata:local_reminders_assignnote');

        return $collection;
    }

    /**
     * Contexts that contain notification state for this user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT ctx.id
                  FROM {local_reminders_assignnote} n
                  JOIN {assign_grades} g ON g.id = n.gradeid
                  JOIN {course_modules} cm ON cm.instance = g.assignment
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :modulelevel
                 WHERE g.userid = :userid";
        $contextlist->add_from_sql($sql, [
            'modulelevel' => CONTEXT_MODULE,
            'userid' => $userid,
        ]);
        return $contextlist;
    }

    /**
     * Export notification state.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_MODULE) {
                continue;
            }
            $cm = get_coursemodule_from_id('assign', $context->instanceid);
            if (!$cm) {
                continue;
            }
            $notes = $DB->get_records_sql(
                "SELECT n.*
                   FROM {local_reminders_assignnote} n
                   JOIN {assign_grades} g ON g.id = n.gradeid
                  WHERE g.assignment = :assignid AND g.userid = :userid",
                ['assignid' => $cm->instance, 'userid' => $userid]
            );
            if (!$notes) {
                continue;
            }
            $exported = [];
            foreach ($notes as $note) {
                $exported[] = [
                    'contenthash' => $note->contenthash,
                    'timemodified' => transform::datetime($note->timemodified),
                ];
            }
            writer::with_context($context)->export_data(
                [get_string('assignheading', 'local_reminders')],
                (object) ['notes' => $exported]
            );
        }
    }

    /**
     * Delete notification state for every user in this assignment.
     *
     * @param \context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }
        $cm = get_coursemodule_from_id('assign', $context->instanceid);
        if (!$cm) {
            return;
        }
        $gradeids = $DB->get_fieldset_select('assign_grades', 'id', 'assignment = :assignid', [
            'assignid' => $cm->instance,
        ]);
        if ($gradeids) {
            $DB->delete_records_list('local_reminders_assignnote', 'gradeid', $gradeids);
        }
    }

    /**
     * Delete notification state for one user.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_MODULE) {
                continue;
            }
            $cm = get_coursemodule_from_id('assign', $context->instanceid);
            if (!$cm) {
                continue;
            }
            $gradeids = $DB->get_fieldset_select('assign_grades', 'id', 'assignment = :assignid AND userid = :userid', [
                'assignid' => $cm->instance,
                'userid' => $userid,
            ]);
            if ($gradeids) {
                $DB->delete_records_list('local_reminders_assignnote', 'gradeid', $gradeids);
            }
        }
    }

    /**
     * Users who have notification state in this context.
     *
     * @param userlist $userlist
     * @return void
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }
        $cm = get_coursemodule_from_id('assign', $context->instanceid);
        if (!$cm) {
            return;
        }
        $sql = "SELECT g.userid
                  FROM {local_reminders_assignnote} n
                  JOIN {assign_grades} g ON g.id = n.gradeid
                 WHERE g.assignment = :assignid";
        $userlist->add_from_sql('userid', $sql, ['assignid' => $cm->instance]);
    }

    /**
     * Delete notification state for the listed users.
     *
     * @param approved_userlist $userlist
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }
        $cm = get_coursemodule_from_id('assign', $context->instanceid);
        if (!$cm) {
            return;
        }
        $userids = $userlist->get_userids();
        if (!$userids) {
            return;
        }
        list($insql, $params) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['assignid'] = $cm->instance;
        $gradeids = $DB->get_fieldset_select(
            'assign_grades',
            'id',
            "assignment = :assignid AND userid {$insql}",
            $params
        );
        if ($gradeids) {
            $DB->delete_records_list('local_reminders_assignnote', 'gradeid', $gradeids);
        }
    }
}
