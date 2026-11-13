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
 * Hebrew strings for assignment comment notifications.
 *
 * @package    local_reminders
 * @copyright  2026 local_reminders contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['assignheading'] = 'התראות רכיב מטלה';
$string['assignheadingdesc'] = 'שליחת מייל לצד השני כאשר מוגשת מטלה, או כאשר מתווספת הערה להגשה או משוב.';
$string['assigncommentnotify'] = 'התראה למייל בעת קבלת הערה למטלות';
$string['assigncommentnotifydesc'] = 'כאשר האפשרות פעילה, הגשת מטלה או הוספת הערה על ידי הסטודנט שולחת מייל למנחים. תגובת מנחה או משוב טקסטואלי שולחים מייל לסטודנט. אם במטלה עצמה כבר מופעלת הודעה למנחים על הגשות, לא נשלח מייל נוסף עבור ההגשה. משוב נשלח רק כאשר טקסט המשוב משתנה, ורק אחרי שהציון שוחרר אם מופעל תהליך בדיקה.';
$string['messageprovider:reminders_assigncomment'] = 'התראות על הגשות, הערות ומשוב במטלות';
$string['assignmailsubmittedsubject'] = 'הוגשה מטלה: {$a->assignment}';
$string['assignmailsubmittedbody'] = '{$a->user} הגיש/ה את המטלה "{$a->assignment}" בקורס "{$a->course}".

לצפייה במטלה:
{$a->url}';
$string['assignmailsubmittedanonsubject'] = 'הוגשה מטלה: {$a->assignment}';
$string['assignmailsubmittedanonbody'] = 'הוגשה המטלה "{$a->assignment}" בקורס "{$a->course}".

לצפייה במטלה:
{$a->url}';
$string['assignmailstudentsubject'] = 'הערה חדשה במטלה: {$a->assignment}';
$string['assignmailstudentbody'] = '{$a->user} השאיר/ה הערה במטלה "{$a->assignment}" בקורס "{$a->course}":

{$a->comment}

לצפייה במטלה:
{$a->url}';
$string['assignmailstudentanonsubject'] = 'הערה חדשה במטלה: {$a->assignment}';
$string['assignmailstudentanonbody'] = 'נוספה הערה במטלה "{$a->assignment}" בקורס "{$a->course}":

{$a->comment}

לצפייה במטלה:
{$a->url}';
$string['assignmailteachersubject'] = 'הערה חדשה על המטלה שלך: {$a->assignment}';
$string['assignmailteacherbody'] = '{$a->user} השאיר/ה הערה על ההגשה שלך במטלה "{$a->assignment}" בקורס "{$a->course}":

{$a->comment}

לצפייה במטלה:
{$a->url}';
$string['assignmailteacheranonsubject'] = 'הערה חדשה על המטלה שלך: {$a->assignment}';
$string['assignmailteacheranonbody'] = 'נוספה הערה על ההגשה שלך במטלה "{$a->assignment}" בקורס "{$a->course}":

{$a->comment}

לצפייה במטלה:
{$a->url}';
$string['assignmailfeedbacksubject'] = 'משוב חדש על המטלה שלך: {$a->assignment}';
$string['assignmailfeedbackbody'] = '{$a->user} פרסם/ה משוב על המטלה "{$a->assignment}" בקורס "{$a->course}":

{$a->comment}

לצפייה במטלה:
{$a->url}';
$string['assignmailfeedbackanonsubject'] = 'משוב חדש על המטלה שלך: {$a->assignment}';
$string['assignmailfeedbackanonbody'] = 'פורסם משוב על המטלה "{$a->assignment}" בקורס "{$a->course}":

{$a->comment}

לצפייה במטלה:
{$a->url}';
$string['privacy:metadata'] = 'תוסף התזכורות שומר גיבוב של משוב מטלה שכבר נשלח במייל, כדי לא לשלוח שוב את אותו משוב.';
$string['privacy:metadata:local_reminders_assignnote'] = 'מתעד איזה טקסט משוב של מטלה כבר נשלח במייל.';
$string['privacy:metadata:local_reminders_assignnote:gradeid'] = 'ציון המטלה שאליו שייך מצב ההתראה.';
$string['privacy:metadata:local_reminders_assignnote:contenthash'] = 'גיבוב של טקסט המשוב שנשלח.';
$string['privacy:metadata:local_reminders_assignnote:timemodified'] = 'המועד שבו עודכן מצב ההתראה.';
