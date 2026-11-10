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
 * The template shown when a bulk task stops before processing any folder because the working
 * user has no username to identify them to Panopto.
 *
 * This is deliberately separate from bulk_task_access_error: that one reports folders the working
 * user cannot see, which are skipped while the rest of the task carries on. Here nothing carries
 * on, so the admin has to be told the task stopped rather than that it continued without a folder.
 *
 * @package block_panopto
 * @copyright  Panopto 2009 - 2025
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
?>

<div class='block_panopto'>
    <div class='panoptoProcessInformation'>
        <div class='error'>
            <?php
            /**
             * Displays the reason the bulk task could not start. The message takes no parameters
             * because the username it would otherwise name is the one that is missing.
             *
             * @package block_panopto
             * @copyright  Panopto 2009 - 2025
             * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
             */
            echo get_string('bulk_task_blocked_no_username', 'block_panopto');
            ?>
        </div>
    </div>
</div>
