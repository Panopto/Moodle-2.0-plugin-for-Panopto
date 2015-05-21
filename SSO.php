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

global $CFG, $USER;

if (empty($CFG)) {
    require_once("../../config.php");
}
require_once($CFG->libdir . '/weblib.php');
require_once("lib/block_panopto_lib.php");

$servername = required_param("serverName", PARAM_HOST);
$callbackurl = required_param("callbackURL", PARAM_URL);
$expiration = preg_replace('/[^0-9\.]/', '', required_param("expiration", PARAM_RAW)); // A float doesn't have the required precision.
$requestauthcode = required_param("authCode", PARAM_ALPHANUM);
$action = optional_param("action", "", PARAM_ALPHA);

$relogin = ($action == "relogin");

if ($relogin || (isset($USER->username) && ($USER->username == "guest"))) {
    require_logout();

    // Return to this page, minus the "action=relogin" parameter.
    redirect($CFG->wwwroot . "/blocks/panopto/SSO.php" .
            "?authCode=$requestauthcode" .
            "&serverName=$servername" .
            "&expiration=$expiration" .
            "&callbackURL=" . urlencode($callbackurl));
    return;
}

// No course ID (0).  Don't autologin guests (false).
require_login(0, false);

// Reproduce canonically-ordered incoming auth payload.
$requestauthpayload = "serverName=" . $servername . "&expiration=" . $expiration;

// Verify passed in parameters are properly signed.
if (panopto_validate_auth_code($requestauthpayload, $requestauthcode)) {
    $userkey = panopto_decorate_username($USER->username);

    // Generate canonically-ordered auth payload string.
    $responseparams ="serverName=" . $servername . "&externalUserKey=" . $userkey . "&expiration=" . $expiration;
    // Sign payload with shared key and hash.
    $responseauthcode = panopto_generate_auth_code($responseparams);

    // Encode user key in case the backslash causes a sequence to be interpreted as an escape sequence
    // (e.g. in the case of usernames that begin with digits).
    // Maintain the original canonical string to avoid signature mismatch.
    $responseparamsencoded = "serverName=" . $servername . "&externalUserKey=" . urlencode($userkey) . "&expiration=" . $expiration;

    $separator = (strpos($callbackurl, "?") ? "&" : "?");
    $redirecturl = $callbackurl . $separator . $responseparamsencoded . "&authCode=" . $responseauthcode;

    // Redirect to Panopto Focus login page.
    redirect($redirecturl);
} else {
    echo $OUTPUT->header();

    echo "Invalid auth code.";

    echo $OUTPUT->footer();
}
/* End of file SSO.php */