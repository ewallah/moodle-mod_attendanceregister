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
 * attendanceregister class tests.
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
final class attendaceregister_test extends \advanced_testcase {
    /** @var stdClass Course */
    private $course;

    /** @var stdClass attendance module*/
    private $mod;

    /** @var int userid */
    private $userid;

    /**
     * Basic setup for these tests.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $dg = $this->getDataGenerator();
        $this->course = $dg->create_course();
        $this->userid = $dg->create_and_enrol($this->course)->id;
        $this->mod = $dg->create_module('attendanceregister', ['course' => $this->course->id]);
    }

    /**
     * Test functions.
     * #[CoversClass(mod_attendanceregister\attendanceregister)]
     */
    public function test_functions(): void {
        // TODO: assert.
        attendanceregister::calculate_last_user_online_session_logout($this->mod, $this->userid);
        attendanceregister::build_new_user_sessions($this->mod, $this->userid);
        attendanceregister::update_user_aggregates($this->mod, $this->userid);
        attendanceregister::get_cached_user_grandtotal($this->mod, $this->userid);
        attendanceregister::get_courses_ids_in_category($this->course);
        attendanceregister::get_coursed_ids_meta_linked($this->course);
        $cnt = 0;
        attendanceregister::get_user_log_entries_in_courses($this->userid, 0, [$this->course->id], $cnt);
        attendanceregister::save_session($this->mod, $this->userid, 0, 0);
        attendanceregister::delete_user_online_sessions($this->mod, $this->userid);
        attendanceregister::delete_user_aggregates($this->mod, $this->userid);
        attendanceregister::get_user_oldest_log_entry_timestamp($this->userid);
        attendanceregister::attain_lock($this->mod, $this->userid);
        attendanceregister::release_lock($this->mod, $this->userid);
        attendanceregister::shorten_comment('aaaaaaaaa');
        attendanceregister::othername($this->userid);
        attendanceregister::iscondition($this->mod);
        attendanceregister::calculatecompletion($this->mod, $this->userid);
        attendanceregister::iscomplete($this->mod, []);
    }
}
