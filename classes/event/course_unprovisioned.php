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

namespace block_panopto\event;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded
defined('MOODLE_INTERNAL') || die();

/**
 * Event triggered when a course's Panopto folder mapping is removed.
 *
 * The event is in the course context, or in the system context when the course no longer exists.
 *
 * @property-read array $other {
 *      Extra information about the event.
 *
 *      - int courseid: the id of the unprovisioned course.
 *      - string servername: the Panopto server the course was provisioned on.
 * }
 *
 * @package    block_panopto
 * @copyright  Panopto 2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_unprovisioned extends \core\event\base {
    /**
     * Init method.
     */
    protected function init() {
        $this->data['crud'] = 'd';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Returns localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event_course_unprovisioned', 'block_panopto');
    }

    /**
     * Returns non-localised event description.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '{$this->userid}' unprovisioned the course with id '{$this->other['courseid']}' " .
            "from '{$this->other['servername']}'.";
    }

    /**
     * Validates the event data.
     *
     * @throws \coding_exception
     */
    protected function validate_data() {
        parent::validate_data();

        if (!isset($this->other['courseid'])) {
            throw new \coding_exception("The 'courseid' value must be set in other.");
        }

        // A course whose mapping lost its server can still be unprovisioned, so servername may be null.
        if (!array_key_exists('servername', $this->other)) {
            throw new \coding_exception("The 'servername' value must be set in other.");
        }
    }

    /**
     * Maps the course id in other during a course log restore.
     *
     * @return array
     */
    public static function get_other_mapping() {
        return [
            'courseid' => ['db' => 'course', 'restore' => 'course'],
        ];
    }
}
