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
 * This file contains all of the language strings needed by Panopto.
 *
 * @package block_panopto
 * @copyright  Panopto 2009 - 2016 with contributions from Spenser Jones (sjones@ambrose.edu)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['add_to_panopto'] = 'Add this course to Panopto (re-add to sync user lists)';
$string['application_key'] = 'Application key';
$string['attempted_moodle_course_id'] = 'Attempted Moodle course ID';
$string['attempted_panopto_server'] = 'Atempted Panopto Server';
$string['api_manager_unavailable'] = 'Unable to create the {$a} manager api client! (Is the Panopto server available, if so are the instance name and application key correct?)';
$string['back_to_config'] = 'Back to config';
$string['back_to_course'] = 'Back to course';
$string['block_edit_error'] = 'Cannot configure block instance: Global configuration incomplete. Please contact your system administrator.';
$string['block_edit_header'] = 'Select the Panopto course folder to display in this block.';
$string['block_edit_header_help'] = 'Choose an existing Panopto course folder or provision a new Panopto course folder for this block';
$string['block_global_add_courses'] = 'Add Moodle courses to Panopto';
$string['block_global_application_key'] = 'Application Key';
$string['block_global_application_key_desc'] = 'Enter the Application Key from the Panopto Identity Providers page.';
$string['block_panopto_publisher_system_role_mapping'] = 'System roles with provisioning permissions';
$string['block_panopto_publisher_system_role_mapping_desc'] = 'Select which system roles can provision new Panopto course folders.';
$string['block_panopto_publisher_mapping'] = 'Publisher role mapping';
$string['block_panopto_publisher_mapping_desc'] = 'Select which course roles are set as publishers in new Panopto course folders.';
$string['block_panopto_creator_mapping'] = 'Creator role mapping';
$string['block_panopto_creator_mapping_desc'] = 'Select which course roles are set as creators in new Panopto course folders.';
$string['block_panopto_non_editing_teacher_provision'] = 'Allow non-editing teacher to provision';
$string['block_panopto_non_editing_teacher_provision_desc'] = 'Enable this option to allow non-editing teachers to provision Panopto course folders.';
$string['block_panopto_auto_sync_imports'] = 'Automatically grant permissions when importing a course';
$string['block_panopto_auto_sync_imports_desc'] = 'Enable this option to allow Panopto to automatically grant viewer permissions when importing a course.';
$string['block_global_hostname'] = 'Panopto Server Hostname';
$string['block_global_hostname_desc'] = 'Enter the FQDN of your Panopto server.';
$string['block_global_instance_desc'] = 'Enter the Instance Name from the Panopto Identity Providers page.';
$string['block_global_instance_name'] = 'Moodle Instance Name';
$string['block_panopto_server_number_desc'] = 'Click \'Save Changes\' to update number of servers.';
$string['block_panopto_server_number_name'] = 'Number of Panopto Servers';
$string['block_panopto_auto_provision'] = 'Automatically provision newly created courses';
$string['block_panopto_auto_provision_desc'] = 'Enable this option to automatically provision a Panopto course folder when a course is created.';
$string['block_panopto_prefix_new_folder_shortnames'] = 'Prefix new Panopto folder display names with the Moodle course\'s shortname.';
$string['block_panopto_prefix_new_folder_shortnames_desc'] = 'Enable this option to add the provisioned course shortname to the beginning of newly made Panopto folder\'s display name.';
$string['completed_recordings'] = 'Completed Recordings';
$string['course'] = 'Course';
$string['course_name'] = 'Course Name';
$string['course_settings'] = 'Course Settings';
$string['creator'] = 'Creator';
$string['creator_help'] = 'A Creator can create and edit content in Panopto';
$string['creators'] = 'Creators';
$string['download_recorder'] = 'Download Recorder';
$string['error_retrieving'] = 'Error retrieving Panopto course folder.';
$string['no_access'] = 'You do not have access to view this Panopto folder.';
$string['existing_course'] = 'Select an existing Panopto folder:';
$string['fetching_content'] = 'Fetching Panopto Content...';
$string['folder_not_found_error'] = 'The folder currenty provisioned to the target Moodle course on the Panopto server could not be found, was it deleted? Provisioning will continue by linking to the default Moodle folder or creating one if it does not already exist.';
$string['import_not_mapped'] = 'The imported course was not provisioned to a Panopto folder! (no session_group_id set in block_panopto_foldermap)';
$string['links'] = 'Links';
$string['live_sessions'] = 'Live Sessions';
$string['missing_required_version'] = 'API call failed to return a response, this could be because the Panopto server you attempted to use does not meet the minimum required version to support this version of the Moodle Panopto Block. This could also be caused by the server being unavailable.';
$string['moodle_course_not_exist'] = 'Moving row to old foldermap, course did not exist inside Moodle.';
$string['no_completed_recordings'] = 'No Completed Recordings';
$string['no_course_selected'] = 'No Panopto course folder selected';
$string['no_creators'] = 'No creators.';
$string['no_live_sessions'] = 'No Live Sessions';
$string['no_publishers'] = 'No publishers.';
$string['no_server'] = 'There are no servers set up for provisioning. Please contact system administrator.';
$string['no_students'] = 'No students.';
$string['no_users_synced'] = 'No Users synced with folder yet';
$string['no_users_synced_desc'] = 'With new API all users enrolled in this course will be synced with Panopto when they login to Moodle.';
$string['or'] = 'OR';
$string['panopto_server_error'] = 'Panopto server {$a} returned with server error, will try again on next sign in.';
$string['panopto:addinstance'] = 'Add a new Panopto block';
$string['panopto:myaddinstance'] = 'Add a new Panopto block to my page';
$string['panopto:provision_aspublisher'] = 'Provision as a publisher';
$string['panopto:provision_asteacher'] = 'Provision as a teacher';
$string['panopto:provision_course'] = 'Provision a course';
$string['panopto:provision_multiple'] = 'Provision multiple courses at once';
$string['pluginname'] = 'Panopto';
$string['podcast_audio'] = 'Audio Podcast';
$string['podcast_feeds'] = 'Podcast Feeds';
$string['podcast_video'] = 'Video Podcast';
$string['provision'] = 'Provision';
$string['provision_course_link_text'] = 'Provision Course';
$string['provision_access_error'] = 'Course already provisioned to a Panopto folder and the current user does not have access to perform operations on that folder (User needs at least viewer access to target Panopto folder).';
$string['provision_courses'] = 'Provision Courses';
$string['provision_error'] = 'Error provisioning course.';
$string['provision_successful'] = 'Successfully provisioned course';
$string['provisioncourseselect'] = 'Select Courses to Provision.';
$string['provisioncourseselect_help'] = 'Multiple selections are possible by Ctrl-clicking (Windows) or Cmd-clicking (Mac).';
$string['publisher'] = 'Publisher';
$string['publisher_help'] = 'A Publisher can approve content submitted by Creators';
$string['publishers'] = 'Publishers';
$string['removing_invalid_folder_row'] = "Moodle course was deleted from Moodle or the link to Panopto does not contain all of the necessary info to connect to this folder. Moving foldermap entry to old_foldermap for user reference.\n";
$string['reprovision_course_link_text'] = 'Reprovision Course';
$string['result'] = 'Result';
$string['require_panopto_version_title'] = 'Minimum Panopto version required for this version of the Moodle Panopto block';
$string['missing_moodle_required_version'] = 'Panopto block requires a Moodle version newer than {$a->requiredversion}, your current Moodle version is: {$a->currentversion}';
$string['role_map_header'] = 'Change Panopto Role Mappings';
$string['role_map_header_help'] = "Choose how Moodle roles map to Panopto roles. Unmapped Moodle roles will be assigned the Viewer role in Panopto";
$string['select_server'] = 'Select a Panopto server';
$string['select_server_help'] = 'Choose the Panopto server where the course folders will be provisioned.';
$string['server_name'] = 'Server name';
$string['server_not_available'] = 'The Panopto server {$a} was not available. Server may be down';
$string['server_info_not_valid'] = 'The server name or application key are not valid, below are attempted values.';
$string['show_all'] = 'Show All';
$string['show_less'] = 'Show Less';
$string['students'] = 'Students';
$string['take_notes'] = 'Take Notes';
$string['unconfigured'] = 'Global configuration incomplete. Please contact your system administrator.';
$string['unknown_provisioning_error'] = "An unknown error has occurred.";
$string['unprovisioned'] = 'This course has not yet been provisioned.';
$string['upgrade_provision_access_error'] = 'UPGRADE BLOCKED: The user {$a} does not have access to a provisioned Panopto course folder. Upgrading user must have at least viewer access to all Panopto course folders. It is highly reccommended that the upgrading user is an Administrator on Panopto.';
$string['upgrade_panopto_required_version'] = 'A Panopto server you are using does not meet the minimum required version to support this version of the Moodle Panopto Block. The upgrade will be blocked from succeeding until all Panopto servers in use meet miniumum version requirements.';
$string['watch_live'] = 'Watch Live';
/* End of file block_panopto.php */
