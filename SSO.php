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
 * manages the single sign on logic between Panopto and Moodle
 *
 * @package block_panopto
 * @copyright  Panopto 2009 - 2016 /With contributions from Spenser Jones (sjones@ambrose.edu)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// This can't be defined Moodle internal because it is called from Panopto to authorize login.

// @codingStandardsIgnoreLine
global $CFG;
if (empty($CFG)) {
    // @codingStandardsIgnoreLine
    require_once(dirname(__FILE__) . '/../../config.php');
}
require_once($CFG->libdir . '/weblib.php');
require_once(dirname(__FILE__) . '/lib/block_panopto_lib.php');

$servername = required_param('serverName', PARAM_HOST);
$callbackurl = required_param('callbackURL', PARAM_URL);
$expirationparam = optional_param('expiration', '', PARAM_RAW);
$requestauthcodeparam = optional_param('authCode', '', PARAM_ALPHANUM);
$action = optional_param('action', '', PARAM_ALPHA);
$configuredserverarray = panopto_get_configured_panopto_servers();

// Normalise before validating so that the host being checked is the host that will
// actually be redirected to, rather than a still-encoded form of it.
if (
    strpos($callbackurl, 'http%') !== false ||
    strpos($callbackurl, 'https%') !== false
) {
    $callbackurl = urldecode($callbackurl);
}

// Set these before any output so the error branches below do not render a page with no
// context or URL set. The query string must be part of this URL: require_login() stores
// qualified_me() as wantsurl, which is $PAGE->url once set, so without the params the
// post-login return is SSO.php with nothing on it.
$PAGE->set_context(context_system::instance());
$PAGE->set_url(panopto_sso_page_url(
    $servername,
    $callbackurl,
    $expirationparam,
    $requestauthcodeparam,
    $action
));

$callbackverified = false;
foreach ($configuredserverarray as $possibleserver) {
    if (strcasecmp($possibleserver, $servername) == 0) {
        // Anchored comparison, shared with panopto_is_allowed_panopto_host. A substring test
        // would also accept hosts such as "demo.panopto.com.attacker.example" and redirect
        // the signed payload off-site.
        $callbackhost = parse_url($callbackurl, PHP_URL_HOST);
        $callbackverified = panopto_host_matches_server($callbackhost, $servername);
        break;
    }
}

if ($callbackverified) {
    // A float doesn't have the required precision.
    $expiration = preg_replace('/[^0-9\.]/', '', required_param('expiration', PARAM_RAW));

    // The whitelist above still admits '', '.' and '1.2.3', all of which cast to a number
    // that would sail through the expiry test below. Reject rather than rewrite: the string
    // is re-signed verbatim further down, so normalising it here would break the signature.
    if (!preg_match('/^[0-9]+(\.[0-9]+)?$/', $expiration)) {
        echo $OUTPUT->header();

        echo $OUTPUT->notification(get_string('sso_invalid_expiration', 'block_panopto'), 'notifyproblem');
        echo $OUTPUT->continue_button(new moodle_url('/'));

        echo $OUTPUT->footer();

        return;
    }

    $requestauthcode = required_param('authCode', PARAM_ALPHANUM);

    if (panopto_is_sso_payload_expired($expiration)) {
        echo $OUTPUT->header();

        echo $OUTPUT->notification(get_string('sso_expired_payload', 'block_panopto'), 'notifyproblem');

        // The payload cannot be re-signed from here, so the only forward path is back into
        // Moodle to start the launch again. Without this the page is a dead end.
        echo $OUTPUT->continue_button(new moodle_url('/'));

        echo $OUTPUT->footer();

        return;
    }

    $relogin = ($action == 'relogin');
    $isguest = isset($USER->username) && ($USER->username == 'guest');

    // Panopto cannot put a Moodle sesskey on its relogin link, so acting on it unconfirmed would
    // let anyone holding a captured SSO URL log a user out. Ask first, as core's login/logout.php
    // does; the continue button posts back with the sesskey. A guest session has nothing to lose.
    // The sesskey is read with a default because confirm_sesskey() with no argument calls
    // required_param() and throws on the first visit, before the confirmation can be shown.
    $sesskey = optional_param('sesskey', '__notpresent__', PARAM_RAW);
    if ($relogin && isloggedin() && !$isguest && !confirm_sesskey($sesskey)) {
        echo $OUTPUT->header();
        echo $OUTPUT->confirm(
            get_string('logoutconfirm'),
            new moodle_url($PAGE->url, ['sesskey' => sesskey()]),
            new moodle_url('/')
        );
        echo $OUTPUT->footer();

        return;
    }

    if ($relogin || $isguest) {
        require_logout();

        // Return to this page, minus the "action=relogin" parameter.
        redirect(panopto_sso_page_url($servername, $callbackurl, $expiration, $requestauthcode));
        return;
    }

    // No course ID (0). Don't autologin guests (false).
    require_login(0, false);

    // Reproduce canonically-ordered incoming auth payload.
    // CallbackURL is not part of it: Panopto signs only serverName and expiration, so it cannot be
    // added on this side without a matching Panopto change. What bounds a captured payload is the
    // anchored callback host check and the expiry check above - where it can go, and for how long.
    $requestauthpayload = 'serverName=' . $servername . '&expiration=' . $expiration;

    // Sign and verify with this server's own key. Without it the key would be found by searching
    // the payload for a configured server name, which picks demo.panopto.com's key for a request
    // from demo.panopto.com.au. A server with no key cannot sign anything, so it is refused.
    $appkey = panopto_get_app_key($servername);

    // Verify passed in parameters are properly signed.
    if (
        !panopto_is_string_empty($appkey) &&
        panopto_validate_auth_code($requestauthpayload, $requestauthcode, $appkey)
    ) {
        $userkey = panopto_decorate_username($USER->username);


        $selectedssotype = get_config('block_panopto', 'sso_sync_type');
        $ssosynctask = new \block_panopto\task\sync_user_login();

        $targetserver = new stdClass();
        $targetserver->name = $servername;
        $targetserver->appkey = $appkey;

        $ssosynctask->set_custom_data([
            'userid' => $USER->id,
            'targetserver' => $targetserver,
        ]);

        switch ($selectedssotype) {
            case 'sync':
                $ssosynctask->execute();
                break;
            case 'asyncsync':
                \core\task\manager::queue_adhoc_task($ssosynctask);
                break;
        }

        // Strip ReturnUrl so we can append it on the end. parse_url() returns null when the
        // callback has no query string, and parse_str() rejects null on PHP 8.1+.
        $callbackquery = parse_url($callbackurl, PHP_URL_QUERY);
        parse_str(is_string($callbackquery) ? $callbackquery : '', $params);
        $returnurl = isset($params['ReturnUrl']) ? $params['ReturnUrl'] : "";

        // Handle fragment with hash.
        $fragment = "#";
        if (strpos($callbackurl, "#") !== false) {
            $fragment .= strtok(explode("#", $callbackurl)[1], '&');
        } else {
            $fragment = "";
        }

        // Strip ReturnUrl from original URL and clean if necessary.
        // This is constant LoginPageRedirectParamKey = "ReturnUrl" so it should always arrive in this format.
        $url = preg_replace('/&?ReturnUrl=[^&]*/', '', $callbackurl);
        $url = str_replace('?&', '?', $url);
        $url = rtrim($url, '?');

        // Generate canonically-ordered auth payload string.
        $responseparams = 'serverName=' . $servername . '&externalUserKey=' . $userkey . '&expiration=' . $expiration;
        // Sign payload with shared key and hash.
        $responseauthcode = panopto_generate_auth_code($responseparams, $appkey);

        // Encode user key in case the backslash causes a sequence to be interpreted as an escape sequence.
        // (e.g. in the case of usernames that begin with digits).
        // Maintain the original canonical string to avoid signature mismatch.
        $responseparamsencoded =
            'serverName=' . $servername . '&externalUserKey=' . urlencode($userkey) . '&expiration=' . $expiration;

        $fullreturnurl = !empty($returnurl) ? '&ReturnUrl=' . urlencode($returnurl) . $fragment : "";
        $separator = (strpos($url, '?') ? '&' : '?');
        $redirecturl = $url . $separator . $responseparamsencoded . '&authCode=' . $responseauthcode . $fullreturnurl;

        // Redirect to Panopto Focus login page.
        redirect($redirecturl);
    } else {
        echo $OUTPUT->header();

        echo get_string('sso_invalid_authcode', 'block_panopto');

        echo $OUTPUT->footer();
    }
} else {
        echo $OUTPUT->header();

        echo get_string('sso_invalid_server', 'block_panopto');

        echo $OUTPUT->footer();
}
/* End of file SSO.php */
