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
 * This class collects al current User's Capabilities regarding the current instance of Attendance Register
 *
 * @package mod_attendanceregister
 * @copyright 2016 CINECA
 * @author  Lorenzo Nicora <fad@nicus.it>
 * @author  Renaat Debleu <info@eWallah.net>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_attendanceregister;

/**
 * This class collects al current User's Capabilities regarding the current instance of Attendance Register
 *
 * @package mod_attendanceregister
 * @copyright 2016 CINECA
 * @author  Lorenzo Nicora <fad@nicus.it>
 * @author  Renaat Debleu <info@eWallah.net>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_capabilities {
    /** @var bool istracked */
    public $istracked = false;
    /** @var bool canviewown */
    public $canviewown = false;
    /** @var bool canviewother */
    public $canviewother = false;
    /** @var bool canaddown */
    public $canaddown = false;
    /** @var bool canaddother */
    public $canaddother = false;
    /** @var bool candeleteown */
    public $candeleteown = false;
    /** @var bool candeleteother */
    public $candeleteother = false;
    /** @var bool canrecalc */
    public $canrecalc = false;

    /**
     * Create an instance for the CURRENT User and Context
     *
     * @param object $context
     */
    public function __construct($context) {
        $this->canviewown = has_capability(ATTENDANCEREGISTER_CAPABILITY_VIEW_OWN_REGISTERS, $context, null, true);
        $this->canviewother = has_capability(ATTENDANCEREGISTER_CAPABILITY_VIEW_OTHER_REGISTERS, $context, null, true);
        $this->canrecalc = has_capability(ATTENDANCEREGISTER_CAPABILITY_RECALC_SESSIONS, $context, null, true);
        $this->istracked = has_capability(ATTENDANCEREGISTER_CAPABILITY_TRACKED, $context, null, false);
        $this->canaddown = has_capability(ATTENDANCEREGISTER_CAPABILITY_ADD_OWN_OFFLINE_SESSIONS, $context, null, false);
        $this->canaddother = has_capability(ATTENDANCEREGISTER_CAPABILITY_ADD_OTHER_OFFLINE_SESSIONS, $context, null, false);
        $this->candeleteown = has_capability(ATTENDANCEREGISTER_CAPABILITY_DELETE_OWN_OFFLINE_SESSIONS, $context, null, false);
        $this->candeleteother = has_capability(ATTENDANCEREGISTER_CAPABILITY_DELETE_OTHER_OFFLINE_SESSIONS, $context, null, false);
    }

    /**
     * Checks if the current user can view a given User's Register.
     *
     * @param  int $userid (null means current user's register)
     * @return boolean
     */
    public function canview($userid) {
        return (((attendanceregister::iscurrentuser($userid)) && $this->canviewown) || $this->canviewother);
    }

    /**
     * Checks if the current user can delete a given User's Offline Sessions
     *
     * @param  int $userid (null means current user's register)
     * @return boolean
     */
    public function canddeletesession($userid) {
        return (((attendanceregister::iscurrentuser($userid)) &&  $this->candeleteown) || $this->candeleteother);
    }

    /**
     * Check if the current USER can add Offline Sessions for a specified User
     *
     * @param stdClass $register
     * @param int $userid (null means current user's register)
     * @return boolean
     */
    public function canaddsession($register, $userid) {
        if (attendanceregister::iscurrentuser($userid)) {
            return $this->canaddown;
        } else if ($this->canaddother) {
            $user = attendanceregister::getuser($userid);
            return attendanceregister_is_tracked_user($register, $user);
        }
        return false;
    }
}
