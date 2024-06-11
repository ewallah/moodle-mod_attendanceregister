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
 * PHPUnit generator tests
 *
 * @package mod_attendanceregister
 * @copyright 2016 CINECA
 * @author  Lorenzo Nicora <fad@nicus.it>
 * @author  Renaat Debleu <info@eWallah.net>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_attendanceregister;

/**
 * other tests
 *
 * @package mod_attendanceregister
 * @copyright 2016 CINECA
 * @author  Lorenzo Nicora <fad@nicus.it>
 * @author  Renaat Debleu <info@eWallah.net>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class other_test extends \advanced_testcase {
    /**
     * Setup function.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Test the user capabilites
     * @covers \mod_attendanceregister\attendanceregister
     * @covers \mod_attendanceregister\user_capabilities
     */
    public function test_user_capabilities(): void {
        global $CFG, $USER;
        require_once($CFG->dirroot . '/mod/attendanceregister/lib.php');
        $dg = $this->getDataGenerator();
        $course = $dg->create_course();
        $context = \context_course::instance($course->id);
        $uc = new user_capabilities($context);
        $userid = $dg->create_user()->id;
        $dg->enrol_user($userid, $course->id);
        $this->AssertFalse($uc->canview($userid));
        $this->AssertFalse($uc->canddeletesession($userid));
        $this->AssertFalse($uc->canaddsession(null, $userid));
        $this->setAdminUser();
        $this->AssertFalse($uc->canview($USER->id));
        $this->AssertFalse($uc->canddeletesession($USER->id));
        $this->AssertFalse($uc->canaddsession(null, $USER->id));
        $cm = $dg->create_module('attendanceregister', ['course' => $course->id]);
        $context = \context_module::instance($cm->cmid);
        assign_capability('mod/attendanceregister:addotherofflinesess', CAP_ALLOW, 5, $context->id, true);
        assign_capability('mod/attendanceregister:deleteownofflinesess', CAP_ALLOW, 5, $context->id, true);
        assign_capability('mod/attendanceregister:deleteotherofflinesess', CAP_ALLOW, 5, $context->id, true);
        assign_capability('mod/attendanceregister:viewotherregisters', CAP_ALLOW, 5, $context->id, true);
        role_assign(5, $userid, $context->id);
        $uc = new user_capabilities($context);
        $this->assertTrue($uc->canview($userid));
        $this->AssertFalse($uc->canddeletesession($userid));
        $this->AssertFalse($uc->canaddsession($cm, $userid));
        $this->setAdminUser();
        $this->assertTrue($uc->canview($userid));
        $this->AssertFalse($uc->canddeletesession($userid));
        $this->AssertFalse($uc->canaddsession($cm, $userid));
        $this->assertTrue($uc->canview($USER->id));
        $this->AssertFalse($uc->canddeletesession($USER->id));
        $this->AssertFalse($uc->canaddsession($cm, $USER->id));
    }

    /**
     * Test the events
     * @covers \mod_attendanceregister\attendanceregister
     * @covers \mod_attendanceregister\event\course_module_viewed
     * @covers \mod_attendanceregister\event\course_module_instance_list_viewed
     * @covers \mod_attendanceregister\event\mod_attendance_recalculation
     * @covers \mod_attendanceregister\event\participants_attendance_report_viewed
     * @covers \mod_attendanceregister\event\user_attendance_addoffline
     * @covers \mod_attendanceregister\event\user_attendance_deloffline
     * @covers \mod_attendanceregister\event\user_attendance_details_viewed
     */
    public function test_events(): void {
        $dg = $this->getDataGenerator();
        $course = $dg->create_course();
        $userid = $dg->create_user()->id;
        $dg->enrol_user($userid, $course->id);
        $ar = $dg->create_module('attendanceregister', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('attendanceregister', $ar->id, $course->id, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $para = ['context' => $context, 'objectid' => $cm->id];
        $event = \mod_attendanceregister\event\course_module_viewed::create($para);
        $event->add_record_snapshot('course_modules', $cm);
        $event->add_record_snapshot('course', $course);
        $event->trigger();
        \mod_attendanceregister\event\mod_attendance_recalculation::create($para)->trigger();
        \mod_attendanceregister\event\participants_attendance_report_viewed::create($para)->trigger();
        \mod_attendanceregister\event\user_attendance_addoffline::create($para)->trigger();
        \mod_attendanceregister\event\user_attendance_deloffline::create($para)->trigger();
        \mod_attendanceregister\event\user_attendance_details_viewed::create($para)->trigger();

        $context = \context_course::instance($course->id);
        \mod_attendanceregister\event\course_module_instance_list_viewed::create(['context' => $context])->trigger();
    }

    /**
     * Test the tasks
     * @covers \mod_attendanceregister\attendanceregister
     * @covers \mod_attendanceregister\task\cron_task
     */
    public function test_task(): void {
        $dg = $this->getDataGenerator();
        $course = $dg->create_course();
        $userid = $dg->create_user()->id;
        $dg->enrol_user($userid, $course->id);
        $dg->create_module('attendanceregister', ['course' => $course->id]);
        $task = new \mod_attendanceregister\task\cron_task();
        $this->AssertEquals('Attendance Cron', $task->get_name());
        $this->AssertEquals(null, $task->execute());
    }

    /**
     * Test several logins
     * @covers \mod_attendanceregister\attendanceregister
     * @covers \mod_attendanceregister\task\cron_task
     * @covers \mod_attendanceregister\tracked_courses
     * @covers \mod_attendanceregister\user_aggregates
     * @covers \mod_attendanceregister\user_aggregates_summary
     * @covers \mod_attendanceregister\user_sessions
     * @covers \mod_attendanceregister\user_capabilities
     */
    public function test_logins(): void {
        global $CFG, $DB;
        $CFG->enablecompletion = 1;
        $dg = $this->getDataGenerator();
        $course = $dg->create_course(['enablecompletion' => 1]);
        $user = $dg->create_user();
        $dg->enrol_user($user->id, $course->id);
        $page = $dg->create_module('page', ['course' => $course->id], ['completion' => 2, 'completionview' => 1]);
        $dg->create_module('attendanceregister', ['course' => $course->id]);
        $context = \context_module::instance($page->cmid);
        $cm = get_coursemodule_from_instance('page', $page->id);
        $this->setUser($user);
        $this->assertTrue(isloggedin());

        $i = 0;
        $coursecontext = \context_course::instance($course->id);
        while ($i < 10) {
            page_view($page, $course, $cm, $context);
            $event = \mod_page\event\course_module_instance_list_viewed::create(['context' => $coursecontext]);
            $event->add_record_snapshot('course', $course);
            $event->trigger();
            $i++;
        }
        $this->setAdminUser();
        $task = new \mod_attendanceregister\task\cron_task();
        $this->AssertEquals('Attendance Cron', $task->get_name());
        $this->AssertEquals(null, $task->execute());
        $task->execute();
        $records = $DB->get_records('attendanceregister');
        foreach ($records as $record) {
            $class1 = new tracked_courses($record);
            $this->assertNotEmpty($class1->html_table());
            $usercaps = new user_capabilities(\context_course::instance($course->id));
            $class3 = new user_sessions($record, $user->id, $usercaps);
            $this->assertNotEmpty($class3->html_table());
            $class4 = new user_aggregates($record, $user->id, $class3);
            $this->assertNotEmpty($class4->html_table());
            $class5 = new user_aggregates_summary();
            $this->assertNotEmpty($class5);
        }
    }

    /**
     * Test backup
     * @covers \mod_attendanceregister\attendanceregister
     * @covers \backup_attendanceregister_activity_structure_step
     * @covers \backup_attendanceregister_activity_task
     * @covers \restore_attendanceregister_activity_structure_step
     * @covers \restore_attendanceregister_activity_task
     */
    public function test_backup(): void {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        set_config('backup_general_users', 1, 'backup');
        set_config('backup_general_logs', 1, 'backup');
        $dg = $this->getDataGenerator();
        $courseid = $dg->create_course()->id;
        $userid = $dg->create_user()->id;
        $dg->enrol_user($userid, $courseid);
        $cm = $dg->create_module('attendanceregister', ['course' => $courseid]);
        $session = new \stdClass();
        $session->register = $cm->id;
        $session->userid = $userid;
        $session->login = time() - 1000;
        $session->logout = time();
        $session->duration = 1000;
        $session->onlinesess = false;
        $session->refcourse = $courseid;
        $DB->insert_record('attendanceregister_session', $session);
        $this->setAdminUser();
        $task = new task\cron_task();
        $task->execute();
        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $courseid,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $USER->id
        );
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();
        unset($bc);
        $courseid = $dg->create_course()->id;
        $rc = new \restore_controller(
            $backupid,
            $courseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $USER->id,
            \backup::TARGET_CURRENT_ADDING
        );
        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();
        unset($rc);
    }

    /**
     * Test forms.
     * @covers \mod_attendanceregister\attendanceregister
     * @covers \mod_attendanceregister\attendanceregister
     * @covers \mod_attendanceregister_mod_form
     * @covers \mod_attendanceregister\forms\selfcertification_edit_form
     */
    public function test_forms(): void {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot . '/mod/attendanceregister/mod_form.php');
        $dg = $this->getDataGenerator();
        $this->setAdminUser();
        $course = $dg->create_course();
        $ar = $dg->create_module('attendanceregister', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('attendanceregister', $ar->id, $course->id, false, MUST_EXIST);
        $register = $DB->get_record('attendanceregister', ['id' => $cm->instance], '*', MUST_EXIST);
        $modinfo = get_fast_modinfo($course);
        $section = $modinfo->get_section_info(1);
        $current = new \stdClass();
        $current->instance = $cm;
        $arr = [];
        $form = new \mod_attendanceregister_mod_form($current, $section, $cm, $course);
        $form->add_completion_rules();
        $form->completion_rule_enabled([]);
        $form->get_data();
        $form->data_preprocessing($arr);

        $customformdata = ['register' => $register, 'courses' => [$course->id]];
        $mform = new forms\selfcertification_edit_form(null, $customformdata);
        $mform->validation(['a' => $cm->instance, 'login' => time() - 1000, 'logout' => time(), 'userid' => $USER->id], []);
    }

    /**
     * Test other files.
     * @coversNothing
     */
    public function test_files(): void {
        global $CFG;
        $plugin = new \stdClass();
        include($CFG->dirroot . '/mod/attendanceregister/version.php');
        include($CFG->dirroot . '/mod/attendanceregister/db/tasks.php');
        include($CFG->dirroot . '/mod/attendanceregister/db/log.php');
        include($CFG->dirroot . '/mod/attendanceregister/db/access.php');
        include($CFG->dirroot . '/mod/attendanceregister/db/upgrade.php');
        $this->assertNotEmpty($plugin);
    }
}
