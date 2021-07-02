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
 * Panopto lti helper object. Contains info required for Panopto LTI tools to be used in text editors
 *
 * @package block_panopto
 * @copyright  Panopto 2021
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class panoptoblock_lti_utility {

    /**
     * Get the id of the pre-configured LTI tool that matched the Panopto server a course is provisioned to.
     * If multiple LTI tools are configured to a single server this will get the first one.
     *
     * @param int $courseid - the id of the course we are targeting in moodle.
     * @return int the id of the first matching tool
     */
    public static function get_course_tool($courseid) {
        global $DB;
        require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/mod/lti/locallib.php');

        $ltitooltypes = $DB->get_records('lti_types', null, 'name');

        $targetservername = null;

        $blockexists = $DB->get_record('block', array('name' => 'panopto'), 'name');
        if (!empty($blockexists)) {
            $targetservername = $DB->get_field('block_panopto_foldermap', 'panopto_server', array('moodleid' => $courseid));
        }

        // If the course if not provisioned with the Panopto block then get the default panopto server fqdn.
        if (empty($targetservername)) {
            $targetservername = get_config('block_panopto', 'automatic_operation_target_server');
        }

        foreach ($ltitooltypes as $type) {
            $type->config = lti_get_config(
                (object)[
                    'typeid' => $type->id,
                ]
            );

            if (!empty($targetservername) && strpos($type->config['toolurl'], $targetservername) !== false &&
                $type->state == LTI_TOOL_STATE_CONFIGURED) {
                $currentconfig = lti_get_type_config($type->id);

                if (!empty($currentconfig['customparameters']) &&
                    strpos($currentconfig['customparameters'], 'panopto_course_embed_tool') !== false) {
                    return $type;
                }
            }
        }

        return null;
    }
}
