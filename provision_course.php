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
 * The provision course logic for Panopto
 *
 * @package block_panopto
 * @copyright  Panopto 2009 - 2016 /With contributions from Spenser Jones (sjones@ambrose.edu)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// @codingStandardsIgnoreLine
global $CFG;
if (empty($CFG)) {
    // @codingStandardsIgnoreLine
    require_once(dirname(__FILE__) . '/../../config.php');
}
require_once($CFG->libdir . '/formslib.php');
require_once(dirname(__FILE__) . '/classes/panopto_provision_form.php');
require_once(dirname(__FILE__) . '/lib/panopto_data.php');
require_once(dirname(__FILE__) . '/lib/block_panopto_lib.php');

global $courses;

// Populate list of servernames to select from.
$aserverarray = [];
$appkeyarray = [];

$numservers = get_config('block_panopto', 'server_number');
$numservers = isset($numservers) ? $numservers : 0;
$numservers += 1;

for ($serverwalker = 1; $serverwalker <= $numservers; $serverwalker += 1) {
    // Generate strings corresponding to potential servernames in the config.
    $thisservername = get_config('block_panopto', 'server_name' . $serverwalker);
    $thisappkey = get_config('block_panopto', 'application_key' . $serverwalker);

    $hasservername = !panopto_is_string_empty($thisservername);
    if ($hasservername && !panopto_is_string_empty($thisappkey)) {
        $aserverarray[$serverwalker - 1] = $thisservername;
        $appkeyarray[$serverwalker - 1] = $thisappkey;
    }
}

// If only one server, simply provision with that server. Setting these values will circumvent loading the selection form
// prior to provisioning.
if (count($aserverarray) == 1) {
    // Get first element from associative array. aServerArray and appKeyArray will have same key values.
    $key = array_keys($aserverarray);
    $selectedserver = trim($aserverarray[$key[0]]);
    $selectedkey = trim($appkeyarray[$key[0]]);
}

require_login();


// Set course context if we are in a course, otherwise use system context.
$courseidparam = optional_param('course_id', 0, PARAM_INT);
if ($courseidparam != 0) {
    $context = context_course::instance($courseidparam, MUST_EXIST);
} else {
    $context = context_system::instance();
}

$PAGE->set_context($context);

$returnurl = optional_param('return_url', $CFG->wwwroot . '/admin/settings.php?section=blocksettingpanopto', PARAM_LOCALURL);

$urlparams = ['return_url' => $returnurl];
if ($courseidparam != 0) {
    $urlparams['course_id'] = $courseidparam;
}

$PAGE->set_url('/blocks/panopto/provision_course.php', $urlparams);
$PAGE->set_pagelayout('base');

$mform = new panopto_provision_form($PAGE->url);

// This $courses is set below when this request should provision immediately: either a course_id.
// GET from "Sync this course to Panopto", or a submitted bulk-provision form.
$courses = [];

if ($mform->is_cancelled()) {
    redirect(new moodle_url($returnurl));
} else {
    $provisiontitle = get_string('provision_courses', 'block_panopto');
    $PAGE->set_pagelayout('base');
    $PAGE->set_title($provisiontitle);
    $PAGE->set_heading($provisiontitle);

    if ($courseidparam != 0) {
        // Course context. "Sync this course to Panopto" loads this page with course_id in
        // GET and provisions that course without showing the bulk course list.
        require_capability('block/panopto:provision_course', $context);

        // Provisioning sends course and user details to Panopto, so a bare request only shows the
        // confirmation below. Its continue button posts back with confirm set and the sesskey.
        if (optional_param('confirm', 0, PARAM_BOOL)) {
            require_sesskey();
            $courses = panopto_courses_to_provision($courseidparam, null);
        }

        $editcourseurl = new moodle_url($returnurl);
        $PAGE->navbar->add(get_string('pluginname', 'block_panopto'), $editcourseurl);
    } else {
        // System context.
        require_capability('block/panopto:provision_multiple', $context);

        $data = $mform->get_data();
        if ($data) {
            $courses = panopto_courses_to_provision(0, $data->courses);
            $selectedserver = trim($aserverarray[$data->servers]);
            $selectedkey = trim($appkeyarray[$data->servers]);
        }

        $manageblocks = new moodle_url('/admin/blocks.php');
        $panoptosettings = new moodle_url('/admin/settings.php?section=blocksettingpanopto');
        $PAGE->navbar->add(get_string('blocks'), $manageblocks);
        $PAGE->navbar->add(get_string('pluginname', 'block_panopto'), $panoptosettings);
    }

    $PAGE->navbar->add($provisiontitle, new moodle_url($PAGE->url));
}

echo $OUTPUT->header();

if ($courses) {
    $coursecount = count($courses);
    // Provisioning syncs every enrolled user to Panopto, so even one large course can outlast
    // the default time limit.
    core_php_time_limit::raise();

    foreach ($courses as $courseid) {
        if (empty($courseid)) {
            continue;
        }

        // Set the current Moodle course to retrieve info for / provision.
        $panoptodata = new \panopto_data($courseid);

        // If an application key and server name are pre-set (happens when provisioning from multi-select page) use those,
        // otherwise retrieve values from the db.
        if (
            isset($selectedserver) && !empty($selectedserver) &&
            isset($selectedkey) && !empty($selectedkey)
        ) {
            // If we are not using the same server remove the folder ID reference.
            // NOTE: A Moodle course can only point to one Panopto server at a time.
            // So reprovisioning to a different server erases the folder mapping to the original server.
            if ($panoptodata->servername !== $selectedserver) {
                $panoptodata->sessiongroupid = null;
            }
            $panoptodata->servername = $selectedserver;
            $panoptodata->applicationkey = $selectedkey;
        }

        if (
            isset($panoptodata->servername) && !empty($panoptodata->servername) &&
            isset($panoptodata->applicationkey) && !empty($panoptodata->applicationkey)
        ) {
            $provisioningdata = $panoptodata->get_provisioning_info();
            $provisioneddata = $panoptodata->provision_course($provisioningdata, false);
            include(dirname(__FILE__) . '/views/provisioned_course.html.php');

            // Each course costs several Panopto round trips, so a bulk selection can run for many
            // minutes. Without this the per-course blocks above stay buffered and the page looks
            // hung until the very end.
            panopto_bulk_flush_output();
        } else if ($coursecount == 1) {
            // If there is only one course in the count and the server info is invalid redirect
            // to the form for manual provisioning.
            $mform->display();
        } else {
            // For some reason the server name or application key are invalid and we can't redirect
            // to the form since there are multiple courses, let the user know.
            echo "<div class='block_panopto'>" .
                "<div class='panoptoProcessInformation'>" .
                    "<div class='errorMessage'>" . get_string('server_info_not_valid', 'block_panopto') . "</div>" .
                    "<div class='attribute'>" . get_string('server_name', 'block_panopto') . "</div>" .
                    "<div class='value'>" . format_string($panoptodata->servername, false) . "</div>" .
                "</div>" .
            "</div>";
        }
    }
    // The course sync link returns to the course page; bulk provisioning returns to the block settings.
    $backstring = ($courseidparam != 0) ? 'back_to_course' : 'back_to_config';
    echo html_writer::link($returnurl, get_string($backstring, 'block_panopto'));
} else if ($courseidparam != 0) {
    $course = get_course($courseidparam);
    $confirmurl = new moodle_url($PAGE->url, [
        'confirm' => 1,
        'sesskey' => sesskey(),
    ]);

    echo $OUTPUT->confirm(
        get_string('provision_course_confirm', 'block_panopto', format_string($course->fullname)),
        $confirmurl,
        new moodle_url($returnurl)
    );
} else {
    $mform->display();
}

echo $OUTPUT->footer();
/* End of file provision_course.php */
