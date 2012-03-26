<?php
/* Copyright Panopto 2009 - 2011 / With contributions from Spenser Jones (sjones@ambrose.edu)
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

require_once("lib/panopto_data.php");

class block_panopto_edit_form extends block_edit_form {
    protected function specific_definition($mform) {
        global $COURSE, $CFG;

        // Construct the Panopto data proxy object
        $panopto_data = new panopto_data($COURSE->id);

        if(!empty($panopto_data->servername) && !empty($panopto_data->instancename) && !empty($panopto_data->applicationkey)) {
            $mform->addElement('header', 'configheader', get_string('block_edit_header', 'block_panopto'));

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
        } else {
            $mform->addElement('static', 'error', '', get_string('block_edit_error', 'block_panopto'));
        }
    }
}
