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
 * The Panopto soap client that uses timeouts
 *
 * @package block_panopto
 * @copyright Panopto 2020
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// This can't be defined Moodle internal because it is called from Panopto to authorize login.

/**
 * Panopto timeout soap client class.
 *
 * @package block_panopto
 * @copyright  Panopto 2009 - 2016
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class PanoptoTimeoutSoapClient extends SoapClient {
    /**
     * @var int $sockettimeout socket timeout
     */
    private $sockettimeout;

    /**
     * @var int $connecttimeout connection timeout
     */
    private $connecttimeout;

    /**
     * @var string $proxyhost proxy host
     */
    private $proxyhost;

    /**
     * @var int $proxyport proxy port
     */
    private $proxyport;

    /**
     * @var array $panoptocookies Panopto cookies
     */
    private $panoptocookies;

    /**
     * Set connection timeout
     *
     * @param int $connecttimeout
     */
    public function set_connection_timeout($connecttimeout) {
        $connecttimeout = intval($connecttimeout);

        if (!is_null($connecttimeout) && !is_int($connecttimeout)) {
            throw new Exception("Invalid connection timeout value");
        }

        $this->connecttimeout = $connecttimeout;
    }

    /**
     * Set socket timeout
     *
     * @param int $sockettimeout
     */
    public function set_socket_timeout($sockettimeout) {
        $sockettimeout = intval($sockettimeout);

        if (!is_null($sockettimeout) && !is_int($sockettimeout)) {
            throw new Exception("Invalid socket timeout value");
        }

        $this->sockettimeout = $sockettimeout;
    }

    /**
     * Set proxy host
     *
     * @param string $proxyhost
     */
    public function set_proxy_host($proxyhost) {
        $this->proxyhost = $proxyhost;
    }

    /**
     * Set proxy port
     *
     * @param int $proxyport
     */
    public function set_proxy_port($proxyport) {
        $this->proxyport = $proxyport;
    }

    /**
     * Set Panopto cookies
     */
    public function getpanoptocookies() {
        return $this->panoptocookies;
    }

    /**
     * Create a SOAP request
     *
     * @param string $request XML SOAP request
     * @param string $location The URL to request
     * @param string $action The SOAP action
     * @param int $version The SOAP version
     * @param bool $oneway determine if response is expected or not
     */
    public function do_request($request, $location, $action, $version, $oneway = false) {
        if (empty($this->sockettimeout) && empty($this->connecttimeout)) {
            // Call via parent because we require no timeout.
            $response = parent::__doRequest($request, $location, $action, $version, $oneway);

            $lastresponseheaders = $this->__getLastResponseHeaders();
            preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $lastresponseheaders, $matches);
            $this->panoptocookies = array();
            foreach ($matches[1] as $item) {
                parse_str($item, $cookie);
                $this->panoptocookies = array_merge($this->panoptocookies, $cookie);
            }
        } else {

            $curl = new \curl();
            $options = [
                'CURLOPT_VERBOSE' => false,
                'CURLOPT_RETURNTRANSFER' => true,
                'CURLOPT_HEADER' => true,
                'CURLOPT_HTTPHEADER' => array('Content-Type: text/xml',
                                              'SoapAction: ' . $action)
            ];

            if (!is_null($this->sockettimeout)) {
                $options['CURLOPT_TIMEOUT'] = $this->sockettimeout;
            }

            if (!is_null($this->connecttimeout)) {
                $options['CURLOPT_CONNECTTIMEOUT'] = $this->connecttimeout;
            }

            if (!empty($this->proxyhost)) {
                $options['CURLOPT_PROXY'] = $this->proxyhost;
            }

            if (!empty($this->proxyport)) {
                $options['CURLOPT_PROXYPORT'] = $this->proxyport;
            }

            $response = $curl->post($location, $request, $options);

            // Get cookies.
            $actualresponseheaders = (isset($curl->info["header_size"])) ? substr($response, 0, $curl->info["header_size"]) : "";
            preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $actualresponseheaders, $matches);
            $this->panoptocookies = array();
            foreach ($matches[1] as $item) {
                parse_str($item, $cookie);
                $this->panoptocookies = array_merge($this->panoptocookies, $cookie);
            }

            $actualresponse = (isset($curl->info["header_size"])) ? substr($response, $curl->info["header_size"]) : "";

            if ($curl->get_errno()) {
                throw new Exception($response);
            }

            $response = $actualresponse;
        }

        // Return?
        if (!$oneway) {
            return $response;
        }
    }
}

/* End of file panopto_timeout_soap_client.php */
