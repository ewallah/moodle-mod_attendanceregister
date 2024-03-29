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
 * Privacy main class.
 *
 * @package mod_attendanceregister
 * @copyright 2016 CINECA
 * @author  Lorenzo Nicora <fad@nicus.it>
 * @author  Renaat Debleu <info@eWallah.net>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_attendanceregister\privacy;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\writer;
use core_privacy\local\request\helper;
use core_privacy\local\request\deletion_criteria;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\approved_userlist;


/**
 * Privacy main class.
 *
 * @package mod_attendanceregister
 * @copyright 2016 CINECA
 * @author  Lorenzo Nicora <fad@nicus.it>
 * @author  Renaat Debleu <info@eWallah.net>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements \core_privacy\local\request\core_userlist_provider, \core_privacy\local\metadata\provider, \core_privacy\local\request\plugin\provider {
    /**
     * Returns information about how attendanceregister stores its data.
     *
     * @param   collection     $collection The initialised collection to add items to.
     * @return  collection     A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection {
        $arr = ['userid' => 'privacy:metadata:attendanceregister_session:userid',
                'login' => 'privacy:metadata:attendanceregister_session:login',
                'logout' => 'privacy:metadata:attendanceregister_session:logout',
                'duration' => 'privacy:metadata:attendanceregister_session:duration',
                'onlinesess' => 'privacy:metadata:attendanceregister_session:onlinesess',
                'comments' => 'privacy:metadata:attendanceregister_session:comments', ];
        $collection->add_database_table('attendanceregister_session', $arr, 'privacy:metadata:attendanceregister_session');
        $arr = ['userid' => 'privacy:metadata:attendanceregister_aggregate:userid',
                'duration' => 'privacy:metadata:attendanceregister_aggregate:duration',
                'onlinesess' => 'privacy:metadata:attendanceregister_aggregate:onlinesess',
                'total' => 'privacy:metadata:attendanceregister_aggregate:total',
                'grandtotal' => 'privacy:metadata:attendanceregister_aggregate:grandtotal',
                'lastsessionlogout' => 'privacy:metadata:attendanceregister_aggregate:lastsessionlogout', ];
        $collection->add_database_table('attendanceregister_aggregate', $arr, 'privacy:metadata:attendanceregister_aggregate');
        $arr = ['userid' => 'privacy:metadata:attendanceregister_lock:userid',
                'takenon' => 'privacy:metadata:attendanceregister_lock:takenon', ];
        $collection->add_database_table('attendanceregister_lock', $arr, 'privacy:metadata:attendanceregister_lock');
        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param   int           $userid       The user to search.
     * @return  contextlist   $contextlist  The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): \core_privacy\local\request\contextlist {
        $contextlist = new \core_privacy\local\request\contextlist();
        $contextlist->add_system_context();
        return $contextlist;
    }

    /**
     * Get the list of users within a specific context.
     *
     * @param   userlist    $userlist   The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if ($context->contextlevel == CONTEXT_SYSTEM) {
            $sql = "SELECT * FROM {attendanceregister_session}";
            $userlist->add_from_sql('userid', $sql, []);
            $userlist->add_from_sql('addedbyuserid', $sql, []);
            $sql = "SELECT * FROM {attendanceregister_aggregate}";
            $userlist->add_from_sql('userid', $sql, []);
            $sql = "SELECT * FROM {attendanceregister_lock}";
            $userlist->add_from_sql('userid', $sql, []);
        }
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param   approved_contextlist    $contextlist    The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $user = $contextlist->get_user();
        foreach ($contextlist as $context) {
            if ($context->contextlevel == CONTEXT_SYSTEM) {
                $contextdata = helper::get_context_data($context, $user);
                helper::export_context_files($context, $user);
                writer::with_context($context)->export_data([], $contextdata);
                $data = [];
                $sql = "SELECT * FROM {attendanceregister_session} WHERE userid = :userid1 OR addedbyuserid = :userid2";
                $recordset = $DB->get_recordset_sql($sql, ['userid1' => $user->id, 'userid2' => $user->id]);
                foreach ($recordset as $record) {
                    $data[] = [
                        'userid' => $record->userid,
                        'login' => transform::datetime($record->login),
                        'logout' => transform::datetime($record->logout),
                        'duration' => $record->duration,
                        'onlinesess' => transform::yesno($record->onlinesess),
                        'comments' => $record->comments, ];
                }

                $sql = "SELECT * FROM {attendanceregister_aggregate} WHERE userid = :userid1";
                $recordset = $DB->get_recordset_sql($sql, ['userid1' => $user->id]);
                foreach ($recordset as $record) {
                    $data[] = [
                        'userid' => $record->userid,
                        'duration' => $record->duration,
                        'onlinesess' => transform::yesno($record->onlinesess),
                        'total' => $record->total,
                        'grandtotal' => $record->grandtotal,
                        'lastlogout' => transform::datetime($record->lastsessionlogout),
                    ];
                }

                $sql = "SELECT userid, takenon FROM {attendanceregister_lock} WHERE userid = :userid1";
                $recordset = $DB->get_recordset_sql($sql, ['userid1' => $user->id]);
                foreach ($recordset as $record) {
                    $data[] = ['userid' => $record->userid, 'takenon' => transform::datetime($record->takenon)];
                }
                $recordset->close();
                if (!empty($data)) {
                    writer::with_context($context)->export_related_data(
                        [],
                        'sessions',
                        (object) ['sessions' => array_values($data)]
                    );
                }
            }
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param   context                 $context   The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context->contextlevel == CONTEXT_SYSTEM) {
            $DB->delete_records('attendanceregister_session', []);
            $DB->delete_records('attendanceregister_aggregate', []);
            $DB->delete_records('attendanceregister_lock', []);
        }
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param   approved_contextlist    $contextlist    The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        $contexts = $contextlist->get_contexts();
        foreach ($contexts as $context) {
            if ($context->contextlevel == CONTEXT_SYSTEM) {
                $DB->delete_records('attendanceregister_session', ['userid' => $userid]);
                $DB->delete_records('attendanceregister_session', ['addedbyuserid' => $userid]);
                $DB->delete_records('attendanceregister_aggregate', ['userid' => $userid]);
                $DB->delete_records('attendanceregister_lock', ['userid' => $userid]);
            }
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param   approved_userlist       $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        $context = $userlist->get_context();
        if ($context->contextlevel == CONTEXT_SYSTEM) {
            [$userinsql, $params] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
            $DB->delete_records_select('attendanceregister_session', "userid {$userinsql}", $params);
            $DB->delete_records_select('attendanceregister_session', "addedbyuserid {$userinsql}", $params);
            $DB->delete_records_select('attendanceregister_aggregate', "userid {$userinsql}", $params);
            $DB->delete_records_select('attendanceregister_lock', "userid {$userinsql}", $params);
        }
    }
}
