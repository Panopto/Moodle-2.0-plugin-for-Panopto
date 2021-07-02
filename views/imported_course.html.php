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
 * the imported course result template
 *
 * @package block_panopto
 * @copyright  Panopto 2009 - 2017
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
?>

<div class='block_panopto'>
    <div class='panoptoProcessInformation'>
        <div class='value'>
            <?php
            if (isset($targetpanoptodata) && !empty($targetpanoptodata)) {
            ?>
                <div class='attribute'><?php echo get_string('attempted_target_course_id', 'block_panopto') ?></div>
                <div class='value'><?php echo $courseimport->target_moodle_id ?></div>
                <div class='attribute'><?php echo get_string('attempted_import_course_id', 'block_panopto') ?></div>
                <div class='value'><?php echo $courseimport->import_moodle_id ?></div>
                <div class='attribute'><?php echo get_string('attempted_panopto_server', 'block_panopto') ?></div>
                <div class='value'><?php echo $targetpanopto->servername ?></div>
                <div class='attribute'><?php echo get_string('import_status', 'block_panopto') ?></div>
                <?php
                foreach ($importresults as $importresult) {
                    if (isset($importresult->errormessage)) {
                    ?>
                        <div class='value'>
                            <?php echo get_string('import_error', 'block_panopto', $importresult) ?>
                        </div>
                        <?php
                    } else {
                    ?>
                        <div class='value'>
                            <?php echo get_string('import_success', 'block_panopto', $importresult) ?>
                        </div>
                        <?php
                    }
                }
            } else {
                if ($targetpanopto === panopto_reinitialize::NO_COURSE_EXISTS) {
                    ?>
                        <div class='errorMessage'><?php echo get_string('target_moodle_course_deleted', 'block_panopto') ?></div>
                    <?php
                } else {
                    ?>
                        <div class='errorMessage'><?php echo get_string('target_invalid_panopto_data', 'block_panopto') ?></div>
                    <?php
                }
            }
            ?>
        </div>
    </div>
</div>
