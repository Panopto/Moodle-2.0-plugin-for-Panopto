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
 * Main library functions for the Panopto block
 *
 * @package block_panopto
 * @copyright  Panopto 2009 - 2016 /With contributions from Spenser Jones (sjones@ambrose.edu)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// This can't be defined Moodle internal because it is called from Panopto to authorize login.

/**
 * Push whatever output has been produced so far out to the browser.
 *
 * Bulk operations echo their progress as they go, but PHP and the web server hold that output in
 * a buffer until the request ends, so without this the admin watches a bar that never moves and
 * concludes the task died. panopto_data::print_log() flushes as well, but only on the branch
 * where logs go to the screen, so turning on logging to file used to silence the whole UI.
 *
 * This cannot defeat buffering applied downstream of PHP, such as mod_deflate or nginx
 * proxy_buffering, so long bulk runs still belong on the CLI.
 */
function panopto_bulk_flush_output() {
    if (CLI_SCRIPT) {
        return;
    }

    // Calling ob_flush() with no active buffer emits a notice, so check for one first.
    if (!empty(ob_get_status())) {
        ob_flush();
    }

    flush();
}

/**
 * Write an invisible byte or two to the page so the connection is seen to be alive.
 *
 * The idle timeouts in nginx, Apache and most load balancers fire after a fixed stretch with no
 * bytes arriving from PHP, and they reset whenever some do. Flushing an empty buffer sends
 * nothing and so resets nothing, which is why this emits a comment rather than just flushing:
 * a bulk run that spends minutes inside one large course would otherwise be cut off mid folder
 * and leave the progress bar frozen on whatever percentage it last painted.
 *
 * Only worth calling from a stretch of work that is both long and silent. It is a no-op on the
 * CLI, which has no proxy in front of it.
 */
function panopto_bulk_keepalive() {
    if (CLI_SCRIPT) {
        return;
    }

    echo '<!-- panopto bulk operation in progress -->';

    panopto_bulk_flush_output();
}

/**
 * Send a Javascript alert to the window for the current user to see.
 *
 * @param string $alertmessage - the message the user is supposed to see.
 */
function panopto_alert_user($alertmessage) {
    echo html_writer::script('alert(' . panopto_js_literal((string) $alertmessage) . ');');
}

/**
 * Encode a value as a JavaScript literal that is safe to place inside an inline script element.
 *
 * Encoded rather than concatenated: a quote or </script> in the value would otherwise end the
 * string literal or the element and run whatever followed it. The HEX flags also escape the
 * characters js_writer's plain json_encode leaves alone, such as <!-- and quotes.
 *
 * @param mixed $value the value to encode
 * @return string the JavaScript literal
 */
function panopto_js_literal($value) {
    return json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}

/**
 * A URL from Panopto that is safe to render as a link, or '' when it is not.
 *
 * The block renders the viewer, notes, settings, podcast and recorder URLs that Panopto's API
 * returns as links. Escaping keeps them inside the attribute but would still let a javascript:
 * or data: URL run when clicked, so only the listed schemes are kept. http(s) URLs must also be
 * well formed by PARAM_URL's rules, which the block_panopto_get_content web service declares.
 *
 * @param string|null $url the URL as Panopto returned it
 * @param string[] $schemes the schemes to allow. Panopto's iTunes podcast links use itpc.
 * @return string the URL unchanged, or '' when it is empty or not allowed
 */
function panopto_clean_link_url($url, array $schemes = ['http', 'https']) {
    if (!is_string($url) || trim($url) === '') {
        return '';
    }

    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    if (!in_array($scheme, $schemes, true)) {
        return '';
    }

    // PARAM_URL does not know itpc, so only http(s) links are held to its syntax.
    if (in_array($scheme, ['http', 'https'], true) && clean_param($url, PARAM_URL) !== $url) {
        return '';
    }

    return $url;
}

/**
 * Prepend the instance name to the Moodle course ID to create an external ID for Panopto Focus.
 *
 * @param int $moodlecourseid the id the the Moodle course being edited
 */
function panopto_decorate_course_id($moodlecourseid) {
    return (get_config('block_panopto', 'instance_name') . ':' . $moodlecourseid);
}

/**
 * Decorate a Moodle username with the instancename outside the context of a panopto_data object.
 *
 * @param int $moodleusername the name the the Moodle user being edited
 */
function panopto_decorate_username($moodleusername) {
    return (get_config('block_panopto', 'instance_name') . '\\' . $moodleusername);
}

/**
 * Sign the payload with the proof that it was generated by trusted code.
 *
 * Callers that know which server they are talking to pass that server's key. Without one, the key
 * is looked up from the first configured server whose name appears in the payload - a substring
 * match, so a payload for demo.panopto.com.au can resolve to demo.panopto.com's key.
 *
 * @param string $payload auth string being passed to be validated
 * @param string|null $applicationkey the key to sign with, or null to look it up from the payload
 * @return string the auth code
 */
function panopto_generate_auth_code($payload, $applicationkey = null) {
    $sharedsecret = $applicationkey;

    if (panopto_is_string_empty($sharedsecret)) {
        $index = 1;

        $numservers = get_config('block_panopto', 'server_number');
        $numservers = isset($numservers) ? $numservers : 0;

        // Increment numservers by 1 to take into account starting at 0.
        $numservers += 1;

        for ($serverwalker = 1; $serverwalker <= $numservers; ++$serverwalker) {
            $thisservername = get_config('block_panopto', 'server_name' . $serverwalker);
            if (isset($thisservername) && !empty($thisservername)) {
                if (strpos($payload, $thisservername)) {
                    $index = $serverwalker;
                    break;
                }
            }
        }

        $sharedsecret = get_config('block_panopto', 'application_key' . $index);
    }

    $signedpayload = $payload . '|' . $sharedsecret;

    $authcode = strtoupper(sha1($signedpayload));

    return $authcode;
}

/**
 * Ensures auth code is valid
 *
 * @param string $payload auth string being passed to be validated
 * @param string $authcode the authcode being validated
 * @param string|null $applicationkey the key to validate against, or null to look it up from the payload
 * @return bool true when the auth code matches
 */
function panopto_validate_auth_code($payload, $authcode, $applicationkey = null) {
    // A constant-time comparison: == would also compare two numeric-looking strings as numbers.
    return hash_equals(panopto_generate_auth_code($payload, $applicationkey), (string) $authcode);
}

/**
 * Takes an api url, then checks the Panopto config for proxy settings, and returns the relevant serviceparams object.
 *
 * @param string $apiurl
 */
function panopto_generate_wsdl_service_params($apiurl) {
    $serviceparams = ['wsdl_url' => $apiurl];

    // Set the SOAP service URI (namespace) - required for SOAP calls.
    $serviceparams['wsdl_uri'] = 'http://tempuri.org/';

    // Check to see if the user set any proxy options.
    $proxyhost = get_config('block_panopto', 'wsdl_proxy_host');
    $proxyport = get_config('block_panopto', 'wsdl_proxy_port');

    if (isset($proxyhost) && !empty($proxyhost)) {
        $serviceparams['wsdl_proxy_host'] = $proxyhost;
    }

    if (isset($proxyport) && !empty($proxyport)) {
        $serviceparams['wsdl_proxy_port'] = $proxyport;
    }

    $serviceparams['panopto_connection_timeout'] = get_config('block_panopto', 'panopto_connection_timeout');
    $serviceparams['panopto_socket_timeout'] = get_config('block_panopto', 'panopto_socket_timeout');

    // The generated *WsdlClass strips the wsdl_ prefix and hands these straight to SoapClient,
    // so they also govern the WSDL download done inside the constructor. Without them that
    // download runs on plain PHP streams with default_socket_timeout and no proxy, which is
    // what blocked the Moodle web request when the proxy was slow.
    $soapoptions = panopto_soap_client_options($serviceparams);

    if (isset($soapoptions['connection_timeout'])) {
        $serviceparams['wsdl_connection_timeout'] = $soapoptions['connection_timeout'];
    }

    if (isset($soapoptions['stream_context'])) {
        $serviceparams['wsdl_stream_context'] = $soapoptions['stream_context'];
    }

    return $serviceparams;
}

/**
 * Build the native SoapClient constructor options from Panopto service params.
 *
 * Shared by the hand-written user client and, through panopto_generate_wsdl_service_params(),
 * by the generated Auth/Session/UserManagement WsdlClasses so every SoapClient in the plugin,
 * including its WSDL fetch, uses the same proxy and timeouts.
 *
 * @param array $serviceparams output of panopto_generate_wsdl_service_params() or the same shape
 * @return array SoapClient options: proxy_host, proxy_port, connection_timeout, stream_context (each only when applicable)
 */
function panopto_soap_client_options(array $serviceparams): array {
    $options = [];
    $httpcontext = [];

    $proxyhost = $serviceparams['wsdl_proxy_host'] ?? null;
    $proxyport = $serviceparams['wsdl_proxy_port'] ?? null;
    $connectiontimeout = $serviceparams['panopto_connection_timeout'] ?? null;
    $sockettimeout = $serviceparams['panopto_socket_timeout'] ?? null;

    if (!empty($proxyhost)) {
        $options['proxy_host'] = $proxyhost;

        // Stream contexts want a URL; SoapClient takes host and port separately.
        $proxyurl = $proxyhost;
        if (strpos($proxyurl, '://') === false) {
            $proxyurl = 'tcp://' . $proxyurl;
        }
        if (!empty($proxyport)) {
            $options['proxy_port'] = (int) $proxyport;
            $proxyurl .= ':' . (int) $proxyport;
        }

        $httpcontext['proxy'] = $proxyurl;
        // HTTP proxies need the absolute URL in the request line.
        $httpcontext['request_fulluri'] = true;
    }

    if (!empty($connectiontimeout)) {
        $options['connection_timeout'] = (int) $connectiontimeout;
    }

    // Bound the WSDL fetch. Prefer the socket timeout (whole transfer); fall back to the
    // connection timeout so a hung proxy can never hold the request for default_socket_timeout.
    if (!empty($sockettimeout)) {
        $httpcontext['timeout'] = (float) $sockettimeout;
    } else if (!empty($connectiontimeout)) {
        $httpcontext['timeout'] = (float) $connectiontimeout;
    }

    if (!empty($httpcontext)) {
        $options['stream_context'] = stream_context_create(['http' => $httpcontext]);
    }

    return $options;
}

/**
 * Get configured Panopto servers.
 *
 */
function panopto_get_configured_panopto_servers() {

    $numservers = get_config('block_panopto', 'server_number');
    $numservers = isset($numservers) ? $numservers : 0;

    // Increment numservers by 1 to take into account starting at 0.
    $numservers += 1;

    $targetserverarray = [];
    for ($serverwalker = 1; $serverwalker <= $numservers; ++$serverwalker) {
        // Generate strings corresponding to potential servernames in the config.
        $thisservername = get_config('block_panopto', 'server_name' . $serverwalker);
        $thisappkey = get_config('block_panopto', 'application_key' . $serverwalker);

        if (!panopto_is_string_empty($thisservername) && !panopto_is_string_empty($thisappkey)) {
            $targetserverarray[$thisservername] = $thisservername;
        }
    }

    return $targetserverarray;
}

/**
 * Returns true if the given host equals a Panopto server name or is a subdomain of it.
 *
 * Matching is anchored on a dot boundary. A substring comparison would wrongly accept
 * hosts such as "demo.panopto.com.attacker.example"
 *
 * This is the single implementation of the comparison. SSO.php binds to the specific
 * serverName it was handed rather than to the whole configured list, so it calls this
 * directly instead of going through panopto_is_allowed_panopto_host
 *
 * @param string $host the host to test, as returned by parse_url
 * @param string $server a Panopto server name from the plugin configuration
 * @return bool true when the host belongs to that server
 */
function panopto_host_matches_server($host, $server) {
    if (panopto_is_string_empty($host) || panopto_is_string_empty($server)) {
        return false;
    }

    $host = trim($host);
    $server = trim($server);

    if (strcasecmp($host, $server) === 0) {
        return true;
    }

    $dotsuffix = '.' . $server;

    return strlen($host) > strlen($dotsuffix) &&
        strcasecmp(substr($host, -strlen($dotsuffix)), $dotsuffix) === 0;
}

/**
 * Returns every Panopto host this Moodle is known to talk to.
 *
 * Deliberately wider than panopto_get_configured_panopto_servers(): that function only
 * returns a server when an application key is set alongside it, because it feeds the
 * SOAP signing path where the key is required. Content hosts are not signed, and an
 * LTI-1.3-only deployment legitimately has no application keys at all - sourcing an
 * allowlist from that function would return an empty list and reject every real embed.
 * The two single-server settings folded in here are the same fallbacks
 * panoptoblock_lti_utility::get_target_server_name() launches against
 *
 * Anything that is not a fully qualified name is dropped, so an untouched default cannot
 * become an allowlist entry
 *
 * @return array server names, keyed by name
 */
function panopto_get_known_panopto_hosts() {
    $candidates = [get_config('mod_panoptocourseembed', 'default_panopto_server')];

    $numservers = get_config('block_panopto', 'server_number');
    $numservers = isset($numservers) ? $numservers : 0;

    // Increment numservers by 1 to take into account starting at 0.
    $numservers += 1;

    for ($serverwalker = 1; $serverwalker <= $numservers; ++$serverwalker) {
        $candidates[] = get_config('block_panopto', 'server_name' . $serverwalker);
    }

    $candidates[] = get_config('block_panopto', 'automatic_operation_target_server');

    $hosts = [];
    foreach ($candidates as $candidate) {
        if (panopto_is_string_empty($candidate)) {
            continue;
        }

        $candidate = trim($candidate);

        // A Panopto server is always a fully qualified name, and requiring the dot keeps
        // placeholder values out of the allowlist. automatic_operation_target_server is a
        // configselect whose default is the index 0, so a site that never picked a server
        // stores the literal "0" - and "0" is a host a browser resolves, to 0.0.0.0.
        if (strpos($candidate, '.') === false) {
            continue;
        }

        $hosts[$candidate] = $candidate;
    }

    return $hosts;
}

/**
 * Returns true if the host of the given URL belongs to a known Panopto server.
 *
 * @param string $url the absolute URL whose host should be validated
 * @return bool true when the URL host belongs to a known Panopto server
 */
function panopto_is_allowed_panopto_host($url) {
    if (panopto_is_string_empty($url)) {
        return false;
    }

    $host = parse_url($url, PHP_URL_HOST);
    if (empty($host)) {
        return false;
    }

    foreach (panopto_get_known_panopto_hosts() as $possibleserver) {
        if (panopto_host_matches_server($host, $possibleserver)) {
            return true;
        }
    }

    return false;
}

/**
 * Returns true if a Panopto SSO payload's expiration timestamp has already passed.
 *
 * The expiration is part of the signed payload, so without this check a captured SSO URL
 * replays for as long as the shared key lives.
 *
 * The caller is expected to have already rejected values that are not a plain decimal number;
 * this function only decides whether a well-formed value is in the past.
 *
 * @param string $expiration the expiration value as it arrived: seconds since 1601-01-01 UTC as
 *     Panopto issues it, or Unix seconds.
 * @param int|null $now unix timestamp to compare against. Defaults to now, overridden by tests.
 * @return bool true when the payload has expired and the launch must be refused.
 */
function panopto_is_sso_payload_expired($expiration, $now = null) {
    // Panopto issues the expiration in seconds since 1601-01-01 UTC - the .NET and Windows file
    // time epoch - not since 1970. On that scale the Unix epoch falls at 11644473600.
    $windowsepochoffset = 11644473600;

    // 2100-01-01, well past any expiry a Panopto server would legitimately issue in seconds.
    $secondsupperbound = 4102444800;

    // Absorb clock skew between Moodle and the Panopto server.
    $clockskewgrace = 300;

    $now = is_null($now) ? time() : $now;
    $expirationtime = (float) $expiration;

    if ($expirationtime <= 0) {
        return false;
    }

    // Only the 1970-2100 window of the 1601-based scale is converted. Unix seconds cannot reach
    // it before the year 2338, and a millisecond value only lands in it if it means mid-1970,
    // so inside this window the unit is unambiguous.
    if ($expirationtime >= $windowsepochoffset && $expirationtime < $windowsepochoffset + $secondsupperbound) {
        $expirationtime -= $windowsepochoffset;
    }

    if ($expirationtime >= $secondsupperbound) {
        // Neither Unix seconds nor Panopto's 1601-based seconds, so it is in some other unit and
        // comparing it to time() would be meaningless. Every such payload is accepted rather than
        // locking the whole site out of SSO on an assumption, but that means expiry is not being
        // enforced - say so, because otherwise this fails open silently.
        debugging(
            'Panopto SSO expiration ' . $expiration . ' is not in Unix seconds or in seconds since ' .
                '1601; expiry was not enforced for this request.',
            DEBUG_DEVELOPER
        );

        return false;
    }

    return $expirationtime < ($now - $clockskewgrace);
}

/**
 * SSO endpoint URL, including the query parameters that must survive require_login().
 *
 * require_login() stores qualified_me() as wantsurl, and qualified_me() prefers $PAGE->url
 * over $FULLME once it has been set. A page URL with no query string therefore sends the
 * user back to SSO.php without serverName after they sign in on Moodle.
 *
 * Empty optional values are omitted so a later required_param() still reports them missing
 * instead of receiving an empty string.
 *
 * @param string $servername Panopto server name from the incoming request
 * @param string $callbackurl Panopto callback URL from the incoming request
 * @param string $expiration payload expiration, empty when absent
 * @param string $authcode incoming auth code, empty when absent
 * @param string $action optional SSO action such as relogin
 * @return moodle_url
 */
function panopto_sso_page_url($servername, $callbackurl, $expiration = '', $authcode = '', $action = '') {
    $params = [
        'serverName' => $servername,
        'callbackURL' => $callbackurl,
    ];

    if ($expiration !== '') {
        $params['expiration'] = $expiration;
    }

    if ($authcode !== '') {
        $params['authCode'] = $authcode;
    }

    if ($action !== '') {
        $params['action'] = $action;
    }

    return new moodle_url('/blocks/panopto/SSO.php', $params);
}

/**
 * Returns a list of objects containing valid servername/appkey pairs we are targeting.
 *
 */
function panopto_get_target_panopto_server() {
    $ret = null;
    $targetserver = get_config('block_panopto', 'automatic_operation_target_server');

    $numservers = get_config('block_panopto', 'server_number');
    $numservers = isset($numservers) ? $numservers : 0;

    // Increment numservers by 1 to take into account starting at 0.
    $numservers += 1;
    for ($serverwalker = 1; $serverwalker <= $numservers; ++$serverwalker) {
        // Generate strings corresponding to potential servernames in the config.
        $thisservername = get_config('block_panopto', 'server_name' . $serverwalker);

        if (strcasecmp($thisservername, $targetserver) == 0) {
            $thisappkey = get_config('block_panopto', 'application_key' . $serverwalker);
            $hasvaliddata = isset($thisappkey) && !empty($thisappkey);

            // If we have valid data for the server then try to ensure the category branch.
            if ($hasvaliddata) {
                $ret = new stdClass();
                $ret->name = $thisservername;
                $ret->appkey = $thisappkey;
                break;
            }
        }
    }
    return $ret;
}

/**
 * Returns a list of objects containing valid servername/appkey pairs.
 *
 */
function panopto_get_valid_panopto_servers() {
    $ret = [];

    $numservers = get_config('block_panopto', 'server_number');
    $numservers = isset($numservers) ? $numservers : 0;

    // Increment numservers by 1 to take into account starting at 0.
    $numservers += 1;

    for ($serverwalker = 1; $serverwalker <= $numservers; ++$serverwalker) {
        // Generate strings corresponding to potential servernames in the config.
        $thisservername = get_config('block_panopto', 'server_name' . $serverwalker);
        $thisappkey = get_config('block_panopto', 'application_key' . $serverwalker);
        $hasvaliddata = isset($thisappkey) && !empty($thisappkey);

        // If we have valid data for the server then try to ensure the category branch.
        if ($hasvaliddata) {
            $targetserver = new stdClass();
            $targetserver->name = $thisservername;
            $targetserver->appkey = $thisappkey;
            $ret[] = $targetserver;
        }
    }
    return $ret;
}

/**
 * Retrieve the app key for the target panopto server.
 *
 * Server names are host names, so they are matched case-insensitively - the same way SSO.php
 * matches an incoming serverName against the configured servers.
 *
 * @param string $panoptoservername the server we are trying to get the application key for
 * @return string|null the application key, or null when no configured server has that name
 */
function panopto_get_app_key($panoptoservername) {
    if (panopto_is_string_empty($panoptoservername)) {
        return null;
    }

    $numservers = get_config('block_panopto', 'server_number');
    $numservers = isset($numservers) ? $numservers : 0;

    // Increment numservers by 1 to take into account starting at 0.
    $numservers += 1;

    for ($serverwalker = 1; $serverwalker <= $numservers; ++$serverwalker) {
        // Generate strings corresponding to potential servernames in the config.
        $thisservername = get_config('block_panopto', 'server_name' . $serverwalker);
        $thisappkey = get_config('block_panopto', 'application_key' . $serverwalker);

        $hasservername = !panopto_is_string_empty($thisservername);
        if ($hasservername && strcasecmp($thisservername, $panoptoservername) == 0) {
            return $thisappkey;
        }
    }

    return null;
}

/**
 * Validates Panopto configuration against the target server before bulk operations.
 * This prevents common issues where production config is copied to test environments.
 *
 * @param string $servername The Panopto server name to validate
 * @param string $applicationkey The application key to validate
 * @return array Array with 'valid' boolean and 'error_message' string if invalid
 */
function panopto_validate_configuration($servername = null, $applicationkey = null) {
    global $USER;

    // Get configuration if not provided.
    if ($servername === null || $applicationkey === null) {
        $targetserver = panopto_get_target_panopto_server();
        if (!$targetserver) {
            return [
                'valid' => false,
                'error_message' => get_string('config_error_invalid_server_config', 'block_panopto'),
            ];
        }
        $servername = $targetserver->name;
        $applicationkey = $targetserver->appkey;
    }

    // Check if server is reachable.
    $serverurl = 'https://' . $servername . '/Panopto';
    if (!\panopto_data::is_server_alive($serverurl)) {
        return [
            'valid' => false,
            'error_message' => get_string('config_error_server_unreachable', 'block_panopto', $servername),
        ];
    }

    // Try to authenticate and validate configuration.
    try {
        // Create auth client to test authentication.
        $apiuseruserkey = panopto_decorate_username($USER->username);
        $apiuserauthcode = panopto_generate_auth_code($apiuseruserkey . '@' . $servername, $applicationkey);

        $authclient = new panopto_auth_soap_client($servername, $apiuseruserkey, $apiuserauthcode);

        // Test authentication by getting server version.
        $serverversion = $authclient->get_server_version();

        if (!$serverversion) {
            return [
                'valid' => false,
                'error_message' => get_string('config_error_auth_failed', 'block_panopto'),
            ];
        }

        // If we get here, configuration is valid.
        return [
            'valid' => true,
            'error_message' => '',
        ];
    } catch (Exception $e) {
        \panopto_data::print_log('Configuration validation failed: ' . $e->getMessage());
        return [
            'valid' => false,
            'error_message' => get_string('config_error_auth_failed', 'block_panopto'),
        ];
    }
}

/**
 * Used to instantiate a user soap client for a given instance of panopto_data.
 * Should be called only the first time a soap client is needed for an instance.
 *
 * @param string $username the name of the current user
 * @param string $servername the name of the active server
 * @param string $applicationkey the key need for the user to be authenticated
 */
function panopto_instantiate_user_soap_client($username, $servername, $applicationkey) {
    // Compute web service credentials for given user.
    $apiuseruserkey = panopto_decorate_username($username);
    $apiuserauthcode = panopto_generate_auth_code($apiuseruserkey . '@' . $servername, $applicationkey);

    // Instantiate our SOAP client.
    return new panopto_user_soap_client($servername, $apiuseruserkey, $apiuserauthcode);
}

/**
 * Used to instantiate a session soap client for a given instance of panopto_data.
 *
 * @param string $username the name of the current user
 * @param string $servername the name of the active server
 * @param string $applicationkey the key need for the user to be authenticated
 */
function panopto_instantiate_session_soap_client($username, $servername, $applicationkey) {
    // Compute web service credentials for given user.
    $apiuseruserkey = panopto_decorate_username($username);
    $apiuserauthcode = panopto_generate_auth_code($apiuseruserkey . '@' . $servername, $applicationkey);

    // Instantiate our SOAP client.
    return new panopto_session_soap_client($servername, $apiuseruserkey, $apiuserauthcode);
}

/**
 * Used to instantiate a soap client for calling Panopto's iAuth service.
 * Should be called only the first time an  auth soap client is needed for an instance.
 *
 * @param string $username the name of the current user
 * @param string $servername the name of the active server
 * @param string $applicationkey the key need for the user to be authenticated
 */
function panopto_instantiate_auth_soap_client($username, $servername, $applicationkey) {
    // Compute web service credentials for given user.
    $apiuseruserkey = panopto_decorate_username($username);
    $apiuserauthcode = panopto_generate_auth_code($apiuseruserkey . '@' . $servername, $applicationkey);

    // Instantiate our SOAP client.
    return new panopto_auth_soap_client($servername, $apiuseruserkey, $apiuserauthcode);
}

/**
 * Returns true if a string is null or empty, false otherwise
 *
 * @param string $name the string being checked for null or empty
 */
function panopto_is_string_empty($name) {
    return (!isset($name) || trim($name) === '');
}

/**
 * Courses that this provision_course.php request should sync.
 *
 * "Sync this course to Panopto" loads provision_course.php with course_id in GET.
 * Once confirmed, that course is provisioned even though the bulk form was not posted.
 * The bulk admin form supplies courses only after POST. An empty result means
 * display the form.
 *
 * @param int $courseidparam course_id request param, 0 if absent
 * @param array|string|null $formcourses course ids submitted on the bulk form, if any
 * @return array
 */
function panopto_courses_to_provision(int $courseidparam, $formcourses): array {
    if ($courseidparam !== 0) {
        return [$courseidparam];
    }
    if (empty($formcourses)) {
        return [];
    }
    return array_map('intval', array_values((array) $formcourses));
}

/**
 * Returns true if the test guid is empty (all values in guid add to 0)
 *
 * @param string $guidstring the uid being tested in string format.
 */
function panopto_is_guid_empty($guidstring) {
    // Assuming the guid is valid.
    $retval = false;
    $val = array_sum(explode("-", $guidstring));

    if ($val == 0) {
        // Guid is empty.
        $retval = true;
    }

    return $retval;
}

/**
 * Returns true if the user info has not been cleared
 *
 * @param string $userinfostring information to check
 */
function panopto_user_info_valid($userinfostring) {
    // Assuming the guid is valid.
    $retval = true;

    if (strcmp($userinfostring, '--Deleted--') === 0) {
        // Guid is empty.
        $retval = false;
    }

    return $retval;
}

/**
 * Get all roles for context and context level
 *
 * @param string $targetcontext target context
 */
function panopto_get_all_roles_at_context_and_contextlevel($targetcontext) {
    global $DB;

    $sql = "SELECT r.*, rn.name AS contextalias " .
        "FROM {role} r " .
        "INNER JOIN {role_context_levels} rcl ON (rcl.contextlevel = :targetcontextlevel AND rcl.roleid = r.id) " .
        "LEFT JOIN {role_names} rn ON (rn.contextid = :targetcontext AND rn.roleid = r.id) " .
        "ORDER BY r.sortorder ASC";
    return $DB->get_records_sql(
        $sql,
        ['targetcontext' => $targetcontext->id, 'targetcontextlevel' => $targetcontext->contextlevel]
    );
}

/**
 * Unsets all system publishers that are not currently mapped
 *  and maps all roles that are currently set as publishers to have the proper capability.
 */
function panopto_update_system_publishers() {
    global $DB;
    $capability = 'block/panopto:provision_aspublisher';
    $systemcontext = context_system::instance();
    $systemrolearray = panopto_get_all_roles_at_context_and_contextlevel($systemcontext);
    $publisherrolesstring = get_config('block_panopto', 'publisher_system_role_mapping');
    $publishersystemroles = explode(',', $publisherrolesstring);
    $publisherprocessed = false;

    // Remove the system publisher capability from all old publishers.
    foreach ($systemrolearray as $possiblesystempublisher) {
        $targetname = !empty($possiblesystempublisher->name) ? $possiblesystempublisher->name : $possiblesystempublisher->shortname;
        if (!in_array($targetname, $publishersystemroles)) {
            $publisherprocessed = true;
            unassign_capability($capability, $possiblesystempublisher->id, $systemcontext);
        }
    }

    // Build and process new/old changes to capabilities to roles and capabilities.
    $publisherprocessed = \panopto_data::build_and_assign_context_capability_to_roles(
        $systemcontext,
        $publishersystemroles,
        $capability
    ) || $publisherprocessed;

    // If any changes where made, context needs to be flagged as dirty to be re-cached.
    if ($publisherprocessed) {
        $systemcontext->mark_dirty();
    }
}
/* End of file block_panopto_lib.php */
