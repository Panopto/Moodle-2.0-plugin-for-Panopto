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

defined('MOODLE_INTERNAL') || die();

require_once("lib/panopto_data.php");
require_once(dirname(__FILE__) . '/../../lib/accesslib.php');

/**
 * Form for modifying Panopto block's per-course config settings.
 *
 * @package block_panopto
 * @copyright  Panopto 2009 - 2015
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_panopto_edit_form extends block_edit_form {

    /**
     * Core function for creation of form defined in block_panopto_edit_form class
     */
    protected function specific_definition($mform) {
        global $COURSE, $CFG;

        // Construct the Panopto data proxy object.
        $panoptodata = new panopto_data($COURSE->id);

        if (!empty($panoptodata->servername) && !empty($panoptodata->instancename) && !empty($panoptodata->applicationkey)) {
            $mform->addElement('header', 'configheader', get_string('block_edit_header', 'block_panopto'));

            $params = new stdClass;
            $params->course_id = $COURSE->id;
            $params->return_url = $_SERVER['REQUEST_URI'];
            $querystring = http_build_query($params, '', '&');
            $provisionurl = "$CFG->wwwroot/blocks/panopto/provision_course.php?" . $querystring;

            $addtopanopto = get_string('add_to_panopto', 'block_panopto');
            $or = get_string('or', 'block_panopto');
            $mform->addElement('html', "<a href='$provisionurl'>$addtopanopto</a><br><br>-- $or --<br><br>");

            $courselist = $panoptodata->get_course_options();
            $mform->addElement('selectgroups', 'config_course', get_string('existing_course', 'block_panopto'), $courselist['courses']);
            $mform->setDefault('config_course', $courselist['selected']);

            // Set course context to get roles.
            $context = context_course::instance($COURSE->id);

            // Get current role mappings.
            $currentmappings = $panoptodata->get_course_role_mappings($COURSE->id);

            // Get roles that current user may assign in this course.
            $currentcourseroles = get_assignable_roles($context, $rolenamedisplay = ROLENAME_ALIAS, $withusercounts = false, $user = null);
            while ($role = current($currentcourseroles)) {
                $rolearray[key($currentcourseroles)] = $currentcourseroles[key($currentcourseroles)];
                next($currentcourseroles);
            }

            $mform->addElement('header', 'rolemapheader', get_string('role_map_header', 'block_panopto'));
            $mform->addElement('html', get_string('role_map_info_text', 'block_panopto'));

            $createselect = $mform->addElement('select', 'config_creator', 'Creator', $rolearray, null);
            $createselect->setMultiple(true);

            // Set default selected to previous setting.
            if (!empty($currentmappings['creator'])) {
                $createselect->setSelected($currentmappings['creator']);
            }
            $pubselect = $mform->addElement('select', 'config_publisher', 'Publisher', $rolearray, null);
            $pubselect->setMultiple(true);

            // Set default selected to previous setting.
            if (!empty($currentmappings['publisher'])) {
                $pubselect->setSelected($currentmappings['publisher']);
            }
        } else {
            $mform->addElement('static', 'error', '', get_string('block_edit_error', 'block_panopto'));
        }
    }

}

/* End of file edit_form.php */
