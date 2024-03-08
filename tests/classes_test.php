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
 * Classes tests.
 *
 * @package mod_attendanceregister
 * @copyright 2016 CINECA
 * @author  Lorenzo Nicora <fad@nicus.it>
 * @author  Renaat Debleu <info@eWallah.net>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_attendanceregister;

use stdClass;

/**
 * Unit tests classes
 *
 * @package mod_attendanceregister
 * @copyright 2016 CINECA
 * @author  Lorenzo Nicora <fad@nicus.it>
 * @author  Renaat Debleu <info@eWallah.net>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class classes_test extends \advanced_testcase {

    /** @var stdClass context context */
    private $context;

    /** @var int course id*/
    private $courseid;

    /** @var int course module id*/
    private $cmid;

    /** @var int userid */
    private $userid;

    /**
     * Basic setup for these tests.
     */
    public function setUp(): void {
        $this->resetAfterTest();
        $dg = $this->getDataGenerator();
        $this->courseid = $dg->create_course()->id;
        $this->userid = $dg->create_user()->id;
        $cm = $dg->create_module('attendanceregister', ['course' => $this->courseid]);
        $dg->create_module('attendanceregister', ['course' => $this->courseid]);
        $this->context = \context_module::instance($cm->cmid);
        $this->cmid = $cm->id;
    }

    /**
     * Test tracked courses.
     * @covers \attendanceregister_tracked_courses
     * @covers \attendanceregister_tracked_users
     * @covers \attendanceregister_user_aggregates
     * @covers \attendanceregister_user_sessions
     * @covers \attendanceregister_user_aggregates_summary
     */
    public function test_tracked_courses(): void {
        $this->tracked_courses();
        $this->fill_database();
        $this->tracked_courses();
    }

    /**
     * Test the records.
     */
    private function tracked_courses() {
        global $DB;
        $this->setAdminUser();
        $records = $DB->get_records('attendanceregister');
        foreach ($records as $record) {
            $class1 = new \attendanceregister_tracked_courses($record);
            $this->assertNotEmpty($class1->html_table());
            $usercaps = new \attendanceregister_user_capablities($this->context);
            $this->assertTrue($usercaps->canview($this->userid));
            $this->assertFalse($usercaps->canddeletesession($this->userid));
            $this->assertFalse($usercaps->canaddsession($record, $this->userid));
            $class2 = new \attendanceregister_tracked_users($record, $usercaps, 0);
            $this->assertNotEmpty($class2->html_table());
            $class3 = new \attendanceregister_user_sessions($record, $this->userid, $usercaps);
            $this->assertNotEmpty($class3->html_table());
            $class4 = new \attendanceregister_user_aggregates($record, $this->userid, $class3);
            $this->assertNotEmpty($class4->html_table());
            $class5 = new \attendanceregister_user_aggregates_summary();
            $this->assertNotEmpty($class5);
        }
        $this->setUser($this->userid);
        foreach ($records as $record) {
            $class1 = new \attendanceregister_tracked_courses($record);
            $this->assertNotEmpty($class1->html_table());
            $usercaps = new \attendanceregister_user_capablities($this->context);
            $usercaps->canview($this->userid);
            $usercaps->canddeletesession($this->userid);
            $usercaps->canaddsession($record, $this->userid);
            $class2 = new \attendanceregister_tracked_users($record, $usercaps, 0);
            $this->assertNotEmpty($class2->html_table());
            $class3 = new \attendanceregister_user_sessions($record, $this->userid, $usercaps);
            $this->assertNotEmpty($class3->html_table());
            $class4 = new \attendanceregister_user_aggregates($record, $this->userid, $class3);
            $this->assertNotEmpty($class4->html_table());
            $class5 = new \attendanceregister_user_aggregates_summary();
            $this->assertNotEmpty($class5);
        }
    }

    /**
     * Fill the database.
     */
    private function fill_database() {
        global $DB;
        $dg = $this->getDataGenerator();
        $dg->enrol_user($this->userid, $this->courseid);
        $lock = new stdClass();
        $lock->register = $this->cmid;
        $lock->userid = $this->userid;
        $lock->takenon = time();
        $DB->insert_record('attendanceregister_lock', $lock);
        $aggregate = new stdClass();
        $aggregate->register = $this->cmid;
        $aggregate->userid = $this->userid;
        $aggregate->onlinesess = 0;
        $aggregate->refcourse = $this->courseid;
        $aggregate->duration = 30;
        $aggregate->total = 1;
        $aggregate->grandtotal = 0;
        $DB->insert_record('attendanceregister_aggregate', $aggregate);
        $aggregate->duration = 20;
        $aggregate->total = 0;
        $aggregate->grandtotal = 1;
        $DB->insert_record('attendanceregister_aggregate', $aggregate);
        $aggregate->onlinesess = 0;
        $aggregate->duration = 40;
        $aggregate->total = 1;
        $aggregate->grandtotal = 1;
        $DB->insert_record('attendanceregister_aggregate', $aggregate);
        $aggregate->total = 0;
        $aggregate->grandtotal = 0;
        $DB->insert_record('attendanceregister_aggregate', $aggregate);
        $aggregate->refcourse = null;
        $DB->insert_record('attendanceregister_aggregate', $aggregate);

        $session = new stdClass();
        $session->register = $this->cmid;
        $session->userid = $this->userid;
        $session->login = time() - 1000;
        $session->logout = time();
        $session->duration = 1000;
        $session->onlinesess = false;
        $session->refcourse = null;
        $session->comments = null;
        $DB->insert_record('attendanceregister_session', $session);
        $session = new stdClass();
        $session->register = $this->cmid;
        $session->userid = $this->userid;
        $session->login = time() - 1000;
        $session->logout = time();
        $session->duration = 1000;
        $session->onlinesess = true;
        $session->refcourse = null;
        $session->comments = null;
        $DB->insert_record('attendanceregister_session', $session);
        $userid = $dg->create_user()->id;
        $dg->enrol_user($userid, $this->courseid);
        $session = new stdClass();
        $session->register = $this->cmid;
        $session->userid = $userid;
        $session->login = time() - 5000;
        $session->logout = time();
        $session->duration = 5000;
        $session->onlinesess = true;
        $session->refcourse = $this->courseid;
        $session->comments = 'comment';
        $DB->insert_record('attendanceregister_session', $session);
        $session->register = $this->cmid;
        $session->onlinesess = false;
        $DB->insert_record('attendanceregister_session', $session);

        $lock = new stdClass();
        $lock->register = $this->cmid;
        $lock->userid = $userid;
        $lock->takenon = time();
        $DB->insert_record('attendanceregister_lock', $lock);
        $task = new \mod_attendanceregister\task\cron_task();
        $task->execute();
    }
}
