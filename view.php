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
 * View attendance
 *
 * @package mod_attendanceregister
 * @copyright 2016 CINECA
 * @author  Lorenzo Nicora <fad@nicus.it>
 * @author  Renaat Debleu <info@eWallah.net>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_OUTPUT_BUFFERING', true);

require('../../config.php');
require_once('lib.php');
require_once($CFG->libdir . '/completionlib.php');

// Main parameters.
$userid = optional_param('userid', 0, PARAM_INT);
$id = optional_param('id', 0, PARAM_INT);
$a = optional_param('a', 0, PARAM_INT);
$groupid = optional_param('group', 0, PARAM_INT);

// Other parameters.
$inputaction = optional_param('action', '', PARAM_ALPHA);
$inputsessionid = optional_param('session', null, PARAM_INT);

if ($id) {
    $cm = get_coursemodule_from_id('attendanceregister', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $register = $DB->get_record('attendanceregister', ['id' => $cm->instance], '*', MUST_EXIST);
} else {
    $register = $DB->get_record('attendanceregister', ['id' => $a], '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('attendanceregister', $register->id, $register->course, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $id = $cm->id;
}

$sessiontodelete = null;
if ($inputsessionid) {
    $sessiontodelete = attendanceregister_get_session($inputsessionid);
}

require_course_login($course, false, $cm);

if (!($context = context_module::instance($cm->id))) {
    throw new \moodle_exception('badcontext');
}

$usercaps = new attendanceregister_user_capablities($context);
if (!$userid && !$usercaps->canviewother) {
    $userid = $USER->id;
}

// Requires capabilities to view own or others' register.
if (attendanceregister__iscurrentuser($userid)) {
    require_capability(ATTENDANCEREGISTER_CAPABILITY_VIEW_OWN_REGISTERS, $context);
} else {
    require_capability(ATTENDANCEREGISTER_CAPABILITY_VIEW_OTHER_REGISTERS, $context);
}

// Require capability to recalculate.
$dorecalc = false;
$doschedrecalc = false;
if ($inputaction == ATTENDANCEREGISTER_ACTION_RECALCULATE) {
    require_capability(ATTENDANCEREGISTER_CAPABILITY_RECALC_SESSIONS, $context);
    $dorecalc = true;
}
if ($inputaction == ATTENDANCEREGISTER_ACTION_SCHEDULERECALC) {
    require_capability(ATTENDANCEREGISTER_CAPABILITY_RECALC_SESSIONS, $context);
    $doschedrecalc = true;
}

// Printable version?
$printable = false;
if ($inputaction == ATTENDANCEREGISTER_ACTION_PRINTABLE) {
    $printable = true;
}

// Check permissions and ownership for showing offline session form or saving them.
$doshowofflinesessionform = false;
$dosaveofflinesession = false;
// Only if Offline Sessions are enabled (and No printable-version action).
if ($register->offlinesessions &&  !$printable) {
    // Only if User is NOT logged-in-as, or ATTENDANCEREGISTER_ALLOW_LOGINAS_OFFLINE_SESSIONS is enabled.
    if (!\core\session\manager::is_loggedinas() || ATTENDANCEREGISTER_ALLOW_LOGINAS_OFFLINE_SESSIONS) {
        // If user is on his own Register and may save own Sessions
        // or is on other's Register and may save other's Sessions..
        if ($usercaps->canaddsession($register, $userid)) {
            // Do show Offline Sessions Form.
            $doshowofflinesessionform = true;

            // If action is saving Offline Session...
            if ($inputaction == ATTENDANCEREGISTER_ACTION_SAVE_OFFLINE_SESSION) {
                // Check Capabilities, to show an error if a security violation attempt occurs.
                if (attendanceregister__iscurrentcser($userid)) {
                    require_capability(ATTENDANCEREGISTER_CAPABILITY_ADD_OWN_OFFLINE_SESSIONS, $context);
                } else {
                    require_capability(ATTENDANCEREGISTER_CAPABILITY_ADD_OTHER_OFFLINE_SESSIONS, $context);
                }
                // Do save Offline Session.
                $dosaveofflinesession = true;
            }
        }
    }
}


// Check capabilities to delete self cert (in the meanwhile retrieve the record to delete).
$dodeleteofflinesession = false;
if ($sessiontodelete) {
    // Check if logged-in-as Session Delete.
    if (session_is_loggedinas() && !ATTENDANCEREGISTER_ACTION_SAVE_OFFLINE_SESSION) {
        throw new \moodle_exception('onlyrealusercandeleteofflinesessions', 'attendanceregister');
    } else if (attendanceregister__iscurrentuser($userid)) {
        require_capability(ATTENDANCEREGISTER_CAPABILITY_DELETE_OWN_OFFLINE_SESSIONS, $context);
        $dodeleteofflinesession = true;
    } else {
        require_capability(ATTENDANCEREGISTER_CAPABILITY_DELETE_OTHER_OFFLINE_SESSIONS, $context);
        $dodeleteofflinesession = true;
    }
}

// Retrieve Course Completion info object.
$completion = new completion_info($course);

$usertoprocess = null;
$usersessions = null;
$trackedusers = null;
$str = '';
if ($userid) {
    $usertoprocess = attendanceregister__getuser($userid);
    $usersessions = new attendanceregister_user_sessions($register, $userid, $usercaps);
    $str = ': ' . fullname($usertoprocess);
} else {
    $trackedusers = new attendanceregister_tracked_users($register, $usercaps, $groupid);
}

$url = attendanceregister_makeurl($register, $userid, $groupid, $inputaction);
$PAGE->set_url($url->out());
$PAGE->set_context($context);
$PAGE->set_title(format_string($course->shortname . ': ' . $register->name . $str));

$PAGE->set_heading($course->fullname);
if ($printable) {
    $PAGE->set_pagelayout('print');
}

$params = ['context' => $context, 'objectid' => $register->id];
$event = \mod_attendanceregister\event\course_module_viewed::create($params);
$event->add_record_snapshot('course_modules', $cm);
$event->add_record_snapshot('course', $course);
$event->trigger();

if ($userid == $USER->id && $completion->is_enabled($cm)) {
    $completion->set_module_viewed($cm, $userid);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($register->name . $str));

$doshowcontents = true;
$mform = null;
if ($userid && $doshowofflinesessionform && !$printable) {
    // Prepare form.
    $customformdata = ['register' => $register, 'courses' => $usersessions->trackedcourses->courses];
    // Also pass userid only if is saving for another user.
    if (!attendanceregister__iscurrentuser($userid)) {
        $customformdata['userid'] = $userid;
    }
    $mform = new mod_attendanceregister_selfcertification_edit_form(null, $customformdata);


    // Process Self.Cert Form submission.
    if ($mform->is_cancelled()) {
        redirect($PAGE->url);
    } else if ($dosaveofflinesession && ($formdata = $mform->get_data())) {
        attendanceregister_save_offline_session($register, $formdata);
        echo $OUTPUT->notification(get_string('offline_session_saved', 'attendanceregister'), 'notifysuccess');
        echo $OUTPUT->continue_button(attendanceregister_makeurl($register, $userid));
        $doshowcontents = false;
    }
}

if ($doshowcontents && ($dorecalc || $doschedrecalc)) {
    if ($usertoprocess) {
        $progressbar = new progress_bar('recalcbar', 500, true);
        attendanceregister_force_recalc_user_sessions($register, $userid, $progressbar);
        $usersessions = new attendanceregister_user_sessions($register, $userid, $usercaps);
    } else {
        if ($doschedrecalc) {
            if (!$register->pendingrecalc) {
                attendanceregister_set_pending_recalc($register, true);
            }
        }
        if ($dorecalc) {
            if ($register->pendingrecalc) {
                attendanceregister_set_pending_recalc($register, false);
            }
            set_time_limit(0);
            attendanceregister_delete_all_users_online_sessions_and_aggregates($register);
            $newtrackedusers = attendanceregister_get_tracked_users($register);
            foreach ($newtrackedusers as $user) {
                $progressbar = new progress_bar('recalcbar_' . $user->id, 500, true);
                attendanceregister_force_recalc_user_sessions($register, $user->id, $progressbar, false);
                // No delete needed, having done before [issue #14].
            }
            $trackedusers = new attendanceregister_tracked_users($register, $usercaps, $groupid);
        }
    }
    if ($dorecalc || $doschedrecalc) {
        $s = $dorecalc ? 'recalc_complete' : 'recalc_scheduled';
        echo $OUTPUT->notification(get_string($s, 'attendanceregister'), 'notifysuccess');
    }
    echo $OUTPUT->continue_button(attendanceregister_makeurl($register, $userid));
    $doshowcontents = false;
} else if ($doshowcontents && $dodeleteofflinesession) {
    attendanceregister_delete_offline_session($register, $sessiontodelete->userid, $sessiontodelete->id);
    echo $OUTPUT->notification(get_string('offline_session_deleted', 'attendanceregister'), 'notifysuccess');
    echo $OUTPUT->continue_button(attendanceregister_makeurl($register, $userid));
    $doshowcontents = false;
} else if ($doshowcontents) {
    if ($userid) {
        echo $OUTPUT->container_start('attendanceregister_buttonbar btn-group');
        if ($usercaps->canviewother && !$printable) {
            echo $OUTPUT->single_button(
                attendanceregister_makeurl($register),
                get_string('back_to_tracked_user_list', 'attendanceregister'),
                'get'
            );
            $logurl = new moodle_url('/report/log/index.php', ['chooselog' => 1, 'showusers' => 1,
               'showcourses' => 1, 'id' => 1, 'user' => $userid, 'logformat' => 'showashtml', ]);
            echo $OUTPUT->single_button($logurl, 'Logs', 'get');
        }
        echo $OUTPUT->container_end();
        echo '<br />';
        if ($mform && $register->offlinesessions && !$printable) {
            echo "<br />";
            echo $OUTPUT->box_start('generalbox attendanceregister_offlinesessionform');
            $mform->display();
            echo $OUTPUT->box_end();
        }
        echo html_writer::div(html_writer::table($usersessions->useraggregates->html_table()), 'table-responsive');
        echo html_writer::div(html_writer::table($usersessions->html_table()), 'table-responsive');
    } else {
        if ($usercaps->canrecalc && !$printable) {
            echo groups_allgroups_course_menu($course, $url, true, $groupid);
        }
        if ($register->pendingrecalc && $usercaps->canrecalc && !$printable) {
            echo $OUTPUT->notification(get_string('recalc_scheduled_on_next_cron', 'attendanceregister'));
        } else if (!attendanceregister__didcronran($cm)) {
            echo $OUTPUT->notification(get_string('first_calc_at_next_cron_run', 'attendanceregister'));
        }
        echo $OUTPUT->container_start('attendanceregister_buttonbar btn-group');
        if ($usercaps->istracked) {
            $linkurl = attendanceregister_makeurl($register, $USER->id);
            echo $OUTPUT->single_button($linkurl, get_string('show_my_sessions', 'attendanceregister'), 'get');
        }
        echo $OUTPUT->container_end();
        echo '<br />';
        echo html_writer::div(html_writer::table($trackedusers->trackedcourses->html_table()), 'table-responsive');
        echo html_writer::div(html_writer::table($trackedusers->html_table()), 'table-responsive');
    }
}

if (!$printable) {
    echo $OUTPUT->footer();
}
