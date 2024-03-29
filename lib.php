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
 * Attendance register library.
 *
 * @package mod_attendanceregister
 * @copyright 2016 CINECA
 * @author  Lorenzo Nicora <fad@nicus.it>
 * @author  Renaat Debleu <info@eWallah.net>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;

require_once("$CFG->dirroot/mod/attendanceregister/locallib.php");
require_once("attendanceregister_user_aggregates_summary.class.php");
require_once("attendanceregister_user_aggregates.class.php");
require_once("attendanceregister_user_sessions.class.php");
require_once("attendanceregister_tracked_courses.class.php");
require_once("attendanceregister_tracked_users.class.php");

/**
 * Average timeout between user's requests to be considered in the same user's session
 */
define("ATTENDANCEREGISTER_DEFAULT_SESSION_TIMEOUT", 30);

/**
 * Max number of days back, a user may insert an offline-work certification (if enabled)
 */
define("ATTENDANCEREGISTER_DEFAULT_DAYS_CERTIFICABLE", 10);


define("ATTENDANCEREGISTER_TYPE_COURSE", "course");
define("ATTENDANCEREGISTER_TYPE_METAENROL", "meta");
define("ATTENDANCEREGISTER_TYPE_CATEGORY", "category");
define("ATTENDANCEREGISTER_TYPE_GLOBAL", "global");

define("ATTENDANCEREGISTER_ACTION_PRINTABLE", "printable");
define("ATTENDANCEREGISTER_ACTION_RECALCULATE", "recalc");
define("ATTENDANCEREGISTER_ACTION_SAVE_OFFLINE_SESSION", "saveoffline");
define("ATTENDANCEREGISTER_ACTION_DELETE_OFFLINE_SESSION", "deloffline");
define("ATTENDANCEREGISTER_ACTION_SCHEDULERECALC", "schedrecalc");

define('ATTENDANCEREGISTER_LOGACTION_VIEW', 'view');
define('ATTENDANCEREGISTER_LOGACTION_VIEW_ALL', 'view all');
define('ATTENDANCEREGISTER_LOGACTION_ADD_OFFLINE', 'add offline');
define('ATTENDANCEREGISTER_LOGACTION_DELETE_OFFLINE', 'delete offline');
define('ATTENDANCEREGISTER_LOGACTION_RECALCULTATE', 'recalculate');

define("ATTENDANCEREGISTER_CAPABILITY_TRACKED", "mod/attendanceregister:tracked");
define("ATTENDANCEREGISTER_CAPABILITY_VIEW_OTHER_REGISTERS", "mod/attendanceregister:viewotherregisters");
define("ATTENDANCEREGISTER_CAPABILITY_VIEW_OWN_REGISTERS", "mod/attendanceregister:viewownregister");
define("ATTENDANCEREGISTER_CAPABILITY_ADD_OWN_OFFLINE_SESSIONS", "mod/attendanceregister:addownofflinesess");
define("ATTENDANCEREGISTER_CAPABILITY_ADD_OTHER_OFFLINE_SESSIONS", "mod/attendanceregister:addotherofflinesess");
define("ATTENDANCEREGISTER_CAPABILITY_DELETE_OWN_OFFLINE_SESSIONS", "mod/attendanceregister:deleteownofflinesess");
define("ATTENDANCEREGISTER_CAPABILITY_DELETE_OTHER_OFFLINE_SESSIONS", "mod/attendanceregister:deleteotherofflinesess");
define("ATTENDANCEREGISTER_CAPABILITY_RECALC_SESSIONS", "mod/attendanceregister:recalcsessions");


// Allow Self Certifications while in Login-as. This should be turned on only for testing!
define("ATTENDANCEREGISTER_ALLOW_LOGINAS_OFFLINE_SESSIONS", false);


// Define the maximum Offline session length that will be considered reasonable (in seconds) Now 12h!
define("ATTENDANCEREGISTER_MAX_REASONEABLE_OFFLINE_SESSION_SECONDS", 12 * 3600);

// Max length for Comments shortening.
define('ATTENDANCEREGISTER_COMMENTS_SHORTEN_LENGTH', 25);

// After how long a Lock is considered an orphan?
define('ATTENDANCEREGISTER_ORPHANED_LOCKS_DELAY_SECONDS', 30 * 60);

// Default completion total duration (in minutes): 1h.
define('ATTENDANCEREGISTER_DEFAULT_COMPLETION_TOTAL_DURATION_MINS', 60);

// Ugly hack to make 3.11 and 4.0 work seamlessly.
if (!defined('FEATURE_MOD_PURPOSE')) {
    define('FEATURE_MOD_PURPOSE', 'mod_purpose');
}

/**
 * Setup a new instance
 *
 * @param  object $register
 * @return int new instance id
 */
function attendanceregister_add_instance($register) {
    global $DB;
    $register->timemodified = time();
    if (!isset($register->type)) {
        $register->type = ATTENDANCEREGISTER_TYPE_COURSE;
    }
    if (!isset($register->sessiontimeout)) {
        $register->sessiontimeout = ATTENDANCEREGISTER_DEFAULT_SESSION_TIMEOUT;
    }
    if (!isset($register->dayscertificable)) {
        $register->dayscertificable = ATTENDANCEREGISTER_DEFAULT_DAYS_CERTIFICABLE;
    }
    if (!isset($register->offlinesessions)) {
        $register->offlinesessions = 0;
    }
    if (!isset($register->offlinecomments)) {
        $register->offlinecomments = 1;
    }
    if (!isset($register->mandatoryofflinecomments)) {
        $register->mandatoryofflinecomments = 0;
    }
    if (!isset($register->offlinespecifycourse)) {
        $register->offlinespecifycourse = 0;
    }
    if (!isset($register->mandatoryofflinespecifycourse)) {
        $register->mandatoryofflinespecifycourse = 0;
    }
    $register->id = $DB->insert_record('attendanceregister', $register);
    return $register->id;
}

/**
 * Update mod instance.
 *
 * @param  stdClass $register
 * @return bool true
 */
function attendanceregister_update_instance($register) {
    global $DB;
    $register->id = $register->instance;
    $register->timemodified = time();
    if (!isset($register->offlinesessions)) {
        $register->offlinesessions = 0;
    }
    if (!isset($register->offlinecomments)) {
        $register->offlinecomments = 0;
    }
    if (!isset($register->mandatoryofflinecomm)) {
        $register->mandatoryofflinecomm = 0;
    }
    if (!isset($register->offlinespecifycourse)) {
        $register->offlinespecifycourse = 0;
    }
    if (!isset($register->mandofflspeccourse)) {
        $register->mandofflspeccourse = 0;
    }
    $old = $DB->get_record('attendanceregister', ['id' => $register->id]);
    if ($old &&  $old->sessiontimeout != $register->sessiontimeout) {
        $register->pendingrecalc = true;
    }
    return $DB->update_record('attendanceregister', $register);
}

/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param  int $id
 * @return bool
 */
function attendanceregister_delete_instance($id) {
    global $DB;
    if (!$register = $DB->get_record("attendanceregister", ["id" => $id])) {
        return false;
    }
    $result = true;
    if (!$DB->delete_records("attendanceregister_session", ["register" => $register->id])) {
        $result = false;
    }
    if (!$DB->delete_records("attendanceregister_lock", ["register" => $register->id])) {
        $result = false;
    }
    if (!$DB->delete_records("attendanceregister_aggregate", ["register" => $register->id])) {
        $result = false;
    }
    if (!$DB->delete_records("attendanceregister", ["id" => $register->id])) {
        return false;
    }
    return $result;
}

/**
 * Supported features
 *
 * @param  string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, null if doesn't know
 */
function attendanceregister_supports($feature) {
    switch ($feature) {
        case FEATURE_GROUPS:
            return false;
        case FEATURE_GROUPINGS:
            return false;
        case FEATURE_GROUPMEMBERSONLY:
            return false;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return false;
        case FEATURE_GRADE_HAS_GRADE:
            return false;
        case FEATURE_GRADE_OUTCOMES:
            return false;
        case FEATURE_RATE:
            return false;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return false;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_OTHER;
        default:
            return null;
    }
}

/**
 * Given a course_module object, this function returns any
 * "extra" information that may be needed when printing
 * this activity in a course listing.
 * See get_array_of_activities() in course/lib.php
 *
 * @param  object $coursemodule
 * @return object|null
 */
function attendanceregister_get_coursemodule_info($coursemodule) {
    global $DB;
    if (
        !$register = $DB->get_record(
            'attendanceregister',
            ['id' => $coursemodule->instance],
            'id, name, intro, introformat, type'
        )
    ) {
        return false;
    }

    $info = new stdClass();
    $info->name = $register->name;
    return $info;
}

/**
 * List of view style log actions
 *
 * @return array
 */
function attendanceregister_get_view_actions() {
    return ['view', 'view all'];
}

/**
 * List of update style log actions
 *
 * @return array
 */
function attendanceregister_get_post_actions() {
    return ['add offline session', 'delete offline session', 'delete offline session'];
}

/**
 * This function is used by the reset_course_userdata function in moodlelib.
 *
 * @param  object $data the data submitted from the reset course.
 * @return array status array
 */
function attendanceregister_reset_userdata($data) {
    return [];
}

/**
 * Returns all other caps used in module
 *
 * @return array
 */
function attendanceregister_get_extra_capabilities() {
    return [
        ATTENDANCEREGISTER_CAPABILITY_TRACKED,
        ATTENDANCEREGISTER_CAPABILITY_VIEW_OTHER_REGISTERS,
        ATTENDANCEREGISTER_CAPABILITY_VIEW_OWN_REGISTERS,
        ATTENDANCEREGISTER_CAPABILITY_ADD_OWN_OFFLINE_SESSIONS,
        ATTENDANCEREGISTER_CAPABILITY_DELETE_OTHER_OFFLINE_SESSIONS,
        ATTENDANCEREGISTER_CAPABILITY_RECALC_SESSIONS, ];
}

/**
 * Function run periodically by cron
 * Execute a Session Update on all Tracked Users of all Registers, if needed
 */
function attendanceregister_cron() {
    global $DB;

    // Remove orphaned Locks [issue #1].
    $orphanbefore = time() - ATTENDANCEREGISTER_ORPHANED_LOCKS_DELAY_SECONDS;
    $DB->delete_records_select('attendanceregister_lock', 'takenon < :takenon', [ 'takenon' => $orphanbefore]);
    $registers = $DB->get_records('attendanceregister');
    foreach ($registers as $register) {
        $course = get_course($register->course);
        // Added by Renaat.
        if (!$course->visible) {
            continue;
        }
        // Added by Renaat.
        if ($course->enddate > 0 && ($course->enddate > time() + (2 * 7 * 24 * 3600))) {
            continue;
        }
        if ($register->pendingrecalc) {
             attendanceregister_force_recalc_all($register);
             attendanceregister_set_pending_recalc($register, false);
        } else {
            $nupdates = attendanceregister_updates_all_users_sessions($register);
            if ($nupdates > 0) {
                mtrace($nupdates . ' Users updated on Attendance Register ID ' . $register->id);
            }
        }
    }
    return true;
}


/**
 * Adds module specific settings to the settings block
 *
 * @param  settings_navigation $settingsnav The settings navigation object
 * @param  navigation_node     $node    The node to add module settings to
 * @return void
 */
function attendanceregister_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $node) {
    global $PAGE;
    if ($PAGE->cm->modname !== 'attendanceregister') {
        return;
    }
    if (empty($PAGE->cm->context)) {
        $PAGE->cm->context = context_module::instance($PAGE->cm->instance);
    }

    $register = $PAGE->activityrecord;
    $params = $PAGE->url->params();
    $usercaps = new attendanceregister_user_capablities($PAGE->cm->context);

    // Add Recalc menu entries to Settings Menu.
    if ($usercaps->canrecalc) {
        if (!empty($params['userid']) || !$usercaps->canviewother) {
            // Single User view.
            $userid = clean_param($params['userid'], PARAM_INT);
            $linkurl = attendanceregister_makeurl($register, $userid, null, ATTENDANCEREGISTER_ACTION_RECALCULATE);
            $menuentry = get_string('force_recalc_user_session', 'attendanceregister');
            $node->add($menuentry, $linkurl, navigation_node::TYPE_SETTING);
        } else {
            // All Users view.
            $linkurl = attendanceregister_makeurl($register, null, null, ATTENDANCEREGISTER_ACTION_RECALCULATE);
            $menuentry = get_string('force_recalc_all_session_now', 'attendanceregister');
            if ($register->pendingrecalc) {
                $menuentry .= ' ' . get_string('recalc_already_pending', 'attendanceregister');
                $node->add($menuentry, $linkurl, navigation_node::TYPE_SETTING);
            } else {
                $node->add($menuentry, $linkurl, navigation_node::TYPE_SETTING);
                // Also adds Schedule Entry.
                $linkurl = attendanceregister_makeurl($register, null, null, ATTENDANCEREGISTER_ACTION_SCHEDULERECALC);
                $menuentry = get_string('schedule_reclalc_all_session', 'attendanceregister');
                $node->add($menuentry, $linkurl, navigation_node::TYPE_SETTING);
            }
        }
    }
}

/**
 * Returns list of available Register types
 *
 * @return array
 */
function attendanceregister_get_register_types() {
    return [
        ATTENDANCEREGISTER_TYPE_COURSE => get_string('type_' . ATTENDANCEREGISTER_TYPE_COURSE, 'attendanceregister'),
        ATTENDANCEREGISTER_TYPE_CATEGORY => get_string('type_' . ATTENDANCEREGISTER_TYPE_CATEGORY, 'attendanceregister'),
        ATTENDANCEREGISTER_TYPE_METAENROL => get_string('type_' . ATTENDANCEREGISTER_TYPE_METAENROL, 'attendanceregister'),
        ATTENDANCEREGISTER_TYPE_GLOBAL => 'global', ];
}

/**
 * Retrieve a Register Session by id
 *
 * @param  int $sessionid
 * @return object the session record
 */
function attendanceregister_get_session($sessionid) {
    global $DB;
    return $DB->get_record('attendanceregister_session', ['id' => $sessionid], '*', MUST_EXIST);
}

/**
 * Retrieve all User's Session in a given Register
 *
 * @param  object $register
 * @param  int    $userid
 * @return array of AttendanceRegisterSession
 */
function attendanceregister_get_user_sessions($register, $userid) {
    global $DB;
    return $DB->get_records('attendanceregister_session', ['register' => $register->id, 'userid' => $userid], 'login DESC');
}

/**
 * Updates recorded Sessions of a User
 * if User's Register is not Locked
 * AND if $recalculation is set
 *      OR attendanceregister_check_user_sessions_need_update(...) returns true
 *
 * @param  object       $register      Register instance
 * @param  int          $userid        User->id
 * @param  progress_bar $progressbar   optional instance of progress_bar to update
 * @param  boolean      $recalculation true on recalculation: ignore locks and needUpdate check
 * @return boolean true if any new session has been found
 */
function attendanceregister_update_user_sessions($register, $userid, progress_bar $progressbar = null, $recalculation = false) {
    // If not running in Recalc,
    // check if a Lock exists on this User's Register; if so, exit immediately.
    if (!$recalculation && attendanceregister__check_lock_exists($register, $userid)) {
        // If a progress bar exists, before exiting reset it.
        attendanceregister__finalize_progress_bar($progressbar, get_string('online_session_updated', 'attendanceregister'));
        return false;
    }

    // Check if Update is needed.
    if ($recalculation) {
        $lastlogout = 0;
        $needupdate = true;
    } else {
        $lastlogout = 0;
        $needupdate = attendanceregister_check_user_sessions_need_update($register, $userid, $lastlogout);
    }

    if ($needupdate) {
        // Calculate all new sesssions after that timestamp.
        return (attendanceregister__build_new_user_sessions($register, $userid, $lastlogout, $progressbar) > 0);
    } else {
        attendanceregister__finalize_progress_bar($progressbar, get_string('online_session_updated', 'attendanceregister'));
        return false;
    }
}

/**
 * Delete all online Sessions and Aggregates in a given Register
 *
 * @param object $register
 */
function attendanceregister_delete_all_users_online_sessions_and_aggregates($register) {
    global $DB;
    $DB->delete_records('attendanceregister_aggregate', ['register' => $register->id]);
    $DB->delete_records('attendanceregister_session', ['register' => $register->id, 'onlinesess' => 1]);
}

/**
 * Force recalculation of all sessions for a given User.
 * First delete currently saved Session, then launch update sessions
 * During the process, attain a Lock on the User's Register
 *
 * @param object       $register
 * @param int          $userid
 * @param progress_bar $progressbar
 * @param boolean      $deleteold before recalculating (default: true)
 */
function attendanceregister_force_recalc_user_sessions($register, $userid, progress_bar $progressbar = null, $deleteold = true) {
    attendanceregister__attain_lock($register, $userid);
    if ($deleteold) {
        $oldesttime = attendanceregister__get_user_oldest_log_entry_timestamp($userid);
        attendanceregister__delete_user_online_sessions($register, $userid, $oldesttime);
        attendanceregister__delete_user_aggregates($register, $userid);
    }
    attendanceregister_update_user_sessions($register, $userid, $progressbar, true);
    attendanceregister__release_lock($register, $userid);
}

/**
 * Force Recalculating all User's Sessions
 * Executes quietly (no Progress Bar)
 * (called after Restore and by Cron)
 *
 * @param object $register
 */
function attendanceregister_force_recalc_all($register) {
    $users = attendanceregister_get_tracked_users($register);
    foreach ($users as $user) {
        attendanceregister_force_recalc_user_sessions($register, $user->id);
    }
}

/**
 * Execute a conditional (if needed) update on all tracked User's Sessions
 * Generates no output, no Progress bar (just debug out)
 *
 * @param  object $register
 * @return int number of updated users
 */
function attendanceregister_updates_all_users_sessions($register) {
    $users = attendanceregister__get_tracked_users_need_update($register);
    $updatedcount = 0;
    foreach ($users as $user) {
        if (attendanceregister_update_user_sessions($register, $user->id)) {
            $updatedcount++;
        }
    }
    return $updatedcount;
}

/**
 * Checks if a User's Sessions need update.
 * Need update if:
 * User->currentlogin is after User's Last Session Logout AND older than Register SessionTimeout
 *
 * It uses Last Session Logout cached in Aggregates
 *
 * Note that this will report "update needed" also if the user logged in the site
 * after the last Session tracked in this Register, but did not touch any Course
 * tracked by this Register
 *
 * @param object $register
 * @param int    $userid
 * @param int    $lastlogout (by ref.) lastlogout returned if update needed
 * @return boolean true if update needed
 */
function attendanceregister_check_user_sessions_need_update($register, $userid, &$lastlogout = null) {
    $user = attendanceregister__getuser($userid);
    if (!$user->lastaccess) {
        return false;
    }
    $aggregate = attendanceregister__get_cached_user_grandtotal($register, $userid);
    if (!$aggregate) {
        $lastlogout = 0;
        return true;
    }
    if (
        ($user->currentlogin > $aggregate->lastsessionlogout) &&
        ((time() - $user->currentlogin) > ($register->sessiontimeout * 60))
    ) {
        $lastlogout = $aggregate->lastsessionlogout;
        return true;
    } else {
        return false;
    }
}



/**
 * Retrieve all Users tracked by a given Register
 *
 * All Users that in the Register's Course have any Role with "mod/attendanceregister:tracked" Capability assigned.
 * (NOT Users having this Capability in all tracked Courses!)
 *
 * @param object $register
 * @param string $groupid
 * @return array of users
 */
function attendanceregister_get_tracked_users($register, $groupid = '') {
    return attendanceregister__get_tracked_users($register, $groupid);
}

/**
 * Checks if a given User is tracked by a Register instance
 * @param object $register
 * @param object $user
 * @return bool
 */
function attendanceregister_is_tracked_user($register, $user) {
    $course = attendanceregister__get_register_course($register);
    $context = context_course::instance($course->id);
    return has_capability(ATTENDANCEREGISTER_CAPABILITY_TRACKED, $context, $user);
}

/**
 * Retrieve all Courses tracked by this Register
 *
 * @param  object $register
 * @return array of Course
 */
function attendanceregister_get_tracked_courses($register) {
    global $DB;
    $course = attendanceregister__get_register_course($register);
    $ids = attendanceregister__get_tracked_courses_ids($register, $course);
    return $DB->get_records_list('course', 'id', $ids, 'sortorder ASC, fullname ASC');
}

/**
 * Format duration (in seconds) in a human-readable format
 * If duration is null  shows '0' or a default string optionally passed as param
 *
 * @param  int    $duration
 * @param  string $default  (opt)
 * @return string
 */
function attendanceregister_format_duration($duration, $default = null) {

    if ($duration == null) {
        if ($default) {
            return $default;
        } else {
            $duration = 0;
        }
    }

    $dur = new stdClass();
    $dur->hours = floor($duration / 3600);
    $dur->minutes = floor(($duration % 3600) / 60);
    if ($dur->hours) {
        return get_string('duration_hh_mm', 'attendanceregister', $dur);
    }
    return get_string('duration_mm', 'attendanceregister', $dur);
}

/**
 * Save a new offline session
 * Data should have been validated before saving
 *
 * Updates Aggregates after saving
 *
 * @param object $register
 * @param array  $formdata
 */
function attendanceregister_save_offline_session($register, $formdata) {
    global $DB, $USER;

    $session = new stdClass();
    $session->register = $register->id;
    $session->userid = isset($formdata->userid) ? $formdata->userid : $USER->id;
    $session->onlinesess = 0;
    $session->login = $formdata->login;
    $session->logout = $formdata->logout;
    $session->duration = $formdata->logout - $formdata->login;
    $session->refcourse = isset($formdata->refcourse) ? $formdata->refcourse : null;
    $session->comments = isset($formdata->comments) ? $formdata->comments : null;
    if (!attendanceregister__iscurrentuser($session->userid)) {
        $session->addedbyuserid = $USER->id;
    }
    $DB->insert_record('attendanceregister_session', $session);
    attendanceregister__update_user_aggregates($register, $session->userid);
}

/**
 * Delete an offline Session
 * then updates Aggregates
 *
 * @param object $register
 * @param int $userid
 * @param int $sessionid
 */
function attendanceregister_delete_offline_session($register, $userid, $sessionid) {
    global $DB;
    $DB->delete_records('attendanceregister_session', ['id' => $sessionid, 'userid' => $userid, 'onlinesess' => 0]);
    attendanceregister__update_user_aggregates($register, $userid);
}

/**
 * Updates pendingrecalc flag of a Register
 *
 * @param  object $register
 * @param  bool $pending Recalc
 */
function attendanceregister_set_pending_recalc($register, $pending) {
    global $DB;
    $pending = $pending ? 1 : 0;
    $DB->update_record_raw('attendanceregister', ['id' => $register->id, 'pendingrecalc' => $pending]);
}


/**
 * Build the URL to the view.php page
 * of this Register
 *
 * @param object  $register
 * @param int     $userid        User ID (optional)
 * @param int     $groupid       Group ID (optional)
 * @param string  $action        Action to execute (optional)
 * @param array   $additional   (opt) other parameters
 * @param boolean $forlog       (def=false) if true prepare the URL for add_to_log (i.e. w/o the prefix '/mod/attendanceregister/')
 */
function attendanceregister_makeurl(
    $register,
    $userid = null,
    $groupid = null,
    $action = null,
    $additional = null,
    $forlog = false
) {

    $params = ['a' => $register->id];
    if ($userid) {
        $params['userid'] = $userid;
    }
    if ($groupid) {
        $params['group'] = $groupid;
    }
    if ($action) {
        $params['action'] = $action;
    }
    if (is_array($additional)) {
        $params = array_merge($params, $additional);
    }

    $url = !$forlog ? '/mod/attendanceregister/' : '';
    $url = new moodle_url($url . 'view.php', $params);
    return $url;
}

/**
 * Call add_to_log()
 *
 * @param object $register
 * @param int    $cmid     course_module->id
 * @param string $action
 * @param int    $userid
 * @param int    $groupid
 */
function attendanceregister_add_to_log($register, $cmid, $action, $userid = null, $groupid = null) {
    // TODO: move to events2.
    $logurl = attendanceregister_makeurl($register, $userid, $groupid, $action, null, true);
    switch ($action) {
        case ATTENDANCEREGISTER_ACTION_RECALCULATE:
            $logaction = ATTENDANCEREGISTER_LOGACTION_RECALCULTATE;
            break;
        case ATTENDANCEREGISTER_ACTION_SAVE_OFFLINE_SESSION:
            $logaction = ATTENDANCEREGISTER_LOGACTION_ADD_OFFLINE;
            break;
        case ATTENDANCEREGISTER_ACTION_DELETE_OFFLINE_SESSION:
            $logaction = ATTENDANCEREGISTER_LOGACTION_DELETE_OFFLINE;
            break;
        case ATTENDANCEREGISTER_ACTION_PRINTABLE:
        default:
            if ($userid) {
                $logaction = ATTENDANCEREGISTER_LOGACTION_VIEW;
            } else {
                $logaction = ATTENDANCEREGISTER_LOGACTION_VIEW_ALL;
            }
    }
    add_to_log($register->course, 'attendanceregister', $logaction, $logurl, '', $cmid);
}


/**
 * Implements activity completion conditions
 * [feature #7]
 *
 * @param  object $course Course
 * @param  object $cm     Course-module
 * @param  int    $userid User ID
 * @param  bool   $type   Type of comparison (or/and; can be used as return value if no conditions)
 * @return bool True if completed, false if not, $type if conditions not set.
 */
function attendanceregister_get_completion_state($course, $cm, $userid, $type) {
    global $CFG, $DB;
    if (!($register = $DB->get_record('attendanceregister', ['id' => $cm->instance]))) {
        throw new Exception("Can't find attendanceregister {$cm->instance}");
    }
    if (!$CFG->enablecompletion) {
        return false;
    }
    if (!$course->enablecompletion) {
        return false;
    }
    if ($register->completiontotaldurationmins) {
        return attendanceregister__calculatecompletion($register, $userid);
    } else {
        return $type;
    }
}
