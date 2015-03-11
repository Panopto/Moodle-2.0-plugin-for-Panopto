<?php

/**
 * Panopto block events processors.
 *
 * @package     block_panopto
 * @copyright   Panopto 2009 - 2013 / With contributions from Spenser Jones (sjones@ambrose.edu)
 * @license     http://www.gnu.org/licenses/lgpl.html GNU LGPL
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/blocks/panopto/lib/panopto_data.php');

/**
 * Panopto class for events handlers.
 *
 * Provides tasks scheduling corresponsing to global Moodle events.
 *
 * @package     block_panopto
 * @copyright   Panopto 2009 - 2013 / With contributions from Spenser Jones (sjones@ambrose.edu)
 * @license     http://www.gnu.org/licenses/lgpl.html GNU LGPL
 */
class block_panopto_rollingsync {

    /**
     * Add user. Called when an enrolment has been created.
     *
     * @param \core\event\user_enrolment_created $event
     * @return void
     */
    public static function enrolmentcreated(\core\event\user_enrolment_created $event) {
        $panopto_data_instance = new panopto_data($event->courseid);
        if ($panopto_data_instance->is_provisioned()) {

            $task = new \block_panopto\task\update_user();
            $task->set_custom_data(array(
                'courseid' => $event->courseid,
                'relateduserid' => $event->relateduserid,
                'contextid' => $event->contextid,
                'eventtype' => "enrol_add"
            ));

            if (get_config('block_panopto', 'async_tasks')) {
                \core\task\manager::queue_adhoc_task($task);
            } else {
                $task->execute();
            }
        }
    }

    /**
     * Remove user. Called when an enrolment has been deleted.
     *
     * @param \core\event\user_enrolment_deleted $event
     * @return void
     */
    public static function enrolmentdeleted(\core\event\user_enrolment_deleted $event) {
        $panopto_data_instance = new panopto_data($event->courseid);
        if ($panopto_data_instance->is_provisioned()) {
            $task = new \block_panopto\task\update_user();
            $task->set_custom_data(array(
                'courseid' => $event->courseid,
                'relateduserid' => $event->relateduserid,
                'contextid' => $event->contextid,
                'eventtype' => "enrol_remove"
            ));

            if (get_config('block_panopto', 'async_tasks')) {
                \core\task\manager::queue_adhoc_task($task);
            } else {
                $task->execute();
            }
        }
    }

    /**
     * Add role. Called when an role has been added.
     *
     * @param \core\event\role_assigned $event
     * @return void
     */
    public static function roleadded(\core\event\role_assigned $event) {
        $panopto_data_instance = new panopto_data($event->courseid);
        if ($panopto_data_instance->is_provisioned()) {
            $task = new \block_panopto\task\update_user();
            $task->set_custom_data(array(
                'courseid' => $event->courseid,
                'relateduserid' => $event->relateduserid,
                'contextid' => $event->contextid,
                'eventtype' => "role"
            ));

            if (get_config('block_panopto', 'async_tasks')) {
                \core\task\manager::queue_adhoc_task($task);
            } else {
                $task->execute();
            }
        }
    }

    /**
     * Delete role. Called when an role has been removed.
     *
     * @param \core\event\role_unassigned $event
     * @return void
     */
    public static function roledeleted(\core\event\role_unassigned $event) {
        $panopto_data_instance = new panopto_data($event->courseid);
        if ($panopto_data_instance->is_provisioned()) {
            $task = new \block_panopto\task\update_user();
            $task->set_custom_data(array(
                'courseid' => $event->courseid,
                'relateduserid' => $event->relateduserid,
                'contextid' => $event->contextid,
                'eventtype' => "role"
            ));

            if (get_config('block_panopto', 'async_tasks')) {
                \core\task\manager::queue_adhoc_task($task);
            } else {
                $task->execute();
            }
        }
    }
}
