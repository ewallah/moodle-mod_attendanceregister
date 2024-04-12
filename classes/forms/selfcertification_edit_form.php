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
 * Attendance form offline
 *
 * @package mod_attendanceregister
 * @copyright 2016 CINECA
 * @author  Lorenzo Nicora <fad@nicus.it>
 * @author  Renaat Debleu <info@eWallah.net>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_attendanceregister\forms;

use moodleform;

/**
 * Class form Offline Session Self-Certification form
 *
 * (Note that the User is always the CURRENT user ($USER))
 *
 * @package mod_attendanceregister
 * @copyright 2016 CINECA
 * @author  Lorenzo Nicora <fad@nicus.it>
 * @author  Renaat Debleu <info@eWallah.net>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class selfcertification_edit_form extends moodleform {
    /**
     * Definition.
     */
    public function definition() {
        global $USER;

        $mform =& $this->_form;

        $register = $this->_customdata['register'];
        $courses = $this->_customdata['courses'];
        if (isset($this->_customdata['userid'])) {
            $userid = $this->_customdata['userid'];
        } else {
            $userid = null;
        }

        $refdate = usergetdate($USER->currentlogin);
        $refts = make_timestamp($refdate['year'], $refdate['mon'], $refdate['mday'], $refdate['hours']);
        $deflogout = $refts;
        $deflogin = $refts - 3600;

        if (\mod_attendanceregister\attendanceregister::iscurrentuser($userid)) {
            $title = get_string('insert_new_offline_session', 'attendanceregister');
        } else {
            $a = new \stdClass();
            $a->fullname = fullname(\mod_attendanceregister\attendanceregister::getuser($userid));
            $title = get_string('insert_new_offline_session_for_another_user', 'attendanceregister', $a);
        }
        $mform->addElement('html', '<h3>' . $title . '</h3>');
        $mform->addElement(
            'date_time_selector',
            'login',
            get_string('offline_session_start', 'attendanceregister'),
            ['defaulttime' => $deflogin, 'optional' => false]
        );
        $mform->addRule('login', get_string('required'), 'required');
        $mform->addHelpButton('login', 'offline_session_start', 'attendanceregister');
        $mform->addElement(
            'date_time_selector',
            'logout',
            get_string('offline_session_end', 'attendanceregister'),
            ['defaulttime' => $deflogout, 'optional' => false]
        );
        $mform->addRule('logout', get_string('required'), 'required');

        if ($register->offlinecomments) {
            $mform->addElement('textarea', 'comments', get_string('comments', 'attendanceregister'));
            $mform->setType('comments', PARAM_TEXT);
            $mform->addRule('comments', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
            if ($register->mandatoryofflinecomm) {
                $mform->addRule('comments', get_string('required'), 'required', null, 'client');
            }
            $mform->addHelpButton('comments', 'offline_session_comments', 'attendanceregister');
        }

        if ($register->offlinespecifycourse) {
            $coursesselect = [];
            if ($register->mandofflspeccourse) {
                $coursesselect[] = get_string('select_a_course', 'attendanceregister');
            } else {
                $coursesselect[] = get_string('select_a_course_if_any', 'attendanceregister');
            }

            foreach ($courses as $course) {
                $coursesselect[$course->id] = $course->fullname;
            }
            $mform->addElement(
                'select',
                'refcourse',
                get_string('offline_session_ref_course', 'attendanceregister'),
                $coursesselect
            );
            if ($register->mandofflspeccourse) {
                $mform->addRule('refcourse', get_string('required'), 'required', null, 'client');
            }
            $mform->addHelpButton('refcourse', 'offline_session_ref_course', 'attendanceregister');
        }

        $mform->addElement('hidden', 'a');
        $mform->setType('a', PARAM_INT);
        $mform->setDefault('a', $register->id);
        $mform->addElement('hidden', 'action');
        $mform->setType('action', PARAM_ACTION);
        $mform->setDefault('action', ATTENDANCEREGISTER_ACTION_SAVE_OFFLINE_SESSION);
        if ($userid) {
            $mform->addElement('hidden', 'userid');
            $mform->setType('userid', PARAM_INT);
            $mform->setDefault('userid', $userid);
        }
        $this->add_action_buttons();
    }

    /**
     * Form validation.
     *
     * @param array $data
     * @param array $files
     * @return array all errors
     */
    public function validation($data, $files) {
        global $USER, $DB;

        $errors = parent::validation($data, $files);
        $register = $DB->get_record('attendanceregister', ['id' => $data['a']], '*', MUST_EXIST);

        $login = $data['login'];
        $logout = $data['logout'];
        if (isset($data['userid'])) {
            $userid = $data['userid'];
        } else {
            $userid = $USER->id;
        }
        if (($logout - $login) <= 0) {
            $errors['login'] = get_string('login_must_be_before_logout', 'attendanceregister');
        }
        if (($logout - $login) > ATTENDANCEREGISTER_MAX_REASONEABLE_OFFLINE_SESSION_SECONDS) {
            $hours = floor(($logout - $login) / 3600);
            $errors['login'] = get_string('unreasoneable_session', 'attendanceregister', $hours);
        }
        if ((time() - $login) > ($register->dayscertificable * 3600 * 24)) {
            $errors['login'] = get_string('dayscertificable_exceeded', 'attendanceregister', $register->dayscertificable);
        }
        if ($logout > time()) {
            $errors['login'] = get_string('logout_is_future', 'attendanceregister');
        }
        if (\mod_attendanceregister\attendanceregister::check_overlapping_old_sessions($register, $userid, $login, $logout)) {
            $errors['login'] = get_string('overlaps_old_sessions', 'attendanceregister');
        }
        if (\mod_attendanceregister\attendanceregister::check_overlapping_current_session($register, $userid, $login, $logout)) {
            $errors['login'] = get_string('overlaps_current_session', 'attendanceregister');
        }
        return $errors;
    }
}
