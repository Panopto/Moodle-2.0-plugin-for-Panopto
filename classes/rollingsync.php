<?php

/**
 * Panopto block events processors.
 *
 * @package     block_panopto
 * @copyright   Panopto 2009 - 2013 / With contributions from Spenser Jones (sjones@ambrose.edu)
 * @license     http://www.gnu.org/licenses/lgpl.html GNU LGPL
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__) . '/../../../config.php');
require_once(dirname(__FILE__) . '/../lib/panopto_data.php');

/**
 * Handlers for each different event type.
 */
class block_panopto_rollingsync {

    /**
     * Called when an enrolment has been created.
     */
    public static function enrolmentcreated(\core\event\user_enrolment_created $event) {
        global $CFG;

        if (\panopto_data::get_panopto_course_id($event->courseid) === false) {
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

        if (\panopto_data::get_panopto_course_id($event->courseid) === false) {
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
     * Called when an role has been added.
     */
    public static function roleadded(\core\event\role_assigned $event) {
        global $CFG;

        if (\panopto_data::get_panopto_course_id($event->courseid) === false) {
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

        if (\panopto_data::get_panopto_course_id($event->courseid) === false) {
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
