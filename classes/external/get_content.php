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
 * Panopto block external service for getting Panopto content.
 *
 * @package block_panopto
 * @copyright  Panopto 2025
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_panopto\external;

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__) . '/../../lib/panopto_data.php');

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_value;
use core_external\external_single_structure;

/**
 * External service for getting Panopto content.
 *
 * @package block_panopto
 * @copyright Panopto 2009 - 2025
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_content extends external_api {
    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters(
            ['courseid' => new external_value(PARAM_INT, 'Course ID')]
        );
    }

    /**
     * Returns description of method result value
     *
     * The block's data rather than its markup: the block_panopto/block_content template renders
     * it and escapes every value. Links are '' when Panopto gave none that is safe to link to.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        $recording = [
            // Raw because the template escapes it, and cleaning would change a name such as "<div> vs <span>".
            'name' => new external_value(PARAM_RAW, 'Session name, as plain text'),
            'viewerurl' => new external_value(PARAM_URL, 'Viewer link, opened through Panopto SSO'),
            'alt' => new external_value(PARAM_BOOL, 'Whether the row has the alternate background'),
        ];

        return new external_single_structure([
            'errors' => new external_multiple_structure(
                // Raw for the same reason; a site can customise these strings, with HTML too.
                new external_single_structure(['message' => new external_value(PARAM_RAW, 'Error message, as plain text')]),
                'Errors to show at the top of the block'
            ),
            'unprovisioned' => new external_value(PARAM_BOOL, 'Whether the course has no Panopto folder yet'),
            'provisionurl' => new external_value(PARAM_URL, 'Link to provision the course, for users who can'),
            'showcontent' => new external_value(PARAM_BOOL, 'Whether the folder was loaded and the lists below apply'),
            'instance' => new external_value(PARAM_RAW, 'Panopto instance name, posted with the SSO form'),
            'livesessions' => new external_multiple_structure(
                new external_single_structure($recording + [
                    'notesurl' => new external_value(PARAM_URL, 'Note taking link, opened through Panopto SSO'),
                ]),
                'Sessions broadcasting now'
            ),
            'recordings' => new external_multiple_structure(
                new external_single_structure($recording),
                'The first completed recordings, always shown'
            ),
            'morerecordings' => new external_multiple_structure(
                new external_single_structure($recording),
                'The remaining completed recordings, shown with "Show all"'
            ),
            'hasmorerecordings' => new external_value(PARAM_BOOL, 'Whether there are more recordings'),
            'audiopodcasturl' => new external_value(PARAM_RAW, 'Audio podcast link, http(s) or itpc'),
            'audiorssurl' => new external_value(PARAM_URL, 'Audio podcast RSS link'),
            'videopodcasturl' => new external_value(PARAM_RAW, 'Video podcast link, http(s) or itpc'),
            'videorssurl' => new external_value(PARAM_URL, 'Video podcast RSS link'),
            'feediconurl' => new external_value(PARAM_URL, 'Podcast feed icon'),
            'settingsurl' => new external_value(PARAM_URL, 'Panopto folder settings link, for users allowed to see it'),
            'showrecorderlinks' => new external_value(PARAM_BOOL, 'Whether to show the recorder downloads'),
            'windowsrecorderurl' => new external_value(PARAM_URL, 'Windows recorder download link'),
            'macrecorderurl' => new external_value(PARAM_URL, 'Mac recorder download link'),
        ]);
    }

    /**
     * Get Panopto content for a course
     * @param int $courseid Course ID
     * @return array the block's data, as described by execute_returns()
     */
    public static function execute($courseid) {
        $params = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid]);

        // The external API's own access check: login, course access and the page context together.
        self::validate_context(\context_course::instance($params['courseid']));

        // The Panopto SOAP calls below can be slow; release the session lock so this
        // request doesn't block the user's other page loads while it waits.
        \core\session\manager::write_close();

        $panoptodata = new \panopto_data($params['courseid']);

        return $panoptodata->get_block_content_data();
    }
}
