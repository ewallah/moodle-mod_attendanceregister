<?php

/**
 * Attendance Register plugin version info
 *
 * @package    mod
 * @subpackage attendanceregister
 * @version $Id
 *
 * @author Lorenzo Nicora <fad@nicus.it>
 *
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;
$plugin->version  = 2014121202;
$plugin->requires = 2011120100;  // Requires this Moodle version
$plugin->cron     = 300;
$plugin->component = 'mod_attendanceregister'; // Full name of the plugin (used for diagnostics)
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = "2014.12.12.01"; // User-friendly version number