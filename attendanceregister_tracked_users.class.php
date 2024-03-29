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
 * Tracked Courses
 *
 * @package mod_attendanceregister
 * @copyright 2016 CINECA
 * @author  Lorenzo Nicora <fad@nicus.it>
 * @author  Renaat Debleu <info@eWallah.net>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Holds all tracked Users of an Attendance Register
 *
 * Implements method to return html_table to render it.
 *
 * @package mod_attendanceregister
 * @copyright 2016 CINECA
 * @author  Lorenzo Nicora <fad@nicus.it>
 * @author  Renaat Debleu <info@eWallah.net>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attendanceregister_tracked_users {
    /** @var array users Array of User */
    public $users;

    /** @var array usersaggregates Array if attendanceregister_user_aggregates_summary keyed by $userid */
    public $usersaggregates;


    /** @var attendanceregister_tracked_courses trackedcourses containing all tracked Courses  */
    public $trackedcourses;

    /** @var stdClass register Ref. to AttendanceRegister instance */
    private $register;

    /** @var attendanceregister_user_capablities usercaps Ref to mod_attendanceregister_user_capablities instance */
    private $usercaps;


    /**
     * Constructor
     * Load all tracked User's and their summaris
     * Load list of tracked Courses
     *
     * @param object $register
     * @param attendanceregister_user_capablities $usercaps
     * @param int $groupid
     */
    public function __construct($register, attendanceregister_user_capablities $usercaps, $groupid) {
        $this->register = $register;
        $this->usercaps = $usercaps;
        $this->users = attendanceregister_get_tracked_users($register, $groupid);
        $this->trackedcourses = new attendanceregister_tracked_courses($register);
        $ids = attendanceregister__extract_property($this->users, 'id');
        $aggregates = attendanceregister__get_all_users_aggregate_summaries($register);
        $this->usersaggregates = [];
        foreach ($aggregates as $aggregate) {
            // Retain only tracked users.
            if (in_array($aggregate->userid, $ids)) {
                // Create User's attendanceregister_user_aggregates_summary instance if not exists.
                if (!isset($this->usersaggregates[$aggregate->userid])) {
                    $this->usersaggregates[$aggregate->userid] = new attendanceregister_user_aggregates_summary();
                }
                // Populate attendanceregister_user_aggregates_summary fields.
                if ($aggregate->grandtotal) {
                    $this->usersaggregates[$aggregate->userid]->grandtotal = $aggregate->duration;
                    $this->usersaggregates[$aggregate->userid]->lastlogout = $aggregate->lastsessionlogout;
                } else if ($aggregate->total && $aggregate->onlinesess == 1) {
                    $this->usersaggregates[$aggregate->userid]->onlinetotal = $aggregate->duration;
                } else if ($aggregate->total && $aggregate->onlinesess == 0) {
                    $this->usersaggregates[$aggregate->userid]->offlinetotal = $aggregate->duration;
                }
            }
        }
    }

    /**
     * Build the html_table object to represent details
     *
     * @return html_table
     */
    public function html_table() {
        $table = new html_table();
        $s = ' attendanceregister_userlist table table-condensed table-bordered table-striped table-hover';
        $table->attributes['class'] .= $s;
        $table->head = [
            get_string('count', 'attendanceregister'),
            get_string('fullname', 'attendanceregister'),
            get_string('total_time_online', 'attendanceregister'),
        ];
        $table->align = ['left', 'left', 'right'];

        if ($this->register->offlinesessions) {
            $table->head[] = get_string('total_time_offline', 'attendanceregister');
            $table->align[] = 'right';
            $table->head[] = get_string('grandtotal_time', 'attendanceregister');
            $table->align[] = 'right';
        }
        $table->head[] = get_string('last_session_logout', 'attendanceregister');
        $table->align[] = 'left';

        if ($this->users) {
            $rowcount = 0;
            foreach ($this->users as $user) {
                $rowcount++;
                $useraggregate = null;
                if (isset($this->usersaggregates[$user->id])) {
                    $useraggregate = $this->usersaggregates[$user->id];
                }
                // Basic columns.
                $linkurl = attendanceregister_makeurl($this->register, $user->id);
                $fullname = '<a href="' . $linkurl . '">' . fullname($user) . '</a>';
                $duration = $useraggregate ? $useraggregate->onlinetotal : null;
                $tablerow = new html_table_row([$rowcount, $fullname, attendanceregister_format_duration($duration)]);

                // Add class for zebra stripes.
                $tablerow->attributes['class'] .= ($rowcount % 2) ? ' attendanceregister_oddrow' : ' attendanceregister_evenrow';

                // Optional columns.
                if ($this->register->offlinesessions) {
                    $duration = $useraggregate ? $useraggregate->offlinetotal : null;
                    $tablerow->cells[] = new html_table_cell(attendanceregister_format_duration($duration));
                    $duration = $useraggregate ? $useraggregate->grandtotal : null;
                    $tablerow->cells[] = new html_table_cell(attendanceregister_format_duration($duration));
                }

                if ($useraggregate) {
                    $tablerow->cells[] = new html_table_cell(attendanceregister__formatdate($useraggregate->lastlogout));
                } else {
                    $tablerow->cells[] = new html_table_cell(get_string('no_session', 'attendanceregister'));
                }
                $table->data[] = $tablerow;
            }
        } else {
            // No User.
            $row = new html_table_row();
            $labelcell = new html_table_cell(get_string('no_tracked_user', 'attendanceregister'));
            $labelcell->colspan = count($table->head);
            $row->cells[] = $labelcell;
            $table->data[] = $row;
        }
        return $table;
    }
}
