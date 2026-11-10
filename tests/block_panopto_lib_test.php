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

namespace block_panopto;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/panopto/lib/block_panopto_lib.php');
require_once($CFG->dirroot . '/blocks/panopto/lib/lti/panoptoblock_lti_utility.php');

/**
 * Unit tests for the security helpers in block_panopto_lib.
 *
 * These guard the two checks that decide whether a signed payload leaves this site: which hosts
 * a launch may be posted to, and how long an SSO payload stays valid.
 *
 * @package    block_panopto
 * @category   test
 * @copyright  2026 Panopto
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class block_panopto_lib_test extends \advanced_testcase {
    /**
     * A course_id query param provisions that course even when the bulk form was not posted.
     *
     * @return void
     * @covers ::panopto_courses_to_provision
     */
    public function test_courses_to_provision_uses_course_id_without_form_data(): void {
        $this->assertSame([12], panopto_courses_to_provision(12, null));
        $this->assertSame([12], panopto_courses_to_provision(12, [3, 4]));
        $this->assertSame([3, 4], panopto_courses_to_provision(0, [3, 4]));
        $this->assertSame([5], panopto_courses_to_provision(0, '5'));
        $this->assertSame([], panopto_courses_to_provision(0, null));
        $this->assertSame([], panopto_courses_to_provision(0, []));
    }

    /**
     * With a proxy and both timeouts configured, the service params carry everything SoapClient
     * needs to proxy and bound the WSDL download itself, not just the later SOAP calls.
     *
     * @return void
     * @covers ::panopto_generate_wsdl_service_params
     * @covers ::panopto_soap_client_options
     */
    public function test_wsdl_service_params_carry_proxy_and_timeouts_to_wsdl_fetch(): void {
        $this->resetAfterTest();

        set_config('wsdl_proxy_host', 'proxy.example.test', 'block_panopto');
        set_config('wsdl_proxy_port', '3128', 'block_panopto');
        set_config('panopto_connection_timeout', '15', 'block_panopto');
        set_config('panopto_socket_timeout', '30', 'block_panopto');

        $params = panopto_generate_wsdl_service_params('https://demo.panopto.com/Panopto/PublicAPI/4.6/Auth.svc?singlewsdl');

        // Existing keys the generated WsdlClass and curl path already read.
        $this->assertSame('proxy.example.test', $params['wsdl_proxy_host']);
        $this->assertSame('3128', $params['wsdl_proxy_port']);
        $this->assertSame('15', $params['panopto_connection_timeout']);
        $this->assertSame('30', $params['panopto_socket_timeout']);

        // New: stripped to connection_timeout / stream_context by *WsdlClass::initSoapClient().
        $this->assertSame(15, $params['wsdl_connection_timeout']);
        $this->assertIsResource($params['wsdl_stream_context']);

        $http = stream_context_get_options($params['wsdl_stream_context'])['http'];
        $this->assertSame('tcp://proxy.example.test:3128', $http['proxy']);
        $this->assertTrue($http['request_fulluri']);
        $this->assertSame(30.0, $http['timeout']);
    }

    /**
     * Without a proxy the stream context still bounds the fetch, but must not send traffic
     * through a proxy that does not exist.
     *
     * @return void
     * @covers ::panopto_generate_wsdl_service_params
     * @covers ::panopto_soap_client_options
     */
    public function test_wsdl_service_params_without_proxy_have_timeout_only(): void {
        $this->resetAfterTest();

        unset_config('wsdl_proxy_host', 'block_panopto');
        unset_config('wsdl_proxy_port', 'block_panopto');
        set_config('panopto_connection_timeout', '15', 'block_panopto');
        set_config('panopto_socket_timeout', '30', 'block_panopto');

        $params = panopto_generate_wsdl_service_params('https://demo.panopto.com/Panopto/PublicAPI/4.6/Auth.svc?singlewsdl');

        $this->assertArrayNotHasKey('wsdl_proxy_host', $params);
        $this->assertArrayNotHasKey('wsdl_proxy_port', $params);
        $this->assertSame(15, $params['wsdl_connection_timeout']);

        $http = stream_context_get_options($params['wsdl_stream_context'])['http'];
        $this->assertArrayNotHasKey('proxy', $http);
        $this->assertArrayNotHasKey('request_fulluri', $http);
        $this->assertSame(30.0, $http['timeout']);
    }

    /**
     * The option builder shared by the user client and the generated classes.
     *
     * @return void
     * @covers ::panopto_soap_client_options
     */
    public function test_soap_client_options_builder(): void {
        // Nothing configured: nothing added, so SoapClient falls back to its defaults.
        $this->assertSame([], panopto_soap_client_options([]));
        $this->assertSame([], panopto_soap_client_options([
            'wsdl_proxy_host' => '',
            'panopto_connection_timeout' => '',
            'panopto_socket_timeout' => false,
        ]));

        // Proxy without a port: still proxied, no port appended.
        $options = panopto_soap_client_options(['wsdl_proxy_host' => 'proxy.example.test']);
        $this->assertSame('proxy.example.test', $options['proxy_host']);
        $this->assertArrayNotHasKey('proxy_port', $options);
        $this->assertArrayNotHasKey('connection_timeout', $options);
        $http = stream_context_get_options($options['stream_context'])['http'];
        $this->assertSame('tcp://proxy.example.test', $http['proxy']);
        $this->assertTrue($http['request_fulluri']);
        $this->assertArrayNotHasKey('timeout', $http);

        // A host that already carries a scheme is passed to the stream context untouched.
        $options = panopto_soap_client_options(['wsdl_proxy_host' => 'tcp://10.0.0.1', 'wsdl_proxy_port' => '8080']);
        $this->assertSame(8080, $options['proxy_port']);
        $this->assertSame('tcp://10.0.0.1:8080', stream_context_get_options($options['stream_context'])['http']['proxy']);

        // Only a connection timeout: it also bounds the WSDL fetch rather than leaving it unbounded.
        $options = panopto_soap_client_options(['panopto_connection_timeout' => '7']);
        $this->assertSame(7, $options['connection_timeout']);
        $this->assertSame(7.0, stream_context_get_options($options['stream_context'])['http']['timeout']);
    }

    /**
     * Host and server name pairs, with the expected result of comparing them.
     *
     * @return array[] each case is [host, server name, expected match]
     */
    public static function host_matches_server_provider(): array {
        return [
            'identical' => ['demo.panopto.com', 'demo.panopto.com', true],
            'case insensitive' => ['DEMO.Panopto.COM', 'demo.panopto.com', true],
            'subdomain' => ['eu.demo.panopto.com', 'demo.panopto.com', true],
            'deep subdomain' => ['a.b.demo.panopto.com', 'demo.panopto.com', true],
            'surrounding whitespace' => [' demo.panopto.com ', 'demo.panopto.com', true],
            'server configured with whitespace' => ['demo.panopto.com', ' demo.panopto.com ', true],

            // The reason this helper exists: every one of these passes a substring test.
            'attacker suffix' => ['demo.panopto.com.attacker.example', 'demo.panopto.com', false],
            'attacker prefix' => ['attacker-demo.panopto.com.evil.test', 'demo.panopto.com', false],
            'no dot boundary' => ['evildemo.panopto.com', 'demo.panopto.com', false],
            'server embedded in path-like host' => ['x.demo.panopto.com.co', 'demo.panopto.com', false],

            'unrelated host' => ['example.com', 'demo.panopto.com', false],
            'empty host' => ['', 'demo.panopto.com', false],
            'empty server' => ['demo.panopto.com', '', false],
            'both empty' => ['', '', false],
            // A leading dot leaves an empty label, which is not a subdomain of anything.
            'dot prefix only' => ['.demo.panopto.com', 'demo.panopto.com', false],
        ];
    }

    /**
     * A host may only match a server when it is that server or sits under it on a dot boundary.
     *
     * @dataProvider host_matches_server_provider
     * @param string $host the host being tested
     * @param string $server the configured Panopto server name
     * @param bool $expected whether the host should be accepted
     * @covers ::panopto_host_matches_server
     */
    public function test_host_matches_server(string $host, string $server, bool $expected): void {
        $this->assertSame($expected, panopto_host_matches_server($host, $server));
    }

    /**
     * A URL is allowed when its host belongs to a server named in the plugin configuration.
     *
     * @covers ::panopto_is_allowed_panopto_host
     */
    public function test_is_allowed_panopto_host_uses_configured_servers(): void {
        $this->resetAfterTest();

        self::clear_server_configuration();
        set_config('server_name1', 'demo.panopto.com', 'block_panopto');

        $this->assertTrue(panopto_is_allowed_panopto_host('https://demo.panopto.com/Panopto/Pages/Viewer.aspx?id=1'));
        $this->assertTrue(panopto_is_allowed_panopto_host('https://eu.demo.panopto.com/Panopto/Pages/Viewer.aspx'));

        $this->assertFalse(panopto_is_allowed_panopto_host('https://demo.panopto.com.attacker.example/Panopto/'));
        $this->assertFalse(panopto_is_allowed_panopto_host('https://attacker.example/Panopto/'));
    }

    /**
     * Tool URL matching is on the host, not a substring of the whole URL.
     *
     * @dataProvider tool_url_matches_server_provider
     * @param string $toolurl the tool launch URL
     * @param string $server the Panopto server name
     * @param bool $expected whether the URL should match that server
     * @covers \panoptoblock_lti_utility::tool_url_matches_server
     */
    public function test_tool_url_matches_server(string $toolurl, string $server, bool $expected): void {
        $this->assertSame($expected, \panoptoblock_lti_utility::tool_url_matches_server($toolurl, $server));
    }

    /**
     * Tool URLs and the server names they should or should not match.
     *
     * @return array[] each case is [tool url, server name, expected match]
     */
    public static function tool_url_matches_server_provider(): array {
        $server = 'demo.panopto.com';

        return [
            'exact host' => ['https://demo.panopto.com/Panopto/LTI/Launch', $server, true],
            'subdomain host' => ['https://eu.demo.panopto.com/Panopto/LTI/Launch', $server, true],
            'case insensitive host' => ['https://DEMO.Panopto.COM/Panopto/LTI/Launch', $server, true],

            'attacker suffix host' => ['https://demo.panopto.com.attacker.example/Panopto/LTI', $server, false],
            'server name only in path' => ['https://evil.example/demo.panopto.com/LTI', $server, false],
            'server name only in query' => ['https://evil.example/LTI?next=demo.panopto.com', $server, false],
            'unrelated host' => ['https://attacker.example/Panopto/LTI', $server, false],
            'empty url' => ['', $server, false],
            'empty server' => ['https://demo.panopto.com/Panopto/LTI', '', false],
            'hostless url' => ['/Panopto/LTI', $server, false],
        ];
    }

    /**
     * The two single-server settings are honoured, so an LTI-1.3-only site still resolves a host.
     *
     * Those sites have no application key, so they have no entry in the server list that
     * panopto_get_configured_panopto_servers() builds.
     *
     * @covers ::panopto_get_known_panopto_hosts
     * @covers ::panopto_is_allowed_panopto_host
     */
    public function test_is_allowed_panopto_host_uses_single_server_fallbacks(): void {
        $this->resetAfterTest();

        self::clear_server_configuration();
        set_config('automatic_operation_target_server', 'tasks.panopto.com', 'block_panopto');
        set_config('default_panopto_server', 'embed.panopto.com', 'mod_panoptocourseembed');

        $this->assertTrue(panopto_is_allowed_panopto_host('https://tasks.panopto.com/Panopto/Pages/Viewer.aspx'));
        $this->assertTrue(panopto_is_allowed_panopto_host('https://embed.panopto.com/Panopto/Pages/Sessions/List.aspx'));
        $this->assertFalse(panopto_is_allowed_panopto_host('https://other.panopto.example/Panopto/'));
    }

    /**
     * With nothing configured there is no host to compare against, so nothing is allowed.
     *
     * @covers ::panopto_is_allowed_panopto_host
     */
    public function test_is_allowed_panopto_host_rejects_everything_when_unconfigured(): void {
        $this->resetAfterTest();

        self::clear_server_configuration();

        $this->assertFalse(panopto_is_allowed_panopto_host('https://demo.panopto.com/Panopto/'));
    }

    /**
     * The untouched default of the target-server setting must not become an allowlist entry.
     *
     * It is a configselect defaulting to the index 0, so an unconfigured site stores "0" - and
     * a browser resolves the host "0" to 0.0.0.0, which would make http://0/ an accepted
     * launch endpoint on every site that never picked a server.
     *
     * @covers ::panopto_get_known_panopto_hosts
     * @covers ::panopto_is_allowed_panopto_host
     */
    public function test_is_allowed_panopto_host_ignores_placeholder_server_values(): void {
        $this->resetAfterTest();

        self::clear_server_configuration();
        set_config('automatic_operation_target_server', '0', 'block_panopto');

        $this->assertEmpty(panopto_get_known_panopto_hosts());
        $this->assertFalse(panopto_is_allowed_panopto_host('http://0/Panopto/Pages/Viewer.aspx'));
    }

    /**
     * Values that are not an absolute URL have no host to check and must not be allowed through.
     *
     * @covers ::panopto_is_allowed_panopto_host
     */
    public function test_is_allowed_panopto_host_rejects_hostless_values(): void {
        $this->resetAfterTest();

        self::clear_server_configuration();
        set_config('server_name1', 'demo.panopto.com', 'block_panopto');

        $this->assertFalse(panopto_is_allowed_panopto_host(''));
        $this->assertFalse(panopto_is_allowed_panopto_host('/Panopto/Pages/Viewer.aspx'));
        $this->assertFalse(panopto_is_allowed_panopto_host('javascript:alert(1)'));
        $this->assertFalse(panopto_is_allowed_panopto_host('demo.panopto.com/Panopto/'));
    }

    /**
     * Expiration values paired with the clock they are compared against.
     *
     * The grace period is 300 seconds, so with a clock at 1000 the cut-off is 700.
     *
     * @return array[] each case is [expiration, now, expected expiry]
     */
    public static function sso_payload_expired_provider(): array {
        return [
            'long past' => ['1000', 100000, true],
            'fractional and past' => ['1000.5', 100000, true],
            'one second past the grace period' => ['699', 1000, true],
            'exactly at the grace boundary' => ['700', 1000, false],
            'inside the grace period' => ['800', 1000, false],
            'in the future' => ['100000', 1000, false],
            // Absent rather than expired: the caller signs whatever it was handed, and older
            // Panopto servers omit this.
            'zero' => ['0', 100000, false],
            // A value Panopto actually sent: seconds since 1601-01-01, which is 1791977349.7627 in
            // Unix seconds. Before the fix it fell into the fail-open branch and was never checked.
            'panopto 1601-based, before expiry' => ['13436450949.7627', 1791977189, false],
            'panopto 1601-based, inside the grace period' => ['13436450949.7627', 1791977649, false],
            'panopto 1601-based, past the grace period' => ['13436450949.7627', 1791977650, true],
        ];
    }

    /**
     * A payload is expired once its timestamp is further in the past than the clock-skew grace.
     *
     * @dataProvider sso_payload_expired_provider
     * @param string $expiration the expiration value as it arrived
     * @param int $now the clock to compare against
     * @param bool $expected whether the payload should be treated as expired
     * @covers ::panopto_is_sso_payload_expired
     */
    public function test_is_sso_payload_expired(string $expiration, int $now, bool $expected): void {
        $this->assertSame($expected, panopto_is_sso_payload_expired($expiration, $now));
    }

    /**
     * A value too large to be Unix seconds is let through, but not quietly.
     *
     * Comparing milliseconds or ticks against time() would expire every payload and lock the
     * site out of SSO, so the check stands down - and says so, because that is a fail-open.
     *
     * @covers ::panopto_is_sso_payload_expired
     */
    public function test_is_sso_payload_expired_reports_unrecognised_units(): void {
        $this->assertFalse(panopto_is_sso_payload_expired('4102444800', 100000));
        $this->assertDebuggingCalled();

        // Milliseconds, which is the realistic way this happens.
        $this->assertFalse(panopto_is_sso_payload_expired('1700000000000', 1700000000));
        $this->assertDebuggingCalled();
    }

    /**
     * The SSO page URL keeps the query params require_login() will store as wantsurl.
     *
     * @covers ::panopto_sso_page_url
     */
    public function test_sso_page_url_keeps_login_return_params(): void {
        $callback = 'https://demo.panopto.com/Panopto/Pages/Auth/Login.aspx?instance=Moodle';
        $url = panopto_sso_page_url(
            'demo.panopto.com',
            $callback,
            '1234567890',
            'abc123def',
            ''
        );

        $this->assertStringEndsWith('/blocks/panopto/SSO.php', $url->get_path());
        $this->assertSame('demo.panopto.com', $url->get_param('serverName'));
        $this->assertSame($callback, $url->get_param('callbackURL'));
        $this->assertSame('1234567890', $url->get_param('expiration'));
        $this->assertSame('abc123def', $url->get_param('authCode'));
        $this->assertNull($url->get_param('action'));
    }

    /**
     * action is only on the URL when it was actually supplied.
     *
     * @covers ::panopto_sso_page_url
     */
    public function test_sso_page_url_includes_action_when_set(): void {
        $url = panopto_sso_page_url(
            'demo.panopto.com',
            'https://demo.panopto.com/Panopto/',
            '1',
            'aa',
            'relogin'
        );

        $this->assertSame('relogin', $url->get_param('action'));
    }

    /**
     * The course query param is used when it is a positive id of a real course.
     *
     * @covers \panoptoblock_lti_utility::resolve_launch_course
     */
    public function test_resolve_launch_course_uses_course_param(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();

        [$courseid, $resolved, $context] = $this->with_launch_request(
            ['course' => $course->id],
            null,
            static function () {
                return \panoptoblock_lti_utility::resolve_launch_course();
            }
        );

        $this->assertEquals($course->id, $courseid);
        $this->assertEquals($course->id, $resolved->id);
        $this->assertInstanceOf(\context_course::class, $context);
        $this->assertEquals($course->id, $context->instanceid);
    }

    /**
     * The course param wins over a referer, so a copied embed keeps the course it was given.
     *
     * @covers \panoptoblock_lti_utility::resolve_launch_course
     */
    public function test_resolve_launch_course_prefers_course_param_over_referer(): void {
        global $CFG;

        $this->resetAfterTest();

        $embedded = $this->getDataGenerator()->create_course();
        $referercourse = $this->getDataGenerator()->create_course();

        [$courseid] = $this->with_launch_request(
            ['course' => $embedded->id],
            $CFG->wwwroot . '/course/view.php?id=' . $referercourse->id,
            static function () {
                return \panoptoblock_lti_utility::resolve_launch_course();
            }
        );

        $this->assertEquals($embedded->id, $courseid);
    }

    /**
     * Legacy embeds omitted the course param, so a same-site course-view referer is accepted.
     *
     * @covers \panoptoblock_lti_utility::resolve_launch_course
     */
    public function test_resolve_launch_course_uses_same_site_course_referer(): void {
        global $CFG;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();

        [$courseid] = $this->with_launch_request(
            [],
            $CFG->wwwroot . '/course/view.php?id=' . $course->id,
            static function () {
                return \panoptoblock_lti_utility::resolve_launch_course();
            }
        );

        $this->assertEquals($course->id, $courseid);
    }

    /**
     * An attacker-controlled referer on another host must not choose the course.
     *
     * @covers \panoptoblock_lti_utility::resolve_launch_course
     */
    public function test_resolve_launch_course_rejects_foreign_referer(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();

        [$courseid, $resolved, $context] = $this->with_launch_request(
            [],
            'https://evil.example/course/view.php?id=' . $course->id,
            static function () {
                return \panoptoblock_lti_utility::resolve_launch_course();
            }
        );

        $this->assertSame(0, $courseid);
        $this->assertNull($resolved);
        $this->assertNull($context);
    }

    /**
     * A suffix-host that starts with wwwroot is not this site.
     *
     * @covers \panoptoblock_lti_utility::resolve_launch_course
     */
    public function test_resolve_launch_course_rejects_wwwroot_suffix_referer(): void {
        global $CFG;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();

        [$courseid] = $this->with_launch_request(
            [],
            $CFG->wwwroot . '.evil.example/course/view.php?id=' . $course->id,
            static function () {
                return \panoptoblock_lti_utility::resolve_launch_course();
            }
        );

        $this->assertSame(0, $courseid);
    }

    /**
     * Array-style id values would make clean_param throw, so they are ignored.
     *
     * @covers \panoptoblock_lti_utility::resolve_launch_course
     */
    public function test_resolve_launch_course_ignores_array_referer_id(): void {
        global $CFG;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();

        [$courseid] = $this->with_launch_request(
            [],
            $CFG->wwwroot . '/course/view.php?id[]=' . $course->id,
            static function () {
                return \panoptoblock_lti_utility::resolve_launch_course();
            }
        );

        $this->assertSame(0, $courseid);
    }

    /**
     * A missing query string on the course-view referer is not a course id.
     *
     * @covers \panoptoblock_lti_utility::resolve_launch_course
     */
    public function test_resolve_launch_course_ignores_referer_without_query(): void {
        global $CFG;

        $this->resetAfterTest();

        [$courseid] = $this->with_launch_request(
            [],
            $CFG->wwwroot . '/course/view.php',
            static function () {
                return \panoptoblock_lti_utility::resolve_launch_course();
            }
        );

        $this->assertSame(0, $courseid);
    }

    /**
     * Run a callback with a temporary GET bag and optional referer.
     *
     * @param array $get replacement $_GET values
     * @param string|null $referer HTTP_REFERER to set, or null to unset it
     * @param callable $callback the launch resolution to run
     * @return array the callback return value
     */
    protected function with_launch_request(array $get, ?string $referer, callable $callback): array {
        $oldget = $_GET;
        $hadreferer = array_key_exists('HTTP_REFERER', $_SERVER);
        $oldreferer = $hadreferer ? $_SERVER['HTTP_REFERER'] : null;

        $_GET = $get;
        if ($referer === null) {
            unset($_SERVER['HTTP_REFERER']);
        } else {
            $_SERVER['HTTP_REFERER'] = $referer;
        }

        try {
            return $callback();
        } finally {
            $_GET = $oldget;
            if ($hadreferer) {
                $_SERVER['HTTP_REFERER'] = $oldreferer;
            } else {
                unset($_SERVER['HTTP_REFERER']);
            }
        }
    }

    /**
     * Blank out every setting the allowlist is built from.
     *
     * Moodle applies each plugin's declared defaults when the test database is installed, so a
     * test that needs a known starting point has to establish it rather than assume it. With
     * server_number at 0 the only numbered server read back is server_name1.
     *
     * @return void
     */
    protected static function clear_server_configuration(): void {
        set_config('automatic_operation_target_server', '', 'block_panopto');
        set_config('default_panopto_server', '', 'mod_panoptocourseembed');
        set_config('server_number', 0, 'block_panopto');
        set_config('server_name1', '', 'block_panopto');
    }

    /**
     * An explicit application key is what the payload is signed with.
     *
     * @return void
     * @covers ::panopto_generate_auth_code
     */
    public function test_generate_auth_code_signs_with_the_key_it_is_given(): void {
        $this->resetAfterTest();

        set_config('server_number', 0, 'block_panopto');
        set_config('server_name1', 'demo.panopto.com', 'block_panopto');
        set_config('application_key1', 'configured-key', 'block_panopto');

        $payload = 'apiuser@demo.panopto.com';

        $this->assertSame(
            strtoupper(sha1($payload . '|explicit-key')),
            panopto_generate_auth_code($payload, 'explicit-key')
        );
    }

    /**
     * A server whose name contains another configured server's name is signed with its own key.
     *
     * Without an explicit key the lookup matches server names as substrings of the payload, so a
     * payload for demo.panopto.com.au resolves to demo.panopto.com's key. Callers that pass the
     * key they mean are no longer exposed to that.
     *
     * @return void
     * @covers ::panopto_generate_auth_code
     */
    public function test_generate_auth_code_uses_given_key_when_server_names_overlap(): void {
        $this->resetAfterTest();

        set_config('server_number', 1, 'block_panopto');
        set_config('server_name1', 'demo.panopto.com', 'block_panopto');
        set_config('application_key1', 'key-for-com', 'block_panopto');
        set_config('server_name2', 'demo.panopto.com.au', 'block_panopto');
        set_config('application_key2', 'key-for-com-au', 'block_panopto');

        $payload = 'apiuser@demo.panopto.com.au';

        // The fallback lookup picks the first substring match, which is the wrong server.
        $this->assertSame(strtoupper(sha1($payload . '|key-for-com')), panopto_generate_auth_code($payload));

        $this->assertSame(
            strtoupper(sha1($payload . '|key-for-com-au')),
            panopto_generate_auth_code($payload, 'key-for-com-au')
        );
    }

    /**
     * Without a key, signing still falls back to the key of the server named in the payload.
     *
     * @return void
     * @covers ::panopto_generate_auth_code
     */
    public function test_generate_auth_code_falls_back_to_configured_key(): void {
        $this->resetAfterTest();

        set_config('server_number', 0, 'block_panopto');
        set_config('server_name1', 'demo.panopto.com', 'block_panopto');
        set_config('application_key1', 'configured-key', 'block_panopto');

        $payload = 'serverName=demo.panopto.com&expiration=1';
        $expected = strtoupper(sha1($payload . '|configured-key'));

        $this->assertSame($expected, panopto_generate_auth_code($payload));
        $this->assertSame($expected, panopto_generate_auth_code($payload, null));
        $this->assertSame($expected, panopto_generate_auth_code($payload, ''));
    }

    /**
     * Server names are matched exactly but without regard to case, as SSO.php matches serverName.
     *
     * @return void
     * @covers ::panopto_get_app_key
     */
    public function test_get_app_key_matches_server_name_case_insensitively(): void {
        $this->resetAfterTest();

        set_config('server_number', 1, 'block_panopto');
        set_config('server_name1', 'demo.panopto.com', 'block_panopto');
        set_config('application_key1', 'key-for-com', 'block_panopto');
        set_config('server_name2', 'Demo.Panopto.com.au', 'block_panopto');
        set_config('application_key2', 'key-for-com-au', 'block_panopto');

        $this->assertSame('key-for-com', panopto_get_app_key('demo.panopto.com'));
        $this->assertSame('key-for-com-au', panopto_get_app_key('demo.panopto.com.au'));
        $this->assertSame('key-for-com', panopto_get_app_key('DEMO.PANOPTO.COM'));
        $this->assertNull(panopto_get_app_key('panopto.com'));
        $this->assertNull(panopto_get_app_key(''));
        $this->assertNull(panopto_get_app_key(null));
    }

    /**
     * Validating with the server's own key accepts its signature and rejects another server's.
     *
     * @return void
     * @covers ::panopto_validate_auth_code
     */
    public function test_validate_auth_code_uses_given_key_when_server_names_overlap(): void {
        $this->resetAfterTest();

        set_config('server_number', 1, 'block_panopto');
        set_config('server_name1', 'demo.panopto.com', 'block_panopto');
        set_config('application_key1', 'key-for-com', 'block_panopto');
        set_config('server_name2', 'demo.panopto.com.au', 'block_panopto');
        set_config('application_key2', 'key-for-com-au', 'block_panopto');

        $payload = 'serverName=demo.panopto.com.au&expiration=13436450949.7627';
        $signedbycomau = strtoupper(sha1($payload . '|key-for-com-au'));
        $signedbycom = strtoupper(sha1($payload . '|key-for-com'));

        $this->assertTrue(panopto_validate_auth_code($payload, $signedbycomau, 'key-for-com-au'));
        $this->assertFalse(panopto_validate_auth_code($payload, $signedbycom, 'key-for-com-au'));
        $this->assertFalse(panopto_validate_auth_code($payload, '', 'key-for-com-au'));
    }

    /**
     * URLs from Panopto paired with whether the block may link to them.
     *
     * @return array[] each case is [url, allowed schemes or null for the default, expected result]
     */
    public static function clean_link_url_provider(): array {
        $viewer = 'https://demo.panopto.com/Panopto/Pages/Viewer.aspx?id=6c0313fd-177a-40dd-9e8a-b4f300d923e6&instance=moodle';
        $itunes = 'itpc://demo.panopto.com/Panopto/Podcast/Podcast.ashx?courseid=6c0313fd&type=mp3';

        return [
            'https viewer link' => [$viewer, null, $viewer],
            'http link' => ['http://demo.panopto.com/Panopto/', null, 'http://demo.panopto.com/Panopto/'],
            'folder settings link with a fragment' => [
                'https://demo.panopto.com/Panopto/Pages/Sessions/List.aspx#folderID=%226c0313fd%22',
                null,
                'https://demo.panopto.com/Panopto/Pages/Sessions/List.aspx#folderID=%226c0313fd%22',
            ],
            'javascript' => ['javascript:alert(1)', null, ''],
            'javascript in capitals' => ['JAVASCRIPT:alert(1)', null, ''],
            'javascript with leading space' => [' javascript:alert(1)', null, ''],
            'data url' => ['data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==', null, ''],
            'relative link' => ['/Panopto/Pages/Viewer.aspx', null, ''],
            'empty' => ['', null, ''],
            'null' => [null, null, ''],
            'malformed https' => ['https://demo.panopto.com/Panopto/Pages/Viewer.aspx?name=a b', null, ''],
            'itpc refused by default' => [$itunes, null, ''],
            'itpc kept when allowed' => [$itunes, ['http', 'https', 'itpc'], $itunes],
            'javascript refused even with itpc allowed' => ['javascript:alert(1)', ['http', 'https', 'itpc'], ''],
        ];
    }

    /**
     * Only http(s) URLs, or the schemes a caller allows, are kept as links.
     *
     * @dataProvider clean_link_url_provider
     * @param string|null $url the URL as Panopto returned it
     * @param string[]|null $schemes the allowed schemes, or null for the default
     * @param string $expected the URL to link to, or ''
     * @covers ::panopto_clean_link_url
     */
    public function test_clean_link_url(?string $url, ?array $schemes, string $expected): void {
        $actual = $schemes === null ? panopto_clean_link_url($url) : panopto_clean_link_url($url, $schemes);

        $this->assertSame($expected, $actual);
    }
}
