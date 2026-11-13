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
 * Tests for assignment submission and comment emails.
 *
 * @package    local_reminders
 * @copyright  2026 local_reminders contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_reminders;

use local_reminders\privacy\provider;
use mod_assign_test_generator;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/assign/tests/generator.php');
require_once($CFG->dirroot . '/comment/lib.php');

/**
 * Assignment notification tests.
 *
 * @package    local_reminders
 * @copyright  2026 local_reminders contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_reminders\assign_notifier
 */
final class assign_notifier_test extends \advanced_testcase {

    use mod_assign_test_generator;

    /**
     * Create a course, teacher, student, and assignment.
     *
     * @param array $params Assignment generator params.
     * @return array
     */
    private function create_assignment(array $params = []): array {
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student', [
            'firstname' => 'Submitter',
            'lastname' => 'Student',
        ]);
        $assign = $this->create_instance($course, array_merge([
            'name' => 'My assignment',
            'submissiondrafts' => 0,
            'sendnotifications' => 0,
            'assignsubmission_onlinetext_enabled' => 1,
            'assignfeedback_comments_enabled' => 1,
        ], $params));
        return [$course, $teacher, $student, $assign];
    }

    /**
     * Messages produced by this plugin.
     *
     * @param \phpunit_message_sink $sink
     * @return array
     */
    private function plugin_messages(\phpunit_message_sink $sink): array {
        return $sink->get_messages_by_component('local_reminders');
    }

    /**
     * The setting stays off until an administrator enables it.
     */
    public function test_disabled_sends_nothing(): void {
        $this->resetAfterTest();
        set_config('local_reminders_assigncommentnotify', 0);
        list($course, $teacher, $student, $assign) = $this->create_assignment();

        $sink = $this->redirectMessages();
        $this->add_submission($student, $assign, 'My answer');
        $sink->close();

        $this->assertCount(0, $this->plugin_messages($sink));
    }

    /**
     * Submitting an assignment emails the grader.
     */
    public function test_submission_emails_grader(): void {
        $this->resetAfterTest();
        set_config('local_reminders_assigncommentnotify', 1);
        list($course, $teacher, $student, $assign) = $this->create_assignment();

        $sink = $this->redirectMessages();
        $this->add_submission($student, $assign, 'My answer');
        $messages = $this->plugin_messages($sink);
        $sink->close();

        $this->assertCount(1, $messages);
        $message = reset($messages);
        $this->assertEquals($teacher->id, $message->useridto);
        $this->assertStringContainsString('Submitter Student', $message->fullmessage);
        $this->assertStringContainsString('My assignment', $message->fullmessage);
    }

    /**
     * Blind marking keeps the student name out of the grader email.
     */
    public function test_submission_hides_student_when_blind_marking(): void {
        $this->resetAfterTest();
        set_config('local_reminders_assigncommentnotify', 1);
        list($course, $teacher, $student, $assign) = $this->create_assignment([
            'blindmarking' => 1,
        ]);

        $sink = $this->redirectMessages();
        $this->add_submission($student, $assign, 'My answer');
        $messages = $this->plugin_messages($sink);
        $sink->close();

        $this->assertCount(1, $messages);
        $message = reset($messages);
        $this->assertStringNotContainsString('Submitter', $message->fullmessage);
    }

    /**
     * An assignment that already notifies graders is not emailed a second time.
     */
    public function test_builtin_grader_notification_is_not_duplicated(): void {
        $this->resetAfterTest();
        set_config('local_reminders_assigncommentnotify', 1);
        list($course, $teacher, $student, $assign) = $this->create_assignment([
            'sendnotifications' => 1,
        ]);

        $sink = $this->redirectMessages();
        $this->add_submission($student, $assign, 'My answer');
        $messages = $this->plugin_messages($sink);
        $sink->close();

        $this->assertCount(0, $messages);
    }

    /**
     * A student comment emails the grader, and a grader comment emails the student.
     */
    public function test_submission_comments_email_the_other_party(): void {
        global $CFG;
        $this->resetAfterTest();
        $CFG->usecomments = 1;
        set_config('local_reminders_assigncommentnotify', 1);
        list($course, $teacher, $student, $assign) = $this->create_assignment();
        $this->add_submission($student, $assign, 'My answer');

        $submission = $assign->get_user_submission($student->id, false);
        $context = $assign->get_context();

        $this->setUser($student);
        $sink = $this->redirectMessages();
        $this->add_comment($assign, $context, $submission->id, 'Please review the file');
        $studentmessages = $this->plugin_messages($sink);
        $sink->close();

        $this->assertCount(1, $studentmessages);
        $message = reset($studentmessages);
        $this->assertEquals($teacher->id, $message->useridto);
        $this->assertStringContainsString('Please review the file', $message->fullmessage);

        $this->setUser($teacher);
        $sink = $this->redirectMessages();
        $this->add_comment($assign, $context, $submission->id, 'Looks good, thanks');
        $teachermessages = $this->plugin_messages($sink);
        $sink->close();

        $this->assertCount(1, $teachermessages);
        $message = reset($teachermessages);
        $this->assertEquals($student->id, $message->useridto);
        $this->assertStringContainsString('Looks good, thanks', $message->fullmessage);
    }

    /**
     * Feedback is emailed when the text changes, including after marking workflow release.
     */
    public function test_feedback_comment_emails_student_once_per_change(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('local_reminders_assigncommentnotify', 1);
        list($course, $teacher, $student, $assign) = $this->create_assignment([
            'markingworkflow' => 1,
        ]);
        $this->add_submission($student, $assign, 'My answer');

        $sink = $this->redirectMessages();
        $this->mark_submission($teacher, $assign, $student, 80, [
            'assignfeedbackcomments_editor' => [
                'text' => 'First feedback',
                'format' => FORMAT_HTML,
            ],
            'workflowstate' => 'inmarking',
        ]);
        $this->assertCount(0, $this->plugin_messages($sink));
        $sink->close();

        $sink = $this->redirectMessages();
        $this->mark_submission($teacher, $assign, $student, 80, [
            'assignfeedbackcomments_editor' => [
                'text' => 'First feedback',
                'format' => FORMAT_HTML,
            ],
            'workflowstate' => 'released',
        ]);
        $messages = $this->plugin_messages($sink);
        $sink->close();

        $this->assertCount(1, $messages);
        $message = reset($messages);
        $this->assertEquals($student->id, $message->useridto);
        $this->assertStringContainsString('First feedback', $message->fullmessage);
        $this->assertEquals(1, $DB->count_records('local_reminders_assignnote'));

        $sink = $this->redirectMessages();
        $this->mark_submission($teacher, $assign, $student, 90, [
            'assignfeedbackcomments_editor' => [
                'text' => 'First feedback',
                'format' => FORMAT_HTML,
            ],
            'workflowstate' => 'released',
        ]);
        $this->assertCount(0, $this->plugin_messages($sink));
        $sink->close();

        $sink = $this->redirectMessages();
        $this->mark_submission($teacher, $assign, $student, 90, [
            'assignfeedbackcomments_editor' => [
                'text' => 'Updated feedback',
                'format' => FORMAT_HTML,
            ],
            'workflowstate' => 'released',
        ]);
        $messages = $this->plugin_messages($sink);
        $sink->close();

        $this->assertCount(1, $messages);
        $message = reset($messages);
        $this->assertStringContainsString('Updated feedback', $message->fullmessage);

        $context = $assign->get_context();
        provider::delete_data_for_user(new \core_privacy\local\request\approved_contextlist(
            $student,
            'local_reminders',
            [$context->id]
        ));
        $this->assertEquals(0, $DB->count_records('local_reminders_assignnote'));
    }

    /**
     * Add a submission comment as the current user.
     *
     * @param \assign $assign
     * @param \context $context
     * @param int $submissionid
     * @param string $text
     * @return void
     */
    private function add_comment($assign, $context, $submissionid, $text): void {
        $options = new \stdClass();
        $options->area = 'submission_comments';
        $options->course = $assign->get_course();
        $options->context = $context;
        $options->itemid = $submissionid;
        $options->component = 'assignsubmission_comments';
        $options->showcount = true;
        $options->displaycancel = true;
        $comment = new \comment($options);
        $comment->add($text);
    }
}
