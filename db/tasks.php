<?php

/**
 * Definition of RoseBuild grade import scheduled task.
 *
 * @package   gradeimport_rosebuild
 * @category  task
 * @copyright 2016 Rose-Hulman Institute of Technology
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$tasks = array(
    array(
        'classname' => 'block_panopto\task\provision_cron',
        'blocking' => 0,
        'minute' => '*',
        'hour' => '*',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*'
    )
);