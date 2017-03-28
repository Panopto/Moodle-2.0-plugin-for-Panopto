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
 * contains main panopto getters
 *
 * @package block_panopto
 * @copyright  Panopto 2009 - 2016 /With contributions from Spenser Jones (sjones@ambrose.edu)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

global $CFG;
if (empty($CFG)) {
    require_once('../../config.php');
}
require_once($CFG->libdir . '/dmllib.php');
require_once('block_panopto_lib.php');
require_once('panopto_soap_client.php');
require_once('panopto_auth_soap_client.php');

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
     * @var int $moodlecourseid current active moodle course id
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
     * @var object $authsoapclient instance of the auth soap client
     */
    public $authsoapclient;

    /**
     * @var int $sessiongroupid is for the current session
     */
    public $sessiongroupid;

    /**
     * @var string $uname username
     */
    public $uname;

    /**
     * @var int $panoptoversion current panopto version
     */
    public $panoptoversion;

    /**
     * @var int PAGE_SIZE size of the pages
     */
    const PAGE_SIZE = 50;

    /**
     * @var int $requireversion Panopto only supports versions of moodle newer than v2.7(2014051200).
     */
    private static $requiredversion = 2014051200;

    /**
     * main constructor
     *
     * @param int $moodlecourseid course id class is being provisioned for
     */
    public function __construct($moodlecourseid) {

        // Fetch global settings from DB.
        $this->instancename = get_config('block_panopto', 'instance_name');

        // Get servername and application key specific to moodle course if ID is specified.
        if (isset($moodlecourseid)) {
            $this->servername = self::get_panopto_servername($moodlecourseid);
            $this->applicationkey = self::get_panopto_app_key($moodlecourseid);
        }

        // Fetch current Panopto course mapping if we have a Moodle course ID.
        // Course will be null initially for batch-provisioning case.
        if (!empty($moodlecourseid)) {
            $this->moodlecourseid = $moodlecourseid;
            $this->sessiongroupid = self::get_panopto_course_id($moodlecourseid);
        }
    }

    /**
     * Returns SystemInfo.
     */
    public function get_system_info() {

        // If no soap client for this instance, instantiate one.
        if (!isset($this->soapclient)) {
            $this->soapclient = $this->instantiate_soap_client($this->uname, $this->servername, $this->applicationkey);
        }

        return $this->soapclient->get_system_info();
    }

    /**
     * Returns if the logged in user can provision.
     *
     * @param int $courseid the moodle id of the course we are checking
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
     * Gets the toal count of users from all roles given a provisioning info object.
     *
     * @param object $provisioninginfo user info for course being counted
     */
    private function get_user_count($provisioninginfo) {
        $usercount = 0;
        if (isset($provisioninginfo->Publishers)) {
            $usercount += count($provisioninginfo->Publishers);
        }
        if (isset($provisioninginfo->Instructors)) {
            $usercount += count($provisioninginfo->Instructors);
        }
        if (isset($provisioninginfo->Students)) {
            $usercount += count($provisioninginfo->Students);
        }
        return $usercount;
    }

    /**
     * Tests to see if a user is already listed in the user array
     *
     * @param object $userinfo the user being tested
     * @param array $userarray an array of current users
     *
     * @return bool whether or not the user is in the list of current users
     */
    private function is_user_in_array($userinfo, $userarray) {
        foreach ($userarray as $currentuser) {
            if ($userinfo->UserKey === $currentuser->UserKey) {
                return true;
            }
        }

        return false;
    }

    /**
     * Merges unique users from the right array into the left arry and returns the new array.
     *
     * @param array $leftarray an array of current users
     * @param array $rightarray an array of users being checked and added
     *
     * @return array the array of merged users
     */
    private function merge_user_arrays($leftarray, $rightarray) {
        $retarray = $leftarray;

        foreach ($rightarray as $possibleinsert) {
            $alreadyinarray = $this->is_user_in_array($possibleinsert, $leftarray);

            if (!$alreadyinarray) {
                array_push($retarray, $possibleinsert);
            }
        }

        return $retarray;
    }

    /**
     * Appends the provisioning info from all importing courses to thise course, for panopto permissions
     *
     * @param object $provisioninginfo the current provisioninginfo.
     *
     */
    private function append_child_provisioning_info($provisioninginfo) {
        $childcourses = self::get_import_target_list($this->moodlecourseid);
        foreach ($childcourses as $childcourse) {
            $childcoursecontext = context_course::instance($childcourse);
            $currentusers = get_enrolled_users($childcoursecontext);

            if (!empty($currentusers)) {

                if (!isset($provisioninginfo->Students)) {
                    $provisioninginfo->Students = array();
                }

                foreach ($currentusers as $user) {
                    $userinfo = new stdClass;
                    $userinfo->UserKey = $this->panopto_decorate_username($user->username);
                    $userinfo->FirstName = $user->firstname;
                    $userinfo->LastName = $user->lastname;
                    $userinfo->Email = $user->email;

                    if (!$this->is_user_in_array($userinfo, $provisioninginfo->Students)) {
                        array_push($provisioninginfo->Students, $userinfo);
                    }
                }
            }
        }

        return $provisioninginfo;
    }

    /**
     * This will iterate through all courses with the same panopto ID and add those users to the provisioning info.
     *
     * @param object $provisioninginfo the current provisioninginfo.
     *
     */
    private function append_shared_course_info($provisioninginfo) {
        $panoptoid = $this->sessiongroupid;
        $sharedcourses = self::get_moodle_course_id($panoptoid);

        foreach ($sharedcourses as $sharedcourse) {
            if ($sharedcourse->moodleid !== $this->moodlecourseid) {
                $sharedpanopto = new panopto_data($sharedcourse->moodleid);
                $sharedprovisioninginfo = $sharedpanopto->get_provisioning_info(false);

                if (isset($sharedprovisioninginfo->Publishers)) {
                    if (isset($provisioninginfo->Publishers)) {
                        $provisioninginfo->Publishers = $this->merge_user_arrays(
                            $provisioninginfo->Publishers,
                            $sharedprovisioninginfo->Publishers
                        );
                    } else {
                        $provisioninginfo->Publishers = $sharedprovisioninginfo->Publishers;
                    }
                }

                if (isset($sharedprovisioninginfo->Instructors)) {
                    if (isset($provisioninginfo->Instructors)) {
                        $provisioninginfo->Instructors = $this->merge_user_arrays(
                            $provisioninginfo->Instructors,
                            $sharedprovisioninginfo->Instructors
                        );
                    } else {
                        $provisioninginfo->Instructors = $sharedprovisioninginfo->Instructors;
                    }
                }

                if (isset($sharedprovisioninginfo->Students)) {
                    if (isset($provisioninginfo->Students)) {
                        $provisioninginfo->Students = $this->merge_user_arrays(
                            $provisioninginfo->Students,
                            $sharedprovisioninginfo->Students
                        );
                    } else {
                        $provisioninginfo->Students = $sharedprovisioninginfo->Students;
                    }

                }
            }
        }

        return $provisioninginfo;
    }

    /**
     * Create the Panopto course and populate its ACLs.
     *
     * @param object $provisioninginfo info for course being provisioned
     */
    public function provision_course($provisioninginfo) {
        $courseinfo = new stdClass();

        // If no soap client for this instance, instantiate one.
        if (!isset($this->soapclient)) {
            $this->soapclient = $this->instantiate_soap_client($this->uname, $this->servername, $this->applicationkey);
        }

        // If no soap client for this instance, instantiate one.
        if (!isset($this->authsoapclient)) {
            $this->authsoapclient = $this->instantiate_auth_soap_client($this->servername);
        }

        // Get Panopto version from server if we don't already have it.
        if (!isset($this->panoptoversion)) {
            $this->panoptoversion = $this->authsoapclient->get_panopto_server_version();
        }

        if (!empty($this->panoptoversion)) {
            if (version_compare($this->panoptoversion, 5) >= 0) {
                // Get total number of users to be provisioned.
                $usercount = $this->get_user_count($provisioninginfo);

                // If there are more than 50 users, do the paged form of provisioning.
                if ($usercount > self::PAGE_SIZE) {
                    $courseinfo = $this->provision_course_with_paging($provisioninginfo);
                } else {
                    $courseinfo = $this->soapclient->provision_course_with_options($provisioninginfo);
                }
            } else {
                $courseinfo = $this->soapclient->provision_course($provisioninginfo);
            }
            if (!empty($courseinfo) && !empty($courseinfo->PublicID)) {
                self::set_panopto_course_id($this->moodlecourseid, $courseinfo->PublicID);
                self::set_panopto_server_name($this->moodlecourseid, $this->servername);
                self::set_panopto_app_key($this->moodlecourseid, $this->applicationkey);
            }
        }
        return $courseinfo;
    }

    /**
     *  Fetch course name and membership info from DB in preparation for provisioning operation.
     *
     * @param bool $getsharedinfo a flag that tells us if we should find courses using the same panopto folder and add thier users.
     */
    public function get_provisioning_info($getsharedinfo = true) {
        global $DB, $CFG;

        // If old role mappings exists, do not remap. Otherwise, set role mappings to defaults.
        $mappings = self::get_course_role_mappings($this->moodlecourseid);
        if (empty($mappings['creator'][0]) && empty($mappings['publisher'][0])) {

            // These settings are returned as a comma seperated string of role Id's.
            $defaultpublishermapping = explode("," , get_config('block_panopto', 'publisher_role_mapping'));
            $defaultcreatormapping = explode("," , get_config('block_panopto', 'creator_role_mapping'));

            // Set the role mappings for the course to the defaults.
            self::set_course_role_mappings(
                $this->moodlecourseid,
                $defaultpublishermapping,
                $defaultcreatormapping
            );

            // Grant course users the proper panopto permissions based on the default role mappings.
            // This will make the role mappings be recognized when provisioning.
            self::set_course_role_permissions(
                $this->moodlecourseid,
                $defaultpublishermapping,
                $defaultcreatormapping
            );
        }

        $provisioninginfo = new stdClass;

        // If we are provisioning a course with a panopto_id set we should provision that folder.
        $coursepanoptoid = $this->sessiongroupid;
        $hasvalidpanoptoid = isset($coursepanoptoid) && !empty($coursepanoptoid);
        if ($hasvalidpanoptoid) {
            $mappedpanoptocourse = $this->get_course();
        }

        $provisioninginfo->ExternalCourseID = $this->instancename . ':' . $this->moodlecourseid;

        if (isset($mappedpanoptocourse) && ($provisioninginfo->ExternalCourseID !== $mappedpanoptocourse->ExternalCourseID)) {
            $provisioninginfo->ExternalCourseID = $mappedpanoptocourse->ExternalCourseID;

            $namechunks = explode(':', $mappedpanoptocourse->DisplayName);
            // Display names generated by panopto are formatted like <shortname>:<longname>, this assumes that format.
            // Hacky but there is not much else we can do until we move to provisioning by folder ID.

            $chunkscount = count($namechunks);
            if ($chunkscount === 2) {
                $provisioninginfo->shortname = $namechunks[0];
                $provisioninginfo->longname = $namechunks[1];
            } else if ($chunkscount === 1) {
                // This is a weird case I don't expect to happen. This should only occur if the user maps to a panopto folder that does not contain any colons.
                $provisioninginfo->shortname = '';
                $provisioninginfo->longname = $namechunks[0];
            } else {
                $provisioninginfo->shortname = $namechunks[0];
                $mappedlongname = '';

                // If there are more than 2 chunks take the chunks after the first one and combine them into a longname.
                // Panopto should recombine these into the original namechunk[0]:mappedlongname. Where mappedlongname = namechunk[1]:namechunk[2]:...:namechunk[n]
                for ($i = 1; $i < count($namechunks); ++$i) {
                    $mappedlongname .= $namechunks[$i];

                    if ($i !== $chunkscount - 1) {
                        $mappedlongname .= ':';
                    }
                }

                $provisioninginfo->longname = $mappedlongname;
            }
        } else {
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
        }

        $provisioninginfo->Server = $this->servername;
        $coursecontext = context_course::instance($this->moodlecourseid, MUST_EXIST);

        // Lookup table to avoid adding instructors as Viewers as well as Creators.
        $publisherhash = array();
        $instructorhash = array();

        $systemcontext = context_system::instance();
        $systempublishers = get_users_by_capability($systemcontext, 'block/panopto:provision_aspublisher');
        $publishers = get_users_by_capability($coursecontext, 'block/panopto:provision_aspublisher');

        if (empty($publishers)) {
            $publishers = array();
        }

        if (!empty($systempublishers)) {
            $totalpublishers = array_merge($publishers, $systempublishers);
        } else {
            $totalpublishers = $publishers;
        }

        if (!empty($totalpublishers)) {
            $provisioninginfo->Publishers = array();
            foreach ($totalpublishers as $publisher) {
                $publisherinfo = new stdClass;
                $publisherinfo->UserKey = $this->panopto_decorate_username($publisher->username);
                $publisherinfo->FirstName = $publisher->firstname;
                $publisherinfo->LastName = $publisher->lastname;
                $publisherinfo->Email = $publisher->email;

                if (!$this->is_user_in_array($publisherinfo, $provisioninginfo->Publishers)) {
                    array_push($provisioninginfo->Publishers, $publisherinfo);
                }

                $publisherhash[$publisher->username] = true;
            }
        }

        // ...moodle/course:update capability will include admins along with teachers, course creators, etc.
        // Could also use moodle/legacy:teacher, moodle/legacy:editingteacher, etc. if those turn out to be more appropriate.
        // File edited - new capability added to access.php to identify instructors without including all site admins etc.
        // New capability used to identify instructors for provisioning.
        $instructors = get_users_by_capability($coursecontext, 'block/panopto:provision_asteacher');

        if (get_config('block_panopto', 'auto_add_admins')) {
            // All super admins should get access to all panopto courses as teachers, since they can all provision in the config page.
            $sql = "SELECT username, firstname, lastname, email " .
                   "FROM {user} " .
                   "WHERE id IN (:siteadmins)";

            $superadmins = $DB->get_records_sql($sql, array('siteadmins' => $CFG->siteadmins));
        } else {
            $superadmins = array();
        }

        if (empty($instructors)) {
            $instructors = array();
        }

        if (!empty($superadmins)) {
            $instructors = array_merge($instructors, $superadmins);
        }

        if (!empty($instructors)) {
            $provisioninginfo->Instructors = array();
            foreach ($instructors as $instructor) {
                $instructorinfo = new stdClass;
                $instructorinfo->UserKey = $this->panopto_decorate_username($instructor->username);
                $instructorinfo->FirstName = $instructor->firstname;
                $instructorinfo->LastName = $instructor->lastname;
                $instructorinfo->Email = $instructor->email;

                if (!$this->is_user_in_array($instructorinfo, $provisioninginfo->Instructors)) {
                    array_push($provisioninginfo->Instructors, $instructorinfo);
                }

                $instructorhash[$instructor->username] = true;
            }
        }

        /*
         * Give all enrolled users at least student-level access. Instructors will be filtered out below.
         * Use get_enrolled_users because, as of Moodle 2.0, capability moodle/course:view no longer
         * corresponds to a participant list.
         */
        $students = get_enrolled_users($coursecontext);

        if (!empty($students)) {
            $provisioninginfo->Students = array();
            foreach ($students as $student) {
                if (array_key_exists($student->username, $instructorhash)) {
                    continue;
                }
                if (array_key_exists($student->username, $publisherhash)) {
                    continue;
                }
                $studentinfo = new stdClass;
                $studentinfo->UserKey = $this->panopto_decorate_username($student->username);
                $studentinfo->FirstName = $student->firstname;
                $studentinfo->LastName = $student->lastname;
                $studentinfo->Email = $student->email;

                array_push($provisioninginfo->Students, $studentinfo);
            }
        }

        // We need to go through each course importing this course, and add thier users to our couse on Panopto's side.
        $provisioninginfo = $this->append_child_provisioning_info($provisioninginfo);

        if ($getsharedinfo && $hasvalidpanoptoid) {
            $provisioninginfo = $this->append_shared_course_info($provisioninginfo);
        }

        return $provisioninginfo;
    }

    /**
     * Initializes and syncs a possible new import
     *
     * @param int $courseid the id of the recipient course
     * @param int $newimportid the id of the course being imported
     *
     */
    public function init_and_sync_import($courseid, $newimportid) {
        $currentimportsources = self::get_import_list($courseid);
        $possibleimportsources = array_merge(
            array($newimportid),
            self::get_import_list($newimportid)
        );

        foreach ($possibleimportsources as $possiblenewimportsource) {
            // If a course is already listed as an import we don't need to reprovision it.
            if (!in_array($possiblenewimportsource, $currentimportsources)) {
                $currentimportsources[] = $possiblenewimportsource;

                self::add_new_course_import($courseid, $possiblenewimportsource);

                $newimportpanopto = new panopto_data($possiblenewimportsource);
                $newimportpanopto->provision_course($newimportpanopto->get_provisioning_info());
            }
        }
    }

    /**
     * Create the provisioning information needed to create permissions on panopto for the new course
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
     *  Get courses visible to the current user.
     */
    public function get_courses() {
        if (!isset($this->soapclient)) {
            $this->soapclient = self::instantiate_soap_client($this->uname, $this->servername, $this->applicationkey);
        }

        $coursesresult = $this->soapclient->get_courses();
        $courses = array();
        if (!empty($coursesresult->CourseInfo)) {
            $courses = $coursesresult->CourseInfo;

            // Single-element return set comes back as scalar, not array (?).
            if (!is_array($courses)) {
                $courses = array($courses);
            }
        }

        return $courses;
    }

    /**
     * Get info about the currently mapped course.
     */
    public function get_course() {
        // If no soap client for this instance, instantiate one.
        if (!isset($this->soapclient)) {
            $this->soapclient = self::instantiate_soap_client($this->uname, $this->servername, $this->applicationkey);
        }

        return $this->soapclient->get_course($this->sessiongroupid);
    }

    /**
     * Get ongoing Panopto sessions for the currently mapped course.
     */
    public function get_live_sessions() {
        $livesessionsresult = $this->soapclient->get_live_sessions($this->sessiongroupid);

        $livesessions = array();
        if (!empty($livesessionsresult->SessionInfo)) {
            $livesessions = $livesessionsresult->SessionInfo;

            // Single-element return set comes back as scalar, not array (?).
            if (!is_array($livesessions)) {
                $livesessions = array($livesessions);
            }
        }

        return $livesessions;
    }

    /**
     * Get recordings available to view for the currently mapped course.
     */
    public function get_completed_deliveries() {
        $completeddeliveriesresult = $this->soapclient->get_completed_deliveries($this->sessiongroupid);

        $completeddeliveries = array();
        if (!empty($completeddeliveriesresult->DeliveryInfo)) {
            $completeddeliveries = $completeddeliveriesresult->DeliveryInfo;

            // Single-element return set comes back as scalar, not array (?)
            if (!is_array($completeddeliveries)) {
                $completeddeliveries = array($completeddeliveries);
            }
        }

        return $completeddeliveries;
    }

    /**
     * Instance method caches Moodle instance name from DB (vs. block_panopto_lib version).
     *
     * @param string $moodleusername name of the moodle user
     */
    public function panopto_decorate_username($moodleusername) {
        return ($this->instancename . "\\" . $moodleusername);
    }

    /**
     * Lets us know if we have a value inside the config for a panopto server, we don't want any of our events to fire on an unconfigured block.
     *
     */
    public static function is_main_block_configured() {
        $numservers = get_config('block_panopto', 'server_number');
        $numservers = isset($numservers) ? $numservers : 0;
        ++$numservers;

        $isconfigured = false;
        if ($numservers > 0) {
            for ($i = 1; $i <= $numservers; ++$i) {
                $possibleserver = get_config('block_panopto', 'server_name' . $i);
                $possibleappkey = get_config('block_panopto', 'application_key' . $i);

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

        if ($CFG->version < self::$requiredversion) {
            $hasminversion = false;
            error_log("Panopto block requires a version newer than " . self::$requiredversion . ", your current version is: " . $CFG->version);
        }

        return $hasminversion;
    }

    /**
     * We need to retrieve the current course mapping in the constructor, so this must be static.
     *
     * @param int $sessiongroupid id of the panopto folder we are trying to get the moodle courses associated with.
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
     * @param int $moodlecourseid id of the current moodle course
     */
    public static function get_panopto_course_id($moodlecourseid) {
        global $DB;
        return $DB->get_field('block_panopto_foldermap', 'panopto_id', array('moodleid' => $moodlecourseid));
    }

    /**
     *  Retrieve the servername for the current course
     *
     * @param int $moodlecourseid id of the current moodle course
     */
    public static function get_panopto_servername($moodlecourseid) {
        global $DB;
        return $DB->get_field('block_panopto_foldermap', 'panopto_server', array('moodleid' => $moodlecourseid));
    }

    /**
     *  Retrieve the app key for the current course
     *
     * @param int $moodlecourseid id of the current moodle course
     */
    public static function get_panopto_app_key($moodlecourseid) {
        global $DB;
        return $DB->get_field('block_panopto_foldermap', 'panopto_app_key', array('moodleid' => $moodlecourseid));
    }

    /**
     * Get the current role mappings set for the current course from the db.
     *
     * @param int $moodlecourseid id of the current moodle course
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
            foreach($creatorrolesraw as $creatorrole) {
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
            foreach($pubrolesraw as $pubrole) {
                $pubroles[] = $pubrole->role_id;
            }
        }

        return array('publisher' => $pubroles, 'creator' => $creatorroles);
    }

    /**
     *  Set the Panopto ID in the db for the current course
     *  Called by Moodle block instance config save method, so must be static.
     *
     * @param int $moodlecourseid id of the current moodle course
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
     * @param int $moodlecourseid id of the current moodle course
     * @param string $panoptoservername the name of the panopto server
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
     * @param int $moodlecourseid id of the current moodle course
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
     * @param int $moodlecourseid id of the current moodle course
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
     * Delete the panopto foldermap row, called when a course is deleted.
     * This function is unused but kept in case we decide to reintroduce the cleaning of table rows.
     *
     * @param int $moodlecourseid id of the target moodle course
     */
    public static function delete_panopto_relation($moodlecourseid) {
        global $DB;
        $deletedrecords = array();
        if ($DB->get_records('block_panopto_foldermap', array('moodleid' => $moodlecourseid))) {
            $deletedrecords['foldermap'] = $DB->delete_records(
                'block_panopto_foldermap',
                array('moodleid' => $moodlecourseid)
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
     * Get list of available courses from db based on user's access level on course
     */
    public function get_course_options() {
        $coursesbyaccesslevel = array('Creator' => array(), 'Viewer' => array(), 'Public' => array());

        $panoptocourses = $this->get_courses();
        if (!empty($panoptocourses)) {
            foreach ($panoptocourses as $courseinfo) {
                array_push($coursesbyaccesslevel[$courseinfo->Access], $courseinfo);
            }

            $options = array();
            foreach (array_keys($coursesbyaccesslevel) as $accesslevel) {
                $courses = $coursesbyaccesslevel[$accesslevel];
                $group = array();
                foreach ($courses as $courseinfo) {
                    $displayname = s($courseinfo->DisplayName);
                    $group[$courseinfo->PublicID] = $displayname;
                }
                $options[$accesslevel] = $group;
            }
        } else if (isset($panoptocourses)) {
            $options = array('Error' => array('-- No Courses Available --'));
        } else {
            $options = array('Error' => array('!! Unable to retrieve course list !!'));
        }

        return array('courses' => $options, 'selected' => $this->sessiongroupid);
    }

    /**
     * Add a user enrollment to the current course
     *
     * @param string $role the role of the current user
     * @param string $userkey the auth key for the user
     */
    public function add_course_user($role, $userkey) {

        // If user has both publisher and creator roles, add both.
        if ($role == 'Creator/Publisher') {
            $this->add_course_user_soap_call('Publisher', $userkey);
            $this->add_course_user_soap_call('Creator', $userkey);
        } else {
            $this->add_course_user_soap_call($role, $userkey);
        }
    }

    /**
     * Makes SOAP call for add_course_user function
     *
     * @param string $role the role of the current user
     * @param string $userkey the auth key for the user
     */
    private function add_course_user_soap_call($role, $userkey) {

        if (!isset($this->soapclient)) {
            $this->soapclient = $this->instantiate_soap_client($this->uname, $this->servername, $this->applicationkey);
        }

        try {
            $result = $this->soapclient->add_user_to_course($this->sessiongroupid, $role, $userkey);
        } catch (Exception $e) {
            error_log("Error: " . $e->getMessage());
            error_log("Code: " . $e->getCode());
            error_log("Line: " . $e->getLine());
        }
        return $result;
    }

    /**
     * Remove a user's enrollment from the current course
     *
     * @param string $role the role of the current user
     * @param string $userkey the auth key for the user
     */
    public function remove_course_user($role, $userkey) {
        {
            $this->remove_course_user_soap_call($role, $userkey);
        }
    }

    /**
     * Makes SOAP call for remove_course_user function
     *
     * @param string $role the role of the current user
     * @param string $userkey the auth key for the user
     */
    private function remove_course_user_soap_call($role, $userkey) {
        if (!isset($this->soapclient)) {
            $this->soapclient = $this->instantiate_soap_client($this->uname, $this->servername, $this->applicationkey);
        }

        try {
            $result = $this->soapclient->remove_user_from_course($this->sessiongroupid, $role, $userkey);
        } catch (Exception $e) {
            error_log("Error: " . $e->getMessage());
            error_log("Code: " . $e->getCode());
            error_log("Line: " . $e->getLine());
        }
        return $result;
    }

    /**
     * Change an enrolled user's role in the current course
     *
     * @param string $role the role of the current user
     * @param string $userkey the auth key for the user
     */
    public function change_user_role($role, $userkey) {

        // If user is to have both creator and publisher roles, change his current role to publisher,
        // And add a creator role.
        if ($role == 'Creator/Publisher') {
            $this->change_user_role_soap_call('Publisher', $userkey);
            $this->add_course_user_soap_call('Creator', $userkey);
        } else {
            $this->change_user_role_soap_call($role, $userkey);
        }
    }

    /**
     * Makes SOAP call for remove_course_user function
     *
     * @param string $role the role of the current user
     * @param string $userkey the auth key for the user
     */
    private function change_user_role_soap_call($role, $userkey) {
        if (!isset($this->soapclient)) {
            $this->soapclient = $this->instantiate_soap_client($this->uname, $this->servername, $this->applicationkey);
        }

        try {
            $result = $this->soapclient->change_user_role($this->sessiongroupid, $role, $userkey);
        } catch (Exception $e) {
            error_log("Error: " . $e->getMessage());
            error_log("Code: " . $e->getCode());
            error_log("Line: " . $e->getLine());
        }
        return $result;
    }

    /**
     * Used to instantiate a soap client for a given instance of panopto_data.
     * Should be called only the first time a soap client is needed for an instance.
     *
     * @param string $username the name of the current user
     * @param string $servername the name of the active server
     * @param string $applicationkey the key need for the user to be authenticated
     */
    public function instantiate_soap_client($username, $servername, $applicationkey) {
        global $USER;
        if (!empty($this->servername)) {
            if (isset($USER->username)) {
                $username = $USER->username;
            } else {
                $username = 'guest';
            }
            $this->uname = $username;
        }

        // Compute web service credentials for current user.
        $apiuseruserkey = panopto_decorate_username($username);
        $apiuserauthcode = panopto_generate_auth_code($apiuseruserkey . '@' . $this->servername, $this->applicationkey);

        // Instantiate our SOAP client.
        return new panopto_soap_client($this->servername, $apiuseruserkey, $apiuserauthcode, 300);
    }

    /**
     * Used to instantiate a soap client for calling Panopto's iAuth service.
     * Should be called only the first time an  auth soap client is needed for an instance.
     */
    public function instantiate_auth_soap_client() {
        // Instantiate our SOAP client.
        return new panopto_auth_soap_client($this->servername);
    }

    /**
     * Gives selected capabilities to specified roles given a context.
     *
     * @param int $context the context of the roles being given the capability
     * @param array $roles an array of roles to be given the capability
     * @param string $capability The capability being given to the roles
     */
    public static function add_context_capability_to_roles($context, $roles, $capability) {
        foreach ($roles as $role) {
            if (isset($role) && trim($role) !== '') {
                assign_capability(
                    $capability,
                    CAP_ALLOW,
                    $role,
                    $context,
                    $overwrite = false
                );
            }
        }
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

        // Clear capabilities from all of course's roles to be reassigned.
        self::clear_capabilities_for_course($courseid);

        self::add_context_capability_to_roles($coursecontext, $publisherroles, 'block/panopto:provision_aspublisher');
        self::add_context_capability_to_roles($coursecontext, $creatorroles, 'block/panopto:provision_asteacher');

        $publishersystemroles = explode(',', get_config('block_panopto', 'publisher_system_role_mapping'));
        if (count($publishersystemroles)) {
            $systemcontext = context_system::instance();
            self::add_context_capability_to_roles($systemcontext, $publishersystemroles, 'block/panopto:provision_aspublisher');

            // Mark dirty (moodle standard for capability changes at context level).
            $systemcontext->mark_dirty();
        }

        // Mark dirty (moodle standard for capability changes at context level).
        $coursecontext->mark_dirty();

        self::set_course_role_mappings($courseid, $publisherroles, $creatorroles);
    }

    /**
     * Clears capabilities from all roles so that they may be reassigned as specified.
     *
     * @param int $courseid the id of the course being targets for clearing
     */
    public static function clear_capabilities_for_course($courseid) {
        $coursecontext = context_course::instance($courseid);

        // Get all roles for current course.
        $currentcourseroles = get_all_roles($coursecontext);

        // Remove publisher and creator capabilities from all roles.
        foreach ($currentcourseroles as $role) {
            unassign_capability('block/panopto:provision_aspublisher', $role->id, $coursecontext);
            unassign_capability('block/panopto:provision_asteacher', $role->id, $coursecontext);

            // Mark dirty (moodle standard for capability changes at context level).
            $coursecontext->mark_dirty();
        }
    }


    /**
     * Clears capabilities from all roles so that they may be reassigned as specified.
     *
     * @param array $userarray the users in the course tobe provisioned
     * @param object $provisioninginfo info about the course
     * @param object $courseoptions options about the course
     */
    private function perform_provisioning_operations($userarray, $provisioninginfo, $courseoptions) {
        $courseinfo = $this->soapclient->provision_course_with_course_options(
            $provisioninginfo,
            $courseoptions
        );

        $this->soapclient->make_paged_call_provision_users($userarray);

        return $courseinfo;
    }

    /**
     * Provisions courses based on uer roles.
     *
     * @param string $rolename the name of the role being provisioned
     * @param object $provisioninginfo info about the provisioning
     * @param array $userarray the users in the course tobe provisioned
     * @param object $courseoptions options about the course
     * @param object $courseinfo information on the course
     */
    private function paged_provision_by_role($rolename, $provisioninginfo, &$userarray,
                                             &$courseoptions, &$courseinfo) {

        if (isset($provisioninginfo->$rolename)) {
            $courseoptions['Clear' . $rolename] = 'true';
            $userarray = array_merge($userarray, $provisioninginfo->$rolename);
            if (count($userarray) > self::PAGE_SIZE) {
                $courseinfo = $this->perform_provisioning_operations($userarray, $provisioninginfo, $courseoptions);
                $userarray = array();
                $courseoptions = array('ProvisionUsers' => 'false');
            }
        }
    }

    // Each role is considered one at a time. If there are more users in a particular role than the threshold for paging, the
    // Course data is synced for that role, and that role's users are provisioned in a paged manner.
    // If there are not more users than the threshold, the users are combined with the users from the next role.
    // If the total number is higher than the threshold they are synced and provisioned together.
    // If the total users after all stages are not over the threshold, all roles (or remaining roles)
    // Are synced and provisioned together.

    /**
     * Function used to provision when the number of users to be provisioned in a single role is over the threshold value.
     *
     * @param object $provisioninginfo info about the provisioning
     */
    public function provision_course_with_paging($provisioninginfo) {
        // Instantiate soap client if it hasn't been already.
        if (!isset($this->soapclient)) {
            $this->soapclient = $this->instantiate_soap_client(
                $this->uname,
                $this->servername,
                $this->applicationkey
            );
        }

        $courseinfo = new stdClass;
        $courseoptions = array('ProvisionUsers' => 'false');
        $userarray = array();

        $this->paged_provision_by_role('Publishers', $provisioninginfo, $userarray, $courseoptions, $courseinfo);
        $this->paged_provision_by_role('Instructors', $provisioninginfo, $userarray, $courseoptions, $courseinfo);
        $this->paged_provision_by_role('Students', $provisioninginfo, $userarray, $courseoptions, $courseinfo);

        // If any users have yet to be provisioned, do it now.
        if (!empty($userarray)) {
            $courseinfo = $this->perform_provisioning_operations($userarray, $provisioninginfo, $courseoptions);
        }

        // Return course info to be displayed.
        return $courseinfo;
    }
}
/* End of file panopto_data.php */
