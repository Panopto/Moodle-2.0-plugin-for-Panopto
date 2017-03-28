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
 * this is the panopto soap client
 *
 * @package block_panopto
 * @copyright  Panopto 2009 - 2016 /With contributions from Spenser Jones (sjones@ambrose.edu)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

// Provisioning operations with many users can take longer than the default 30 seconds to finish executing.
// We disable maximum execution time for running this script so that it will continue to execute for as long as the
// timeout duration specified.
set_time_limit(0);

// This class extends php's built in SoapClient class with logic for handling different timeout durations
// ... for making calls. The default timeout is 60 seconds, but can be changed by including a 'timeout' value
// ... in the options array passed in as an argument.
// Timeouts are measured in seconds.

/**
 * Subclasses SoapClient and hand-crafts SOAP parameters to be compatible with ASP.NET web service in non-WSDL mode.
 *
 * @package block_panopto
 * @copyright  Panopto 2009 - 2015
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class panopto_soap_client extends SoapClient
{

    /**
     * @desc MAX_TIMEOUT Maximum allowed timeout value.
     */
    const MAX_TIMEOUT = 300;

    /**
     * @var array $authparams
     */
    private $authparams;
    // Older PHP SOAP clients fail to pass the SOAPAction header properly.
    // Store the current action so we can insert it in __doRequest.

    /**
     * @var string $currentaction
     */
    private $currentaction;

    /**
     * @var int $timeout Timeout value in seconds. Default is 300 seconds.
     */
    private $timeout = 60;

    /**
     * main constructor
     *
     * @param string $servername
     * @param string $apiuseruserkey
     * @param string $apiuserauthcode
     * @param int    $timeout Number of seconds for SOAP call timeout.
     */
    public function __construct($servername, $apiuseruserkey, $apiuserauthcode, $timeout = null) {

        // Only set timeout if it is a reasonable value.
        if (!empty($timeout) && is_numeric($timeout)) {
            $timeoutoption = (int)$timeout;
            if ($timeoutoption > 0 && $timeoutoption <= self::MAX_TIMEOUT) {
                $this->timeout = $timeoutoption;
            }
        }

        // Cache web service credentials for all calls requiring authentication.
        $this->authparams = array('ApiUserKey' => $apiuseruserkey, 'AuthCode' => $apiuserauthcode);

        // Instantiate SoapClient in non-WSDL mode
        parent::__construct(null, array(
            'location'  => "http://$servername/Panopto/Services/ClientData.svc",
            'uri'       => 'http://services.panopto.com'));

    }

    /**
     * Overrides parent __doRequest function to make SOAP calls with custom timeout.
     *
     * @param string $request the request being processed
     * @param string $location
     * @param string $action
     * @param int    $version
     * @param bool   $oneway
     */
    public function __doRequest($request, $location, $action, $version, $oneway = false) {
        // Attempt to intitialize cURL session to make SOAP calls.
        $curl = curl_init($location);

        // Check cURL was initialized.
        if ($curl !== false) {
            // Set standard cURL options.
            $options = array(
                CURLOPT_RETURNTRANSFER  => true,
                CURLOPT_POST            => true,
                CURLOPT_POSTFIELDS      => $request,
                CURLOPT_NOSIGNAL        => true,
                CURLOPT_SSL_VERIFYPEER  => true,            // All of our SOAP calls must be made via ssl.
                CURLOPT_TIMEOUT         => $this->timeout,  // Set call timeout in seconds.
                CURLOPT_HTTPHEADER      => array(sprintf('Content-Type: %s', $version == 2 ? 'application/soap+xml' : 'text/xml'),
                                                 sprintf('SOAPAction: %s', $this->currentaction)),
            );

            // Attempt to set the options for the cURL call.
            if (curl_setopt_array($curl, $options) !== false) {
                // Make call using cURL (including timeout settings).
                $response = curl_exec($curl);

                // If cURL throws an error, log it.
                $lasterrno = curl_errno($curl);
                if ($lasterrno !== 0) {
                    $lasterrmsg = curl_error($curl);
                    error_log($lasterrmsg);
                    curl_close($curl);
                    // cURL problem, rather than SOAP Fault, exception
                    // will bubble up to where call to  __soapCall()
                    // was made, call_web_method() helper
                    throw new Exception($lasterrmsg, $lasterrno);
                }
            } else {
                // A cURL option could not be set.
                error_log('Failed setting cURL options.');
            }
        } else {
            // ... cURL was not initialized properly.
            error_log("Couldn't initialize cURL to make SOAP calls");
        }

        // Close cURL session.
        curl_close($curl);

        // Return the SOAP response.
        return $response;
    }

    // Wrapper functions for Panopto ClientData web methods.
    /**
     * Call API function to provision a course with Panopto
     *
     * @param array $provisioninginfo
     */
    public function provision_course($provisioninginfo) {
        return $this->call_web_method('ProvisionCourse', array('ProvisioningInfo' => $provisioninginfo));
    }

    // Wrapper functions for Panopto ClientData web methods.
    /**
     * Call API function to provision a course with Panopto
     *
     * @param array $provisioninginfo
     */
    public function provision_course_with_options($provisioninginfo) {
        return $this->call_web_method('ProvisionCourseWithOptions', array('ProvisioningInfoWithOptions' => $provisioninginfo));
    }

    /**
     * Provisioning functions that clears members with a particular role from the acl of the course's folder.
     * These are used for paged provisioning when the user count for a course is over the threshold for a particular role.
     *
     * @param array $provisioninginfo
     * @param array $options
     */
    public function provision_course_with_course_options($provisioninginfo, $options) {
        $completeinfo = array('ProvisioningInfo' => $provisioninginfo, 'CourseOptions' => $options);

        return $this->call_web_method('ProvisionCourseWithOptions', array('ProvisioningInfoWithCourseOptions' => $completeinfo));
    }

    /**
     * Function used to provision users to a course after the course itself has been provisioned. Used by
     * make_paged_call_provision_users.
     *
     * @param array $userprovisioninginfo
     */
    public function provision_users($userprovisioninginfo) {
        return $this->call_web_method('ProvisionUsers', array('UserProvisioningInfo' => $userprovisioninginfo));
    }

    /**
     * Call API funtion to get list of  Panopto courses
     */
    public function get_courses() {
        return $this->call_web_method('GetCourses');
    }

    /**
     *  Call API function to get a particular Panopto course based on passed in session group ID
     *
     * @param int $sessiongroupid
     */
    public function get_course($sessiongroupid) {
        return $this->call_web_method('GetCourse', array('CoursePublicID' => $sessiongroupid));
    }

    /**
     * Call API function to get a list of live sessions course based on passed in session group ID
     *
     * @param int $sessiongroupid
     */
    public function get_live_sessions($sessiongroupid) {
        return $this->call_web_method('GetLiveSessions', array('CoursePublicID' => $sessiongroupid));
    }

    /**
     * Call API function to get a list of live sessions course based on passed in session group ID
     *
     * @param int $sessiongroupid
     */
    public function get_completed_deliveries($sessiongroupid) {
        return $this->call_web_method(
            'GetCompletedDeliveries',
            array('CoursePublicID' => $sessiongroupid)
        );
    }

    /**
     * API call to get system info
     */
    public function get_system_info() {
        // Empty param list, and false to not auto-add auth params.
        return $this->call_web_method('GetSystemInfo', array(), false);
    }
    /**
     *  Calls API function to enroll a user in a Panopto course
     *
     * @param int $sessiongroupid
     * @param string $role the current role
     * @param string $userkey the user auth key
     */
    public function add_user_to_course($sessiongroupid, $role, $userkey) {
        return $this->call_web_method('AddUserToCourse', array('CoursePublicID' => $sessiongroupid,
            'Role' => $role, 'UserKey' => $userkey));
    }

    /**
     *  Calls API function to delete a user's enrollment from a course
     *
     * @param int $sessiongroupid
     * @param string $role the current role
     * @param string $userkey the user auth key
     */
    public function remove_user_from_course($sessiongroupid, $role, $userkey) {
        return $this->call_web_method('RemoveUserFromCourse', array('CoursePublicID' => $sessiongroupid,
            'Role' => $role, 'UserKey' => $userkey));

    }

    /**
     *  Calls API function to change a user's enrollment in a course
     *
     * @param int $sessiongroupid
     * @param string $role the current role
     * @param string $userkey the user auth key
     */
    public function change_user_role($sessiongroupid, $role, $userkey) {
        $this->call_web_method('ChangeUserRole', array('CoursePublicID' => $sessiongroupid,
            'Role' => $role, 'UserKey' => $userkey));
    }

    // Helper functions for calling Panopto ClientData web methods in non-WSDL mode.
    /**
     *  Helper method for making a call to the Panopto API
     *
     * @param string $methodname the name of the web method to be called
     * @param array $namedparams a list of params and thier keys
     * @param bool $auth whether or not this web method will need auth
     */
    private function call_web_method($methodname, $namedparams = array(), $auth = true) {
        $soapvars = $this->get_panopto_soap_vars($namedparams);

        // Include API user and auth code params unless $auth is set to false.
        if ($auth) {
            $authvars = $this->get_panopto_soap_vars($this->authparams);
            $mergedvars = array_merge($soapvars, $authvars);
            $soapvars = $mergedvars;
        }
        // Store action for use in overridden __doRequest.
        $this->currentaction = "http://services.panopto.com/IClientDataService/$methodname";

        // Make the SOAP call via SoapClient::__soapCall.
        try {
            return parent::__soapCall($methodname, $soapvars);
        } catch (Exception $e) {
            error_log("Error:" . $e->getMessage());
            error_log("File: " . $e->getFile());
            error_log("Line: " . $e->getLine());
            error_log("Trace: " . $e->getTraceAsString());
        }

    }
    /**
     * Convert an associative array into an array of SoapVars with name $key and value $value.
     *
     * @param array $params a list of params to get
     */
    private function get_panopto_soap_vars($params) {
        // Screwy syntax to map an instance method taking two params over an associative array.
        return array_map(array('panopto_soap_client', 'get_panopto_soap_var'), array_keys($params), array_values($params));
    }
    /**
     * Construct a scalar-valued SOAP param.
     *
     * @param strong $name the name of the var to get
     * @param string $value the value of the var
     */
    private function get_panopto_soap_var($name, $value) {
        if ($name == 'ProvisioningInfo') {
            $soapvar = $this->get_provisioning_soap_var($value);
        } else if ($name == 'ProvisioningInfoWithOptions') {
            $soapvar = $this->get_provisioning_soap_var_with_options($value);
        } else if ($name == 'ProvisioningInfoWithCourseOptions') {
            $soapvar = $this->get_provisioning_soap_var_with_course_options($value['ProvisioningInfo'], $value['CourseOptions']);
        } else if ($name == 'UserProvisioningInfo') {
            $soapvar = $this->get_user_provisioning_soap_var($value);
        } else {
            $dataelement = $this->get_xml_data_element($name, $value);
            $soapvar = new SoapVar($dataelement, XSD_ANYXML);
        }

        return $soapvar;
    }

    /**
     * XML-encode value and wrap in tags with specified name.
     *
     * @param strong $name the name of the var to get
     * @param string $value the value of the var
     */
    private function get_xml_data_element($name, $value) {
        $valueescaped = htmlspecialchars($value);
        return "<ns1:$name>$valueescaped</ns1:$name>";
    }

    /**
     * Creates a SOAP var formatted correctly to use in the provision_course call
     *
     * @param array $provisioninginfo
     */
    private function get_provisioning_soap_var($provisioninginfo) {
        $soapstruct = $this->get_formatted_provisioning_info($provisioninginfo);
        return new SoapVar($soapstruct, XSD_ANYXML);
    }

    /**
     * Creates a SOAP var formatted correctly to use in the provision_course call
     *
     * @param array $provisioninginfo
     */
    private function get_provisioning_soap_var_with_options($provisioninginfo) {
        $soapstruct = $this->get_formatted_provisioning_info($provisioninginfo);
        $soapstruct .= '<ns1:UserOptions>';
        $soapstruct .= $this->get_xml_data_element('HasMailLectureNotifications', 'false');
        $soapstruct .= '</ns1:UserOptions>';

        return new SoapVar($soapstruct, XSD_ANYXML);
    }

    /**
     * Creates a SOAP var formatted correctly to use in the provision_course call
     *
     * @param array $provisioninginfo
     * @param array $courseoptions
     */
    private function get_provisioning_soap_var_with_course_options($provisioninginfo, $courseoptions) {
        $soapstruct = $this->get_formatted_provisioning_info($provisioninginfo);

        // If we don't want to provision users, add a CourseOptions array with 'ProvisionUsers' set to false.
        if ($courseoptions != null) {
            $soapstruct .= '<ns1:CourseOptions>';
            foreach ($courseoptions as $key => $value) {
                $soapstruct .= $this->get_xml_data_element($key, $value);
            }

            $soapstruct .= '</ns1:CourseOptions>';
        }

        $soapstruct .= '<ns1:UserOptions>';
        $soapstruct .= $this->get_xml_data_element('HasMailLectureNotifications', 'false');
        $soapstruct .= '</ns1:UserOptions>';
        return new SoapVar($soapstruct, XSD_ANYXML);
    }

    /**
     * gets formatted provisioning info
     *
     * @param array $provisioninginfo
     */
    private function get_formatted_provisioning_info($provisioninginfo) {

        // DO NOT CHANGE THE ORDERING HERE!
        // The order should be: External course ID, Instructors, Longname, Publishers, Shortname, Students.
        // If you change the order, things will break.
        $soapstruct = '<ns1:ProvisioningInfo>';
        $soapstruct .= $this->get_xml_data_element('ExternalCourseID', $provisioninginfo->ExternalCourseID);
        if (!empty($provisioninginfo->Instructors)) {
            $soapstruct .= '<ns1:Instructors>';
            foreach ($provisioninginfo->Instructors as $instructor) {
                $soapstruct .= '<ns1:UserProvisioningInfo>';
                $soapstruct .= $this->get_xml_data_element('Email', $instructor->Email);
                $soapstruct .= $this->get_xml_data_element('FirstName', $instructor->FirstName);
                $soapstruct .= $this->get_xml_data_element('LastName', $instructor->LastName);
                $soapstruct .= $this->get_xml_data_element('UserKey', $instructor->UserKey);
                $soapstruct .= '</ns1:UserProvisioningInfo>';
            }
            $soapstruct .= '</ns1:Instructors>';
        } else {
            $soapstruct .= '<ns1:Instructors />';
        }
        $soapstruct .= $this->get_xml_data_element('LongName', $provisioninginfo->longname);
        if (!empty($provisioninginfo->Publishers)) {
            $soapstruct .= '<ns1:Publishers>';
            foreach ($provisioninginfo->Publishers as $publisher) {
                $soapstruct .= '<ns1:UserProvisioningInfo>';
                $soapstruct .= $this->get_xml_data_element('Email', $publisher->Email);
                $soapstruct .= $this->get_xml_data_element('FirstName', $publisher->FirstName);
                $soapstruct .= $this->get_xml_data_element('LastName', $publisher->LastName);
                $soapstruct .= $this->get_xml_data_element('UserKey', $publisher->UserKey);
                $soapstruct .= '</ns1:UserProvisioningInfo>';
            }
            $soapstruct .= '</ns1:Publishers>';
        } else {
            $soapstruct .= '<ns1:Publishers />';
        }
        $soapstruct .= $this->get_xml_data_element('ShortName', $provisioninginfo->shortname);
        if (!empty($provisioninginfo->Students)) {
            $soapstruct .= '<ns1:Students>';
            foreach ($provisioninginfo->Students as $student) {
                $soapstruct .= '<ns1:UserProvisioningInfo>';
                $soapstruct .= $this->get_xml_data_element('Email', $student->Email);
                $soapstruct .= $this->get_xml_data_element('FirstName', $student->FirstName);
                $soapstruct .= $this->get_xml_data_element('LastName', $student->LastName);
                $soapstruct .= $this->get_xml_data_element('UserKey', $student->UserKey);
                $soapstruct .= '</ns1:UserProvisioningInfo>';
            }
            $soapstruct .= '</ns1:Students>';
        } else {
            $soapstruct .= '<ns1:Students />';
        }
        $soapstruct .= '</ns1:ProvisioningInfo>';

        return $soapstruct;
    }

    /**
     * Gets formatted soap variable used for paged calls to ProvisionUsers.
     *
     * @param array $userprovisioninginfo
     */
    private function get_user_provisioning_soap_var($userprovisioninginfo) {
        $soapstruct = '';
        if (!empty($userprovisioninginfo)) {
            $soapstruct .= '<ns1:ProvisioningInfo>';
            foreach ($userprovisioninginfo as $user) {
                $soapstruct .= '<ns1:UserProvisioningInfo>';
                $soapstruct .= $this->get_xml_data_element('Email', $user->Email);
                $soapstruct .= $this->get_xml_data_element('FirstName', $user->FirstName);
                $soapstruct .= $this->get_xml_data_element('LastName', $user->LastName);
                $soapstruct .= $this->get_xml_data_element('UserKey', $user->UserKey);
                $soapstruct .= '</ns1:UserProvisioningInfo>';
            }
            $soapstruct .= '</ns1:ProvisioningInfo>';
        } else {
            $soapstruct .= '<ns1:ProvisioningInfo />';
        }

        $soapstruct .= '<ns1:UserOptions>';
        $soapstruct .= $this->get_xml_data_element('HasMailLectureNotifications', 'false');
        $soapstruct .= '</ns1:UserOptions>';

        return new SoapVar($soapstruct, XSD_ANYXML);
    }

    /**
     * Splits array of UserProvisioningInfo into pages, and makes separate calls to ProvisionUSers with each page to prevent
     * timeouts when there are many users being provisioned to a course.
     *
     * @param array $userarray
     */
    public function make_paged_call_provision_users($userarray) {
        $arraysize = count($userarray);
        $pagesize = 50;
        $pagenumber = 1;

        // While there is still at least one full page of users to provision,
        // make calls to provision the full page-sized amount of users.
        while ($pagenumber * $pagesize <= $arraysize) {
            $userpage = array_slice(
                $userarray,
                ($pagenumber - 1) * $pagesize,
                $pagesize
            );
            $this->provision_users($userpage);

            $pagenumber += 1;
        }

        // Provision the remaining users in their own page. If there was less than a single page of users in the first place,
        // only this logic will be called.
        $finalpage = array_slice(
                $userarray,
                ($pagenumber - 1) * $pagesize
            );
        $this->provision_users($finalpage);
    }

}
/* End of file PanoptoSoapClient.php */
