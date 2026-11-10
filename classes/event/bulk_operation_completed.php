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
 * Event triggered when a site-wide Panopto operation finishes, from the web page or the CLI.
 *
 * @property-read array $other {
 *      Extra information about the event.
 *
 *      - string operation: one of the values in self::OPERATIONS.
 * }
 *
 * @package    block_panopto
 * @copyright  Panopto 2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bulk_operation_completed extends \core\event\base {
    /** @var string[] The operations this event reports. */
    public const OPERATIONS = [
        'build_category_structure',
    ];

    /**
     * Init method.
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Returns localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event_bulk_operation_completed', 'block_panopto');
    }

    /**
     * Returns non-localised event description.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '{$this->userid}' completed the Panopto bulk operation '{$this->other['operation']}'.";
    }

    /**
     * Validates the event data.
     *
     * @throws \coding_exception
     */
    protected function validate_data() {
        parent::validate_data();

        if ($this->contextlevel != CONTEXT_SYSTEM) {
            throw new \coding_exception('Context level must be CONTEXT_SYSTEM.');
        }

        if (!isset($this->other['operation']) || !in_array($this->other['operation'], self::OPERATIONS, true)) {
            throw new \coding_exception("The 'operation' value must be set in other to a known operation.");
        }
    }

    /**
     * The event is not in a course, so it is never part of a course restore.
     *
     * @return bool
     */
    public static function get_other_mapping() {
        return false;
    }
}
