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
 * User aggregates
 *
 * @package mod_attendanceregister
 * @copyright 2016 CINECA
 * @author  Lorenzo Nicora <fad@nicus.it>
 * @author  Renaat Debleu <info@eWallah.net>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * User aggregates
 *
 * @package mod_attendanceregister
 * @copyright 2016 CINECA
 * @author  Lorenzo Nicora <fad@nicus.it>
 * @author  Renaat Debleu <info@eWallah.net>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attendanceregister_user_aggregates {
    /** @var int grandtotal total of all sessions */
    public $grandtotal = 0;

    /** @var int onlinetotal Total of all Online Sessions */
    public $onlinetotal = 0;

    /** @var int $offlinetotal Total of all Offline Sessions */
    public $offlinetotal = 0;

    /** @var array $percouse Offline sessions, per refcourseid */
    public $percourse = [];

    /** @var int $nocoursesessions Offline Sessions w/o any RefCourse */
    public $nocoursesessions = 0;

    /** @var int $lastlogout Last calculated Session Logout */
    public $lastlogout = 0;

    /** @var attendanceregister_user_sessions $sessions */
    private $sessions;

    /** @var stdClass $user User instance */
    public $user;

    /** @var attendanceregister_session $usersessions */
    public $usersessions;

    /**
     * Create an instance for a given register and user
     *
     * @param object                           $register
     * @param int                              $userid
     * @param attendanceregister_user_sessions $sessions
     */
    public function __construct($register, $userid, attendanceregister_user_sessions $sessions) {
        $this->usersessions = $sessions;
        $this->user = attendanceregister__getuser($userid);
        $this->sessions = $sessions;
        $aggregates = attendanceregister__get_user_aggregates($register, $userid);
        foreach ($aggregates as $aggregate) {
            if ($aggregate->grandtotal) {
                $this->grandtotal = $aggregate->duration;
                $this->lastlogout = $aggregate->lastsessionlogout;
            } else if ($aggregate->total && $aggregate->onlinesess == 1) {
                $this->onlinetotal = $aggregate->duration;
            } else if ($aggregate->total && $aggregate->onlinesess == 0) {
                $this->offlinetotal = $aggregate->duration;
            } else if (!$aggregate->total && $aggregate->onlinesess == 0 && $aggregate->refcourse != null) {
                $this->percourse[$aggregate->refcourse] = $aggregate->duration;
            } else if (!$aggregate->total && $aggregate->onlinesess == 0 && $aggregate->refcourse == null) {
                $this->nocoursesessions = $aggregate->duration;
            } else {
                // Should not happen!
                debugging('Unconsistent Aggregate: ' . json_encode($aggregate), DEBUG_DEVELOPER);
            }
        }
    }


    /**
     * Build the html_table object to represent summary
     *
     * @return html_table
     */
    public function html_table() {
        $table = new html_table();
        $s = ' attendanceregister_usersummary table table-condensed table-bordered table-striped table-hover';
        $table->attributes['class'] .= $s;
        $table->head[] = get_string('user_sessions_summary', 'attendanceregister');
        $table->headspan = [3];

        $row = new html_table_row();
        $label = new html_table_cell(get_string('prev_site_login', 'attendanceregister'));
        $label->colspan = 2;
        $row->cells[] = $label;
        $row->cells[] = new html_table_cell(attendanceregister__formatdate($this->user->lastlogin));
        $table->data[] = $row;

        $row = new html_table_row();
        $label = new html_table_cell(get_string('last_site_login', 'attendanceregister'));
        $label->colspan = 2;
        $row->cells[] = $label;
        $row->cells[] = new html_table_cell(attendanceregister__formatdate($this->user->currentlogin));
        $table->data[] = $row;

        $row = new html_table_row();
        $label = new html_table_cell(get_string('last_site_access', 'attendanceregister'));
        $label->colspan = 2;
        $row->cells[] = $label;
        $row->cells[] = new html_table_cell(attendanceregister__formatdate($this->user->lastaccess));
        $table->data[] = $row;

        $row = new html_table_row();
        $label = new html_table_cell(get_string('last_calc_online_session_logout', 'attendanceregister'));
        $label->colspan = 2;
        $row->cells[] = $label;
        $row->cells[] = new html_table_cell(attendanceregister__formatdate($this->lastlogout));
        $table->data[] = $row;

        $table->data[] = 'hr';

        $row = new html_table_row();
        $row->attributes['class'] .= ' attendanceregister_onlinesubtotal success';
        $label = new html_table_cell(get_string('online_sessions_total_duration', 'attendanceregister'));
        $label->colspan = 2;
        $row->cells[] = $label;
        $row->cells[] = new html_table_cell(attendanceregister_format_duration($this->onlinetotal));
        $table->data[] = $row;
        if ($this->offlinetotal) {
            $table->data[] = 'hr';
            foreach ($this->percourse as $refcourseid => $offlinesessions) {
                $row = new html_table_row();
                $row->cells[] = new html_table_cell(get_string('offline_refcourse_duration', 'attendanceregister'));
                if ($refcourseid) {
                    $row->cells[] = new html_table_cell($this->usersessions->trackedcourses->courses[$refcourseid]->fullname);
                } else {
                    $row->cells[] = new html_table_cell(get_string('not_specified', 'attendanceregister'));
                }
                $row->cells[] = new html_table_cell(attendanceregister_format_duration($offlinesessions));
                $table->data[] = $row;
            }
            if ($this->nocoursesessions) {
                $row = new html_table_row();
                $row->cells[] = new html_table_cell(get_string('offline_refcourse_duration', 'attendanceregister'));
                $row->cells[] = new html_table_cell(get_string('no_refcourse', 'attendanceregister'));
                $row->cells[] = new html_table_cell(attendanceregister_format_duration($this->nocoursesessions));
                $table->data[] = $row;
            }
            $row = new html_table_row();
            $row->attributes['class'] .= ' attendanceregister_offlinesubtotal';
            $label = new html_table_cell(get_string('offline_sessions_total_duration', 'attendanceregister'));
            $label->colspan = 2;
            $row->cells[] = $label;
            $row->cells[] = new html_table_cell(attendanceregister_format_duration($this->offlinetotal));
            $table->data[] = $row;
            $row = new html_table_row();
            $row->attributes['class'] .= ' attendanceregister_grandtotal active';
            $label = new html_table_cell(get_string('sessions_grandtotal_duration', 'attendanceregister'));
            $label->colspan = 2;
            $row->cells[] = $label;
            $row->cells[] = new html_table_cell(attendanceregister_format_duration($this->grandtotal));
            $table->data[] = $row;
        }
        return $table;
    }
}
