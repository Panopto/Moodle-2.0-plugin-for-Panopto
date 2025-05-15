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
 * Build the necessary category structure for Panopto.
 *
 * @package block_panopto
 * @author Leon Stringer <leon.stringer@ucl.ac.uk>
 * @copyright  2025 onwards UCL {@link https://www.ucl.ac.uk/}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_panopto\task;

use core\output\stored_progress_bar;

require_once(dirname(__FILE__) . '/../../lib/panopto_category_data.php');

/**
 * Build necessary category structure.
 */
class build_category_structure extends \core\task\adhoc_task {
    use \core\task\stored_progress_task_trait;

    /**
     * The main execution function of the class
     * @throws \moodle_exception If target Panopto server does not meet the
     * minimum required version.
     */
    public function execute() {
        global $DB, $OUTPUT;

        $data = (array) $this->get_custom_data();
        $selectedserver = $data['selectedserver'];
        $selectedkey = $data['selectedkey'];
        $categorybuilder = new \panopto_category_data(null, $selectedserver, $selectedkey);
        $categorybuilder->ensure_auth_manager();

        $this->progress = stored_progress_bar::get_by_idnumber($this->get_stored_progress_idnumber());
        $this->progress->auto_update(false); // Suppress JavaScript output.

        if (!$categorybuilder->has_valid_panopto_version()) {
            $panoptoversioninfo = [
                'activepanoptoversion' => $categorybuilder->get_active_panopto_server_version(),
                'requiredpanoptoversion' => \panopto_category_data::$categoriesrequiredpanoptoversion];
            $this->progress->error(get_string('categoriesbuilderror', 'block_panopto'));
            throw new \moodle_exception('categories_need_newer_panopto', 'block_panopto', '', $panoptoversioninfo);
        }

        // Get all categories with no children (all leaf nodes).
        $leafcategories = $DB->get_records_sql(
            'SELECT id FROM {course_categories} WHERE id NOT IN (SELECT parent FROM {course_categories})'
        );
        $built = 0;

        foreach ($leafcategories as $leafcategory) {
            $categorybuilder->set_moodle_category_id($leafcategory->id);
            $categorybuilder->set_session_group_id(\panopto_category_data::get_panopto_category_id($leafcategory->id, $selectedserver));
            if ($categorybuilder->ensure_category_branch(false, null)) {
                $built++;
            }

            $this->progress->update($built, count($leafcategories), get_string('categoriesbuilt', 'block_panopto', $built));
        }

        if ($built < count($leafcategories)) {
            $this->progress->error(get_string('categoriesbuilderror', 'block_panopto'));
            throw new \moodle_exception('categoriesbuilderror', 'block_panopto');
        }
    }

    /**
     * Method that can be called after creating this task but before it's
     * started so that the progress instance ID exists, as this is needed to
     * render a progress bar on the web page that corresponds to this task.
     *
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
     *
     * @return string Converted string, for example,
     * block_panopto_task_build_category_structure_118.
     */
    private function get_stored_progress_idnumber(): string {
        // Construct a unique name for the progress bar.
        $name = get_class($this) . '_' . $this->get_id();

        return stored_progress_bar::convert_to_idnumber($name);
    }

    /**
     * Used to indicate if the task should be re-run if it fails.
     *
     * @return bool true if the task should be retried until it succeeds, false otherwise.
     */
    public function retry_until_success(): bool {
        return false;
    }
}
