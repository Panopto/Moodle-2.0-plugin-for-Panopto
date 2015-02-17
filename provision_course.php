<?php

/* Copyright Panopto 2009 - 2013 / With contributions from Spenser Jones (sjones@ambrose.edu)
 *
 * This file is part of the Panopto plugin for Moodle.
 *
 * The Panopto plugin for Moodle is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * The Panopto plugin for Moodle is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with the Panopto plugin for Moodle.  If not, see <http://www.gnu.org/licenses/>.
 */

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');
require_once('lib/panopto_data.php');

global $courses;

//Populate list of servernames to select from
$aserverArray = array();
$appKeyArray = array();
if (isset($_SESSION['numservers'])) {
    $maxval = $_SESSION['numservers'];
} else {
    $maxval = 1;
}
for ($x = 0; $x < $maxval; $x++) {
    //generate strings corresponding to potential servernames in $CFG
    $thisServerName = 'block_panopto_server_name' . ($x + 1);
    $thisAppKey = 'block_panopto_application_key' . ($x + 1);
    if ((isset($CFG->$thisServerName) && !IsNullOrEmptyString($CFG->$thisServerName)) && (!IsNullOrEmptyString($CFG->$thisAppKey))) {
        $aserverArray[$x] = $CFG->$thisServerName;
        $appKeyArray[$x] = $CFG->$thisAppKey;
    }
}

class panopto_provision_form extends moodleform {

    protected $title = '';
    protected $description = '';

    function definition() {

        global $DB;
        global $aserverArray;

        $mform = & $this->_form;
        $courses_raw = $DB->get_records('course', null, '', 'id, shortname, fullname');
        $courses = array();
        if ($courses_raw) {
            foreach ($courses_raw as $course) {
                $courses[$course->id] = $course->shortname . ': ' . $course->fullname;
            }
        }
        asort($courses);

        $serverselect = $mform->addElement('select', 'servers', 'Select a Panopto server', $aserverArray);
        $select = $mform->addElement('select', 'courses', get_string('provisioncourseselect', 'block_panopto'), $courses);
        $select->setMultiple(true);
        $select->setSize(32);
        $mform->addHelpButton('courses', 'provisioncourseselect', 'block_panopto');

        $this->add_action_buttons(true, 'Provision');
    }

}

require_login();


// Set course context if we are in a course, otherwise use system context.
$course_id_param = optional_param('course_id', 0, PARAM_INT);
if ($course_id_param != 0) {
    $context = context_course::instance($course_id_param, MUST_EXIST);
} else {
    $context = context_system::instance();
}

$PAGE->set_context($context);

$return_url = optional_param('return_url', $CFG->wwwroot . '/admin/settings.php?section=blocksettingpanopto', PARAM_LOCALURL);

$urlparams['return_url'] = $return_url;

$PAGE->set_url('/blocks/panopto/provision_course.php', $urlparams);
$PAGE->set_pagelayout('base');

$mform = new panopto_provision_form($PAGE->url);

if ($mform->is_cancelled()) {
    redirect(new moodle_url($return_url));
} else {
    $provision_title = get_string('provision_courses', 'block_panopto');
    $PAGE->set_pagelayout('base');
    $PAGE->set_title($provision_title);
    $PAGE->set_heading($provision_title);

    if ($course_id_param != 0) {
        // Course context
        require_capability('block/panopto:provision_course', $context);

        $courses = array($course_id_param);
        $edit_course_url = new moodle_url($return_url);
        $PAGE->navbar->add(get_string('pluginname', 'block_panopto'), $edit_course_url);
    } else {
        // System context
        require_capability('block/panopto:provision_multiple', $context);

        $data = $mform->get_data();
        if ($data) {
            $courses = $data->courses;
            $selectedserver = $aserverArray[$data->servers];
            $selectedkey = $appKeyArray[$data->servers];
            $CFG->servername = $selectedserver;
            $CFG->appkey = $selectedkey;
        }

        $manage_blocks = new moodle_url('/admin/blocks.php');
        $panopto_settings = new moodle_url('/admin/settings.php?section=blocksettingpanopto');
        $PAGE->navbar->add(get_string('blocks'), $manage_blocks);
        $PAGE->navbar->add(get_string('pluginname', 'block_panopto'), $panopto_settings);
    }

    $PAGE->navbar->add($provision_title, new moodle_url($PAGE->url));

    echo $OUTPUT->header();

    if ($courses) {
        $provisioned = array();
        $panopto_data = new panopto_data(null);
        foreach ($courses as $course_id) {
            if (empty($course_id)) {
                continue;
            }
            // Set the current Moodle course to retrieve info for / provision.
            $panopto_data->moodle_course_id = $course_id;

            //If an application key and server name are pre-set (happens when provisioning from multi-select page) use those, otherwise retrieve
            //values from the db.
            if (isset($selectedserver)) {
                $panopto_data->servername = $selectedserver;
            } else {
                $panopto_data->servername = $panopto_data->get_panopto_servername($panopto_data->moodle_course_id);
            }
            if (isset($selectedkey)) {
                $panopto_data->applicationkey = $selectedkey;
            } else {
                $panopto_data->applicationkey = $panopto_data->get_panopto_app_key($panopto_data->moodle_course_id);
            }
            $provisioning_data = $panopto_data->get_provisioning_info();
            $provisioned_data = $panopto_data->provision_course($provisioning_data);
            include 'views/provisioned_course.html.php';
        }
        echo "<a href='$return_url'>Back to config</a>";
    } else {
        $mform->display();
    }

    echo $OUTPUT->footer();
}

function IsNullOrEmptyString($name) {
    return (!isset($name) || trim($name) === '');
}
/* End of file provision_course.php */
