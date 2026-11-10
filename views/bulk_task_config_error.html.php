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
 * The template shown when a bulk task cannot start because the Panopto configuration is invalid.
 *
 * This is a different failure from bulk_task_access_error: that one reports folders the working
 * user cannot see, which are skipped, while this one stops the whole task because no folder on
 * the site can be reached.
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
             * Displays the reason the Panopto configuration check failed, as reported by
             * panopto_validate_configuration(). The message is escaped because it can carry a
             * server name read back from config.
             *
             * @package block_panopto
             * @copyright  Panopto 2009 - 2025
             * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
             */
            echo s(get_string('bulk_task_config_error', 'block_panopto', $configvalidation['error_message']));
            ?>
        </div>
    </div>
</div>
