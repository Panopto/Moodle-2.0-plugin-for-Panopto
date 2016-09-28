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
 * @package block_panopto
 * @copyright  Panopto 2009 - 2015 with contributions from Spenser Jones (sjones@ambrose.edu)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = array(
    // User enrolled event.
    array(
        'eventname' => '\core\event\user_enrolment_created',
        'callback' => 'block_panopto_rollingsync::enrollmentcreated',
    ),
    // User unenrolled event.
    array(
        'eventname' => '\core\event\user_enrolment_deleted',
        'callback' => 'block_panopto_rollingsync::enrollmentdeleted',
    ),
    // Event when user has role updated.
    array(
        'eventname' => '\core\event\user_enrolment_updated',
        'callback' => 'block_panopto_rollingsync::enrolmentupdated',
    ),
    // Event when user has role added to enrollment.
    array(
        'eventname' => '\core\event\role_assigned',
        'callback' => 'block_panopto_rollingsync::roleadded',
    ),
    // Event when user has role removed from enrollment.
    array(
        'eventname' => '\core\event\role_unassigned',
        'callback' => 'block_panopto_rollingsync::roledeleted',
    ),
    array(
        'eventname' => '\core\event\course_created',
        'callback' => 'block_panopto_rollingsync::coursecreated',
        ),
);
