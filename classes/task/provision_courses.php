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
 * Provision courses task for Panopto.
 *
 * @package block_panopto
 * @copyright  2025 onwards UCL {@link https://www.ucl.ac.uk/}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_panopto\task;

use core\output\stored_progress_bar;

require_once(dirname(__FILE__) . '/../../lib/panopto_data.php');

/**
 * Provision courses task for Panopto.
 */
class provision_courses extends \core\task\adhoc_task {
    use \core\task\stored_progress_task_trait;

    /**
     * The main execution function of the class
     */
    public function execute() {
        global $OUTPUT;

        $data = (array) $this->get_custom_data();
        $courseids = $data['courseids'];
        $selectedserver = $data['selectedserver'];
        $selectedkey = $data['selectedkey'];

        $this->progress = stored_progress_bar::get_by_idnumber($this->get_stored_progress_idnumber());
        $this->progress->auto_update(false); // Suppress JavaScript output.

        $provisioned = 0;

        foreach ($courseids as $courseid) {
            if (empty($courseid)) {
                continue;
            }

            $provisioneddata = null;

            try {
                $panoptodata = new \panopto_data($courseid);

                if ($panoptodata->servername !== $selectedserver) {
                    $panoptodata->sessiongroupid = null;
                }

                $panoptodata->servername = $selectedserver;
                $panoptodata->applicationkey = $selectedkey;
                $provisioninginfo = $panoptodata->get_provisioning_info();
                $provisioneddata = $panoptodata->provision_course($provisioninginfo, false);
            } catch (\Exception $exception) {
                mtrace_exception($exception);
            }

            if (empty($provisioneddata) ||
                    isset($provisioneddata->errormessage) ||
                    isset($provisioneddata->accesserror) ||
                    isset($provisioneddata->unknownerror)) {
                mtrace($OUTPUT->render_from_template('block_panopto/provisionerror.cli', $provisioneddata));
            } else {
                $templatedata = clone $provisioneddata;
                $templatedata->fullname = $provisioninginfo->fullname;
                $templatedata->userswillbesyncedcustom = (get_config('block_panopto', 'sync_after_login') || get_config('block_panopto', 'sync_on_enrolment'));
                $templatedata->asyncwaitwarning = get_config('block_panopto', 'async_tasks');
                $templatedata->syncafterprovisioning = get_config('block_panopto', 'sync_after_provisioning');

                if ($templatedata->syncafterprovisioning) {
                    if (!empty($provisioneddata->publishers)) {
                        $templatedata->publishers = join(', ', $provisioneddata->publishers);
                    }

                    if (!empty($provisioneddata->creators)) {
                        $templatedata->creators = join(', ', $provisioneddata->creators);
                    }

                    if (!empty($provisioneddata->viewers)) {
                        $templatedata->viewers = join(', ', $provisioneddata->viewers);
                    }
                }
                mtrace($OUTPUT->render_from_template('block_panopto/provisionsuccess.cli', $templatedata));
                $provisioned++;
            }

            $this->progress->update($provisioned, count($courseids), get_string('provisionsuccess', 'block_panopto', $provisioned));
        }

        if ($errorcount = count($courseids) - $provisioned) {
            $this->progress->error(get_string('provisionerror', 'block_panopto', $errorcount));
            throw new \moodle_exception('provisiontaskfail', 'block_panopto', '', $errorcount);
        }
    }

    /**
     * Method that can be called after creating this task but before it's
     * started so that the progress instance ID exists, as this is needed to
     * render a progress bar on the web page that corresponds to this task.
     * @return string Unique ID for this task instance's progress.
     */
    public function progress_start(): string {
        $this->start_stored_progress();
        return $this->get_stored_progress_idnumber();
    }

    /**
     * Override parent so we can render the progress bar on the web page (the
     * parent trait sets the renderer to CLI).
     */
    protected function start_stored_progress(): void {
        $this->progress = new stored_progress_bar($this->get_stored_progress_idnumber());

        // Start the progress.
        $this->progress->start();
    }

    /**
     * Get the unique ID for this process.
     * @return string Converted string, for example,
     * block_panopto_task_provision_courses_118.
     */
    private function get_stored_progress_idnumber(): string {
        // Construct a unique name for the progress bar.
        $name = get_class($this) . '_' . $this->get_id();

        return stored_progress_bar::convert_to_idnumber($name);
    }

    /**
     * Used to indicate if the task should be re-run if it fails.
     * By default, tasks will be retried until they succeed, other tasks can override this method to change this behaviour.
     *
     * @return bool true if the task should be retried until it succeeds, false otherwise.
     */
    public function retry_until_success(): bool {
        return false;
    }
}
