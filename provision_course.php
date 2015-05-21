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
 * @copyright  Panopto 2009 - 2015 /With contributions from Spenser Jones (sjones@ambrose.edu)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');
require_once('lib/panopto_data.php');

global $courses;

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
        $aserverarray[$x] = $CFG->$thisservername;
        $appkeyarray[$x] = $CFG->$thisappkey;
    }
}

/**
 * Create form for server selection.
 *
 * @package block_panopto
 * @copyright  Panopto 2009 - 2015
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class panopto_provision_form extends moodleform {

    protected $title = '';
    protected $description = '';

    /**
     * Defines a panopto provision form
     */
    public function definition() {

        global $DB;
        global $aserverarray;

        $mform = & $this->_form;
        $coursesraw = $DB->get_records('course', null, '', 'id, shortname, fullname');
        $courses = array();
        if ($coursesraw) {
            foreach ($coursesraw as $course) {
                $courses[$course->id] = $course->shortname . ': ' . $course->fullname;
            }
        }
        asort($courses);

        $serverselect = $mform->addElement('select', 'servers', 'Select a Panopto server', $aserverarray);
        $select = $mform->addElement('select', 'courses', get_string('provisioncourseselect', 'block_panopto'), $courses);
        $select->setMultiple(true);
        $select->setSize(32);
        $mform->addHelpButton('courses', 'provisioncourseselect', 'block_panopto');

        $this->add_action_buttons(true, 'Provision');
    }

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

$urlparams['return_url'] = $returnurl;

$PAGE->set_url('/blocks/panopto/provision_course.php', $urlparams);
$PAGE->set_pagelayout('base');

$mform = new panopto_provision_form($PAGE->url);

if ($mform->is_cancelled()) {
    redirect(new moodle_url($returnurl));
} else {
    $provisiontitle = get_string('provision_courses', 'block_panopto');
    $PAGE->set_pagelayout('base');
    $PAGE->set_title($provisiontitle);
    $PAGE->set_heading($provisiontitle);

    if ($courseidparam != 0) {
        // Course context.
        require_capability('block/panopto:provision_course', $context);

        $courses = array($courseidparam);
        $editcourseurl = new moodle_url($returnurl);
        $PAGE->navbar->add(get_string('pluginname', 'block_panopto'), $editcourseurl);
    } else {
        // System context.
        require_capability('block/panopto:provision_multiple', $context);

        $data = $mform->get_data();
        if ($data) {
            $courses = $data->courses;
            $selectedserver = $aserverarray[$data->servers];
            $selectedkey = $appkeyarray[$data->servers];
            $CFG->servername = $selectedserver;
            $CFG->appkey = $selectedkey;
        }

        $manageblocks = new moodle_url('/admin/blocks.php');
        $panoptosettings = new moodle_url('/admin/settings.php?section=blocksettingpanopto');
        $PAGE->navbar->add(get_string('blocks'), $manageblocks);
        $PAGE->navbar->add(get_string('pluginname', 'block_panopto'), $panoptosettings);
    }

    $PAGE->navbar->add($provisiontitle, new moodle_url($PAGE->url));

    echo $OUTPUT->header();

    if ($courses) {
        $provisioned = array();
        $panoptodata = new panopto_data(null);
        foreach ($courses as $courseid) {
            if (empty($courseid)) {
                continue;
            }
            // Set the current Moodle course to retrieve info for / provision.
            $panoptodata->moodlecourseid = $courseid;

            // If an application key and server name are pre-set (happens when provisioning from multi-select page) use those, otherwise retrieve
            // values from the db.
            if (isset($selectedserver)) {
                $panoptodata->servername = $selectedserver;
            } else {
                $panoptodata->servername = $panoptodata->get_panopto_servername($panoptodata->moodlecourseid);
            }
            if (isset($selectedkey)) {
                $panoptodata->applicationkey = $selectedkey;
            } else {
                $panoptodata->applicationkey = $panoptodata->get_panopto_app_key($panoptodata->moodlecourseid);
            }
            $provisioningdata = $panoptodata->get_provisioning_info();
            $provisioneddata = $panoptodata->provision_course($provisioningdata);
            include('views/provisioned_course.html.php');
        }
        echo "<a href='$returnurl'>Back to config</a>";
    } else {
        $mform->display();
    }

    echo $OUTPUT->footer();
}

/**
 *Returns true if a string is null or empty, false otherwise
 */
function is_null_or_empty_string($name) {
    return (!isset($name) || trim($name) === '');
}

/* End of file provision_course.php */