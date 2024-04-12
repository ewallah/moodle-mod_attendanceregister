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
 * Attendance register user aggregates summary.
 *
 * @package mod_attendanceregister
 * @copyright 2016 CINECA
 * @author  Lorenzo Nicora <fad@nicus.it>
 * @author  Renaat Debleu <info@eWallah.net>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_attendanceregister;

/**
 * Represents a User's Aggregate for a Register
 *
 * Holds in a single Object attendanceregister_aggregate records for
 * summary infos only (total & grandtotal)
 * for a User and a Register instance.
 *
 * @package mod_attendanceregister
 * @copyright 2016 CINECA
 * @author  Lorenzo Nicora <fad@nicus.it>
 * @author  Renaat Debleu <info@eWallah.net>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_aggregates_summary {
    /** @var int grandtotal total of all sessions */
    public $grandtotal = 0;

    /** @var int onlinetotal total of all Online Sessions */
    public $onlinetotal = 0;

    /** @var int offlinetotal total of all Offline Sessions */
    public $offlinetotal = 0;

    /** @var int lastlogout Last calculated Session Logout */
    public $lastlogout = 0;
}
