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

require_once($CFG->libdir . '/externallib.php');
require_once(dirname(__FILE__) . '/../../lib/panopto_data.php');

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;

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
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_value(PARAM_RAW, 'HTML content');
    }

    /**
     * Get Panopto content for a course
     * @param int $courseid Course ID
     * @return string HTML content
     */
    public static function execute($courseid) {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid]);

        $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);
        require_login($course);

        // Construct the Panopto data proxy object.
        $panoptodata = new \panopto_data($params['courseid']);
        $content = new \stdClass();
        $content->text = '';

        // Get the Panopto content.
        $panoptocontent = $panoptodata->get_block_content();
        if (!empty($panoptocontent)) {
            $content->text = $panoptocontent;
        }

        return $content->text;
    }
}
