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
 * Adds rolling sync capability to Panopto
 *
 * @package block_panopto
 * @copyright Panopto 2009 - 2016 /With contributions from Spenser Jones (sjones@ambrose.edu),
 * Skylar Kelty <S.Kelty@kent.ac.uk>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
if (empty($CFG)) {
    require_once(dirname(__FILE__) . '/../../../config.php');
}

require_once(dirname(__FILE__) . '/../lib/panopto_data.php');
require_once(dirname(__FILE__) . '/../lib/panoptoblock_lti_utility.php');
require_once($CFG->libdir . '/pagelib.php');
require_once($CFG->libdir . '/blocklib.php');

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
        global $DB;

        if (get_config('block_panopto', 'auto_insert_lti_link_to_new_courses')) {

            // Get a matching LTI tool for the course.
            $tool = \panoptoblock_lti_utility::get_course_tool($event->courseid);

            if (!empty($tool)) {
                // Default intro should be a folderview.
                $draftideditor = file_get_submitted_draft_itemid('introeditor');
                file_prepare_draft_area($draftideditor, null, null, null, null, array('subdirs' => true));

                $moduleinfo = new stdClass();
                $moduleinfo->modulename = 'lti';
                $moduleinfo->course = $event->courseid;
                $moduleinfo->section = 0;
                $moduleinfo->name = get_string('panopto_course_tool', 'block_panopto');
                $moduleinfo->title = $tool->name;
                $moduleinfo->typeid = $tool->id;
                $moduleinfo->showdescriptionlaunch = false;
                $moduleinfo->showtitlelaunch = false;
                $moduleinfo->launchcontainer = LTI_LAUNCH_CONTAINER_DEFAULT;
                $moduleinfo->visible = true;
                $moduleinfo->intro = '';
                $moduleinfo->icon = 'https://static-contents.panopto.com/prod/panopto_logo_moodle_tool_60x60.png';
                $moduleinfo->introeditor = array('text' => $moduleinfo->intro, 'format' => FORMAT_HTML, 'itemid' => $draftideditor);
                create_module($moduleinfo);
            }
        }

        if (get_config('block_panopto', 'auto_add_block_to_new_courses')) {
            $course = $DB->get_record('course', array('id' => $event->courseid));

            if ($event->courseid == SITEID) {
                $pagetypepattern = 'site-index';
            } else {
                $pagetypepattern = 'course-view-*';
            }

            $page = new moodle_page();
            $page->set_course($course);
            $page->blocks->add_blocks(array(BLOCK_POS_LEFT => array('panopto')), $pagetypepattern);
        }

        if (!\panopto_data::is_main_block_configured() ||
            !\panopto_data::has_minimum_version()) {
            return;
        }

        $allowautoprovision = get_config('block_panopto', 'auto_provision_new_courses');

        if ($allowautoprovision == 'oncoursecreation') {
            $task = new \block_panopto\task\provision_course();
            $task->set_custom_data(array(
                'courseid' => $event->courseid,
                'relateduserid' => $event->relateduserid,
                'contextid' => $event->contextid,
                'eventtype' => 'role'
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

            $panoptodata = new \panopto_data($newcourseid);
            $originalpanoptodata = new \panopto_data($originalcourseid);

            // We should only perform the import if both the target and the source course are provisioned in panopto.
            if (isset($panoptodata->servername) && !empty($panoptodata->servername) &&
                isset($panoptodata->applicationkey) && !empty($panoptodata->applicationkey) &&
                isset($panoptodata->sessiongroupid) && !empty($panoptodata->sessiongroupid) &&
                isset($originalpanoptodata->servername) && !empty($originalpanoptodata->servername) &&
                isset($originalpanoptodata->applicationkey) && !empty($originalpanoptodata->applicationkey) &&
                isset($originalpanoptodata->sessiongroupid) && !empty($originalpanoptodata->sessiongroupid)) {

                $panoptodata->ensure_auth_manager();
                $activepanoptoserverversion = $panoptodata->authmanager->get_server_version();
                $useccv2 = version_compare(
                    $activepanoptoserverversion,
                    \panopto_data::$ccv2requiredpanoptoversion,
                    '>='
                );

                if ($useccv2) {
                    $panoptodata->copy_panopto_content($originalcourseid);
                } else {
                    $panoptodata->init_and_sync_import_ccv1($originalcourseid);
                }
            }
        }
    }

    /**
     * Called when a user has been unenrolled.
     *
     * @param \core\event\user_enrolment_deleted $event
     */
    public static function userenrolmentdeleted(\core\event\user_enrolment_deleted $event) {
        if (!\panopto_data::is_main_block_configured() ||
            !\panopto_data::has_minimum_version()) {
            return;
        }

        $task = new \block_panopto\task\sync_user();
        $task->set_custom_data(array(
            'courseid' => $event->courseid,
            'userid' => $event->relateduserid
        ));

        if (get_config('block_panopto', 'async_tasks')) {
            \core\task\manager::queue_adhoc_task($task);
        } else {
            $task->execute();
        }
    }

    /**
     * Called when a user has been updated.
     *
     * @param \core\event\user_enrolment_updated $event
     */
    public static function userenrolmentupdated(\core\event\user_enrolment_updated $event) {
        if (!\panopto_data::is_main_block_configured() ||
            !\panopto_data::has_minimum_version()) {
            return;
        }

        $task = new \block_panopto\task\sync_user();
        $task->set_custom_data(array(
            'courseid' => $event->courseid,
            'userid' => $event->relateduserid
        ));

        if (get_config('block_panopto', 'async_tasks')) {
            \core\task\manager::queue_adhoc_task($task);
        } else {
            $task->execute();
        }
    }

    /**
     * Called when a user has been enrolled.
     *
     * @param \core\event\role_assigned $event
     */
    public static function roleassigned(core\event\role_assigned $event) {
        if (!\panopto_data::is_main_block_configured() ||
            !\panopto_data::has_minimum_version()) {
            return;
        }

        if (get_config('block_panopto', 'sync_on_enrolment')) {
            $task = new \block_panopto\task\sync_user();
            $task->set_custom_data(array(
                'courseid' => $event->courseid,
                'userid' => $event->relateduserid
            ));

            if (get_config('block_panopto', 'async_tasks')) {
                \core\task\manager::queue_adhoc_task($task);
            } else {
                $task->execute();
            }
        }
    }

    /**
     * Called when a user enrollment has been updated.
     *
     * @param \core\event\role_unassigned $event
     */
    public static function roleunassigned(core\event\role_unassigned $event) {
        if (!\panopto_data::is_main_block_configured() ||
            !\panopto_data::has_minimum_version()) {
            return;
        }

        if (get_config('block_panopto', 'sync_on_enrolment')) {
            $task = new \block_panopto\task\sync_user();
            $task->set_custom_data(array(
                'courseid' => $event->courseid,
                'userid' => $event->relateduserid
            ));

            if (get_config('block_panopto', 'async_tasks')) {
                \core\task\manager::queue_adhoc_task($task);
            } else {
                $task->execute();
            }
        }
    }

    /**
     * Called when a user has logged in
     *
     * @param \core\event\user_loggedin $event
     */
    public static function userloggedin(\core\event\user_loggedin $event) {
        if (!\panopto_data::is_main_block_configured() ||
            !\panopto_data::has_minimum_version()) {
            return;
        }

        if (get_config('block_panopto', 'sync_after_login')) {

            $task = new \block_panopto\task\sync_user_login();
            $task->set_custom_data(array(
                'userid' => $event->userid
            ));

            if (get_config('block_panopto', 'async_tasks')) {
                \core\task\manager::queue_adhoc_task($task);
            } else {
                $task->execute();
            }
        }
    }

    /**
     * Called when a user has logged in as a different user.
     * This will sync the sub user being logged in as, not the admin user performing the action.
     *
     * @param \core\event\user_loggedinas $event
     */
    public static function userloggedinas(\core\event\user_loggedinas $event) {
        if (!\panopto_data::is_main_block_configured() ||
            !\panopto_data::has_minimum_version()) {
            return;
        }

        if (get_config('block_panopto', 'sync_after_login')) {

            $task = new \block_panopto\task\sync_user_login();
            $task->set_custom_data(array(
                'userid' => $event->relateduserid
            ));

            if (get_config('block_panopto', 'async_tasks')) {
                \core\task\manager::queue_adhoc_task($task);
            } else {
                $task->execute();
            }
        }
    }
}
