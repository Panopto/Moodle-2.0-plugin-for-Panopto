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
 * Panopto block golobal setings.
 *
 * @package     block_panopto
 * @copyright   Panopto 2009 - 2013 / With contributions from Spenser Jones (sjones@ambrose.edu)
 * @license     http://www.gnu.org/licenses/lgpl.html GNU LGPL
 */

defined('MOODLE_INTERNAL') || die;
global $CFG;
global $numservers;
$numservers = $CFG->block_panopto_server_number;

if ($ADMIN->fulltree) {
    $_SESSION['numservers'] = $numservers;

    // Up to 10 servers can be added.
    $servernumberchoices = array_combine(range(1, 10), range(1, 10));
    $servernumberdefault = 1;
    $settings->add(new admin_setting_configselect('block_panopto_server_number',
            get_string('block_global_servernumber', 'block_panopto'), get_string('block_global_servernumber_description', 'block_panopto'), $servernumberdefault, $servernumberchoices));

    $settings->add(new admin_setting_configtext('block_panopto_instance_name',
            get_string('block_global_instance_name', 'block_panopto'),
            get_string('block_global_instance_description', 'block_panopto'), 'moodle', PARAM_TEXT));

    $numservers = (isset($CFG->block_panopto_server_number)) ? (int)$CFG->block_panopto_server_number : $servernumberdefault;
    for ($x = 1; $x <= $numservers; $x++) {
        $settings->add(new admin_setting_configtext('block_panopto_server_name' . $x,
                get_string('block_global_hostname', 'block_panopto') . " " . $x, '', '', PARAM_TEXT));

        $settings->add(new admin_setting_configtext('block_panopto_application_key' . $x,
                get_string('block_global_application_key', 'block_panopto') . " " . $x, '', '', PARAM_TEXT));
    }

    $settings->add(new admin_setting_configcheckbox('block_panopto_async_tasks',
            get_string('block_panopto_async_tasks', 'block_panopto'), '', 0));

    $link = '<a href="' . $CFG->wwwroot . '/blocks/panopto/provision_course.php">' . get_string('block_global_add_courses', 'block_panopto') . '</a>';
    $settings->add(new admin_setting_heading('block_panopto_add_courses', '', $link));
}
