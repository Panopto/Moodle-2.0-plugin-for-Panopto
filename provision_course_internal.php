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

/**
 * Panopto block course provisioning routine.
 *
 * @package     block_panopto
 * @copyright   Panopto 2009 - 2013 / With contributions from Spenser Jones (sjones@ambrose.edu)
 * @license     http://www.gnu.org/licenses/lgpl.html GNU LGPL
 */

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');
require_once('lib/panopto_data.php');
require_once('lib.php');

//Create form for server selection
class panopto_provision_form extends moodleform {

    function definition() {
        global $DB;

        $mform = & $this->_form;

        $serverhostnames = block_panopto_get_servers();
        $serverselect = $mform->addElement('select', 'servers', 'Select a Panopto server', $serverhostnames);

        $this->add_action_buttons(true, 'Provision');
    }

}

require_login();


//This page requires a course ID to be passed in as a param. If accessed directly without clicking on a link for the course,
//no id is passed and the script fails. Similarly if no ID is passed with via a link (should never happen) the script will fail
$course_id = required_param('id', PARAM_INT);

//course context
$context = context_course::instance($course_id, MUST_EXIST);
$PAGE->set_context($context);

//Return URL is course page
$return_url = optional_param('return_url', $CFG->wwwroot . '/course/view.php?id=' . $course_id, PARAM_LOCALURL);
$urlparams['return_url'] = $return_url;
$PAGE->set_url('/blocks/panopto/provision_course_internal.php?id=' . $course_id, $urlparams);
$PAGE->set_pagelayout('base');


$mform = new panopto_provision_form($PAGE->url);
$serverhostnames = block_panopto_get_servers();
$courses = array();

if ($mform->is_cancelled()) {
    redirect(new moodle_url($return_url));
} else {
    //set Moodle page info
    $provision_title = get_string('provision_courses', 'block_panopto');
    $PAGE->set_pagelayout('base');
    $PAGE->set_title($provision_title);
    $PAGE->set_heading($provision_title);
    // Course context
    require_capability('block/panopto:provision_course', $context);
    $edit_course_url = new moodle_url($return_url);
    $PAGE->navbar->add(get_string('pluginname', 'block_panopto'), $edit_course_url);
    $data = $mform->get_data();

    // If there is form data, use it to determine the server and app key to provision to.
    if ($data) {
        $selectedserver = get_config('block_panopto', 'server_name' . $data->servers);
        $selectedkey = get_config('block_panopto', 'application_key' . $data->servers);
    } else if (count($serverhostnames) == 1) {
        // If only one server, simply provision with that server.
        $servernumber = key($serverhostnames);
        $selectedserver = get_config('block_panopto', 'server_name' . $servernumber);
        $selectedkey = get_config('block_panopto', 'application_key' . $servernumber);
    }

    $manage_blocks = new moodle_url('/admin/blocks.php');
    $panopto_settings = new moodle_url('/admin/settings.php?section=blocksettingpanopto');
    $PAGE->navbar->add(get_string('blocks'), $manage_blocks);
    $PAGE->navbar->add(get_string('pluginname', 'block_panopto'), $panopto_settings);
    $PAGE->navbar->add($provision_title, new moodle_url($PAGE->url));
    echo $OUTPUT->header();

    //If there are no servers specified for provisioning, give a failure notice and allow user to return to course page
    if (empty($serverhostnames)) {
        echo "There are no servers set up for provisioning. Please contact system administrator. 
        <br/>
        <a href='$return_url'>Back to course</a>";
    } else if (isset($selectedserver)) {
        //If a $selected server is set, it means that a server has been chosen and that the provisioning should be done instead of
        //loading the selection form
        $provisioned = array();
        $panopto_data = new panopto_data(null);

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
        echo "<a href='$return_url'>Back to course</a>";
    } else {
        $mform->display();
    }

    echo $OUTPUT->footer();
}