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
 * Panopto block instance configuration form.
 *
 * @package     block_panopto
 * @copyright   Panopto 2009 - 2013 / With contributions from Spenser Jones (sjones@ambrose.edu)
 * @license     http://www.gnu.org/licenses/lgpl.html GNU LGPL
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Form for editing Panopto block instances.
 *
 * @copyright   Panopto 2009 - 2013 / With contributions from Spenser Jones (sjones@ambrose.edu)
 * @license     http://www.gnu.org/licenses/lgpl.html GNU LGPL
 */
class block_panopto_edit_form extends block_edit_form {

    protected function specific_definition($mform) {
        global $COURSE, $CFG;

        // Construct the Panopto data proxy object
        $panopto_data = new panopto_data($COURSE->id);

        if ($panopto_data->is_provisioned()) {
            $mform->addElement('header', 'configheader', get_string('block_edit_header', 'block_panopto'));

            $params = new stdClass;
            $params->course_id = $COURSE->id;
            $params->return_url = $_SERVER['REQUEST_URI'];
            $query_string = http_build_query($params, '', '&');
            $provision_url = "$CFG->wwwroot/blocks/panopto/provision_course.php?" . $query_string;

            $add_to_panopto = get_string('add_to_panopto', 'block_panopto');
            $or = get_string('or', 'block_panopto');
            $mform->addElement('html', "<a href='$provision_url'>$add_to_panopto</a><br><br>-- $or --<br><br>");

            $course_list = $panopto_data->get_course_options();
            $mform->addElement('selectgroups', 'config_course', get_string('existing_course', 'block_panopto'), $course_list['courses']);
            $mform->setDefault('config_course', $course_list['selected']);

            //set course context to get roles
            $context = context_course::instance($COURSE->id);

            //get current role mappings
            $current_mappings = $panopto_data->get_course_role_mappings($COURSE->id);

            //get roles that current user may assign in this course
            $current_course_roles = get_assignable_roles($context, $rolenamedisplay = ROLENAME_ALIAS, $withusercounts = false, $user = null);
            while ($role = current($current_course_roles)) {
                $rolearray[key($current_course_roles)] = $current_course_roles[key($current_course_roles)];
                next($current_course_roles);
            }

            $mform->addElement('header', 'rolemapheader', get_string('role_map_header', 'block_panopto'));
            $mform->addElement('html', get_string('role_map_info_text', 'block_panopto'));

            $createselect = $mform->addElement('select', 'config_creator', 'Creator', $rolearray, null);
            $createselect->setMultiple(true);
            //Set default selected to previous setting
            if (!empty($current_mappings['creator'])) {
                $createselect->setSelected($current_mappings['creator']);
            }
            $pubselect = $mform->addElement('select', 'config_publisher', 'Publisher', $rolearray, null);
            $pubselect->setMultiple(true);
            //Set default selected to previous setting
            if (!empty($current_mappings['publisher'])) {
                $pubselect->setSelected($current_mappings['publisher']);
            }
        } else {
            $mform->addElement('static', 'error', '', get_string('block_edit_error', 'block_panopto'));
        }
    }
}