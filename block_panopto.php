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
 * @copyright  Panopto 2009 - 2015 /With contributions from Spenser Jones (sjones@ambrose.edu)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once("lib/panopto_data.php");
require_once(dirname(__FILE__) . '/../../lib/accesslib.php');

/**
 * Base class for the Panopto block for Moodle.
 * 
 * @package block_panopto
 * @copyright  Panopto 2009 - 2015
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_panopto extends block_base {

    /**
     *Name of the panopto block. Should match the block's directory name on the server.
     */
    public $blockname = "panopto";

    /**
     * Set system properties of plugin.
     */
    public function init() {
        global $COURSE;
        $this->title = get_string('pluginname', 'block_panopto');
    }

    /**
    * Block has global config (display "Settings" link on blocks admin page).
    */
    public function has_config() {
        return true;
    }

    /**
     * Save global block data in mdl_config_plugins table instead of global CFG variable.
     */
    public function config_save($data) {

        foreach ($data as $name => $value) {
            set_config($name, trim($value), $this->blockname);
        }
        return true;
    }

    /**
     * Block has per-instance config (display edit icon in block header).
     */
    public function instance_allow_config() {
        return true;
    }

    /**
     * Save per-instance config in custom table instead of mdl_block_instance configdata column.
     */
    public function instance_config_save($data, $nolongerused = false) {
        global $COURSE;
        if (!empty($data->course)) {
            panopto_data::set_panopto_course_id($COURSE->id, $data->course);

            // If role mapping info is given, map roles.
            if (!empty($data->creator) || !empty($data->publisher)) {
                self::set_course_role_permissions($COURSE->id, $data->publisher, $data->creator);

                // Get course context.
                $context = context_course::instance($COURSE->id);
            }
        } else {
            // If server is not set globally, there will be no other form values to push into config.
            return true;
        }
    }

    /**
     * Cron function to provision all valid courses at once.
     * Hittesh Ahuja - University of Bath.
     */
    public function cron() {
        global $CFG, $USER, $DB;
        $panoptodata = new panopto_data(null);

        // Check Panopto Focus API Settings exist.
        if (empty($panoptodata->servername) || empty($panoptodata->instancename) || empty($panoptodata->applicationkey)) {
            mtrace(get_string('unconfigured', 'block_panopto'));
            return true; // Exiting.
        }
        // Get only those courses that have Panopto folders mapped.
        // For each course, provision the course.
        $panoptocourses = $DB->get_records('block_panopto_foldermap');
        foreach ($panoptocourses as $course) {

            // Set the  course to retrieve info for / provision.
            // Check if the course exists.
            $moodlecourse = $DB->get_record('course', array('id' => $course->moodleid));
            if (!$moodlecourse) {
                continue;
            }
            $panoptodata->moodlecourseid = $course->moodleid;
            $provisioningdata = $panoptodata->get_provisioning_info();
            $provisioneddata = $panoptodata->provision_course($provisioningdata);
            if (!empty($provisioneddata)) {
                mtrace("Successfully provisioned course for $provisioneddata->ExternalCourseID");
            } else {
                mtrace("+++ Error provisioning course for Moodle Course ID : $course->moodleid");
            }
        }
        return true;
    }

    /**
     * Generate HTML for block contents.
     */
    public function get_content() {
        global $CFG, $COURSE, $USER;

        // Sync role mapping. In case this is the first time block is running we need to load old settings from db.
        // They will be the default values if this is the first time running.
        $mapping = panopto_data::get_course_role_mappings($COURSE->id);
        self::set_course_role_permissions($COURSE->id, $mapping['publisher'], $mapping['creator']);

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass;

        // Construct the Panopto data proxy object.
        $panoptodata = new panopto_data($COURSE->id);

        if (empty($panoptodata->servername) || empty($panoptodata->instancename) || empty($panoptodata->applicationkey)) {
            $this->content->text = get_string('unprovisioned', 'block_panopto') . "
            <br/><br/>
            <a href='$CFG->wwwroot/blocks/panopto/provision_course_internal.php?id=$COURSE->id'>Provision Course</a>";
            $this->content->footer = "";

            return $this->content;
        }

        try {
            if (!$panoptodata->sessiongroupid) {
                $this->content->text = get_string('no_course_selected', 'block_panopto');
            } else {
                // Get course info from SOAP service.
                $courseinfo = $panoptodata->get_course();

                // Panopto course was deleted, or an exception was thrown while retrieving course data.
                if ($courseinfo->Access == "Error") {
                    if (!isset($this->content->text)) {
                      $this->content->text = "<span class='error'>" . get_string('error_retrieving', 'block_panopto') . "</span>";
                    } else {
                      $this->content->text .= "<span class='error'>" . get_string('error_retrieving', 'block_panopto') . "</span>";
                    }                    
                } else {
                    // SSO form passes instance name in POST to keep URLs portable.
                      if (!isset($this->content->text)) {
                          $this->content->text = "
                              <form name='SSO' method='post'>
                                  <input type='hidden' name='instance' value='$panoptodata->instancename' />
                              </form>";
                      } else {
                          $this->content->text .= "
                              <form name='SSO' method='post'>
                                  <input type='hidden' name='instance' value='$panoptodata->instancename' />
                              </form>";
                      }

                    $this->content->text .= '<div><b>' . get_string('live_sessions', 'block_panopto') . '</b></div>';
                    $livesessions = $panoptodata->get_live_sessions();
                    if (!empty($livesessions)) {
                        $i = 0;
                        foreach ($livesessions as $livesession) {
                            // Alternate gray background for readability.
                            $altclass = ($i % 2) ? "listItemAlt" : "";

                            $livesessiondisplayname = s($livesession->Name);
                            $this->content->text .= "<div class='listItem $altclass'>
                            $livesessiondisplayname
                                                         <span class='nowrap'>
                                                            [<a href='javascript:panopto_launchNotes(\"$livesession->LiveNotesURL\")'
                                                                >" . get_string('take_notes', 'block_panopto') . '</a>]';
                            if ($livesession->BroadcastViewerURL) {
                                $this->content->text .= "[<a href='$livesession->BroadcastViewerURL' onclick='return panopto_startSSO(this)'>"
                                        . get_string('watch_live', 'block_panopto') . '</a>]';
                            }
                            $this->content->text .= "
                                                         </span>
                                                    </div>";
                            $i++;
                        }
                    } else {
                        $this->content->text .= '<div class="listItem">'
                                . get_string('no_live_sessions', 'block_panopto') . '</div>';
                    }

                    $this->content->text .= "<div class='sectionHeader'><b>"
                            . get_string('completed_recordings', 'block_panopto') . '</b></div>';
                    $completeddeliveries = $panoptodata->get_completed_deliveries();
                    if (!empty($completeddeliveries)) {
                        $i = 0;
                        foreach ($completeddeliveries as $completeddelivery) {
                            // Collapse to 3 lectures by default.
                            if ($i == 3) {
                                $this->content->text .= "<div id='hiddenLecturesDiv'>";
                            }

                            // Alternate gray background for readability.
                            $altclass = ($i % 2) ? "listItemAlt" : "";

                            $completeddeliverydisplayname = s($completeddelivery->DisplayName);
                            $this->content->text .= "<div class='listItem $altclass'>
                                                        <a href='$completeddelivery->ViewerURL' onclick='return panopto_startSSO(this)'>
                                                        $completeddeliverydisplayname
                                                        </a>
                                                    </div>";
                            $i++;
                        }

                        // If some lectures are hidden, display "Show all" link.
                        if ($i > 3) {
                            $this->content->text .= "</div>";
                            $this->content->text .= "<div id='showAllDiv'>";
                            $this->content->text .= "[<a id='showAllToggle' href='javascript:panopto_toggleHiddenLectures()'>"
                                    . get_string('show_all', 'block_panopto') . '</a>]';
                            $this->content->text .= "</div>";
                        }
                    } else {
                        $this->content->text .= "<div class='listItem'>" . get_string('no_completed_recordings', 'block_panopto') . '</div>';
                    }

                    if ($courseinfo->AudioPodcastURL) {
                        $this->content->text .= "<div class='sectionHeader'><b>" . get_string('podcast_feeds', 'block_panopto') . "</b></div>
                                                 <div class='listItem'>
                                                    <img src='$CFG->wwwroot/blocks/panopto/images/feed_icon.gif' />
                                                    <a href='$courseinfo->AudioPodcastURL'>" . get_string('podcast_audio', 'block_panopto') . "</a>
                                                    <span class='rssParen'>(</span
                                                        ><a href='$courseinfo->AudioRssURL' target='_blank' class='rssLink'>RSS</a
                                                    ><span class='rssParen'>)</span>
                                                 </div>";
                        if ($courseinfo->VideoPodcastURL) {
                            $this->content->text .= "
                                                 <div class='listItem'>
                                                    <img src='$CFG->wwwroot/blocks/panopto/images/feed_icon.gif' /> 
                                                    <a href='$courseinfo->VideoPodcastURL'>" . get_string('podcast_video', 'block_panopto') . "</a>
                                                    <span class='rssParen'>(</span
                                                        ><a href='$courseinfo->VideoRssURL' target='_blank' class='rssLink'>RSS</a
                                                    ><span class='rssParen'>)</span>
                                                 </div>";
                        }
                    }
                    $context = context_course::instance($COURSE->id, MUST_EXIST);
                    if (has_capability('moodle/course:update', $context)) {
                        $this->content->text .= "<div class='sectionHeader'><b>" . get_string('links', 'block_panopto') . "</b></div>
                                                 <div class='listItem'>
                                                    <a href='$courseinfo->CourseSettingsURL' onclick='return panopto_startSSO(this)'
                                                        >" . get_string('course_settings', 'block_panopto') . "</a>
                                                 </div>\n";
                        $systeminfo = $panoptodata->get_system_info();
                        $this->content->text .= "<div class='listItem'>
                                                    " . get_string('download_recorder', 'block_panopto') . "
                                                        <span class='nowrap'>
                                                            (<a href='$systeminfo->RecorderDownloadUrl'>Windows</a>
                                                            | <a href='$systeminfo->MacRecorderDownloadUrl'>Mac</a>)</span>
                                                </div>";
                    }

                    $this->content->text .= '
                        <script type="text/javascript">
                    // Function to pop up Panopto live note taker.
                    function panopto_launchNotes(url) {
                        // Open empty notes window, then POST SSO form to it.
                        var notesWindow = window.open("", "PanoptoNotes", "width=500,height=800,resizable=1,scrollbars=0,status=0,location=0");
                        document.SSO.action = url;
                        document.SSO.target = "PanoptoNotes";
                        document.SSO.submit();

                        // Ensure the new window is brought to the front of the z-order.
                        notesWindow.focus();
                    }

                    function panopto_startSSO(linkElem) {
                        document.SSO.action = linkElem.href;
                        document.SSO.target = "_blank";
                        document.SSO.submit();

                        // Cancel default link navigation.
                        return false;
                    }

                    function panopto_toggleHiddenLectures() {
                        var showAllToggle = document.getElementById("showAllToggle");
                        var hiddenLecturesDiv = document.getElementById("hiddenLecturesDiv");

                        if(hiddenLecturesDiv.style.display == "block") {
                            hiddenLecturesDiv.style.display = "none";
                            showAllToggle.innerHTML = "' . get_string('show_all', 'block_panopto') . '";
                        } else {
                        hiddenLecturesDiv.style.display = "block";
                        showAllToggle.innerHTML = "' . get_string('show_less', 'block_panopto') . '";
                    }
                }
                </script>';
                }
            }
        } catch (Exception $e) {
            $this->content->text .= "<br><br><span class='error'>" . get_string('error_retrieving', 'block_panopto') . "</span>";

        }

        if(has_capability('moodle/course:update', context_course::instance($COURSE->id, MUST_EXIST))) {
            $backtocourse = urlencode($CFG->wwwroot . '/course/view.php?id=' . $COURSE->id);
            $this->content->text .= '<div class="provision"><span><a href="' . $CFG->wwwroot . '/blocks/panopto/provision_course.php?course_id=' . $COURSE->id . '&return_url=' . $backtocourse .'" >' . get_string('block_add_thiscourse', 'block_panopto') . '</
a></span></div>';
        }

        $this->content->footer = '';

        return $this->content;
    }

    /**
     * Return applicable formats
     */
    public function applicable_formats() {
        return array(
            'my' => false,
            'all' => true
        );
    }

    /**
     * Gives selected capabilities to specified roles.
     */
    public function set_course_role_permissions($courseid, $publisherroles, $creatorroles) {
        $coursecontext = context_course::instance($courseid);

        // Clear capabilities from all of course's roles to be reassigned.
        self::clear_capabilities_for_course($courseid);

        foreach ($publisherroles as $role) {
            if (isset($role) && trim($role)!=='' ){
                assign_capability('block/panopto:provision_aspublisher', CAP_ALLOW, $role, $coursecontext, $overwrite = false);
            }

        }
        foreach ($creatorroles as $role) {
            if (isset($role) && trim($role)!=='' ){
                assign_capability('block/panopto:provision_asteacher', CAP_ALLOW, $role, $coursecontext, $overwrite = false);
                }
        }
        // Mark dirty (moodle standard for capability changes at context level).
        $coursecontext->mark_dirty();

        panopto_data::set_course_role_mappings($courseid, $publisherroles, $creatorroles);
    }

    /**
     * Clears capabilities from all roles so that they may be reassigned as specified.
     */
    public function clear_capabilities_for_course($courseid) {
        $coursecontext = context_course::instance($courseid);

        // Get all roles for current course.
        $currentcourseroles = get_all_roles($coursecontext);

        // Remove publisher and creator capabilities from all roles.
        foreach ($currentcourseroles as $role) {
            unassign_capability('block/panopto:provision_aspublisher', $role->id, $coursecontext);
            unassign_capability('block/panopto:provision_asteacher', $role->id, $coursecontext);
            // Mark dirty (moodle standard for capability changes at context level).
            $coursecontext->mark_dirty();
        }
    }

}

// End of block_panopto.php.
