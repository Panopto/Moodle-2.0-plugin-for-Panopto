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
 * contains main Panopto getters
 *
 * @package block_panopto
 * @copyright  Panopto 2009 - 2018 /With contributions from Spenser Jones (sjones@ambrose.edu), and Tim Lock
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

global $CFG;
if (empty($CFG)) {
    require_once('../../config.php');
}

require_once($CFG->libdir . '/dmllib.php');
require_once('block_panopto_lib.php');
require_once('panopto_auth_soap_client.php');
require_once('panopto_user_soap_client.php');
require_once('panopto_session_soap_client.php');

/**
 * Panopto data object. Contains info required for provisioning a course with Panopto.
 *
 * @package block_panopto
 * @copyright  Panopto 2009 - 2015
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class panopto_data {

    /**
     * @var string $instancename course id class is being provisioned for
     */
    public $instancename;

    /**
     * @var int $moodlecourseid current active Moodle course id
     */
    public $moodlecourseid;

    /**
     * @var string $servername
     */
    public $servername;

    /**
     * @var int $applicationkey
     */
    public $applicationkey;

    /**
     * @var object $soapclient instance of the soap client
     */
    public $soapclient;

    /**
     * @var object $sessionmanager instance of the session soap client
     */
    public $sessionmanager;

    /**
     * @var object $usermanager instance of the user soap client
     */
    public $usermanager;

    /**
     * @var object $authmanager instance of the auth soap client
     */
    public $authmanager;

    /**
     * @var int $sessiongroupid is for the current session
     */
    public $sessiongroupid;

    /**
     * @var string $uname username
     */
    public $uname;

    /**
     * @var int PAGE_SIZE size of the pages
     */
    const PAGE_SIZE = 50;

    /**
     * @var int $requireversion Panopto only supports versions of Moodle newer than v2.7(2014051200).
     */
    private static $requiredversion = 2014051200;

    /**
     * @var string $requiredpanoptoversion Any block_panopto newer than 2017061000 will require a Panopto server to be at least this version to succeed.
     */
    public static $requiredpanoptoversion = '5.4.0';

    /**
     * main constructor
     *
     * @param int $moodlecourseid course id class is being provisioned for. Can be null for bulk provisioning and manual provisioning.
     */
    public function __construct($moodlecourseid) {
        global $USER;

        // Fetch global settings from DB.
        $this->instancename = get_config('block_panopto', 'instance_name');

        // Get servername and application key specific to Moodle course if ID is specified.
        if (isset($moodlecourseid) && !empty($moodlecourseid)) {
            $this->servername = self::get_panopto_servername($moodlecourseid);
            $this->applicationkey = self::get_panopto_app_key($moodlecourseid);

            $this->moodlecourseid = $moodlecourseid;
            $this->sessiongroupid = self::get_panopto_course_id($moodlecourseid);
        }

        if (isset($USER->username)) {
            $username = $USER->username;
        } else {
            $username = 'guest';
        }
        $this->uname = $username;
    }

    /**
     * Returns SystemInfo.
     */
    public function get_recorder_download_urls() {

        $this->ensure_session_manager();

        return $this->sessionmanager->get_recorder_download_urls();
    }

    /**
     * Returns if the logged in user can provision.
     *
     * @param int $courseid the Moodle id of the course we are checking
     */
    public function can_user_provision($courseid) {
        global $USER;

        // Get the context of the course so we can get capaibilities.
        $context = context_course::instance($courseid, MUST_EXIST);

        return has_capability('block/panopto:provision_aspublisher', $context, $USER->id) ||
            has_capability('block/panopto:provision_asteacher', $context, $USER->id) ||
            has_capability('moodle/course:update', $context, $USER->id);
    }

    /**
     * Return the correct role for a user, given a context.
     *
     * @param int $contextid
     * @param int $userid
     */
    public static function get_role_from_context($context, $userid) {
        $role = 'Viewer';

        $canprovisionaspublisher = has_capability('block/panopto:provision_aspublisher', $context, $userid);
        $canprovisionasteacher = has_capability('block/panopto:provision_asteacher', $context, $userid);

        if ($canprovisionaspublisher) {
            if ($canprovisionasteacher) {
                $role = 'Creator/Publisher';
            } else {
                $role = 'Publisher';
            }
        } else if ($canprovisionasteacher) {
            $role = 'Creator';
        }

        return $role;
    }

    /**
     * Return the session manager, if it does not yet exist try to create it.
     */
    public function ensure_session_manager() {
        // If no session soap client exists instantiate one.
        if (!isset($this->sessionmanager)) {
            $this->sessionmanager = self::instantiate_session_soap_client(
                $this->uname,
                $this->servername,
                $this->applicationkey
            );

            if (!isset($this->sessionmanager)) {
                self::print_log(get_string('api_manager_unavailable', 'block_panopto', 'session'));
            }
        }
    }

    /**
     * Return the auth manager, if it does not yet exist try to create it.
     */
    public function ensure_auth_manager() {
        // If no session soap client exists instantiate one.
        if (!isset($this->authmanager)) {
            // If no auth soap client for this instance, instantiate one.
            $this->authmanager = self::instantiate_auth_soap_client(
                $this->uname,
                $this->servername,
                $this->applicationkey
            );

            if (!isset($this->authmanager)) {
                self::print_log(get_string('api_manager_unavailable', 'block_panopto', 'auth'));
            }
        }
    }

    /**
     * Return the user manager, if it does not yet exist try to create it.
     *
     * @param $usertomanage since the User management works on the user passed in through the auth param we need to pass the uname for the user we are managing.
     */
    public function ensure_user_manager($usertomanage) {
        // If no session soap client exists instantiate one.
        if (!isset($this->usermanager) || ($this->usermanager->authparam->UserKey !== $this->panopto_decorate_username($usertomanage))) {

            // If no auth soap client for this instance, instantiate one.
            $this->usermanager = self::instantiate_user_soap_client(
                $usertomanage,
                $this->servername,
                $this->applicationkey
            );

            if (!isset($this->usermanager)) {
                self::print_log(get_string('api_manager_unavailable', 'block_panopto', 'user'));
            }
        }
    }

    /**
     * Create the Panopto course folder and populate its ACLs.
     *
     * @param object $provisioninginfo info for course being provisioned
     */
    public function provision_course($provisioninginfo, $skipusersync) {
        global $CFG, $USER, $DB;

        self::print_log_verbose(get_string('attempt_provision_course', 'block_panopto', $provisioninginfo->externalcourseid));

        if (isset($provisioninginfo->fullname) && !empty($provisioninginfo->fullname) &&
            isset($provisioninginfo->externalcourseid) && !empty($provisioninginfo->externalcourseid)) {

            $this->ensure_session_manager();

            if (isset($this->sessiongroupid) && !empty($this->sessiongroupid) && ($this->sessiongroupid !== false)) {

                self::print_log_verbose(get_string('course_already_provisioned', 'block_panopto', $this->sessiongroupid));

                $courseinfo = $this->sessionmanager->set_external_course_access_for_roles(
                    $provisioninginfo->fullname,
                    $provisioninginfo->externalcourseid,
                    $this->sessiongroupid
                );
            } else {
                $courseinfo = $this->sessionmanager->provision_external_course_with_roles(
                    $provisioninginfo->fullname,
                    $provisioninginfo->externalcourseid
                );
            }

            if (isset($courseinfo) && isset($courseinfo->Id)) {

                self::print_log_verbose(get_string('provision_successful', 'block_panopto', $this->sessiongroupid));

                // Store the Panopto folder Id in the foldermap table so we know it exists.
                self::set_course_foldermap(
                    $this->moodlecourseid,
                    $courseinfo->Id,
                    $this->servername,
                    $this->applicationkey,
                    $provisioninginfo->externalcourseid
                );

                $this->ensure_auth_manager();

                $currentblockversion = $DB->get_record(
                    'config_plugins',
                    array('plugin' => 'block_panopto', 'name' => 'version'),
                    'value'
                );

                // If we succeeded in provisioning lets send the Panopto server some updated integration information.
                $this->authmanager->report_integration_info(
                    get_config('block_panopto', 'instance_name'),
                    $currentblockversion->value,
                    $CFG->version
                );

                if (!$skipusersync) {
                    // syncs every user enrolled in the course, this is fairly expensive so it should be normally turned off.
                    if (get_config('block_panopto', 'sync_after_provisioning')) {
                        $coursecontext = context_course::instance($this->moodlecourseid);
                        $enrolledusers = get_enrolled_users($coursecontext);

                        // sync every user enrolled in the course
                        foreach ($enrolledusers as $enrolleduser) {
                            $this->sync_external_user($enrolleduser->id);
                        }
                    } else if ($this->uname !== 'guest') {
                        // uname will be guest is provisioning/upgrading through cli, no need to sync this 'user'.
                        // This is intended to make sure provisioning teachers get access without relogging, so we only need to perform this if we aren't syncing all enrolled users.

                        // Update permissions so user can see everything they should.
                        $this->sync_external_user($USER->id);
                    }
                }
            } else {
                // Give the user some basic info they can use to debug or send to AE.
                $courseinfo = new stdClass;
                $courseinfo->moodlecourseid = $this->moodlecourseid;
                $courseinfo->servername = $this->servername;
                $courseinfo->applicationkey = $this->applicationkey;
                $courseinfo->missingrequiredversion = true;
                $courseinfo->requiredpanoptoversion = self::$requiredpanoptoversion;
            }
        } else {
            // Give the user some basic info they can use to debug or send to AE.
            $courseinfo = new stdClass;
            $courseinfo->moodlecourseid = $this->moodlecourseid;
            $courseinfo->servername = $this->servername;

            if (isset($provisioninginfo->accesserror) && $provisioninginfo->accesserror === true) {
                $courseinfo->accesserror = true;
            } else {
                self::print_log(get_string('unknown_provisioning_error', 'block_panopto'));
                $courseinfo->unknownerror = true;
            }
        }

        return $courseinfo;
    }

    /**
     *  Fetch course name and membership info from DB in preparation for provisioning operation.
     *
     */
    public function get_provisioning_info() {
        global $DB;

        self::print_log_verbose(get_string('get_provisioning_info', 'block_panopto', $this->moodlecourseid));

        $this->check_course_role_mappings();

        $provisioninginfo = new stdClass;

        // If we are provisioning a course with a panopto_id set we should provision that folder.
        // We need to keep this because users can map to folders that weren't created in Moodle so Moodle has no knowledge of the externalcourseid.
        $hasvalidpanoptoid = isset($this->sessiongroupid) && !empty($this->sessiongroupid);

        if ($hasvalidpanoptoid) {
            $mappedpanoptocourse = $this->get_folders_by_id_no_sync();
        }

        // The get_folders_by_id api wrapper returns -1 if the api returns a folder not found error.
        $foundmappedfolder = isset($mappedpanoptocourse) && ($mappedpanoptocourse !== -1);

        // The get_folders_by_id api wrapper returns false if the api call got a user has no access error.
        $userhasaccesstofolder = $foundmappedfolder && ($mappedpanoptocourse !== false);

        if ($userhasaccesstofolder) {
            $provisioninginfo->sessiongroupid = $this->sessiongroupid;
            $provisioninginfo->fullname = $mappedpanoptocourse->Name;
        } else if ($foundmappedfolder && !$userhasaccesstofolder) {
            // API call returned false, course exists but the user does not have access to the folder.
            self::print_log(get_string('provision_access_error', 'block_panopto'));
            $provisioninginfo->accesserror = true;
            return $provisioninginfo;
        } else {
            if ($hasvalidpanoptoid && !$foundmappedfolder) {
                // If we had a sessiongroupid set from a previous folder, but that folder was not found on Panopto.
                // Set the current sessiongroupid to null to allow for a fresh provisioning/folder.
                // Provisioning will fail if this is not done, the wrong API endpoint will be called.
                self::print_log(get_string('folder_not_found_error', 'block_panopto'));
                $this->sessiongroupid = null;
                $provisioninginfo->couldnotfindmappedfolder = true;
            }

            $provisioninginfo->shortname = $DB->get_field(
                'course',
                'shortname',
                array('id' => $this->moodlecourseid)
            );

            $provisioninginfo->longname = $DB->get_field(
                'course',
                'fullname',
                array('id' => $this->moodlecourseid)
            );

            if (!isset($provisioninginfo->shortname) || empty($provisioninginfo->shortname)) {
                $provisioninginfo->shortname = substr($provisioninginfo->longname, 0, 5);
            }

            $provisioninginfo->fullname = '';
            if (get_config('block_panopto', 'prefix_new_folder_names')) {
                $provisioninginfo->fullname .= $provisioninginfo->shortname . ': ';
            }
            $provisioninginfo->fullname .= $provisioninginfo->longname;
        }

        // Always set this, even in the case of an already existing folder we will overwrite the old Id with this one.
        $provisioninginfo->externalcourseid = $this->instancename . ':' . $this->moodlecourseid;

        return $provisioninginfo;
    }

    /**
     * Initializes and syncs a possible new import
     *
     * @param int $newimportid the id of the course being imported
     *
     */
    public function init_and_sync_import($newimportid) {

        self::print_log_verbose(get_string('init_import_target', 'block_panopto', $this->moodlecourseid));
        self::print_log_verbose(get_string('init_import_source', 'block_panopto', $newimportid));

        $importinfo = null;
        $currentimportsources = self::get_import_list($this->moodlecourseid);

        $this->ensure_session_manager();
        $importinarray = in_array($newimportid, $currentimportsources);

        if (!$importinarray) {
            // If a course is already listed as an import we don't need to add it to the import array, but we can still resync the groups.
            self::add_new_course_import($this->moodlecourseid, $newimportid);
        }

        $importpanopto = new panopto_data($newimportid);
        $provisioninginfo = $this->get_provisioning_info();

        if (!isset($importpanopto->sessiongroupid)) {
            self::print_log(get_string('import_not_mapped', 'block_panopto'));
        } else if (!isset($provisioninginfo->accesserror)) {
            // Only do this code if we have proper access to the target Panopto course folder.
            $importresult = $this->sessionmanager->set_copied_external_course_access_for_roles(
                $provisioninginfo->fullname,
                $provisioninginfo->externalcourseid,
                $importpanopto->sessiongroupid
            );

            if (isset($importresult)) {
                $importinfo = $importresult;
            } else {
                self::print_log(get_string('missing_required_version', 'block_panopto'));
                return false;
            }
        }

        return $importinfo;
    }

    /**
     * Attempts to get a folder by it's external id
     *
     */
    public function get_folders_by_external_id() {
        global $USER;
        $ret = false;

        // Update permissions so user can see everything they should.
        $this->sync_external_user($USER->id);

        if (isset($this->sessiongroupid)) {
            $this->ensure_session_manager();

            $provisioninginfo = $this->get_provisioning_info();
            $ret = $this->sessionmanager->get_folders_by_external_id($provisioninginfo->externalcourseid);
        }

        return $ret;
    }

    /**
     * Attempts to get a folder by it's public Guid
     *
     */
    public function get_folders_by_id() {
        global $USER;

        $ret = false;

        // Update permissions so user can see everything they should.
        $this->sync_external_user($USER->id);

        return $this->get_folders_by_id_no_sync();
    }

    /**
     * Attempts to get a folder by it's public Guid without syncing it to Panopto.
     *
     */
    public function get_folders_by_id_no_sync() {
        $ret = false;

        if (isset($this->sessiongroupid)) {
            $this->ensure_session_manager();

            $ret = $this->sessionmanager->get_folders_by_id($this->sessiongroupid);

        } else {
            // In this case the course is not mapped and the folder does not exist.
            // I think -1 is fitting here. This case is handled differenty than false in our upgrade script.
            $ret = null;
        }

        return $ret;
    }

    /**
     * Attempts to get all folders the user has access to.
     *
     */
    public function get_folders_list() {
        global $USER;
        $ret = false;


        // Update permissions so user can see everything they should.
        $this->sync_external_user($USER->id);

        if (isset($this->sessiongroupid)) {
            $this->ensure_session_manager();

            $ret = $this->sessionmanager->get_folders_list();
        }

        return $ret;
    }

    /**
     * Sync a user with all of the courses he is enrolled in on the current Panopto server
     *
     */
    public function sync_external_user($userid) {
        global $DB, $CFG;

        self::print_log_verbose(get_string('attempt_sync_user', 'block_panopto', $userid));
        self::print_log_verbose(get_string('attempt_sync_user_server', 'block_panopto', $this->servername));

        $userinfo = $DB->get_record('user', array('id' => $userid));

        // Only sync if we find an existing user with the given id.
        if (isset($userinfo) && ($userinfo !== false)) {
            $instancename = get_config('block_panopto', 'instance_name');

            $currentcourses = enrol_get_users_courses($userid, true);

            // Go through each course.
            $groupstosync = array();
            foreach ($currentcourses as $course) {
                $coursecontext = context_course::instance($course->id);

                $coursepanopto = new panopto_data($course->id);

                // Check to see if we are already going to provision a specific Panopto server, if we are just add the groups to the already made array
                // If not add the server to the list of servers.
                if (isset($coursepanopto->servername) && !empty($coursepanopto->servername) && $coursepanopto->servername === $this->servername &&
                    isset($coursepanopto->applicationkey) && !empty($coursepanopto->applicationkey) &&
                    isset($coursepanopto->sessiongroupid) && !empty($coursepanopto->sessiongroupid)) {

                    $role = self::get_role_from_context($coursecontext, $userid);

                    // Build a list of ExternalGroupIds using a specific format.
                    // E.g. moodle31:courseId_viewers/moodle31:courseId_creators.
                    $groupname = $coursepanopto->instancename . ':' . $course->id;
                    if (strpos($role, 'Viewer') !== false) {
                        $groupstosync[] = $groupname . "_viewers";
                    }

                    if (strpos($role, 'Creator') !== false) {
                        $groupstosync[] = $groupname . "_creators";
                    }

                    if (strpos($role, 'Publisher') !== false) {
                        $groupstosync[] = $groupname . "_publishers";
                    }
                }
            }


            // Only try to sync the users if he Panopto server is up.
            if (self::is_server_alive('https://' . $this->servername . '/Panopto')) {

                $this->ensure_user_manager($userinfo->username);

                $this->usermanager->sync_external_user(
                    $userinfo->firstname,
                    $userinfo->lastname,
                    $userinfo->email,
                    $groupstosync
                );
            } else {
                self::print_log(get_string('panopto_server_error', 'block_panopto', $this->servername));
            }
        }

        return;
    }

    /**
     * Create the provisioning information needed to create permissions on Panopto for the new course
     *
     * @param int $courseid the id of the course being updated
     * @param int $newimportid courseid that the target course imports from
     */
    public static function add_new_course_import($courseid, $newimportid) {
        global $DB;
        $rowarray = array('target_moodle_id' => $courseid, 'import_moodle_id' => $newimportid);

        $currentrow = $DB->get_record('block_panopto_importmap', $rowarray);
        if (!$currentrow) {
            $row = (object) $rowarray;
            return $DB->insert_record('block_panopto_importmap', $row);
        }

        return;
    }

    /**
     * Get the courseid's of the courses being imported to this course
     *
     * @param int $courseid
     */
    public static function get_import_list($courseid) {
        global $DB;

        $courseimports = $DB->get_records(
            'block_panopto_importmap',
            array('target_moodle_id' => $courseid),
            null,
            'import_moodle_id'
        );

        $retarray = array();
        if (isset($courseimports) && !empty($courseimports)) {
            foreach ($courseimports as $courseimport) {
                $retarray[] = $courseimport->import_moodle_id;
            }
        }

        return $retarray;
    }

    /**
     * Get the courseid's of the courses importing the given course
     *
     * @param int $courseid
     */
    public static function get_import_target_list($courseid) {
        global $DB;

        $courseimports = $DB->get_records(
            'block_panopto_importmap',
            array('import_moodle_id' => $courseid),
            null,
            'target_moodle_id'
        );

        $retarray = array();
        if (isset($courseimports) && !empty($courseimports)) {
            foreach ($courseimports as $courseimport) {
                $retarray[] = $courseimport->target_moodle_id;
            }
        }

        return $retarray;
    }

    /**
     * Get ongoing Panopto sessions for the currently mapped course.
     */
    public function get_session_list() {
        $sessionlist = array();
        if ($this->servername && $this->applicationkey && $this->sessiongroupid) {
            $this->ensure_session_manager();
        }

        $sessionlist = $this->sessionmanager->get_session_list($this->sessiongroupid);

        return $sessionlist;
    }

    /**
     * Instance method caches Moodle instance name from DB (vs. block_panopto_lib version).
     *
     * @param string $moodleusername name of the Moodle user
     */
    public function panopto_decorate_username($moodleusername) {
        return ($this->instancename . "\\" . $moodleusername);
    }

    /**
     * Lets us know if we have a value inside the config for a Panopto server, we don't want any of our events to fire on an unconfigured block.
     *
     */
    public static function is_main_block_configured() {

        $numservers = get_config('block_panopto', 'server_number');
        $numservers = isset($numservers) ? $numservers : 0;

        // Increment numservers by 1 to take into account starting at 0.
        ++$numservers;

        $isconfigured = false;
        if ($numservers > 0) {
            for ($serverwalker = 1; $serverwalker <= $numservers; ++$serverwalker) {
                $possibleserver = get_config('block_panopto', 'server_name' . $serverwalker);
                $possibleappkey = get_config('block_panopto', 'application_key' . $serverwalker);

                if (isset($possibleserver) && !empty($possibleserver) &&
                    isset($possibleappkey) && !empty($possibleappkey)) {
                    $isconfigured = true;
                    break;
                }
            }
        }

        return $isconfigured;
    }

    /**
     * Lets us know is we are using at least the minumum required version for the Panopto block
     *
     */
    public static function has_minimum_version() {
        global $CFG;

        $hasminversion = true;
        $versionobject = new stdClass;
        $versionobject->requiredversion = self::$requiredversion;
        $versionobject->currentversion = $CFG->version;

        if ($CFG->version < self::$requiredversion) {
            $hasminversion = false;
            self::print_log(get_string('missing_moodle_required_version', 'block_panopto', $versionobject));
        }

        return $hasminversion;
    }

    /**
     * We need to retrieve the current course mapping in the constructor, so this must be static.
     *
     * @param int $sessiongroupid id of the Panopto folder we are trying to get the Moodle courses associated with.
     */
    public static function get_moodle_course_id($sessiongroupid) {
        global $DB;
        return $DB->get_records(
            'block_panopto_foldermap',
            array('panopto_id' => $sessiongroupid),
            null,
            'moodleid'
        );
    }

    /**
     * We need to retrieve the current course mapping in the constructor, so this must be static.
     *
     * @param int $moodlecourseid id of the current Moodle course
     */
    public static function get_panopto_course_id($moodlecourseid) {
        global $DB;
        return $DB->get_field('block_panopto_foldermap', 'panopto_id', array('moodleid' => $moodlecourseid));
    }

    /**
     *  Retrieve the servername for the current course
     *
     * @param int $moodlecourseid id of the current Moodle course
     */
    public static function get_panopto_servername($moodlecourseid) {
        global $DB;
        return $DB->get_field('block_panopto_foldermap', 'panopto_server', array('moodleid' => $moodlecourseid));
    }

    /**
     *  Retrieve the app key for the current course
     *
     * @param int $moodlecourseid id of the current Moodle course
     */
    public static function get_panopto_app_key($moodlecourseid) {
        global $DB;
        return $DB->get_field('block_panopto_foldermap', 'panopto_app_key', array('moodleid' => $moodlecourseid));
    }

    /**
     *  Checks for course role mappings with Panopto. If none exist then set to the defaults.
     *
     */
    public function check_course_role_mappings() {
        // If old role mappings exists, do not remap. Otherwise, set role mappings to defaults.
        $mappings = self::get_course_role_mappings($this->moodlecourseid);
        if (empty($mappings['creator']) && empty($mappings['publisher'])) {

            // These settings are returned as a comma seperated string of role Id's.
            $defaultpublishermapping = explode("," , get_config('block_panopto', 'publisher_role_mapping'));
            $defaultcreatormapping = explode("," , get_config('block_panopto', 'creator_role_mapping'));

            // Set the role mappings for the course to the defaults.
            self::set_course_role_mappings(
                $this->moodlecourseid,
                $defaultpublishermapping,
                $defaultcreatormapping
            );

            // Grant course users the proper Panopto permissions based on the default role mappings.
            // This will make the role mappings be recognized when provisioning.
            self::set_course_role_permissions(
                $this->moodlecourseid,
                $defaultpublishermapping,
                $defaultcreatormapping
            );
        }
    }

    /**
     * Get the current role mappings set for the current course from the db.
     *
     * @param int $moodlecourseid id of the current Moodle course
     */
    public static function get_course_role_mappings($moodlecourseid) {
        global $DB;

        $pubroles = array();
        $creatorroles = array();

         // Get creator roles as an array.
        $creatorrolesraw = $DB->get_records(
            'block_panopto_creatormap',
            array('moodle_id' => $moodlecourseid),
            'role_id'
        );

        if (isset($creatorrolesraw) && !empty($creatorrolesraw)) {
            foreach ($creatorrolesraw as $creatorrole) {
                $creatorroles[] = $creatorrole->role_id;
            }
        }

         // Get publisher roles as an array.
        $pubrolesraw = $DB->get_records(
            'block_panopto_publishermap',
            array('moodle_id' => $moodlecourseid),
            'role_id'
        );

        if (isset($pubrolesraw) && !empty($pubrolesraw)) {
            foreach ($pubrolesraw as $pubrole) {
                $pubroles[] = $pubrole->role_id;
            }
        }

        return array('publisher' => $pubroles, 'creator' => $creatorroles);
    }

    /**
     *  Set the Panopto ID in the db for the current course
     *  Called by Moodle block instance config save method, so must be static.
     *
     * @param int $moodlecourseid id of the current Moodle course.
     * @param int $sessiongroupid the id of the current session group.
     * @param int $servername name of the server the sessiongroup is located on.
     * @param int $appkey the appkey needed to access the Identity provider on the server.
     */
    public static function set_course_foldermap($moodlecourseid, $sessiongroupid, $servername, $appkey, $externalcourseid) {
        global $DB;
        $row = (object) array(
            'moodleid' => $moodlecourseid,
            'panopto_id' => $sessiongroupid,
            'panopto_server' => $servername,
            'panopto_app_key' => $appkey
        );

        $oldrecord = $DB->get_record('block_panopto_foldermap', array('moodleid' => $moodlecourseid));

        if ($oldrecord) {
            $row->id = $oldrecord->id;
            return $DB->update_record('block_panopto_foldermap', $row);
        } else {
            return $DB->insert_record('block_panopto_foldermap', $row);
        }
    }

    /**
     *  Set the Panopto ID in the db for the current course
     *  Called by Moodle block instance config save method, so must be static.
     *
     * @param int $moodlecourseid id of the current Moodle course
     * @param int $sessiongroupid the id of the current session group
     */
    public static function set_panopto_course_id($moodlecourseid, $sessiongroupid) {
        global $DB;
        if ($DB->get_records('block_panopto_foldermap', array('moodleid' => $moodlecourseid))) {
            return $DB->set_field(
                'block_panopto_foldermap',
                'panopto_id',
                $sessiongroupid,
                array('moodleid' => $moodlecourseid)
            );
        } else {
            $row = (object) array('moodleid' => $moodlecourseid, 'panopto_id' => $sessiongroupid);
            return $DB->insert_record('block_panopto_foldermap', $row);
        }
    }

    /**
     * Set the Panopto server name in the db for the current course
     *
     * @param int $moodlecourseid id of the current Moodle course
     * @param string $panoptoservername the name of the Panopto server
     */
    public static function set_panopto_server_name($moodlecourseid, $panoptoservername) {
        global $DB;
        if ($DB->get_records('block_panopto_foldermap', array('moodleid' => $moodlecourseid))) {
            return $DB->set_field(
                'block_panopto_foldermap',
                'panopto_server',
                $panoptoservername,
                array('moodleid' => $moodlecourseid)
            );
        } else {
            $row = (object) array('moodleid' => $moodlecourseid, 'panopto_server' => $panoptoservername);
            return $DB->insert_record('block_panopto_foldermap', $row);
        }
    }

    /**
     * Set the Panopto app key associated with the current course on the db
     *
     * @param int $moodlecourseid id of the current Moodle course
     * @param string $panoptoappkey
     */
    public static function set_panopto_app_key($moodlecourseid, $panoptoappkey) {
        global $DB;
        if ($DB->get_records('block_panopto_foldermap', array('moodleid' => $moodlecourseid))) {
            return $DB->set_field(
                'block_panopto_foldermap',
                'panopto_app_key',
                $panoptoappkey,
                array('moodleid' => $moodlecourseid)
            );
        } else {
            $row = (object) array('moodleid' => $moodlecourseid, 'panopto_app_key' => $panoptoappkey);
            return $DB->insert_record('block_panopto_foldermap', $row);
        }
    }

    /**
     * Set the selected Panopto role mappings for the current course on the db
     *
     * @param int $moodlecourseid id of the current Moodle course
     * @param array $publisherroles a list of publisher roles
     * @param array $creatorroles a list of creator roles
     */
    public static function set_course_role_mappings($moodlecourseid, $publisherroles, $creatorroles) {
        global $DB;

        // Delete all old records to prevent non-existant mapping staying when they shouldn't.
        $DB->delete_records('block_panopto_publishermap', array('moodle_id' => $moodlecourseid));

        foreach ($publisherroles as $pubrole) {
            $row = (object) array('moodle_id' => $moodlecourseid, 'role_id' => $pubrole);
            $DB->insert_record('block_panopto_publishermap', $row);
        }

        // Delete all old records to prevent non-existant mapping staying when they shouldn't.
        $DB->delete_records('block_panopto_creatormap', array('moodle_id' => $moodlecourseid));

        foreach ($creatorroles as $creatorrole) {
            $row = (object) array('moodle_id' => $moodlecourseid, 'role_id' => $creatorrole);
            $DB->insert_record('block_panopto_creatormap', $row);
        }
    }

    /**
     * Delete the Panopto foldermap row, called when a course is deleted.
     * This function is unused but kept in case we decide to reintroduce the cleaning of table rows.
     *
     * @param int $moodlecourseid id of the target Moodle course
     */
    public static function delete_panopto_relation($moodlecourseid, $movetoinactivetable) {
        global $DB;
        $deletedrecords = array();
        $existingrecords = $DB->get_records('block_panopto_foldermap', array('moodleid' => $moodlecourseid));
        if ($existingrecords) {
            if ($movetoinactivetable) {
                $DB->insert_records('block_panopto_old_foldermap', $existingrecords);
            }

            $deletedrecords['foldermap'] = $DB->delete_records(
                'block_panopto_foldermap',
                array('moodleid' => $moodlecourseid)
            );
        }

        // Clean up any creator role mappings.
        if ($DB->get_records('block_panopto_creatormap', array('moodle_id' => $moodlecourseid))) {
            $DB->delete_records(
                'block_panopto_creatormap',
                array('moodle_id' => $moodlecourseid)
            );
        }

        // Clean up any publisher role mappings.
        if ($DB->get_records('block_panopto_publishermap', array('moodle_id' => $moodlecourseid))) {
            $DB->delete_records(
                'block_panopto_publishermap',
                array('moodle_id' => $moodlecourseid)
            );
        }

        if ($DB->get_records('block_panopto_importmap', array('target_moodle_id' => $moodlecourseid))) {
            $deletedrecords['imports'] = $DB->delete_records(
                'block_panopto_importmap',
                array('target_moodle_id' => $moodlecourseid)
            );
        }

        if ($DB->get_records('block_panopto_importmap', array('import_moodle_id' => $moodlecourseid))) {
            $deletedrecords['exports'] = $DB->delete_records(
                'block_panopto_importmap',
                array('import_moodle_id' => $moodlecourseid)
            );
        }

        return $deletedrecords;
    }

    /**
     * Get list of available courses from db based on user's access level on course.
     */
    public function get_course_options() {

        $panoptocourses = $this->get_folders_list();
        if (!empty($panoptocourses)) {
            $options = array();
            foreach ($panoptocourses as $courseinfo) {
                $options[$courseinfo->Id] = $courseinfo->Name;
            }
        } else if (isset($panoptocourses)) {
            $options = array('Error' => array('-- No Courses Available --'));
        } else {
            $options = array('Error' => array('!! Unable to retrieve course list !!'));
        }

        return array('courses' => $options, 'selected' => $this->sessiongroupid);
    }

    /**
     * Used to instantiate a user soap client for a given instance of panopto_data.
     * Should be called only the first time a soap client is needed for an instance.
     *
     * @param string $username the name of the current user
     * @param string $servername the name of the active server
     * @param string $applicationkey the key need for the user to be authenticated
     */
    public static function instantiate_user_soap_client($username, $servername, $applicationkey) {
        // Compute web service credentials for given user.
        $apiuseruserkey = panopto_decorate_username($username);
        $apiuserauthcode = panopto_generate_auth_code($apiuseruserkey . '@' . $servername, $applicationkey);

        // Instantiate our SOAP client.
        return new panopto_user_soap_client($servername, $apiuseruserkey, $apiuserauthcode);
    }

    /**
     * Used to instantiate a session soap client for a given instance of panopto_data.
     *
     * @param string $username the name of the current user
     * @param string $servername the name of the active server
     * @param string $applicationkey the key need for the user to be authenticated
     */
    public static function instantiate_session_soap_client($username, $servername, $applicationkey) {
        // Compute web service credentials for given user.
        $apiuseruserkey = panopto_decorate_username($username);
        $apiuserauthcode = panopto_generate_auth_code($apiuseruserkey . '@' . $servername, $applicationkey);

        // Instantiate our SOAP client.
        return new panopto_session_soap_client($servername, $apiuseruserkey, $apiuserauthcode);
    }

    /**
     * Used to instantiate a soap client for calling Panopto's iAuth service.
     * Should be called only the first time an  auth soap client is needed for an instance.
     */
    public static function instantiate_auth_soap_client($username, $servername, $applicationkey) {
        // Compute web service credentials for given user.
        $apiuseruserkey = panopto_decorate_username($username);
        $apiuserauthcode = panopto_generate_auth_code($apiuseruserkey . '@' . $servername, $applicationkey);

        // Instantiate our SOAP client.
        return new panopto_auth_soap_client($servername, $apiuseruserkey, $apiuserauthcode);
    }

    /**
     * Build a list of capabilities to be assigned for a specified roles given a context.
     *
     * @param array $roles an array of roles to be given the capability
     * @param string $capability The capability being given to the roles
     */
    public static function build_capability_to_roles($roles, $capability) {
        $assigncaps = array();
        foreach ($roles as $role) {
            if (isset($role) && trim($role) !== '') {
                $assigncaps[$role] = $capability;
            }
        }
        return $assigncaps;
    }

    /**
     * Gives selected capabilities to specified roles given a context, verify that there are capabilities
     * to be added or remove insteaad of rebuilding every page load.
     *
     * @param int $context the context of the roles being given the capability
     * @param array $roles an array of roles to be given the capability
     * @param string $capability The capability being given to the roles
     */
    public static function build_and_assign_context_capability_to_roles($context, $roles, $capability) {
        global $DB;

        $processed = false;
        $assigned = self::build_capability_to_roles($roles, $capability);
        $existing = array();

        // Extract the existing capabilities that have been assigned for context, role and capability.
        foreach ($roles as $key => $roleid) {
            if ($roleid & $DB->record_exists('role_capabilities', array('contextid'=>$context->id, 'roleid'=>$roleid, 'capability'=>$capability))) {
                $existing[$roleid] = $capability;
            }
        }

        // Remove existing capabilities that are no longer needed.
        $assignnew = array_diff($existing, $assigned);
        if (!empty($assignnew)) {
            foreach ($assignnew as $roleid => $cap) {
                unassign_capability($capability, $roleid, $context);
                $processed = true;
            }
        }

        // Add new capabilities that don't exist yet.
        $existingnew = array_diff($assigned, $existing);
        if (!empty($existingnew)) {
            foreach ($existingnew as $roleid => $cap) {
                if (isset($roleid) && trim($roleid) !== '') {
                    assign_capability(
                        $capability,
                        CAP_ALLOW,
                        $roleid,
                        $context,
                        $overwrite = false
                    );
                }
                $processed = true;
            }
        }

        return $processed;
    }

    /**
     * Gives selected capabilities to specified roles.
     *
     * @param int $courseid the id of the course being focused for this operation
     * @param array $publisherroles an array of roles to be made publishers
     * @param array $creatorroles an array of roles to be made creators for the course
     */
    public static function set_course_role_permissions($courseid, $publisherroles, $creatorroles) {
        $coursecontext = context_course::instance($courseid);

        // Get the current role mappings set for the current course from the db.
        $mappings = self::get_course_role_mappings($courseid);

        // Build and process new/old changes to capabilities to be applied to roles and capabilities.
        $capability = 'block/panopto:provision_aspublisher';
        $publisherprocessed = self::build_and_assign_context_capability_to_roles($coursecontext, $publisherroles, $capability);
        $capability = 'block/panopto:provision_asteacher';
        $creatorprocessed = self::build_and_assign_context_capability_to_roles($coursecontext, $creatorroles, $capability);

        // If any changes where made, context needs to be flagged as dirty to be re-cached.
        if ($publisherprocessed || $creatorprocessed) {
            $coursecontext->mark_dirty();
        }

        $systemcontext = context_system::instance();
        $publisherrolesstring = trim(get_config('block_panopto', 'publisher_system_role_mapping'));
        if (isset($publisherrolesstring) && !empty($publisherrolesstring)) {
            $publishersystemroles = explode(',', $publisherrolesstring);
            // Build and process new/old changes to capabilities to roles and capabilities.
            $capability = 'block/panopto:provision_aspublisher';
            $publisherprocessed = self::build_and_assign_context_capability_to_roles($systemcontext, $publishersystemroles, $capability);

            // If any changes where made, context needs to be flagged as dirty to be re-cached.
            if ($publisherprocessed) {
                $systemcontext->mark_dirty();
            }
        }

        self::set_course_role_mappings($courseid, $publisherroles, $creatorroles);
    }

    public static function is_server_alive($url = null) {
        // Only proceed with the cURL check if this toggle is true. This code is dependent on platform/OS specific calls.
        if (!get_config('block_panopto', 'check_server_status')) {
            return true;
        }
        if ($url == null) {
            return false;
        }
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $data = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if (($httpcode >= 200 && $httpcode < 300) || $httpcode == 302) {
            return true;
        } else {
            return false;
        }
    }

    public static function print_log($logmessage) {
        global $CFG;

        if (get_config('block_panopto', 'print_log_to_file')) {
            file_put_contents($CFG->tempdir . '/PanoptoLogs.txt', $logmessage . "\n", FILE_APPEND);
        } else {
            error_log($logmessage);

            // These flush's are needed for longer processes like the Moodle upgrade process and import process.

            // If the oblength are false then there is no active outbut buffer, if we call ob_flush without an output buffer (e.g. from the cli) it will spit out an error. This doesn't break the execution of the script, but it's ugly and a lot of bloat.
            $obstatus = ob_get_status();
            if (isset($obstatus) && !empty($obstatus)) {
                ob_flush();
            }
            flush();
        }
    }

    public static function print_log_verbose($logmessage) {
        if (get_config('block_panopto', 'print_verbose_logs')) {
            self::print_log($logmessage);
        }
    }
}
/* End of file panopto_data.php */
