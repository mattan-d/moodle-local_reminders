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
 * All events consumed by local reminder plugin.
 *
 * @package    local_reminders
 * @copyright  2012 Isuru Madushanka Weerarathna
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\core\event\calendar_event_updated',
        'callback' => '\local_reminders\calendar_observer::calendar_event_updated',
    ],
    [
        'eventname' => '\core\event\calendar_event_created',
        'callback' => '\local_reminders\calendar_observer::calendar_event_added',
    ],
    [
        'eventname' => '\core\event\calendar_event_deleted',
        'callback' => '\local_reminders\calendar_observer::calendar_event_removed',
    ],
    [
        'eventname' => '\mod_assign\event\assessable_submitted',
        'callback' => '\local_reminders\assign_observer::assessable_submitted',
    ],
    [
        'eventname' => '\assignsubmission_comments\event\comment_created',
        'callback' => '\local_reminders\assign_observer::submission_comment_created',
    ],
    [
        'eventname' => '\mod_assign\event\submission_graded',
        'callback' => '\local_reminders\assign_observer::submission_graded',
    ],
];
