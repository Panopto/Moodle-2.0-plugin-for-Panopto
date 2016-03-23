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
 * @package block_panopto
 * @copyright  Panopto 2009 - 2015 /With contributions from Spenser Jones (sjones@ambrose.edu) and by Skylar Kelty <S.Kelty@kent.ac.uk>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__) . '/../../../config.php');
require_once(dirname(__FILE__) . '/../lib/panopto_data.php');

/**
 * Handlers for each different event type.
 *
 * @package block_panopto
 * @copyright Panopto 2009 - 2015
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */
class block_panopto_rollingsync {

    private static $requiredVersion = 2014051200; //Moodle version 2.7 or higher required for rolling sync tasks.

    /**
     * Called when an enrolment has been created.
     */
    public static function enrolmentcreated(\core\event\user_enrolment_created $event) {
        global $CFG;

        if (\panopto_data::get_panopto_course_id($event->courseid) === false
            || $CFG->version < self::$requiredVersion) 
        {
            return;
        }

        $task = new \block_panopto\task\update_user();
        $task->set_custom_data(array(
            'courseid' => $event->courseid,
            'relateduserid' => $event->relateduserid,
            'contextid' => $event->contextid,
            'eventtype' => "enrol_add"
        ));

        if ($CFG->block_panopto_async_tasks) {
            \core\task\manager::queue_adhoc_task($task);
        } else {
            $task->execute();
        }
    }

    /**
     * Called when an enrolment has been deleted.
     */
    public static function enrolmentdeleted(\core\event\user_enrolment_deleted $event) {
        global $CFG;

        if (\panopto_data::get_panopto_course_id($event->courseid) === false
            || $CFG->version < self::$requiredVersion) {
            return;
        }

        $task = new \block_panopto\task\update_user();
        $task->set_custom_data(array(
            'courseid' => $event->courseid,
            'relateduserid' => $event->relateduserid,
            'contextid' => $event->contextid,
            'eventtype' => "enrol_remove"
        ));

        if ($CFG->block_panopto_async_tasks) {
            \core\task\manager::queue_adhoc_task($task);
        } else {
            $task->execute();
        }
    }

    /**
     * Called when an enrolment has been updated.
     */
    public static function enrolmentupdated(\core\event\user_enrolment_updated $event) {
        global $CFG, $DB;
        
        if (\panopto_data::get_panopto_course_id($event->courseid) === false
            || $CFG->version < self::$requiredVersion) {
            return;
        }
        
        $task = new \block_panopto\task\update_user();

        $context = context_course::instance($event->courseid);
        if(is_enrolled($context, $event->relateduserid, '', true)) { // User is enrolled.  Make sure they are added in Panopto
            $task->set_custom_data(array(
                'courseid' => $event->courseid,
                'relateduserid' => $event->relateduserid,
                'contextid' => $event->contextid,
                'eventtype' => "enrol_add"
            ));
        } else { // User is unenrolled or suspended.  Make sure they are removed from Panopto
            $task->set_custom_data(array(
                'courseid' => $event->courseid,
                'relateduserid' => $event->relateduserid,
                'contextid' => $event->contextid,
                'eventtype' => "enrol_remove"
            ));
        }

        if ($CFG->block_panopto_async_tasks) {
            \core\task\manager::queue_adhoc_task($task);
        } else {
            $task->execute();
        }
    }

    /**
     * Called when an role has been added.
     */
    public static function roleadded(\core\event\role_assigned $event) {
        global $CFG;

        if (\panopto_data::get_panopto_course_id($event->courseid) === false
            || $CFG->version < self::$requiredVersion) {
            return;
        }

        $task = new \block_panopto\task\update_user();
        $task->set_custom_data(array(
            'courseid' => $event->courseid,
            'relateduserid' => $event->relateduserid,
            'contextid' => $event->contextid,
            'eventtype' => "role"
        ));

        if ($CFG->block_panopto_async_tasks) {
            \core\task\manager::queue_adhoc_task($task);
        } else {
            $task->execute();
        }
    }

    /**
     * Called when an role has been removed.
     */
    public static function roledeleted(\core\event\role_unassigned $event) {
        global $CFG;

        if (\panopto_data::get_panopto_course_id($event->courseid) === false
            || $CFG->version < self::$requiredVersion) {
            return;
        }

        $task = new \block_panopto\task\update_user();
        $task->set_custom_data(array(
            'courseid' => $event->courseid,
            'relateduserid' => $event->relateduserid,
            'contextid' => $event->contextid,
            'eventtype' => "role"
        ));

        if ($CFG->block_panopto_async_tasks) {
            \core\task\manager::queue_adhoc_task($task);
        } else {
            $task->execute();
        }
    }

}
