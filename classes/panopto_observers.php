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
 * Panopto observers
 *
 * @package    block_panopto
 * @category   block
 * @copyright  University of Kent
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_panopto;
defined('MOODLE_INTERNAL') || die();

/**
 * Panopto observers
 *
 * @package    block_panopto
 * @copyright  University of Kent
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class panopto_observers {
    /**
     * A user enrollment has occurred.
     *
     * @param \core\event\base $event The event.
     * @return void
     */
    public static function user_enrolment_created(\core\event\user_enrolment_created $event) {
    	global $DB;

    	// Are we already due to update this course?
    	if ($DB->record_exists('panopto_course_update_list', array('courseid' => $event->courseid))) {
    		return true;
    	}

    	// Add to course update list
		$update_course = new \stdClass;
		$update_course->courseid = $event->courseid;
		$DB->insert_record('panopto_course_update_list', $update_course);

    	return true;
    }
}