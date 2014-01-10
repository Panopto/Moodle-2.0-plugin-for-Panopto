<?php
/**
 * Panopto course provisioning script
 */

global $CFG, $USER, $DB;

define('CLI_SCRIPT', true);

require_once (dirname(__FILE__) . '/../../../config.php');
require_once ($CFG->libdir . '/accesslib.php');
require_once (dirname(__FILE__) . '/../lib/panopto_data.php');

$USER->username = 'moodlesync';

$result = array();
$panopto_data = new panopto_data(null);

// Go through all courses that need updating and provision them
$courses = $DB->get_records_select("panopto_course_update_list", "courseid");
foreach ($courses as $course) {
  $panopto_data->moodle_course_id = $course->courseid;
  try {
    $provisioning_data = $panopto_data->get_provisioning_info();
    
    // Provision the course
    $panopto_data->provision_course($provisioning_data);

    $result []= array(
      'result' => 'ok',
      'course' => $course->courseid
    );
  } catch( Exception $e ) {
    $result []= array(
      'result' => 'error',
      'course' => $course->courseid,
      'exception' => $e->getMessage()
    );
  }
}

// Clear out the DB
$DB->delete_records_list("panopto_course_update_list", "courseid", array_map(function($course) {
  return $course->courseid;
}, $courses));

echo json_encode($result);