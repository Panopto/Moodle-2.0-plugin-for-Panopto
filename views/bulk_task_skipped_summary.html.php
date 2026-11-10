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
 * The template listing the folders a bulk task skipped because the working user could not
 * access them in Panopto.
 *
 * The same summary goes to panopto_data::print_log(), but that routes to debugging() unless the
 * logs are redirected to a file, and debugging() renders nothing on a site with debug display
 * off. Since the task now finishes instead of stopping at the first such folder, this is the
 * only on-screen signal that anything was left out.
 *
 * @package block_panopto
 * @copyright  Panopto 2009 - 2025
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
?>

<div class='block_panopto'>
    <div class='panoptoProcessInformation'>
        <div class='errorMessage'>
            <?php
            /**
             * Displays how many folders were skipped, out of how many were considered, and the
             * Moodle course ids they were mapped to.
             *
             * @package block_panopto
             * @copyright  Panopto 2009 - 2025
             * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
             */
            echo s(get_string($summarykey, 'block_panopto', $skippedsummary));
            ?>
        </div>
    </div>
</div>
