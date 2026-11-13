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
 * Emails the other party when an assignment is submitted or commented on.
 *
 * @package    local_reminders
 * @copyright  2026 local_reminders contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_reminders;

defined('MOODLE_INTERNAL') || die();

/**
 * Builds and sends assignment submission, comment, and feedback emails.
 *
 * @package    local_reminders
 * @copyright  2026 local_reminders contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assign_notifier {

    /** @var int Maximum comment length included in an email. */
    const COMMENT_MAX_LENGTH = 4000;

    /** @var string Marking workflow state in which students may see feedback. */
    const WORKFLOW_RELEASED = 'released';

    /**
     * Whether assignment comment emails are enabled in plugin settings.
     *
     * @return bool
     */
    public static function is_enabled() {
        global $CFG;
        return !empty($CFG->local_reminders_assigncommentnotify);
    }

    /**
     * Email graders when a student submits, unless the assignment already does that.
     *
     * @param \mod_assign\event\assessable_submitted $event
     * @return void
     */
    public static function assessable_submitted($event) {
        if (!self::is_enabled() || $event->is_restored()) {
            return;
        }

        $activity = self::load_activity($event->contextinstanceid);
        if (!$activity) {
            return;
        }
        list($assign, $cm, $course, $context) = $activity;

        $late = !empty($assign->duedate) && ((int) $assign->duedate < time());
        if (!empty($assign->sendnotifications) || ($late && !empty($assign->sendlatenotifications))) {
            // Moodle already emailed the graders for this submission.
            return;
        }

        $studentid = !empty($event->relateduserid) ? (int) $event->relateduserid : (int) $event->userid;
        $student = \core_user::get_user($studentid);
        if (!$student || !empty($student->deleted)) {
            return;
        }

        $graders = self::get_grader_recipients($assign, $cm, $studentid, $student->id);
        self::deliver($graders, $student, $assign, $context, $course, 'assignmailsubmitted', null, true);
    }

    /**
     * Email the other party when a submission comment is created.
     *
     * @param \assignsubmission_comments\event\comment_created $event
     * @return void
     */
    public static function submission_comment_created($event) {
        global $DB;

        if (!self::is_enabled() || $event->is_restored()) {
            return;
        }

        $activity = self::load_activity($event->contextinstanceid);
        if (!$activity) {
            return;
        }
        list($assign, $cm, $course, $context) = $activity;

        $comment = $DB->get_record('comments', ['id' => $event->objectid]);
        if (!$comment || $comment->commentarea !== 'submission_comments') {
            return;
        }
        $submission = $DB->get_record('assign_submission', ['id' => $comment->itemid]);
        if (!$submission || (int) $submission->assignment !== (int) $assign->id) {
            return;
        }

        $plain = self::plain_text($comment->content, $comment->format, $context);
        if ($plain === '') {
            return;
        }

        $author = \core_user::get_user($comment->userid);
        if (!$author || !empty($author->deleted)) {
            return;
        }

        if (has_capability('mod/assign:grade', $context, $author->id)) {
            $students = self::get_submission_students($submission, $context, $author->id);
            self::deliver($students, $author, $assign, $context, $course, 'assignmailteacher', $plain, false);
            return;
        }

        $studentid = !empty($submission->userid) ? (int) $submission->userid : (int) $author->id;
        $graders = self::get_grader_recipients($assign, $cm, $studentid, $author->id);
        self::deliver($graders, $author, $assign, $context, $course, 'assignmailstudent', $plain, true);
    }

    /**
     * Email the student when saved feedback comment text changes.
     *
     * @param \mod_assign\event\submission_graded $event
     * @return void
     */
    public static function submission_graded($event) {
        global $DB;

        if (!self::is_enabled() || $event->is_restored()) {
            return;
        }

        $activity = self::load_activity($event->contextinstanceid);
        if (!$activity) {
            return;
        }
        list($assign, , $course, $context) = $activity;

        $grade = $DB->get_record('assign_grades', ['id' => $event->objectid]);
        if (!$grade || (int) $grade->assignment !== (int) $assign->id) {
            return;
        }

        self::notify_feedback($assign, $course, $context, $grade);
    }

    /**
     * Send feedback for one grade when it is visible and the text is new.
     *
     * @param \stdClass $assign
     * @param \stdClass $course
     * @param \context_module $context
     * @param \stdClass $grade
     * @return void
     */
    private static function notify_feedback($assign, $course, $context, $grade) {
        global $DB;

        if (!empty($assign->markingworkflow)) {
            $flags = $DB->get_record('assign_user_flags', [
                'assignment' => $assign->id,
                'userid' => $grade->userid,
            ]);
            if (!$flags || $flags->workflowstate !== self::WORKFLOW_RELEASED) {
                return;
            }
        }

        $feedback = $DB->get_record('assignfeedback_comments', ['grade' => $grade->id], '*', IGNORE_MULTIPLE);
        $plain = '';
        if ($feedback) {
            $plain = self::plain_text($feedback->commenttext, $feedback->commentformat, $context);
        }
        $hash = sha1($plain);

        $existing = $DB->get_record('local_reminders_assignnote', ['gradeid' => $grade->id]);
        if ($existing && $existing->contenthash === $hash) {
            return;
        }
        if ($plain === '') {
            self::store_feedback_hash($existing, $grade->id, $hash);
            return;
        }

        $student = \core_user::get_user($grade->userid);
        if (!$student || !self::can_receive($student)) {
            self::store_feedback_hash($existing, $grade->id, $hash);
            return;
        }

        $author = !empty($grade->grader) ? \core_user::get_user($grade->grader) : null;
        if (!$author || !empty($author->deleted)) {
            $author = \core_user::get_noreply_user();
        }

        $sent = self::deliver(
            [$student->id => $student],
            $author,
            $assign,
            $context,
            $course,
            'assignmailfeedback',
            $plain,
            false
        );
        if ($sent > 0) {
            self::store_feedback_hash($existing, $grade->id, $hash);
        }
    }

    /**
     * Remember the feedback text that was emailed for a grade.
     *
     * @param \stdClass|false $existing
     * @param int $gradeid
     * @param string $hash
     * @return void
     */
    private static function store_feedback_hash($existing, $gradeid, $hash) {
        global $DB;

        $now = time();
        if ($existing) {
            $existing->contenthash = $hash;
            $existing->timemodified = $now;
            $DB->update_record('local_reminders_assignnote', $existing);
            return;
        }

        $record = new \stdClass();
        $record->gradeid = $gradeid;
        $record->contenthash = $hash;
        $record->timemodified = $now;
        $DB->insert_record('local_reminders_assignnote', $record);
    }

    /**
     * Graders who should hear about a student's submission or comment.
     *
     * @param \stdClass $assign
     * @param \stdClass $cm
     * @param int $studentid
     * @param int $authorid
     * @return \stdClass[]
     */
    private static function get_grader_recipients($assign, $cm, $studentid, $authorid) {
        $context = \context_module::instance($cm->id);
        $potential = get_enrolled_users(
            $context,
            'mod/assign:receivegradernotifications',
            0,
            'u.*',
            null,
            0,
            0,
            true
        );

        $recipients = [];
        $add = function($user) use (&$recipients, $authorid) {
            if (!$user || (int) $user->id === (int) $authorid || !self::can_receive($user)) {
                return;
            }
            $recipients[$user->id] = $user;
        };

        if (groups_get_activity_groupmode($cm) == SEPARATEGROUPS) {
            $groups = groups_get_all_groups($assign->course, $studentid, $cm->groupingid);
            if ($groups) {
                foreach ($groups as $group) {
                    foreach ($potential as $user) {
                        if ((int) $user->id === (int) $studentid) {
                            continue;
                        }
                        if (groups_is_member($group->id, $user->id)) {
                            $add($user);
                        }
                    }
                }
            } else {
                foreach ($potential as $user) {
                    if ((int) $user->id === (int) $studentid) {
                        continue;
                    }
                    if (!groups_has_membership($cm, $user->id)) {
                        $add($user);
                    }
                }
            }
            return $recipients;
        }

        foreach ($potential as $user) {
            if ((int) $user->id === (int) $studentid) {
                continue;
            }
            $add($user);
        }
        return $recipients;
    }

    /**
     * Students who should receive a grader's comment on this submission.
     *
     * @param \stdClass $submission
     * @param \context_module $context
     * @param int $authorid
     * @return \stdClass[]
     */
    private static function get_submission_students($submission, $context, $authorid) {
        $ids = [];
        if (!empty($submission->userid)) {
            $ids[(int) $submission->userid] = true;
        }
        if (!empty($submission->groupid)) {
            $members = groups_get_members($submission->groupid, 'u.id');
            foreach ($members as $member) {
                $memberid = (int) $member->id;
                if ($memberid === (int) $authorid || isset($ids[$memberid])) {
                    continue;
                }
                if (has_capability('mod/assign:grade', $context, $memberid)) {
                    continue;
                }
                $ids[$memberid] = true;
            }
        }
        unset($ids[(int) $authorid]);

        $users = [];
        foreach (array_keys($ids) as $userid) {
            $user = \core_user::get_user($userid);
            if (self::can_receive($user)) {
                $users[$user->id] = $user;
            }
        }
        return $users;
    }

    /**
     * Send one notification to each recipient, in that person's language.
     *
     * @param \stdClass[] $recipients
     * @param \stdClass $author
     * @param \stdClass $assign
     * @param \context_module $context
     * @param \stdClass $course
     * @param string $stringbase
     * @param string|null $comment
     * @param bool $tograders True when the author is a student and graders are the recipients.
     * @return int Number of messages accepted by the messaging API.
     */
    private static function deliver($recipients, $author, $assign, $context, $course, $stringbase, $comment, $tograders) {
        if (!$recipients) {
            return 0;
        }

        $coursecontext = \context_course::instance($course->id);
        $url = new \moodle_url('/mod/assign/view.php', ['id' => $context->instanceid]);
        $sent = 0;

        foreach ($recipients as $user) {
            if (!self::can_receive($user) || (int) $user->id === (int) $author->id) {
                continue;
            }

            $anonymous = $tograders
                ? (!empty($assign->blindmarking) && empty($assign->revealidentities))
                : (!empty($assign->hidegrader) && !has_capability('mod/assign:showhiddengrader', $context, $user));
            $from = $anonymous ? \core_user::get_noreply_user() : $author;

            $previous = force_current_language($user->lang);
            try {
                $a = new \stdClass();
                $a->assignment = format_string($assign->name, true, ['context' => $context]);
                $a->course = format_string($course->fullname, true, ['context' => $coursecontext]);
                $a->url = $url->out(false);
                $a->comment = (string) $comment;
                if (!$anonymous) {
                    $a->user = fullname($author);
                }
                $key = $stringbase . ($anonymous ? 'anon' : '');
                $subject = get_string($key . 'subject', 'local_reminders', $a);
                $plain = get_string($key . 'body', 'local_reminders', $a);
                if (self::send_message($from, $user, $subject, $plain, $url, $a->assignment, $course->id)) {
                    $sent++;
                }
            } finally {
                force_current_language($previous);
            }
        }

        return $sent;
    }

    /**
     * Hand a notification to Moodle messaging.
     *
     * @param \stdClass $from
     * @param \stdClass $user
     * @param string $subject
     * @param string $plain
     * @param \moodle_url $url
     * @param string $urlname
     * @param int $courseid
     * @return bool
     */
    private static function send_message($from, $user, $subject, $plain, $url, $urlname, $courseid) {
        $urltext = s($url->out(false));
        $html = '<div dir="auto">' . str_replace(
            $urltext,
            '<a href="' . $url->out(false) . '">' . $urltext . '</a>',
            nl2br(s($plain))
        ) . '</div>';

        $message = new \core\message\message();
        $message->component = 'local_reminders';
        $message->name = 'reminders_assigncomment';
        $message->userfrom = $from;
        $message->userto = $user;
        $message->subject = $subject;
        $message->fullmessage = $plain;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = $html;
        $message->smallmessage = $subject;
        $message->notification = 1;
        $message->contexturl = $url;
        $message->contexturlname = $urlname;
        $message->courseid = $courseid;

        return !empty(message_send($message));
    }

    /**
     * Convert stored comment HTML into plain text for an email.
     *
     * @param string $raw
     * @param int $format
     * @param \context $context
     * @return string
     */
    private static function plain_text($raw, $format, $context) {
        if ($raw === null || $raw === '') {
            return '';
        }
        $formatted = format_text($raw, $format, [
            'context' => $context,
            'filter' => false,
            'para' => false,
        ]);
        $plain = trim(html_to_text($formatted, 0, false));
        if (\core_text::strlen($plain) > self::COMMENT_MAX_LENGTH) {
            $plain = \core_text::substr($plain, 0, self::COMMENT_MAX_LENGTH) . '...';
        }
        return $plain;
    }

    /**
     * Load the assignment behind a module-context event.
     *
     * @param int $cmid
     * @return array|null Array of assign, cm, course, context.
     */
    private static function load_activity($cmid) {
        global $DB;

        $cm = get_coursemodule_from_id('assign', $cmid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return null;
        }
        $assign = $DB->get_record('assign', ['id' => $cm->instance]);
        if (!$assign) {
            return null;
        }
        $course = get_course($cm->course);
        $context = \context_module::instance($cm->id);
        return [$assign, $cm, $course, $context];
    }

    /**
     * Whether this user can be emailed.
     *
     * @param \stdClass|false $user
     * @return bool
     */
    private static function can_receive($user) {
        if (!$user || empty($user->id) || !empty($user->deleted) || !empty($user->suspended)) {
            return false;
        }
        if (empty($user->confirmed) || empty($user->email)) {
            return false;
        }
        return true;
    }
}
