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
 * Restore activity structure task
 *
 * @package mod_attendanceregister
 * @copyright 2016 CINECA
 * @author  Lorenzo Nicora <fad@nicus.it>
 * @author  Renaat Debleu <info@eWallah.net>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restore activity structure task
 *
 * @package mod_attendanceregister
 * @copyright 2016 CINECA
 * @author  Lorenzo Nicora <fad@nicus.it>
 * @author  Renaat Debleu <info@eWallah.net>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_attendanceregister_activity_structure_step extends restore_activity_structure_step {
    /**
     * Define the structure of the restore workflow.
     *
     * @return restore_path_element $structure
     */
    protected function define_structure() {
        $paths = [];
        $paths[] = new restore_path_element('attendanceregister', '/activity/attendanceregister');
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Process an attendanceregister restore.
     *
     * @param object $data The data in object form
     * @return void
     */
    protected function process_attendanceregister($data) {
        global $DB;
        $data = (object) $data;
        $data->course = $this->get_courseid();
        $newitemid = $DB->insert_record('attendanceregister', $data);
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Once the database rows have been fully restored, restore the files and do a recalc
     * @return void
     */
    protected function after_execute() {
        global $DB;
        $this->add_related_files('mod_attendanceregister', 'intro', null);
        $register = $DB->get_record('attendanceregister', ['id' => $this->task->get_activityid()], '*', MUST_EXIST);
        attendanceregister_force_recalc_all($register);
    }
}
