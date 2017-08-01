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
 * adds rolling sync capability to Panopto
 *
 * @package block_panopto
 * @copyright Panopto 2009 - 2016 /With contributions from Spenser Jones (sjones@ambrose.edu),
 * Skylar Kelty <S.Kelty@kent.ac.uk>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__) . '/../../../config.php');
require_once(dirname(__FILE__) . '/../lib/panopto_data.php');

/**
 * Handlers for each different event type.
 *
 * @copyright Panopto 2009 - 2016
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * Upon course creation: coursecreated is triggered
 * Upon enroll of user: enrollmentcreated AND roleadded is triggered
 * Upon unassigning role: roledeleted is triggered
 * Upon reassigning role: roleadded is triggered
 * Upon setting enrollment status to suspended: enrolmentupdated is triggered
 * Upon setting enrollment status to reactivated: enrolmentupdated is triggered
 * Upon unenroll of user: roledeleted AND enrollmentdeleted is triggered
 */
class block_panopto_rollingsync {

    /**
     * Called when a course has been created.
     *
     * @param \core\event\course_created $event
     */
    public static function coursecreated(\core\event\course_created $event) {
        if (!\panopto_data::is_main_block_configured() ||
            !\panopto_data::has_minimum_version()) {
            return;
        }

        $allowautoprovision = get_config('block_panopto', 'auto_provision_new_courses');

        if ($allowautoprovision) {
            $selectedserver = get_config('block_panopto', 'server_name1');
            $selectedkey = get_config('block_panopto', 'application_key1');

        $task = new \block_panopto\task\provision_course();
            $task->set_custom_data(array(
                'courseid' => $event->courseid,
                'relateduserid' => $event->relateduserid,
                'contextid' => $event->contextid,
                'eventtype' => 'role',
                'servername' => $selectedserver,
                'appkey' => $selectedkey
            ));
            $task->execute();
        }
    }

    /**
     * Called when a course has been deleted.
     *
     * @param \core\event\course_deleted $event
     */
    public static function coursedeleted(\core\event\course_deleted $event) {
        if (!\panopto_data::is_main_block_configured() ||
            !\panopto_data::has_minimum_version()) {
            return;
        }

        \panopto_data::delete_panopto_relation($event->courseid, true);
    }

    /**
     * Called when a course has been restored (imported/backed up).
     *
     * @param \core\event\course_restored $event
     */
    public static function courserestored(\core\event\course_restored $event) {
        if (!\panopto_data::is_main_block_configured() ||
            !\panopto_data::has_minimum_version()) {
            return;
        }

        $originalcourseenabled = $event->other['samesite'] && isset($event->other['originalcourseid']);
        if (get_config('block_panopto', 'auto_sync_imports') && $originalcourseenabled) {
            $newcourseid = intval($event->courseid);
            $originalcourseid = intval($event->other['originalcourseid']);

            $panoptodata = new panopto_data($newcourseid);
            $panoptodata->init_and_sync_import($originalcourseid);
        }
    }
}
