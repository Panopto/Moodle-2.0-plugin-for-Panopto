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
 * Event triggered when a course is mapped to a Panopto folder, or mapped to a different one.
 *
 * Re-provisioning a course to the folder it is already mapped to does not trigger it.
 *
 * @property-read array $other {
 *      Extra information about the event.
 *
 *      - string servername: the Panopto server the course is provisioned on.
 *      - string folderid: the id of the Panopto folder the course is mapped to.
 * }
 *
 * @package    block_panopto
 * @copyright  Panopto 2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_provisioned extends \core\event\base {
    /**
     * Init method.
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Returns localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event_course_provisioned', 'block_panopto');
    }

    /**
     * Returns non-localised event description.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '{$this->userid}' provisioned the course with id '{$this->courseid}' " .
            "to the Panopto folder '{$this->other['folderid']}' on '{$this->other['servername']}'.";
    }

    /**
     * Returns the course URL.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/course/view.php', ['id' => $this->courseid]);
    }

    /**
     * Validates the event data.
     *
     * @throws \coding_exception
     */
    protected function validate_data() {
        parent::validate_data();

        if ($this->contextlevel != CONTEXT_COURSE) {
            throw new \coding_exception('Context level must be CONTEXT_COURSE.');
        }

        foreach (['servername', 'folderid'] as $key) {
            if (!isset($this->other[$key])) {
                throw new \coding_exception("The '{$key}' value must be set in other.");
            }
        }
    }

    /**
     * The other values are Panopto values, which a course restore does not change.
     *
     * @return bool
     */
    public static function get_other_mapping() {
        return false;
    }
}
