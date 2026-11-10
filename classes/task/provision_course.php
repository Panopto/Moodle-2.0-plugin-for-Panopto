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
 * The provision course class for Panopto.
 *
 * @package block_panopto
 * @copyright Panopto 2009 - 2016 /With contributions from Spenser Jones (sjones@ambrose.edu),
 * Skylar Kelty <S.Kelty@kent.ac.uk>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_panopto\task;

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__) . '/../../lib/panopto_data.php');

/**
 * Panopto "provision course" task.
 *
 * @copyright Panopto 2009 - 2016 /With contributions from Spenser Jones (sjones@ambrose.edu),
 * Skylar Kelty <S.Kelty@kent.ac.uk>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provision_course extends \core\task\adhoc_task {
    /**
     * The the parent component for this class
     */
    public function get_component() {
        return 'block_panopto';
    }

    /**
     * The main execution function of the class
     */
    public function execute() {
        try {
            $eventdata = (array) $this->get_custom_data();

            $panopto = new \panopto_data($eventdata['courseid']);

            // This task provisions a course that had no folder when it was queued. If the course has a
            // working mapping now, someone provisioned it in the meantime, perhaps to another server
            // by hand, and switching it to the automatic target server would undo that.
            if (!empty($panopto->servername) && !empty($panopto->applicationkey) && !empty($panopto->sessiongroupid)) {
                \panopto_data::print_log(
                    "Course {$eventdata['courseid']} is already provisioned to {$panopto->servername}; " .
                    'skipping automatic provisioning.'
                );
                return;
            }

            $targetserver = panopto_get_target_panopto_server();
            $panopto->servername = $targetserver->name;
            $panopto->applicationkey = $targetserver->appkey;
            $provisioninginfo = $panopto->get_provisioning_info();

            // Set when queued on course creation. Cron runs this task as the site admin, so sync the
            // course creator here instead of letting provision_course() sync $USER.
            $creatorid = (int) ($eventdata['creatorid'] ?? 0);

            // A manual provision can also finish while this one is talking to Panopto, so ask
            // provision_course() to keep a mapping that appears in the meantime.
            $provisioneddata = $panopto->provision_course($provisioninginfo, $creatorid > 0, false, true);

            if (!empty($provisioneddata->keptexistingmapping)) {
                return;
            }

            if ($creatorid > 0 && isset($provisioneddata->Id) && !isset($provisioneddata->errormessage)) {
                $panopto->sync_external_user($creatorid);
            }
        } catch (\Exception $e) {
            \panopto_data::print_log($e->getMessage());
        }
    }
}
