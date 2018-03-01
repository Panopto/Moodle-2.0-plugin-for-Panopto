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
 * the main config settings for the Panopto block
 *
 * @package block_panopto
 * @copyright  Panopto 2009 - 2016 /With contributions from Spenser Jones (sjones@ambrose.edu)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;
require_once(dirname(__FILE__) . '/classes/admin/trim_configtext.php');
global $CFG;

$numservers = get_config('block_panopto', 'server_number');
$numservers = isset($numservers) ? $numservers : 0;

// Increment numservers by 1 to take into account starting at 0.
++$numservers;

if ($ADMIN->fulltree) {

    $settings->add(
        new admin_setting_configselect(
            'block_panopto/server_number',
            get_string('block_panopto_server_number_name', 'block_panopto'),
            get_string('block_panopto_server_number_desc', 'block_panopto'),
            0,
            range(1, 10, 1)
        )
    );
    $settings->add(
        new  admin_setting_configtext_trimmed(
            'block_panopto/instance_name',
            get_string('block_global_instance_name', 'block_panopto'),
            get_string('block_global_instance_desc', 'block_panopto'),
            'moodle',
            PARAM_TEXT
        )
    );

    for ($serverwalker = 1; $serverwalker <= $numservers; ++$serverwalker) {
        $settings->add(
            new admin_setting_configtext_trimmed(
                'block_panopto/server_name' . $serverwalker,
                get_string('block_global_hostname', 'block_panopto') . ' ' . $serverwalker,
                get_string('block_global_hostname_desc', 'block_panopto'),
                '',
                PARAM_TEXT
            )
        );
        $settings->add(
            new admin_setting_configtext_trimmed(
                'block_panopto/application_key' . $serverwalker,
                get_string('block_global_application_key', 'block_panopto') . ' ' . $serverwalker,
                get_string('block_global_application_key_desc', 'block_panopto'),
                '',
                PARAM_TEXT
            )
        );
    }
    $settings->add(
        new admin_setting_configcheckbox(
            'block_panopto/sync_after_provisioning',
            get_string('block_panopto_sync_after_provisioning', 'block_panopto'),
            get_string('block_panopto_sync_after_provisioning_desc', 'block_panopto'),
            0
        )
    );
    $settings->add(
        new admin_setting_configcheckbox(
            'block_panopto/sync_on_enrolment',
            get_string('block_panopto_sync_on_enrolment', 'block_panopto'),
            get_string('block_panopto_sync_on_enrolment_desc', 'block_panopto'),
            0
        )
    );
    $settings->add(
        new admin_setting_configcheckbox(
            'block_panopto/async_tasks',
            get_string('block_panopto_async_tasks', 'block_panopto'),
            get_string('block_panopto_async_tasks_desc', 'block_panopto'),
            1
        )
    );
    $settings->add(
        new admin_setting_configcheckbox(
            'block_panopto/prefix_new_folder_names',
            get_string('block_panopto_prefix_new_folder_shortnames', 'block_panopto'),
            get_string('block_panopto_prefix_new_folder_shortnames_desc', 'block_panopto'),
            1
        )
    );
    $settings->add(
        new admin_setting_configcheckbox(
            'block_panopto/auto_provision_new_courses',
            get_string('block_panopto_auto_provision', 'block_panopto'),
            get_string('block_panopto_auto_provision_desc', 'block_panopto'),
            1
        )
    );
    $settings->add(
        new admin_setting_configcheckbox(
            'block_panopto/auto_sync_imports',
            get_string('block_panopto_auto_sync_imports', 'block_panopto'),
            get_string('block_panopto_auto_sync_imports_desc', 'block_panopto'),
            1
        )
    );
    $settings->add(
        new admin_setting_configcheckbox(
            'block_panopto/check_server_status',
            get_string('block_panopto_check_server_status', 'block_panopto'),
            get_string('block_panopto_check_server_status_desc', 'block_panopto'),
            0
        )
    );
    $settings->add(
        new admin_setting_configcheckbox(
            'block_panopto/print_log_to_file',
            get_string('block_panopto_print_log_to_file', 'block_panopto'),
            get_string('block_panopto_print_log_to_file_desc', 'block_panopto'),
            0
        )
    );
    $settings->add(
        new admin_setting_configcheckbox(
            'block_panopto/print_verbose_logs',
            get_string('block_panopto_print_verbose_logs', 'block_panopto'),
            get_string('block_panopto_print_verbose_logs_desc', 'block_panopto'),
            0
        )
    );
    $settings->add(
        new admin_setting_configcheckbox(
            'block_panopto/anyone_view_recorder_links',
            get_string('block_panopto_anyone_view_recorder_links', 'block_panopto'),
            get_string('block_panopto_anyone_view_recorder_links_desc', 'block_panopto'),
            0
        )
    );
    $settings->add(
        new admin_setting_configcheckbox(
            'block_panopto/any_creator_can_view_folder_settings',
            get_string('block_panopto_any_creator_can_view_folder_settings', 'block_panopto'),
            get_string('block_panopto_any_creator_can_view_folder_settings_desc', 'block_panopto'),
            0
        )
    );

    $systemcontext = context_system::instance();
    $systemrolearray = get_assignable_roles($systemcontext, ROLENAME_BOTH);

    $settings->add(
        new admin_setting_configmultiselect(
            'block_panopto/publisher_system_role_mapping',
            get_string('block_panopto_publisher_system_role_mapping', 'block_panopto'),
            get_string('block_panopto_publisher_system_role_mapping_desc', 'block_panopto'),
            array(1),
            $systemrolearray
        )
    );

    $coursecontext = context_course::instance(SITEID);
    $courserolearray = get_assignable_roles($coursecontext, ROLENAME_BOTH);

    $settings->add(
        new admin_setting_configmultiselect(
            'block_panopto/publisher_role_mapping',
            get_string('block_panopto_publisher_mapping', 'block_panopto'),
            get_string('block_panopto_publisher_mapping_desc', 'block_panopto'),
            array(1),
            $courserolearray
        )
    );

    $settings->add(
        new admin_setting_configmultiselect(
            'block_panopto/creator_role_mapping',
            get_string('block_panopto_creator_mapping', 'block_panopto'),
            get_string('block_panopto_creator_mapping_desc', 'block_panopto'),
            array(3, 4),
            $courserolearray
        )
    );

    $link = '<a id="panopto_provision_course_btn" href="' . $CFG->wwwroot . '/blocks/panopto/provision_course.php">' .
        get_string('block_global_add_courses', 'block_panopto') . '</a>';

    $settings->add(new admin_setting_heading('block_panopto_add_courses', '', $link));

    $importlink = '<a id="panopto_reinitialize_imports_btn" href="' . $CFG->wwwroot . '/blocks/panopto/reinitialize_imports.php">' .
        get_string('block_global_reinitialize_all_imports', 'block_panopto') . '</a>';

    $settings->add(new admin_setting_heading('block_panopto_reinitialize_all_imports', '', $importlink));

    $upgradelink = '<a id="panopto_upgrade_folders_btn" href="' . $CFG->wwwroot . '/blocks/panopto/upgrade_all_folders.php">' .
        get_string('block_global_upgrade_all_folders', 'block_panopto') . '</a>';

    $settings->add(new admin_setting_heading('block_panopto_upgrade_all_folders', '', $upgradelink));
}
/* End of file settings.php */
