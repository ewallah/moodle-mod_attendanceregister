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
 * Attendance register local library.
 *
 * @package mod_attendanceregister
 * @copyright 2016 CINECA
 * @author  Lorenzo Nicora <fad@nicus.it>
 * @author  Renaat Debleu <info@eWallah.net>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once("$CFG->libdir/formslib.php");
require_once("$CFG->libdir/completionlib.php");

/**
 * Retrieve the Course object instance of the Course where the Register is
 *
 * @param  object $register
 * @return object Course
 */
function attendanceregister__get_register_course($register) {
    global $DB;
    return $DB->get_record('course', ['id' => $register->course], '*', MUST_EXIST);
}

/**
 * Calculate the the end of the last online Session already calculated
 * for a given user, retrieving the User's Sessions (i.e. do not use cached timestamp in aggregate)
 * If no Session exists, returns 0
 *
 * @param  object $register
 * @param  int    $userid
 * @return int
 */
function attendanceregister__calculate_last_user_online_session_logout($register, $userid) {
    global $DB;
    $params = ['register' => $register->id, 'userid' => $userid];
    $last = $DB->get_field_sql('SELECT MAX(logout) FROM {attendanceregister_session}
        WHERE register = ? AND userid = ? AND onlinesess = 1', $params);
    if ($last === false) {
        $last = 0;
    }
    return $last;
}


/**
 * This is the function that actually process log entries and calculate sessions
 *
 * Calculate and Save all new Sessions of a given User
 * starting from a given timestamp.
 * Optionally updates a progress_bar
 *
 * Also Updates User's Aggregates
 *
 * @param  Attendanceregister  $register
 * @param  int                 $userid
 * @param  int                 $fromtime (default 0)
 * @param  progress_bar        $progressbar optional instance of progress_bar to update
 * @return int                 number of new sessions found
 */
function attendanceregister__build_new_user_sessions($register, $userid, $fromtime = 0, progress_bar $progressbar = null) {
    $course = attendanceregister__get_register_course($register);
    $user = attendanceregister__getuser($userid);
    $trackedcids = attendanceregister__get_tracked_courses_ids($register, $course);
    $logcount = 0;
    $logentries = attendanceregister__get_user_log_entries_in_courses($userid, $fromtime, $trackedcids, $logcount);

    $sessiontimeout = $register->sessiontimeout * 60;
    $prevlog = null;
    $sessionstart = null;
    $logentriescount = 0;
    $newsessionscount = 0;
    $sessionlast = 0;

    if (is_array($logentries) && count($logentries) > 0) {
        foreach ($logentries as $entry) {
            $logentriescount++;
            if (!$prevlog) {
                $prevlog = $entry;
                $sessionstart = $entry->timecreated;
                continue;
            }

            // Check if between prev and current log, last more than Session Timeout
            // if so, the Session ends on the _prev_ log entry.
            if (($entry->timecreated - $prevlog->timecreated) > $sessiontimeout) {
                $newsessionscount++;

                // Estimate Session ended half the Session Timeout after the prev log entry
                // (prev log entry is the last entry of the Session).
                $sessionlast = $prevlog->timecreated;
                $estimatedend = $sessionlast + $sessiontimeout / 2;

                // Save a new session to the prev entry.
                attendanceregister__save_session($register, $userid, $sessionstart, $estimatedend);

                // Update the progress bar, if any.
                if ($progressbar) {
                    $msg = get_string('updating_online_sessions_of', 'attendanceregister', fullname($user));
                    $progressbar->update($logentriescount, $logcount, $msg);
                }

                // Session has ended: session start on current log entry.
                $sessionstart = $entry->timecreated;
            }
            $prevlog = $entry;
        }

        // If le last log entry is not the end of the last calculated session and is older than SessionTimeout
        // create a last session.
        if ($entry->timecreated > $sessionlast && (time() - $entry->timecreated) > $sessiontimeout) {
            $newsessionscount++;
            // In this case entry (and not prevlog is the last entry of the Session).
            $sessionlast = $entry->timecreated;
            $estimatedend = $sessionlast + $sessiontimeout / 2;

            // Save a new session to the prev entry.
            attendanceregister__save_session($register, $userid, $sessionstart, $estimatedend);

            // Update the progress bar, if any.
            if ($progressbar) {
                $msg = get_string('updating_online_sessions_of', 'attendanceregister', fullname($user));
                $progressbar->update($logentriescount, $logcount, $msg);
            }
        }
    }

    // Updates Aggregates.
    attendanceregister__update_user_aggregates($register, $userid);

    // Finalize Progress Bar.
    if ($progressbar) {
        $a = new stdClass();
        $a->fullname = fullname($user);
        $a->numnewsessions = $newsessionscount;
        $msg = get_string('online_session_updated_report', 'attendanceregister', $a);
        attendanceregister__finalize_progress_bar($progressbar, $msg);
    }

    return $newsessionscount;
}

/**
 * Updates Aggregates for a given user
 * and notify completion, if needed [feature #7]
 *
 * @param object $register
 * @param int    $userid
 */
function attendanceregister__update_user_aggregates($register, $userid) {
    global $DB;
    $DB->delete_records('attendanceregister_aggregate', ['userid' => $userid, 'register' => $register->id]);

    $aggregates = [];
    $params = ['registerid' => $register->id, 'userid' => $userid];

    if ($register->offlinesessions) {
        $sql = 'SELECT sess.refcourse, sess.register, sess.userid, 0 AS onlinesess,
                   SUM(sess.duration) AS duration, 0 AS total, 0 as grandtotal
                FROM {attendanceregister_session} sess
                WHERE sess.onlinesess = 0 AND sess.register = :registerid AND sess.userid = :userid
                GROUP BY sess.register, sess.userid, sess.refcourse';
        $offlineaggregates = $DB->get_records_sql($sql, $params);
        // Append records.
        if ($offlineaggregates) {
            $aggregates = array_merge($aggregates, $offlineaggregates);
        }
        $sql = 'SELECT sess.register, sess.userid, 0 AS onlinesess, null AS refcourse,
                   SUM(sess.duration) AS duration, 1 AS total, 0 as grandtotal
                FROM {attendanceregister_session} sess
                WHERE sess.onlinesess = 0 AND sess.register = :registerid AND sess.userid = :userid
                GROUP BY sess.register, sess.userid';
        $totaloffline = $DB->get_record_sql($sql, $params);
        if ($totaloffline) {
            $aggregates[] = $totaloffline;
        }
    }

    $sql = 'SELECT sess.register, sess.userid, 1 AS onlinesess, null AS refcourse,
                  SUM(sess.duration) AS duration, 1 AS total, 0 as grandtotal
            FROM {attendanceregister_session} sess
            WHERE sess.onlinesess = 1 AND sess.register = :registerid AND sess.userid = :userid
            GROUP BY sess.register, sess.userid';
    $onlineaggregate = $DB->get_record_sql($sql, $params);

    if (!$onlineaggregate) {
        $onlineaggregate = new stdClass();
        $onlineaggregate->register = $register->id;
        $onlineaggregate->userid = $userid;
        $onlineaggregate->onlinesess = 1;
        $onlineaggregate->refcourse = null;
        $onlineaggregate->duration = 0;
        $onlineaggregate->total = 1;
        $onlineaggregate->grandtotal = 0;
    }
    $aggregates[] = $onlineaggregate;
    $sql = 'SELECT sess.register, sess.userid, null AS onlinesess, null AS refcourse,
                SUM(sess.duration) AS duration, 0 AS total, 1 as grandtotal
             FROM {attendanceregister_session} sess
            WHERE sess.register = :registerid AND sess.userid = :userid
            GROUP BY sess.register, sess.userid';
    $grandtotalaggregate = $DB->get_record_sql($sql, $params);

    if (!$grandtotalaggregate) {
        $grandtotalaggregate = new stdClass();
        $grandtotalaggregate->register = $register->id;
        $grandtotalaggregate->userid = $userid;
        $grandtotalaggregate->onlinesess = null;
        $grandtotalaggregate->refcourse = null;
        $grandtotalaggregate->duration = 0;
        $grandtotalaggregate->total = 0;
        $grandtotalaggregate->grandtotal = 1;
    }
    $grandtotalaggregate->lastsessionlogout = attendanceregister__calculate_last_user_online_session_logout($register, $userid);
    $aggregates[] = $grandtotalaggregate;

    foreach ($aggregates as $aggregate) {
        $DB->insert_record('attendanceregister_aggregate', $aggregate);
    }

    if (attendanceregister__iscondition($register)) {
        $cm = get_coursemodule_from_instance('attendanceregister', $register->id, $register->course, null, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $completion = new completion_info($course);
        if ($completion->is_enabled($cm)) {
            $completiontracked = ['totaldurationsecs' => $grandtotalaggregate->duration];
            if (attendanceregister__iscomplete($register, $completiontracked)) {
                $completion->update_state($cm, COMPLETION_COMPLETE, $userid);
            } else {
                $completion->update_state($cm, COMPLETION_INCOMPLETE, $userid);
            }
        }
    }
}

/**
 * Retrieve all Users tracked by a given Register.
 * User are sorted by fullname
 *
 * All Users that in the Register's Course have any Role with "mod/attendanceregister:tracked" Capability assigned.
 * (NOT Users having this Capability in all tracked Courses!)
 *
 * @param object $register
 * @param string $groupid
 * @return array of users
 */
function attendanceregister__get_tracked_users($register, $groupid = '') {
    $userids = [];
    $course = attendanceregister__get_register_course($register);
    $courseids = attendanceregister__get_tracked_courses_ids($register, $course);
    foreach ($courseids as $courseid) {
        $context = context_course::instance($courseid);
        $users = get_users_by_capability(
            $context,
            'mod/attendanceregister:viewownregister',
            '',
            '',
            '',
            '',
            $groupid,
            '',
            false,
            true
        );
        $userids = array_merge($users, $userids);
    }
    $unique = attendanceregister__unique_object_array_by_id($userids);
    usort($unique, function ($a, $b) {
        return strcmp(fullname($a), fullname($b));
    });
    return $unique;
}

/**
 * Similar to attendanceregister__get_tracked_users($rgister), but retrieves only
 * those tracked users whose online sessions need to be updated.
 *
 * @param  object $register
 * @return array of users
 */
function attendanceregister__get_tracked_users_need_update($register) {
    global $DB;
    $trackedusers = [];

    // Get Context of each Tracked Course.
    $thiscourse = attendanceregister__get_register_course($register);
    $trackedcoursesids = attendanceregister__get_tracked_courses_ids($register, $thiscourse);
    foreach ($trackedcoursesids as $courseid) {
        $context = context_course::instance($courseid);
        [$esql, $params] = get_enrolled_sql($context, ATTENDANCEREGISTER_CAPABILITY_TRACKED);
        $sql = "SELECT u.* FROM {user} u JOIN ($esql) je ON je.id = u.id
                WHERE u.lastaccess + (:sesstimeout * 60) < :now
                  AND (NOT EXISTS (SELECT * FROM {attendanceregister_session} as3
                                     WHERE as3.userid = u.id AND as3.register = :registerid1 AND as3.onlinesess = 1)
                       OR NOT EXISTS (SELECT * FROM {attendanceregister_aggregate} aa4 WHERE aa4.userid=u.id AND
                           aa4.register=:registerid2  AND aa4.grandtotal = 1)
                       OR EXISTS (SELECT * FROM {attendanceregister_aggregate} aa2, {log} l2
                                    WHERE aa2.userid = u.id AND aa2.register = :registerid3
                                      AND l2.course = :courseid AND l2.userid = aa2.userid
                                      AND aa2.grandtotal = 1
                                      AND l2.time > aa2.lastsessionlogout))";
        $params['sesstimeout'] = $register->sessiontimeout;
        $params['now'] = time();
        $params['registerid1'] = $register->id;
        $params['registerid2'] = $register->id;
        $params['registerid3'] = $register->id;
        $params['courseid'] = $courseid;
        $trackedusersincourse = $DB->get_records_sql($sql, $params);
        $trackedusers = array_merge($trackedusers, $trackedusersincourse);
    }
    return attendanceregister__unique_object_array_by_id($trackedusers);
}

/**
 * Retrieve all User's Aggregates of a given User
 *
 * @param  object $register
 * @param  int    $userid
 * @return array of attendanceregister_aggregate
 */
function attendanceregister__get_user_aggregates($register, $userid) {
    global $DB;
    return $DB->get_records('attendanceregister_aggregate', ['register' => $register->id, 'userid' => $userid]);
}

/**
 * Retrieve User's Aggregates summary-only (only total & grandtotal records)
 * for all Users tracked by the Register.
 *
 * @param  object $register
 * @return array of attendanceregister_aggregate
 */
function attendanceregister__get_all_users_aggregate_summaries($register) {
    global $DB;
    $select = "register = :register AND (total = 1 OR grandtotal = 1)";
    return $DB->get_records_select('attendanceregister_aggregate', $select, ['register' => $register->id]);
}

/**
 * Retrieve cached value of Aggregate grandtotal row
 * (containing grandtotal duration and lastlogout)
 * If no aggregate, return false
 *
 * @param  object $register
 * @param  int    $userid
 * @return an object with grandtotal and lastlogout or FALSE if missing
 */
function attendanceregister__get_cached_user_grandtotal($register, $userid) {
    global $DB;
    $params = ['register' => $register->id, 'userid' => $userid, 'grandtotal' => 1];
    $cnt = $DB->count_records('attendanceregister_aggregate', $params);
    if ($cnt > 1) {
        mtrace($userid . ' failed in register ' . $register->id);
    }
    return $DB->get_record('attendanceregister_aggregate', $params, '*', IGNORE_MISSING);
}


/**
 * Returns an array of Course ID with all Courses tracked by this Register
 * depending on type
 *
 * @param  object $register
 * @param  object $course
 * @return array
 */
function attendanceregister__get_tracked_courses_ids($register, $course) {
    global $DB;
    $ids = [];
    switch ($register->type) {
        case ATTENDANCEREGISTER_TYPE_GLOBAL:
            $ids = $DB->get_fieldset_select('course', 'id', 'id > 1', []);
            break;
        case ATTENDANCEREGISTER_TYPE_METAENROL:
            $ids[] = $course->id;
            $ids = array_merge($ids, attendanceregister__get_coursed_ids_meta_linked($course));
            break;
        case ATTENDANCEREGISTER_TYPE_CATEGORY:
            $ids[] = $course->id;
            $ids = array_merge($ids, attendanceregister__get_courses_ids_in_category($course));
            break;
        default:
            $ids[] = $course->id;
    }

    return $ids;
}

/**
 * Get all IDs of Courses in the same Category of the given Course
 *
 * @param  object $course a Course
 * @return array of int
 */
function attendanceregister__get_courses_ids_in_category($course) {
    global $DB;
    return $DB->get_fieldset_select('course', 'id', 'category = :categoryid ', ['categoryid' => $course->category]);
}

/**
 * Get IDs of all Courses meta-linked to a give Course
 *
 * @param  object $course a Course
 * @return array of int
 */
function attendanceregister__get_coursed_ids_meta_linked($course) {
    global $DB;
    return $DB->get_fieldset_select('enrol', 'customint1', "courseid = :courseid AND enrol = 'meta'", ['courseid' => $course->id]);
}

/**
 * Retrieves all log entries of a given user, after a given time,
 * for all activities in a given list of courses.
 * Log entries are sorted from oldest to newest
 *
 * @param int   $userid
 * @param int   $fromtime
 * @param array $courseids
 * @param int   $logcount  count of records, passed by ref.
 */
function attendanceregister__get_user_log_entries_in_courses($userid, $fromtime, $courseids, &$logcount) {
    global $DB;
    $courseids = implode(',', $courseids);
    if (!$fromtime) {
        $fromtime = 0;
    }
    $sql = "SELECT * FROM {logstore_standard_log} l
       WHERE l.userid = :userid AND l.timecreated > :fromtime AND l.courseid IN ($courseids)
       ORDER BY l.timecreated ASC";
    $logentries = $DB->get_records_sql($sql, ['userid' => $userid, 'fromtime' => $fromtime]);
    $logcount = count($logentries);
    return $logentries;
}

/**
 * Checks if a given login-logout overlap with a User's Session already saved
 * in the Register
 *
 * @param  object $register
 * @param  int $userid
 * @param  int $login
 * @param  int $logout
 * @return boolean true if overlapping
 */
function attendanceregister__check_overlapping_old_sessions($register, $userid, $login, $logout) {
    global $DB;
    $select = 'userid = :userid AND register = :registerid AND
        ((:login BETWEEN login AND logout) OR (:logout BETWEEN login AND logout))';
    $params = [ 'userid' => $userid, 'registerid' => $register->id, 'login' => $login, 'logout' => $logout];
    return $DB->record_exists_select('attendanceregister_session', $select, $params);
}

/**
 * Checks if a given login-logout overlap overlap the current User's session
 * If the user is the current user, just checks if logout is after User's Last Login
 * If is another user, if user's lastaccess is older then sessiontimeout he is supposed to be logged out
 *
 * @param  object $register
 * @param  $int $userid
 * @param  int $login
 * @param  int $logout
 * @return boolean true if overlapping
 */
function attendanceregister__check_overlapping_current_session($register, $userid, $login, $logout) {
    global $USER;
    if ($USER->id == $userid) {
        $user = $USER;
    } else {
        $user = attendanceregister__getuser($userid);
        // If user never logged in, no overlapping could happens.
        if (!$user->lastaccess) {
            return false;
        }

        // If user lastaccess is older than sessiontimeout, the user is supposed to be logged out and no check is done.
        $sessionsecs = $register->sessiontimeout * 60;
        if (!$user->lastaccess < (time() - $sessionsecs)) {
            return false;
        }
    }
    return ($user->currentlogin < $logout);
}

/**
 * Save a new Session
 *
 * @param object  $register
 * @param int     $userid
 * @param int     $login
 * @param int     $logout
 * @param boolean $online
 * @param int     $refid
 * @param string  $comments
 */
function attendanceregister__save_session($register, $userid, $login, $logout, $online = true, $refid = null, $comments = null) {
    global $DB;
    $session = new stdClass();
    $session->register = $register->id;
    $session->userid = $userid;
    $session->login = $login;
    $session->logout = $logout;
    $session->duration = ($logout - $login);
    $session->onlinesess = $online;
    $session->refcourse = $refid;
    $session->comments = $comments;
    $DB->insert_record('attendanceregister_session', $session);
}

/**
 * Delete all online Sessions of a given User
 * If $onlyDeleteAfter is specified, deletes only Sessions with login >= $onlyDeleteAfter
 * (this is used not to delete calculated sessions older than the first available
 * User's log entry)
 *
 * @param object $register
 * @param int    $userid
 * @param int    $onlyafter default) null (=ignored)
 */
function attendanceregister__delete_user_online_sessions($register, $userid, $onlyafter = null) {
    global $DB;
    $params = ['userid' => $userid, 'register' => $register->id, 'onlinesess' => 1];
    if ($onlyafter) {
        $where = 'userid = :userid AND register = :register AND onlinesess = :onlinesess AND login >= :lowerlimit';
        $params['lowerlimit'] = $onlyafter;
        $DB->delete_records_select('attendanceregister_session', $where, $params);
    } else {
        $DB->delete_records('attendanceregister_session', $params);
    }
}

/**
 * Delete all User's Aggrgates of a given User
 *
 * @param object $register
 * @param int    $userid
 */
function attendanceregister__delete_user_aggregates($register, $userid) {
    global $DB;
    $DB->delete_records('attendanceregister_aggregate', ['userid' => $userid, 'register' => $register->id]);
}


/**
 * Retrieve the timestamp of the oldest Log Entry of a User
 * Please not that this is the oldest log entry in the site, not only in tracked courses.
 *
 * @param  int $userid
 * @return int or null if no log entry found
 */
function attendanceregister__get_user_oldest_log_entry_timestamp($userid) {
    global $DB;
    $obj = $DB->get_record_sql(
        'SELECT MIN(time) as oldestlogtime FROM {log} WHERE userid = :userid',
        [ 'userid' => $userid],
        IGNORE_MISSING
    );
    if ($obj) {
        return $obj->oldestlogtime;
    }
    return null;
}

/**
 * Check if a Lock exists on a given User's Register
 *
 * @param stdClass $register
 * @param int $userid
 * @return bool true if lock exists
 */
function attendanceregister__check_lock_exists($register, $userid) {
    global $DB;
    return $DB->record_exists('attendanceregister_lock', ['register' => $register->id, 'userid' => $userid]);
}

/**
 * Attain a Lock on a User's Register
 *
 * @param object $register
 * @param int    $userid
 */
function attendanceregister__attain_lock($register, $userid) {
    global $DB;
    $lock = new stdClass();
    $lock->register = $register->id;
    $lock->userid = $userid;
    $lock->takenon = time();
    $DB->insert_record('attendanceregister_lock', $lock);
}

/**
 * Release (all) Lock(s) on a User's Register.
 *
 * @param object $register
 * @param int    $userid
 */
function attendanceregister__release_lock($register, $userid) {
    global $DB;
    $DB->delete_records('attendanceregister_lock', ['register' => $register->id, 'userid' => $userid]);
}

/**
 * Finalyze (push to 100%) the progressbar, if any, showing a message.
 *
 * @param progress_bar $progressbar Progress Bar instance to update; if null do nothing
 * @param string       $msg
 */
function attendanceregister__finalize_progress_bar($progressbar, $msg = '') {
    if ($progressbar) {
        $progressbar->update_full(100, $msg);
    }
}

/**
 * Extract an array containing values of a property from an array of objets
 *
 * @param  array  $arrayobjects
 * @param  string $name
 * @return array containing only the values of the property
 */
function attendanceregister__extract_property($arrayobjects, $name) {
    $arrayvalue = [];
    foreach ($arrayobjects as $obj) {
        if (($objectproperties = get_object_vars($obj))) {
            if (isset($objectproperties[$name])) {
                $arrayvalue[] = $objectproperties[$name];
            }
        }
    }
    return $arrayvalue;
}

/**
 * Shorten a Comment to a given length, w/o truncating words
 *
 * @param string $text
 * @param int    $maxlen
 */
function attendanceregister__shorten_comment($text, $maxlen = ATTENDANCEREGISTER_COMMENTS_SHORTEN_LENGTH) {
    if (strlen($text) > $maxlen) {
        $text = $text . " ";
        $text = substr($text, 0, $maxlen);
        $text = substr($text, 0, strrpos($text, ' '));
        $text = $text . "...";
    }
    return $text;
}

/**
 * Returns an array with unique objects in a given array
 * comparing by id property
 *
 * @param  array $objarray of object
 * @return array of object
 */
function attendanceregister__unique_object_array_by_id($objarray) {
    $uniqueobjects = [];
    $uniquobjids = [];
    foreach ($objarray as $obj) {
        if (!in_array($obj->id, $uniquobjids)) {
            $uniquobjids[] = $obj->id;
            $uniqueobjects[] = $obj;
        }
    }
    return $uniqueobjects;
}

/**
 * Format a dateTime using userdate()
 * If Debug configuration is active and at ALL or DEVELOPER level,
 * adds extra informations on UnixTimestamp
 * and return "Never" if timestamp is 0
 *
 * @param int $datetime
 * @return string
 */
function attendanceregister__formatdate($datetime) {
    if (!$datetime) {
        return get_string('never', 'attendanceregister');
    }
    return userdate($datetime);
}

/**
 * A shortcut for loading a User
 * It the User does not exist, an error is thrown
 *
 * @param int $userid
 */
function attendanceregister__getuser($userid) {
    global $DB;
    return $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
}

/**
 * Check if a given User ID is of the currently logged user
 *
 * @param  int $userid (consider null as current user)
 * @return boolean
 */
function attendanceregister__iscurrentuser($userid) {
    global $USER;
    return (!$userid || $USER->id == $userid);
}

/**
 * Return user's full name or unknown
 *
 * @param int $otherid
 * @return string
 */
function attendanceregister__othername($otherid) {
    $other = attendanceregister__getuser($otherid);
    if ($other) {
        return fullname($other);
    } else {
        return get_string('unknown', 'attendanceregister');
    }
}

/**
 * Check if any completion condition is enabled in a given Register instance.
 * ANY CHECK FOR ENABLED COMPLETION CONDITION must use this function
 *
 * @param  object $register Register instance
 * @return boolean TRUE if any completion condition is enabled
 */
function attendanceregister__iscondition($register) {
    return (bool)($register->completiontotaldurationmins);
}

/**
 * Check completion of the activity by a user.
 * Note that this method performs aggregation SQL queries for caculating tracked values
 * useful for completion check.
 * Actual completion condition check is delegated
 * to attendanceregister__iscomplete(...)
 *
 * @param  object $register AttendanceRegister
 * @param  int    $userid   User ID
 * @return boolean TRUE if the Activity is complete, FALSE if not complete, NULL if no activity completion has been specified
 */
function attendanceregister__calculatecompletion($register, $userid) {
    global $DB;
    if (!attendanceregister__iscondition($register)) {
        return null;
    }
    $sql = "SELECT SUM(sess.duration) FROM {attendanceregister_session} sess WHERE sess.register=:registerid AND userid=:userid";
    $totalsecs = $DB->get_field_sql($sql, ['registerid' => $register->id, 'userid' => $userid]);
    return attendanceregister__iscomplete($register, ['totaldurationsecs' => $totalsecs]);
}

/**
 * Check if a set of tracked values meets the completion condition for the instance
 *
 * This method implements evaluation of (pre-calculated) tracked values
 * against completion conditions.
 * ANY COMPLETION CHECK (for a user) must be delegated to this method.
 *
 * Values are passed as an associative array i.e. array['totaldurationsecs' => xxxxx]
 *
 * @param  object $register          Register instance
 * @param  array  $trackedvalues     array of tracked values, by parameter name
 * @return boolean TRUE if this values match comletion condition, otherwise FALSE
 */
function attendanceregister__iscomplete($register, $trackedvalues) {
    if (isset($trackedvalues['totaldurationsecs'])) {
        $totaldurationsecs = $trackedvalues['totaldurationsecs'];
        if (!$totaldurationsecs) {
            return false;
        }
        return (($totaldurationsecs / 60) >= $register->completiontotaldurationmins);
    } else {
        return false;
    }
}

/**
 * Check if the Cron form this module ran after the creation of an instance
 *
 * @param  object $cm Course-Module instance
 * @return bool TRUE if the Cron run on this module after instance creation
 */
function attendanceregister__didcronran($cm) {
    global $DB;
    $module = $DB->get_record('modules', ['name' => 'attendanceregister'], '*', MUST_EXIST);
    return ($cm->added < $module->lastcron);
}

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
class mod_attendanceregister_selfcertification_edit_form extends moodleform {
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

        if (attendanceregister__iscurrentuser($userid)) {
            $title = get_string('insert_new_offline_session', 'attendanceregister');
        } else {
            $a = new stdClass();
            $a->fullname = fullname(attendanceregister__getuser($userid));
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
        if (attendanceregister__check_overlapping_old_sessions($register, $userid, $login, $logout)) {
            $errors['login'] = get_string('overlaps_old_sessions', 'attendanceregister');
        }
        if (attendanceregister__check_overlapping_current_session($register, $userid, $login, $logout)) {
            $errors['login'] = get_string('overlaps_current_session', 'attendanceregister');
        }
        return $errors;
    }
}

/**
 * This class collects al current User's Capabilities regarding the current instance of Attendance Register
 *
 * @package mod_attendanceregister
 * @copyright 2016 CINECA
 * @author  Lorenzo Nicora <fad@nicus.it>
 * @author  Renaat Debleu <info@eWallah.net>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attendanceregister_user_capablities {
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
        return (((attendanceregister__iscurrentuser($userid)) && $this->canviewown) || $this->canviewother);
    }

    /**
     * Checks if the current user can delete a given User's Offline Sessions
     *
     * @param  int $userid (null means current user's register)
     * @return boolean
     */
    public function canddeletesession($userid) {
        return (((attendanceregister__iscurrentuser($userid)) &&  $this->candeleteown) || $this->candeleteother);
    }

    /**
     * Check if the current USER can add Offline Sessions for a specified User
     *
     * @param stdClass $register
     * @param int $userid (null means current user's register)
     * @return boolean
     */
    public function canaddsession($register, $userid) {
        if (attendanceregister__iscurrentuser($userid)) {
            return $this->canaddown;
        } else if ($this->canaddother) {
            $user = attendanceregister__getuser($userid);
            return attendanceregister_is_tracked_user($register, $user);
        }
        return false;
    }
}
