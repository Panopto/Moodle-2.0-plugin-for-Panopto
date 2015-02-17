<?php

defined('MOODLE_INTERNAL') || die();
$observers = array(
    //User enrolled event
    array(
        'eventname' => '\core\event\user_enrolment_created',
        'callback' => 'block_panopto_rollingsync::enrolmentcreated',
    ),
    //User unenrolled event
    array(
        'eventname' => '\core\event\user_enrolment_deleted',
        'callback' => 'block_panopto_rollingsync::enrolmentdeleted',
    ),
    //Event when user has role added to enrollment
    array(
        'eventname' => '\core\event\role_assigned',
        'callback' => 'block_panopto_rollingsync::roleadded',
    ),
    //Event when user has role removed from enrollment
    array(
        'eventname' => '\core\event\role_unassigned',
        'callback' => 'block_panopto_rollingsync::roledeleted',
    ),
);