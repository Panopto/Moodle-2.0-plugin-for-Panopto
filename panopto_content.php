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
 * manages the content on the Panopto block
 *
 * @package block_panopto
 * @copyright  Panopto 2009 - 2016 /With contributions from Spenser Jones (sjones@ambrose.edu)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
global $CFG;
if (empty($CFG)) {
    require_once(dirname(__FILE__) . '/../../config.php');
}

require_once(dirname(__FILE__) . '/lib/panopto_data.php');

try {
    require_login();
    require_sesskey();
    // Close the session so that the users other tabs in the same session are not blocked.
    \core\session\manager::write_close();

    header('Content-Type: text/html; charset=utf-8');
    global $CFG, $USER;

    $courseid = required_param('courseid', PARAM_INT);

    $content = new stdClass;

    // Initialize $content->text to an empty string here to avoid trying to append to it before
    // it has been initialized and throwing a warning. Bug 33163.
    $content->text = '';

    // Construct the Panopto data proxy object.
    $panoptodata = new \panopto_data($courseid);

    if (empty($panoptodata->servername) || empty($panoptodata->instancename) || empty($panoptodata->applicationkey)) {
        $content->text = get_string('unprovisioned', 'block_panopto');

        if ($panoptodata->can_user_provision($courseid)) {
            $content->text .= '<br/>' .
            "<a href='$CFG->wwwroot/blocks/panopto/provision_course_internal.php?id=$courseid'>" .
            get_string('provision_course_link_text', 'block_panopto') . '</a>';
        }

        $content->footer = '';

        echo $content->text;
    } else {

        try {
            if (!$panoptodata->sessiongroupid) {
                $content->text = get_string('no_course_selected', 'block_panopto');
            } else if (!\panopto_data::is_server_alive('https://' . $panoptodata->servername . '/Panopto')) {
                $servernotavailableestring = get_string('server_not_available', 'block_panopto', $panoptodata->servername);
                \panopto_data::print_log($servernotavailableestring);
                $content->text .= "<span class='error'>" . $servernotavailableestring . '</span>';
            } else {
                // We can get by external_id but there is no point because atm it calls this method redundantly anyway.
                $courseinfo = $panoptodata->get_folders_by_id();

                if (isset($courseinfo->noaccess) && $courseinfo->noaccess == true) {
                    // The user did not have access to the Panopto content.
                    $content->text .= "<span class='error'>" . get_string('no_access', 'block_panopto') . '</span>';
                } 
                else if (!empty($courseinfo->errormessage)) { 
                    // We failed for some other reason, display the error. 
                    $content->text .= "<span class='error'>" . $courseinfo->errormessage . '</span>';
                } 
                else {
                    // SSO form passes instance name in POST to keep URLs portable.
                    $content->text .= "<form name='SSO' method='post'>" .
                        "<input type='hidden' name='instance' value='$panoptodata->instancename' /></form>";


                    // Get all Completed.
                    $sessionlist = $panoptodata->get_session_list($courseinfo->DeliveriesHaveSpecifiedOrder);
                    $livesessions = array();

                    if (is_array($sessionlist) && !empty($sessionlist)) {
                        foreach ($sessionlist as $sessionobj) {

                            // If the session is a live broadcast from the Windows/Mac Recorder or Remote Recorder check if its live
                            $islivesession = $sessionobj->State === 'Broadcasting';

                            if ($islivesession) {
                                $livesessions[] = $sessionobj;
                            } else if (!empty($sessionobj->Duration)) {
                                $completeddeliveries[] = $sessionobj;
                            }
                        }
                    }

                    $content->text .= '<div><b>' . get_string('live_sessions', 'block_panopto') . '</b></div>';

                    if (!empty($livesessions)) {
                        $i = 0;
                        foreach ($livesessions as $livesession) {
                            // Alternate gray background for readability.
                            $altclass = ($i % 2) ? 'listItemAlt' : '';

                            $livesessiondisplayname = s($livesession->Name);
                            $content->text .= "<div class='listItem $altclass'>" . $livesessiondisplayname .
                                "<span class='nowrap'>" .
                                "[<a href='javascript:panopto_launchNotes(\"$livesession->NotesURL\")'>" .
                                get_string('take_notes', 'block_panopto') . '</a>]';

                            if ($livesession->ViewerUrl) {
                                $content->text .= "[<a href='$livesession->ViewerUrl' " .
                                    "onclick='return panopto_startSSO(this)'>" .
                                    get_string('watch_live', 'block_panopto') . '</a>]';
                            }

                            $content->text .= '</span></div>';
                            $i++;
                        }
                    } else {
                        $content->text .= '<div class="listItem">' .
                            get_string('no_live_sessions', 'block_panopto') . '</div>';
                    }

                    $content->text .= "<div class='sectionHeader'><b>" .
                        get_string('completed_recordings', 'block_panopto') . '</b></div>';

                    if (!empty($completeddeliveries)) {
                        $i = 0;
                        foreach ($completeddeliveries as $completeddelivery) {
                            // Collapse to 3 lectures by default.
                            if ($i == 3) {
                                $content->text .= "<div id='hiddenLecturesDiv'>";
                            }

                            // Alternate gray background for readability.
                            $altclass = ($i % 2) ? 'listItemAlt' : '';

                            $completeddeliverydisplayname = s($completeddelivery->Name);
                            $content->text .= "<div class='listItem $altclass'>" .
                                "<a href='$completeddelivery->ViewerUrl' onclick='return panopto_startSSO(this)'>" .
                                $completeddeliverydisplayname .
                                '</a></div>';
                            $i++;
                        }

                        // If some lectures are hidden, display "Show all" link.
                        if ($i > 3) {
                            $content->text .= '</div>' . "<div id='showAllDiv'>" .
                                "[<a id='showAllToggle' href='javascript:panopto_toggleHiddenLectures()'>" .
                                get_string('show_all', 'block_panopto') . '</a>]</div>';
                        }
                    } else {
                        $content->text .= "<div class='listItem'>" .
                            get_string('no_completed_recordings', 'block_panopto') . '</div>';
                    }

                    if ($courseinfo->AudioPodcastITunesUrl) {
                        $content->text .= "<div class='sectionHeader'><b>" . get_string('podcast_feeds', 'block_panopto') .
                            '</b></div>' .
                            "<div class='listItem'>" .
                                "<img src='$CFG->wwwroot/blocks/panopto/images/feed_icon.gif' />" .
                                "<a href='$courseinfo->AudioPodcastITunesUrl'>" .
                                    get_string('podcast_audio', 'block_panopto') .
                                '</a>' .
                                "<span class='rssParen'>(</span>" .
                                "<a href='$courseinfo->AudioRssUrl' target='_blank' class='rssLink'>RSS</a>" .
                                "<span class='rssParen'>)</span>" .
                            "</div>\n";

                        if ($courseinfo->VideoPodcastITunesUrl) {
                            $content->text .= "<div class='listItem'>" .
                                "<img src='$CFG->wwwroot/blocks/panopto/images/feed_icon.gif' />" .
                                "<a href='$courseinfo->VideoPodcastITunesUrl'>" .
                                    get_string('podcast_video', 'block_panopto') .
                                '</a>' .
                                "<span class='rssParen'>(</span>" .
                                "<a href='$courseinfo->VideoRssUrl' target='_blank' class='rssLink'>RSS</a>" .
                                "<span class='rssParen'>)</span>" .
                                "</div>\n";
                        }
                    }
                    $context = context_course::instance($courseid, MUST_EXIST);

                    // This does not consider roles.
                    $isteacheroradmin = has_capability('moodle/course:update', $context);

                    $hascreatoraccess = has_capability('block/panopto:provision_asteacher', $context, $USER->id);

                    // Settings link can only be viewed by Teachers, Admins. If the proper setting is enabled, any creators can also view the link.
                    if ($hascreatoraccess && ($isteacheroradmin || get_config('block_panopto', 'any_creator_can_view_folder_settings'))) {
                        $content->text .= "<div class='sectionHeader'><b>" . get_string('links', 'block_panopto') .
                            '</b></div>' .
                            "<div class='listItem'>" .
                                "<a href='$courseinfo->SettingsUrl' onclick='return panopto_startSSO(this)'>" .
                                    get_string('course_settings', 'block_panopto') .
                                '</a>' .
                            "</div>\n";
                    }

                    // A the users who can provision are the Moodle admin, and enrolled users given a publisher or creator role.
                    // This makes it so can_user_provision will allow only creators/publishers/admins to see these links.
                    if (get_config('block_panopto', 'anyone_view_recorder_links') || $panoptodata->can_user_provision($courseid)) {
                        $systeminfo = $panoptodata->get_recorder_download_urls();
                        $content->text .= "<div class='listItem'>" .
                            get_string('download_recorder', 'block_panopto') .
                            "<span class='nowrap'>(" .
                            "<a href='$systeminfo->WindowsRecorderDownloadUrl'>Windows</a>" .
                            " | <a href='$systeminfo->MacRecorderDownloadUrl'>Mac</a>)</span>" .
                            "</div>\n";
                    }
                }
            }
        } catch (Exception $e) {
            $content->text .= "<span class='error'>" . get_string('error_retrieving', 'block_panopto') . '</span>';
            \panopto_data::print_log($e->getMessage());
        }

        $content->footer = '';
        echo $content->text;
    }
} catch (Exception $e) {
    header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request', true, 400);
    if (isloggedin()) {
        header('Content-Type: text/plain; charset=utf-8');
        echo $e->getMessage();
    }
}
