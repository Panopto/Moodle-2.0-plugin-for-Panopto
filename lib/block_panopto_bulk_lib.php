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
 * Main library functions for the Panoptop block bulk operations.
 *
 * Warning: These operations can work on many Panopto mappings for many Panopto servers at one time.
 *   Please make sure the user performing this work is an administrator on all Panopto servers to avoid
 *   an access headache.
 *
 * @package block_panopto
 * @copyright  Panopto 2020
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__) . '/block_panopto_lib.php');

/**
 * Update the progress for Panopto bulk operation.
 *
 * @param int $currentprogress the current progress that the bar needs to reflect.
 * @param int $totalitems the total number of items in the current step to be processed.
 * @param int $progressstep if set update the progress step to this value.
 * @param int $taskstep which task step we are on currently.
 * @param int|null $moodlecourseid the course about to be worked on, named in the log when known.
 */
function panopto_update_task_progress($currentprogress, $totalitems, $progressstep, $taskstep, $moodlecourseid = null) {
    global $CFG;

    if (isset($taskstep) && !empty($taskstep)) {
        \panopto_data::print_log(get_string('bulk_task_new_step', 'block_panopto', $taskstep));
    }

    if (!CLI_SCRIPT && $totalitems > 0) {
        $percentdone = (int)((float)$currentprogress / (float)$totalitems * 100.0);
        panopto_bulk_update_progress_bar($percentdone, $progressstep);
    }

    if ($currentprogress > 0) {
        $progressobject = new stdClass();
        $progressobject->currentprogress = $currentprogress;
        $progressobject->totalitems = $totalitems;

        // Logged before the folder is worked on rather than after, and naming the course rather
        // than only its position, so that the last line written identifies whatever the run is
        // sitting on. An index alone left no way to tell which course had stalled a run.
        if (isset($moodlecourseid)) {
            $progressobject->moodlecourseid = $moodlecourseid;

            \panopto_data::print_log(
                get_string('bulk_task_update_progress_course', 'block_panopto', $progressobject)
            );
        } else {
            \panopto_data::print_log(get_string('bulk_task_update_progress', 'block_panopto', $progressobject));
        }
    }
}

/**
 * Enhanced throttling for bulk operations with configurable intervals.
 *
 * @param int $processedcount the current number of operations processed.
 * @param string $operationtype type of operation for throttling context
 * @param int $totalcount total operations to process (optional)
 * @return int Updated processed count
 */
function panopto_handle_bulk_throttle($processedcount, $operationtype = 'bulk_operations', $totalcount = null) {
    require_once(dirname(__FILE__) . '/panopto_throttling.php');

    return panopto_throttling::throttle_bulk_operation($processedcount, $operationtype, $totalcount);
}

/**
 * Report that the working user does not have the Panopto permissions needed for a folder.
 *
 * The banner and the generic "make this user an admin" log line are emitted at most once per
 * request. A single inaccessible folder no longer aborts the run, so without this guard a site
 * with many of them would repeat the same box and the same log line for every one.
 *
 * @param string $targetuser the Panopto username for the current user (so the customer knows who to make an Admin)
 * @param int|null $moodlecourseid the course whose folder could not be accessed, when known
 */
function panopto_bulk_display_access_error($targetuser, $moodlecourseid = null) {
    global $CFG;

    static $errorreported = false;

    if (!$errorreported) {
        $errorreported = true;

        if (!CLI_SCRIPT) {
            include($CFG->dirroot . '/blocks/panopto/views/bulk_task_access_error.html.php');
            panopto_bulk_flush_output();
        }

        \panopto_data::print_log(
            get_string(
                'bulk_task_access_error',
                'block_panopto',
                $targetuser
            )
        );
    }

    // Logged every time: the banner above names the user to fix, this names what was skipped.
    if (isset($moodlecourseid)) {
        \panopto_data::print_log(
            get_string('bulk_task_skipped_inaccessible_folder', 'block_panopto', $moodlecourseid)
        );
    }
}

/**
 * Report that a bulk task stopped before processing anything, rather than skipping and going on.
 *
 * Separate from panopto_bulk_display_access_error() because that message now tells the admin the
 * task carries on without the folders the working user cannot see. When there is no username to
 * identify the working user at all, nothing is checked and nothing carries on, so reusing that
 * message would tell the admin the opposite of what happened.
 */
function panopto_bulk_display_blocked_error() {
    global $CFG;

    if (!CLI_SCRIPT) {
        include($CFG->dirroot . '/blocks/panopto/views/bulk_task_blocked_error.html.php');
        panopto_bulk_flush_output();
    }

    \panopto_data::print_log(get_string('bulk_task_blocked_no_username', 'block_panopto'));
}

/**
 * Emit the container the bulk progress bars are drawn into, at most once per request.
 *
 * This used to be included again on every single update, appending a fresh pair of bar elements,
 * and so a duplicate id, for every folder processed. GetElementById kept finding the first copy
 * so the bar still moved, but a large site ended up with thousands of empty bars down the page.
 */
function panopto_bulk_ensure_progress_bar() {
    global $CFG;

    static $emitted = false;

    if (CLI_SCRIPT || $emitted) {
        return;
    }

    $emitted = true;
    include($CFG->dirroot . '/blocks/panopto/views/bulk_task_progress_bar.html.php');
}

/**
 * Display progress bar for bulk update operation.
 *
 * @param int $percentdone % complete on the progress bar.
 * @param string $steptype progress step status such as 'validating', 'processing', etc.
 */
function panopto_bulk_update_progress_bar($percentdone, $steptype) {
    panopto_bulk_ensure_progress_bar();

    $periodstring = '';
    while (strlen($periodstring) < $percentdone % 4) {
        $periodstring .= '.';
    }
    $percentdone = max(0, min(100, (int) $percentdone));

    // The bar markup is built and escaped here, then handed to the script as JSON, so neither the
    // step name nor the percentage can end the string literal or the script element.
    $barhtml = html_writer::div('&nbsp;', '', [
            'style' => 'width:' . $percentdone . '%;background-color: #228B22;z-index:0;',
        ]) .
        html_writer::span(
            s($percentdone . '% done ' . $steptype . $periodstring),
            '',
            ['style' => 'z-index: 1;position: absolute;top: 0px;transform: translateX(-50%);']
        );

    echo html_writer::script(
        'document.getElementById(' . panopto_js_literal('panopto-progress-bar-' . $steptype) . ').innerHTML = ' .
        panopto_js_literal($barhtml) . ';'
    );

    // Without this the script above sits in the output buffer until the whole run finishes, so
    // the bar appears frozen at whatever percentage the server happened to flush on its own.
    panopto_bulk_flush_output();
}

/**
 * This script will process all passed in folders using the passed in callback
 *
 * @param array $panoptocourseobjects all valid mappings ready to process
 * @param string $bulktaskname the name of the bulk task we are performing on the mappings
 * @param string $workercallback the name of the callback function we use to process a folder
 * @param int|null $totalcount total operations to process (optional)
 */
function panopto_bulk_process_folders($panoptocourseobjects, $bulktaskname, $workercallback, $totalcount = null) {
    $currindex = 0;
    $totaltaskfolders = count($panoptocourseobjects);
    if ($totaltaskfolders > 0) {
        panopto_update_task_progress($currindex, $totaltaskfolders, 'processing', $bulktaskname);
        foreach ($panoptocourseobjects as $mappablecourse) {
            $currindex = panopto_handle_bulk_throttle($currindex, 'bulk_operations', $totalcount ?: $totaltaskfolders);

            panopto_update_task_progress(
                $currindex,
                $totaltaskfolders,
                'processing',
                null,
                $mappablecourse->panopto->moodlecourseid ?? null
            );

            call_user_func($workercallback, $mappablecourse);

            // Released for the same reason as in validation: this folder is finished with, so its
            // clients should go now rather than accumulating one set per folder across the run.
            // Growth like that is the clearest candidate for a long CLI pass dying part way
            // through, since the loop is otherwise flat in what it holds.
            $mappablecourse->panopto->authmanager = null;
            $mappablecourse->panopto->sessionmanager = null;
            $mappablecourse->panopto->usermanager = null;

            // The callbacks echo a result block per folder. Flushing here lets the page grow as
            // the run proceeds rather than arriving in one lump when the request finally ends.
            panopto_bulk_flush_output();
        }
    }
}

/**
 * Callback used to bulk reprovision all folders and imports
 *
 * @param object $mappablecourse the current folder we are provisioning
 */
function panopto_bulk_reprovision_callback($mappablecourse) {
    global $CFG;

    $provisioningdata = $mappablecourse->provisioninginfo;
    $provisioneddata = $mappablecourse->panopto->provision_course($provisioningdata, true, true); // Is_bulk_operation = true.

    if (!CLI_SCRIPT) {
        include($CFG->dirroot . '/blocks/panopto/views/provisioned_course.html.php');
    }
    $mappablecourse->panopto->ensure_auth_manager();
    $activepanoptoserverversion = $mappablecourse->panopto->authmanager->get_server_version();
    $useccv2 = version_compare(
        $activepanoptoserverversion,
        \panopto_data::$ccv2requiredpanoptoversion,
        '>='
    );

    $courseimports = \panopto_data::get_import_list($mappablecourse->panopto->moodlecourseid);
    foreach ($courseimports as $importedcourse) {
        if ($useccv2) {
            $mappablecourse->panopto->copy_panopto_content($importedcourse);
        } else {
            $mappablecourse->panopto->init_and_sync_import_ccv1($importedcourse);
        }

        // The other silent stretch inside a single folder: a course with many imports copies them
        // one after another with nothing written between, which can outlast an idle timeout on its
        // own even when the user loop did not.
        panopto_bulk_keepalive();
    }
}

/**
 * Callback used to bulk update all folder names to match course names
 *
 * @param object $mappablecourse the current folder we are provisioning
 */
function panopto_bulk_rename_callback($mappablecourse) {
    global $CFG;

    // Display the name information for the user.
    // Provisioninginfo should still contain the original name.
    $currentfoldernamecontainer = new stdClass();
    $currentfoldernamecontainer->oldname = $mappablecourse->provisioninginfo->fullname;
    $currentfoldernamecontainer->moodleid = $mappablecourse->panopto->moodlecourseid;
    if ($currentfoldernamecontainer->oldname != $mappablecourse->panopto->get_new_folder_name(null, null)) {
        if ($mappablecourse->panopto->update_folder_name()) {
            // The currentcoursename should get updated during the update_folder_name call.
            $currentfoldernamecontainer->newname = $mappablecourse->panopto->currentcoursename;

            \panopto_data::print_log(
                get_string('bulk_rename_single_success', 'block_panopto', $currentfoldernamecontainer)
            );

            if (!CLI_SCRIPT) {
                include($CFG->dirroot . '/blocks/panopto/views/bulk_rename_single_success.html.php');
            }
        } else {
            \panopto_data::print_log(
                get_string('bulk_rename_single_failed', 'block_panopto', $mappablecourse->panopto->moodlecourseid)
            );
            if (!CLI_SCRIPT) {
                include($CFG->dirroot . '/blocks/panopto/views/bulk_rename_single_failed.html.php');
            }
        }
    } else {
        // The currentcoursename should get updated during the update_folder_name call.
        $currentfoldernamecontainer->newname = $mappablecourse->panopto->currentcoursename;

        \panopto_data::print_log(
            get_string('bulk_rename_single_unnecessary', 'block_panopto', $currentfoldernamecontainer->oldname)
        );

        if (!CLI_SCRIPT) {
            include($CFG->dirroot . '/blocks/panopto/views/bulk_rename_single_success.html.php');
        }
    }
}

/**
 * Grabs every mapping existing in the Panopto FolderMap table and checks that the mapping is
 *   still in a good state (e.g. Folder Deleted in Panopto, Course in Moodle doesn't exist
 *   anymore, or any necessary DB fields still exist). Returns an array of Panopto mapping data
 *   for the mappings that are still in a good state or can be recovered by fresh provisining.
 *
 * @param array $params beginning index and number to process.
 * @param bool $skipimports whether or not to process imported folders.
 */
function panopto_bulk_sanitize_and_get_mappings($params, $skipimports) {
    global $DB, $CFG;

    // Get all active courses mapped to Panopto.
    $oldpanoptocourses = $DB->get_records(
        'block_panopto_foldermap',
        null,
        null,
        'id,moodleid'
    );

    $currindex = 0;
    $totaltasksteps = count($oldpanoptocourses);

    $beginningindex = isset($params[1]) ? max(intval($params[1]), 1) : 1;
    $numbertoprocess = isset($params[2]) ? intval($params[2]) : $totaltasksteps;
    $endingindex = isset($params[2]) ? $beginningindex + intval($params[2]) : $totaltasksteps + 1;

    $processcountobject = new stdClass();
    $processcountobject->beginningindex = $beginningindex;
    $processcountobject->endingindex = $endingindex - 1;

    \panopto_data::print_log(get_string('bulk_task_working_count', 'block_panopto', $processcountobject));

    $upgradestep = get_string('verifying_permission', 'block_panopto');

    $panoptocourseobjects = [];
    $skippedfolders = [];

    // Built before the validation below so the banner has a username to name. The constructor
    // only reads config and the foldermap, so moving it up costs no Panopto round trip.
    $getunamepanopto = new \panopto_data(null);
    $targetuser = $getunamepanopto->panopto_decorate_username($getunamepanopto->uname);

    // Perform configuration validation before proceeding with bulk operations.
    \panopto_data::print_log(get_string('bulk_task_config_validation', 'block_panopto'));
    $configvalidation = panopto_validate_configuration();

    if (!$configvalidation['valid']) {
        \panopto_data::print_log(get_string('bulk_task_config_error', 'block_panopto', $configvalidation['error_message']));

        if (!CLI_SCRIPT) {
            // This used to render the access-error view, which reports a user without folder
            // permissions and reads a $targetuser that was never defined in this scope, so PHP 8
            // warned and the admin was told the wrong thing about the wrong person.
            include($CFG->dirroot . '/blocks/panopto/views/bulk_task_config_error.html.php');
            panopto_bulk_flush_output();
        }

        // Unlike a single inaccessible folder, a bad server config or an unreachable server really
        // does mean no folder can be processed, so this one still stops the run.
        return [];
    }

    // Uname is the working user's Moodle username, which panopto_data fills in identically for
    // every mapping, so an empty one is a property of the request rather than of any one folder.
    // Checked here instead of only inside the loop, which would otherwise walk the entire
    // foldermap building a panopto_data per row just to skip every one for the same reason.
    if (empty($getunamepanopto->uname)) {
        panopto_bulk_display_blocked_error();

        return [];
    }

    // Which mapped and imported courses still exist, found in two queries instead of one per course.
    $existingcourseids = array_flip($DB->get_fieldset_sql(
        'SELECT DISTINCT c.id FROM {course} c JOIN {block_panopto_foldermap} fm ON fm.moodleid = c.id'
    ));
    if (!$skipimports) {
        $existingcourseids += array_flip($DB->get_fieldset_sql(
            'SELECT DISTINCT c.id FROM {course} c JOIN {block_panopto_importmap} im ON im.import_moodle_id = c.id'
        ));
    }

    $numberprocessed = 0;
    panopto_update_task_progress($numberprocessed, $numbertoprocess, 'validating', $upgradestep);
    foreach ($oldpanoptocourses as $oldcourse) {
        ++$currindex;

        if ($currindex < $beginningindex) {
            \panopto_data::print_log(get_string('bulk_task_skipping_folder', 'block_panopto', $currindex));
            continue;
        } else if ($currindex >= $endingindex) {
            \panopto_data::print_log(get_string('bulk_task_reached_count', 'block_panopto'));
            break;
        }

        $numberprocessed = panopto_handle_bulk_throttle($numberprocessed);
        panopto_update_task_progress($numberprocessed, $numbertoprocess, 'validating', null);

        $oldpanoptocourse = new stdClass();
        $oldpanoptocourse->panopto = new \panopto_data($oldcourse->moodleid);

        $moodlecourseexists = isset($existingcourseids[$oldcourse->moodleid]);
        $hasvalidpanoptodata = isset($oldpanoptocourse->panopto->servername) && !empty($oldpanoptocourse->panopto->servername) &&
            isset($oldpanoptocourse->panopto->applicationkey) && !empty($oldpanoptocourse->panopto->applicationkey);

        if ($moodlecourseexists && $hasvalidpanoptodata) {
            if (isset($oldpanoptocourse->panopto->uname) && !empty($oldpanoptocourse->panopto->uname)) {
                $oldpanoptocourse->panopto->ensure_auth_manager();
                $activepanoptoserverversion = $oldpanoptocourse->panopto->authmanager->get_server_version();
                if (!version_compare($activepanoptoserverversion, \panopto_data::$requiredpanoptoversion, '>=')) {
                    if (!CLI_SCRIPT) {
                        include($CFG->dirroot . '/blocks/panopto/views/bulk_task_version_error.html.php');
                    }

                    \panopto_data::print_log(
                        get_string('bulk_task_version_error', 'block_panopto') . '\n' .
                        get_string('impacted_server', 'block_panopto', $oldpanoptocourse->panopto->servername) . '\n' .
                        get_string('minimum_required_version', 'block_panopto', \panopto_data::$requiredpanoptoversion) . '\n' .
                        get_string('current_version', 'block_panopto', $activepanoptoserverversion)
                    );
                }
            } else {
                // Defensive: the request-level check above already covers an empty uname, since
                // every mapping takes it from the same user. Should one still arrive here it is
                // skipped and summarised rather than discarding the whole run as it used to.
                panopto_bulk_display_access_error($targetuser, $oldcourse->moodleid);
                $skippedfolders[] = $oldcourse->moodleid;
                continue;
            }
        } else {
            // Shouldn't hit this case, but in the case a row in the DB has invalid data move it to the old_foldermap.
            \panopto_data::print_log(get_string('bulk_task_removing_bad_folder_row', 'block_panopto', $oldcourse->moodleid));
            \panopto_data::delete_panopto_relation($oldcourse->moodleid, true);
            // Continue to the next entry assuming this one was cleanup.
            continue;
        }

        // Validating makes two Panopto calls per folder, the version check above and then the
        // folder lookup inside get_provisioning_info(), with nothing written between them. The
        // progress update at the top of the loop is the last output until both have returned, so
        // at the default 15 second connect and 30 second socket timeouts a slow pair leaves close
        // to 90 seconds of silence, past the 60 second idle timeout typical of nginx, Apache and
        // load balancers. This caps the silent stretch at one call rather than two.
        panopto_bulk_keepalive();

        $oldpanoptocourse->provisioninginfo = $oldpanoptocourse->panopto->get_provisioning_info();
        if (
            isset($oldpanoptocourse->provisioninginfo->accesserror) &&
            $oldpanoptocourse->provisioninginfo->accesserror === true
        ) {
            // The working user cannot see this folder in Panopto. Skipping it keeps the rest of
            // the run intact, and the summary logged at the end names every folder to revisit
            // once that Panopto account has been granted access.
            panopto_bulk_display_access_error($targetuser, $oldcourse->moodleid);
            $skippedfolders[] = $oldcourse->moodleid;
            continue;
        } else {
            if (
                isset($oldpanoptocourse->provisioninginfo->couldnotfindmappedfolder) &&
                $oldpanoptocourse->provisioninginfo->couldnotfindmappedfolder === true
            ) {
                // Course was mapped to a folder but that folder was not found, most likely folder was deleted on Panopto side.
                // The true parameter moves the row to the old_foldermap instead of deleting it.
                \panopto_data::delete_panopto_relation($oldcourse->moodleid, true);

                // Recreate the default role mappings that were deleted by the above line.
                $oldpanoptocourse->panopto->check_course_role_mappings();

                // Imports SHOULD still work for this case, so continue to below code.
            }

            if (!$skipimports) {
                $courseimports = \panopto_data::get_import_list($oldpanoptocourse->panopto->moodlecourseid);
                foreach ($courseimports as $courseimport) {
                    $importpanopto = new \panopto_data($courseimport);

                    $moodlecourseexists = isset($existingcourseids[$courseimport]);
                    $hasvalidpanoptodata = isset($importpanopto->servername) && !empty($importpanopto->servername) &&
                        isset($importpanopto->applicationkey) && !empty($importpanopto->applicationkey);

                    // Only perform the actions below if the import is in a valid state, otherwise remove it.
                    if ($moodlecourseexists && $hasvalidpanoptodata) {
                        $importpanoptofolder = $importpanopto->get_folders_by_id_no_sync();

                        if (
                            $importpanoptofolder == null ||
                            (isset($importpanoptofolder->notfound) &&
                            $importpanoptofolder->notfound == true)
                        ) {
                            // In this case the folder was not found, not an access issue.
                            // Most likely the folder was deleted and this is an old entry.
                            // Move the entry to the old_foldermap so user still has a reference.
                            \panopto_data::delete_panopto_relation($courseimport, true);
                            // We can still continue on with the upgrade.
                            // Assume this was an old entry that was deleted from Panopto side.
                        } else if (!empty($importpanoptofolder->errormessage)) {
                            // This case means the user failed to get the folder.
                            continue;
                        }
                    } else {
                        \panopto_data::print_log(get_string('bulk_task_removing_bad_folder_row', 'block_panopto', $courseimport));
                        \panopto_data::delete_panopto_relation($courseimport, true);
                        // Continue to the next entry assuming this one was cleanup.
                        continue;
                    }
                }
            }
        }
        // Validating attached an auth client to this folder, and get_provisioning_info() a session
        // client. Every verified folder then sits in this array until the whole run ends, so
        // keeping their clients means one parsed WSDL per folder alive at once, plus a recording
        // of every request each one made when print_verbose_logs turns SoapClient tracing on.
        // Processing calls ensure_auth_manager() and ensure_session_manager() again, so letting
        // them go here costs a reconnect and nothing else.
        $oldpanoptocourse->panopto->authmanager = null;
        $oldpanoptocourse->panopto->sessionmanager = null;
        $oldpanoptocourse->panopto->usermanager = null;

        $panoptocourseobjects[] = $oldpanoptocourse;
    }

    if (!empty($skippedfolders)) {
        // The ids are capped because print_log truncates long messages, and losing the counts to
        // a list of several thousand ids would hide the only numbers worth reading here.
        $displayedids = array_slice($skippedfolders, 0, 50);

        $skippedsummary = new stdClass();
        $skippedsummary->skippedcount = count($skippedfolders);
        $skippedsummary->totalcount = count($skippedfolders) + count($panoptocourseobjects);
        $skippedsummary->courseids = implode(', ', $displayedids) .
            (count($skippedfolders) > count($displayedids) ? ', ...' : '');

        // With every folder skipped there is no remaining work, and the usual summary would
        // promise processing that is not going to happen.
        $summarykey = empty($panoptocourseobjects)
            ? 'bulk_task_skipped_summary_all'
            : 'bulk_task_skipped_summary';

        \panopto_data::print_log(get_string($summarykey, 'block_panopto', $skippedsummary));

        if (!CLI_SCRIPT) {
            // Print_log sends this to debugging() unless the logs are redirected to a file, and
            // debugging() renders nothing with debug display off, so on a default site the banner
            // promised a list the admin had no way to read. The task finishes now instead of
            // stopping at the first bad folder, so this is the only on-screen sign of a gap.
            include($CFG->dirroot . '/blocks/panopto/views/bulk_task_skipped_summary.html.php');
            panopto_bulk_flush_output();
        }
    }

    return $panoptocourseobjects;
}

/**
 * Gets all existing mapped panopto folders, verifies the folders existand then renames them
 * to match their mapped course name.
 *
 * @param array $params
 * $params[1] - The index you would like to start with in the rename process.
 * $params[2] - The number of folders you would like to work on this execution.
 */
function panopto_rename_all_folders($params) {
    // This can take a very long time if users have many folders. Note raise() lifts PHP's own
    // max_execution_time only: PHP-FPM request_terminate_timeout, the web server's read timeout
    // and any load balancer in front of it can still cut a long browser run short, which is why
    // rename_all_folders_cli.php exists and takes a start index and a count.
    core_php_time_limit::raise();

    panopto_bulk_ensure_progress_bar();

    // Get total count for better throttling.
    $mappings = panopto_bulk_sanitize_and_get_mappings($params, true);
    $totalcount = count($mappings);

    panopto_bulk_process_folders(
        $mappings,
        get_string('bulk_rename_start_renaming', 'block_panopto'),
        'panopto_bulk_rename_callback',
        $totalcount
    );
}

/**
 * This script will reprovision all existing Panopto folders associated with this Moodle site.
 * For users experiencing issues with large amounts of courses
 * the following two parameters can be added to the CLI to batching purposes.
 *
 * e.g. upgrade_all_folders.php 550 500
 * This line will start at folder 550 and will process/upgrade up to folder 1050
 *
 * @param array $params
 * $params[1] - The index you would like to start with in the upgrade process.
 * $params[2] - The number of folders you would like to work on this execution.
 */
function panopto_upgrade_all_folders($params) {
    // This can take a very long time if users have many folders. Note raise() lifts PHP's own
    // max_execution_time only: PHP-FPM request_terminate_timeout, the web server's read timeout
    // and any load balancer in front of it can still cut a long browser run short, which is why
    // upgrade_all_folders_cli.php exists and takes a start index and a count.
    core_php_time_limit::raise();

    panopto_bulk_ensure_progress_bar();

    // We do not want to skip imports when bulk reprovisioning all folders.
    panopto_bulk_process_folders(
        panopto_bulk_sanitize_and_get_mappings($params, false),
        get_string('bulk_reprovision_begin_reprovision', 'block_panopto'),
        'panopto_bulk_reprovision_callback'
    );
}
/* End of file block_panopto_bulk_lib.php */
