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
 * mod_attendanceregister data generator
 *
 * @package mod_attendanceregister
 * @copyright 2016 CINECA
 * @author  Lorenzo Nicora <fad@nicus.it>
 * @author  Renaat Debleu <info@eWallah.net>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * mod_attendanceregister data generator
 *
 * @package mod_attendanceregister
 * @copyright 2016 CINECA
 * @author  Lorenzo Nicora <fad@nicus.it>
 * @author  Renaat Debleu <info@eWallah.net>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_attendanceregister_generator extends testing_module_generator {
    /**
     * Create attendance register instance
     * @param stdClass $record
     * @param array $options
     * @return stdClass
     */
    public function create_instance($record = null, array $options = null) {
        global $DB;
        $record = (array)$record;
        $record['showdescription'] = 1;
        $record['offlinesessions'] = 1;
        $return = parent::create_instance($record, $options);
        // Add 2 sessions.
        $session = new stdClass();
        $session->register = $return->cmid;
        $session->userid = 15000;
        $session->login = time() - 5000;
        $session->logout = time();
        $session->duration = 5000;
        $session->onlinesess = 0;
        $session->refcourse = $return->course;
        $session->comments = 'comment1';
        $DB->insert_record('attendanceregister_session', $session);

        $aggregate = new stdClass();
        $aggregate->register = $return->cmid;
        $aggregate->userid = 15000;
        $aggregate->onlinesess = 0;
        $aggregate->refcourse = $return->course;
        $aggregate->duration = 3000;
        $aggregate->total = 0;
        $aggregate->grandtotal = 0;
        $DB->insert_record('attendanceregister_aggregate', $aggregate);

        $lock = new stdClass();
        $lock->register = $return->cmid;
        $lock->userid = 15000;
        $lock->takenon = time();
        $DB->insert_record('attendanceregister_lock', $lock);

        $session = new stdClass();
        $session->register = $return->cmid;
        $session->userid = 15000;
        $session->login = time() - 5000;
        $session->logout = time() + 8000;
        $session->duration = 13000;
        $session->onlinesess = 1;
        $session->refcourse = $return->course;
        $session->comments = 'comment2';
        $DB->insert_record('attendanceregister_session', $session);

        $aggregate = new stdClass();
        $aggregate->register = $return->cmid;
        $aggregate->userid = 15000;
        $aggregate->onlinesess = 1;
        $aggregate->refcourse = $return->course;
        $aggregate->duration = 800;
        $aggregate->total = 0;
        $aggregate->grandtotal = 1;
        $DB->insert_record('attendanceregister_aggregate', $aggregate);

        $lock = new stdClass();
        $lock->register = $return->cmid;
        $lock->userid = 15000;
        $lock->takenon = time();
        $DB->insert_record('attendanceregister_lock', $lock);

        return $return;
    }
}
