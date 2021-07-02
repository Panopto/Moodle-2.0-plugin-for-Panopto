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

class PanoptoTimeoutSoapClient extends SoapClient
{
    private $sockettimeout;
    private $connecttimeout;

    public function set_connection_timeout($connecttimeout) {
        $connecttimeout = intval($connecttimeout);

        if (!is_null($connecttimeout) && !is_int($connecttimeout)) {
            throw new Exception("Invalid connection timeout value");
        }

        $this->connecttimeout = $connecttimeout;
    }

    public function set_socket_timeout($sockettimeout) {
        $sockettimeout = intval($sockettimeout);

        if (!is_null($sockettimeout) && !is_int($sockettimeout)) {
            throw new Exception("Invalid socket timeout value");
        }

        $this->sockettimeout = $sockettimeout;
    }

    public function do_request($request, $location, $action, $version, $oneway = false) {
        if (!$this->sockettimeout && !$this->connecttimeout) {
            // Call via parent because we require no timeout
            $response = parent::__doRequest($request, $location, $action, $version, $oneway);
        } else {
            $curl = new \curl();
            $options = [
                'CURLOPT_VERBOSE' => false,
                'CURLOPT_RETURNTRANSFER' => true,
                'CURLOPT_HEADER' => false,
                'CURLOPT_HTTPHEADER' => array('Content-Type: text/xml',
                                              'SoapAction: ' . $action)
            ];

            if (!is_null($this->sockettimeout)) {
                $options['CURLOPT_TIMEOUT'] = $this->sockettimeout;
            }

            if (!is_null($this->connecttimeout)) {
                $options['CURLOPT_CONNECTTIMEOUT'] = $this->connecttimeout;
            }

            $response = $curl->post($location, $request, $options);

            if ($curl->get_errno()) {
                throw new Exception($response);
            }
        }

        // Return?
        if (!$oneway) {
            return ($response);
        }
    }
}



/* End of file panopto_timeout_soap_client.php */
