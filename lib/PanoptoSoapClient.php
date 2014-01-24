<?php
/* Copyright Panopto 2009 - 2013
 * 
 * This file is part of the Panopto plugin for Moodle.
 * 
 * The Panopto plugin for Moodle is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * The Panopto plugin for Moodle is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with the Panopto plugin for Moodle.  If not, see <http://www.gnu.org/licenses/>.
 */

// Subclasses SoapClient and hand-crafts SOAP parameters to be compatible with ASP.NET web service in non-WSDL mode. 
class PanoptoSoapClient extends SoapClient {
	var $auth_params;
	
	// Older PHP SOAP clients fail to pass the SOAPAction header properly.
	// Store the current action so we can insert it in __doRequest.
	var $current_action;
	
	public function PanoptoSoapClient($server_name, $apiuser_userkey, $apiuser_authcode) {
		// Instantiate SoapClient in non-WSDL mode.
		parent::__construct(null, array('location' => "http://$server_name/Panopto/Services/ClientData.svc",
                                 	    'uri'      => "http://services.panopto.com"));
		
		// Cache web service credentials for all calls requiring authentication.
		$this->auth_params = array("ApiUserKey" => $apiuser_userkey,
  			   						 "AuthCode" => $apiuser_authcode);
	}
	
	// Override SOAP action to work around bug in older PHP SOAP versions.
	function __doRequest($request, $location, $action, $version, $one_way = null) {
		return parent::__doRequest($request, $location, $this->current_action, $version);
	}

	/// Wrapper functions for Panopto ClientData web methods.
	
	function ProvisionCourse($provisioning_info) {
		return $this->CallWebMethod("ProvisionCourse", array("ProvisioningInfo" => $provisioning_info));
	}
	
	function GetCourses() {
		return $this->CallWebMethod("GetCourses");
	}
	
	function GetCourse($sessiongroup_id) {
		return $this->CallWebMethod("GetCourse", array("CoursePublicID" => $sessiongroup_id));
	}
	
	function GetLiveSessions($sessiongroup_id) {
		return $this->CallWebMethod("GetLiveSessions", array("CoursePublicID" => $sessiongroup_id));
	} 
	
	function GetCompletedDeliveries($sessiongroup_id) {
		return $this->CallWebMethod("GetCompletedDeliveries", array("CoursePublicID" => $sessiongroup_id));
	}
	
	function GetSystemInfo() {
		// Empty param list, and false to not auto-add auth params.
		return $this->CallWebMethod("GetSystemInfo", array(), false);
	}
	
	
	/// Helper functions for calling Panopto ClientData web methods in non-WSDL mode.
	
	private function CallWebMethod($method_name, $named_params = array(), $auth = true) {
		$soap_vars = $this->GetPanoptoSoapVars($named_params);

		// Include API user and auth code params unless $auth is set to false.
		if ($auth) {
			$auth_vars = $this->GetPanoptoSoapVars($this->auth_params);
			$merged_vars = array_merge($soap_vars, $auth_vars);
			$soap_vars = $merged_vars;
		}

		/*$cache_key = $method_name . md5(serialize($soap_vars));
        $cache = cache::make('block_panopto', 'panopto');
        $result = $cache->get($cache_key);
        if ($result !== false) {
        	return $result;
        }*/

		// Store action for use in overridden __doRequest.
		$this->current_action = "http://services.panopto.com/IClientDataService/$method_name";
		
		// Make the SOAP call via SoapClient::__soapCall.
		$result = parent::__soapCall($method_name, $soap_vars);
		//$cache->set($cache_key, $result);

		return $result;
	}
	
	// Convert an associative array into an array of SoapVars with name $key and value $value. 
	private function GetPanoptoSoapVars($params) {
		// Screwy syntax to map an instance method taking two params over an associative array.
		return array_map(array("PanoptoSoapClient", "GetPanoptoSoapVar"),
						 array_keys($params),
						 array_values($params));
	}
	
	// Construct a scalar-valued SOAP param.
	private function GetPanoptoSoapVar($name, $value) {
		if($name == "ProvisioningInfo") {
			$soap_var = $this->GetProvisioningSoapVar($value);
		} else {
			$data_element = $this->GetXMLDataElement($name, $value);
			$soap_var = new SoapVar($data_element, XSD_ANYXML);
		}
		
		return $soap_var;
	}
	
	// XML-encode value and wrap in tags with specified name.
	private function GetXMLDataElement($name, $value) {
		$value_escaped = htmlspecialchars($value);
		
		return "<ns1:$name>$value_escaped</ns1:$name>";
	}
	
	private function GetProvisioningSoapVar($provisioning_info) {
		$soap_struct = "<ns1:ProvisioningInfo>";
		$soap_struct .= $this->GetXMLDataElement("ExternalCourseID", $provisioning_info->ExternalCourseID);

		if(!empty($provisioning_info->Instructors))	{
			$soap_struct .= "<ns1:Instructors>";
			foreach($provisioning_info->Instructors as $instructor) {
				$soap_struct .= "<ns1:UserProvisioningInfo>";
				$soap_struct .= $this->GetXMLDataElement("Email", $instructor->Email);
				$soap_struct .= $this->GetXMLDataElement("FirstName", $instructor->FirstName);
				$soap_struct .= $this->GetXMLDataElement("LastName", $instructor->LastName);
				$soap_struct .= $this->GetXMLDataElement("UserKey", $instructor->UserKey);
				$soap_struct .= "</ns1:UserProvisioningInfo>";
			}
			$soap_struct .= "</ns1:Instructors>";
		}	else {
			$soap_struct .= "<ns1:Instructors />";
		}
		
		$soap_struct .= $this->GetXMLDataElement("LongName", $provisioning_info->LongName);
		$soap_struct .= $this->GetXMLDataElement("ShortName", $provisioning_info->ShortName);
		
		if(!empty($provisioning_info->Students)) {
			$soap_struct .= "<ns1:Students>";
			foreach($provisioning_info->Students as $student)	{
				$soap_struct .= "<ns1:UserProvisioningInfo>";
				$soap_struct .= $this->GetXMLDataElement("UserKey", $student->UserKey);
				$soap_struct .= "</ns1:UserProvisioningInfo>";
			}
			$soap_struct .= "</ns1:Students>";
		} else {
			$soap_struct .= "<ns1:Students />";
		}
		
		$soap_struct .= "</ns1:ProvisioningInfo>";
				
		return new SoapVar($soap_struct, XSD_ANYXML);
	}
}

/* End of file PanoptoSoapClient.php */