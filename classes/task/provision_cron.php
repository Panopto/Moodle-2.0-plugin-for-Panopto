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
 * @copyright  Panopto 2009 - 2015 with contributions from Spenser Jones (sjones@ambrose.edu)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_panopto\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Simple task to run the course provision cron.
 *
 * @copyright  2016 Rose-Hulman Institute of Technology
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
 
require_once(dirname(__FILE__) . '/../../lib/panopto_data.php');
        
class provision_cron extends \core\task\scheduled_task {

   public function get_name() {
        return "Panopto Provision Cron";
    }

    public function execute() {
        global $CFG, $DB;

        
        // Populate list of servernames to select from.
        $aserverarray = array();
        $appkeyarray = array();
        if (isset($_SESSION['numservers'])) {
            $maxval = $_SESSION['numservers'];
        } else {
            $maxval = 1;
        }
        for ($x = 0; $x < $maxval; $x++) {
            // Generate strings corresponding to potential servernames in $CFG.
            $thisservername = 'block_panopto_server_name' . ($x + 1);
            $thisappkey = 'block_panopto_application_key' . ($x + 1);
            if ((isset($CFG->$thisservername) && !is_null_or_empty_string($CFG->$thisservername)) && (!is_null_or_empty_string($CFG->$thisappkey))) {
                $CFG->servername = $CFG->$thisservername;
                $CFG->appkey = $CFG->$thisappkey;
            }
        }

        // Code to get the appropriate course id's
        $courses = $DB->get_records_sql("SELECT id FROM {course} c WHERE c.id > 1 AND c.id NOT IN (SELECT moodleid FROM {block_panopto_foldermap})", null, 0, 25);
        
        $provisioned = array();
        $panoptodata = new \panopto_data(null);
        foreach ($courses as $course) {
            if (empty($course->id)) {
                continue;
            }
            // Set the current Moodle course to retrieve info for / provision.
            $panoptodata->moodlecourseid = $course->id;

            // If an application key and server name are pre-set (happens when provisioning from multi-select page) use those, otherwise retrieve
            // values from the db.
            if (isset($CFG->servername)) {
                $panoptodata->servername = $CFG->servername;
            } else {
                $panoptodata->servername = $panoptodata->get_panopto_servername($panoptodata->moodlecourseid);
            }
             
            if (isset($CFG->appkey)) {
                $panoptodata->applicationkey = $CFG->appkey;
            } else {
                $panoptodata->applicationkey = $panoptodata->get_panopto_app_key($panoptodata->moodlecourseid);
            }
            $provisioningdata = $panoptodata->get_provisioning_info();
            $provisioneddata = $panoptodata->provision_course($provisioningdata);
            panopto_provision_display_cron($provisioningdata);
        }   
    }
}

function panopto_provision_display_cron($provisioningdata) {
    echo get_string('course_name', 'block_panopto') . "\n";
    echo '  ' . $provisioningdata->ShortName . ": " . $provisioningdata->LongName . "\n";

    echo get_string('publishers', 'block_panopto') . "\n";
    if (!empty($provisioningdata->Publishers)) {
        $publishers = $provisioningdata->Publishers;

        // Single-element return set comes back as scalar, not array (?).
        if (!is_array($publishers)) {
            $publishers = array($publishers);
        }
        $publisherinfo = array();
        foreach ($publishers as $publisher) {
            array_push($publisherinfo, "  $publisher->UserKey ($publisher->FirstName $publisher->LastName <$publisher->Email>)");
        }

        echo join("\n", $publisherinfo);
    } else {
        echo '  ' . get_string('no_publishers', 'block_panopto');
    }
    echo "\n";

    echo get_string('creators', 'block_panopto') . "\n";
    if (!empty($provisioningdata->Instructors)) {
        $instructors = $provisioningdata->Instructors;

        // Single-element return set comes back as scalar, not array (?).
        if (!is_array($instructors)) {
            $instructors = array($instructors);
        }
        $instructorinfo = array();
        foreach ($instructors as $instructor) {
            array_push($instructorinfo, "  $instructor->UserKey ($instructor->FirstName $instructor->LastName <$instructor->Email>)");
        }

        echo join("\n", $instructorinfo);
    } else {
        echo '  ' . get_string('no_creators', 'block_panopto');
    }
    echo "\n";
    echo get_string('students', 'block_panopto') . "\n";
    if (!empty($provisioningdata->Students)) {
        $students = $provisioningdata->Students;

        // Single-element return set comes back as scalar, not array (?).
        if (!is_array($students)) {
            $students = array($students);
        }
        $studentinfo = array();
        foreach ($students as $student) {
            array_push($studentinfo, $student->UserKey);
        }

        echo '  ' . join(", ", $studentinfo);
    } else {
        echo '  ' . get_string('no_students', 'block_panopto');
    }
    echo "\n";
    echo get_string('result', 'block_panopto');
    if (!empty($provisioneddata)) {
        echo '  {' . $provisioneddata->PublicID . "}\n";
    } else {
        echo '  ' . get_string('provision_error', 'block_panopto');
    }
    echo "\n";
}

/**
 *Returns true if a string is null or empty, false otherwise
 */
function is_null_or_empty_string($name) {
    return (!isset($name) || trim($name) === '');
}