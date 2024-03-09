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
 * PHPUnit generator testcase
 *
 * @package mod_attendanceregister
 * @copyright 2016 CINECA
 * @author  Lorenzo Nicora <fad@nicus.it>
 * @author  Renaat Debleu <info@eWallah.net>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class generator_test extends \advanced_testcase {

    /**
     * Test the generator
     * @covers \mod_attendanceregister_generator
     */
    public function test_generator(): void {
        global $DB;
        $this->resetAfterTest(true);
        $this->assertEquals(0, $DB->count_records('attendanceregister'));
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_attendanceregister');
        $this->assertInstanceOf('mod_attendanceregister_generator', $generator);
        $this->assertEquals('attendanceregister', $generator->get_modulename());

        $generator->create_instance(['course' => $course->id]);
        $generator->create_instance(['course' => $course->id]);
        $attendanceregister = $generator->create_instance(['course' => $course->id]);
        $this->assertEquals(6, $DB->count_records('attendanceregister_session'));
        $this->assertEquals(6, $DB->count_records('attendanceregister_aggregate'));
        $this->assertEquals(6, $DB->count_records('attendanceregister_lock'));

        $cm = get_coursemodule_from_instance('attendanceregister', $attendanceregister->id);
        $this->assertEquals($attendanceregister->id, $cm->instance);
        $this->assertEquals('attendanceregister', $cm->modname);
        $this->assertEquals($course->id, $cm->course);

        $context = \context_module::instance($cm->id);
        $this->assertEquals($attendanceregister->cmid, $context->instanceid);
    }
}
