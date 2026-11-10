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
 * Panopto lti helper object. Contains info required for Panopto LTI tools to be used in text editors
 *
 * @package block_panopto
 * @copyright  Panopto 2021
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class panoptoblock_lti_utility {
    /**
     * Get the id of the pre-configured LTI tool that matched the Panopto server a course is provisioned to.
     * If multiple LTI tools are configured to a single server this will get the first one.
     *
     * @param int $courseid - the id of the course we are targeting in moodle.
     * @param array $requiredcustomparam - custom parameters array.
     * @return int the id of the first matching tool
     */
    public static function get_course_tool_id($courseid, $requiredcustomparam = '') {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/lti/locallib.php');

        $targetservername = self::get_target_server_name($courseid);
        $ltitooltypes = !empty($targetservername)
            ? $DB->get_records('lti_types', ['tooldomain' => $targetservername], 'name')
            : self::get_filtered_lti_tool_types();

        $idmatches = [];
        foreach ($ltitooltypes as $type) {
            $type->config = lti_get_config(
                (object)[
                    'typeid' => $type->id,
                ]
            );

            $config = lti_get_type_type_config($type->id);
            $islti1p3 = $config->lti_ltiversion === LTI_VERSION_1P3;

            if (
                self::tool_url_matches_server($type->config['toolurl'] ?? '', $targetservername) &&
                $type->state == LTI_TOOL_STATE_CONFIGURED
            ) {
                $currentconfig = lti_get_type_config($type->id);

                if (self::is_folio_tool($currentconfig)) {
                    continue;
                }

                if (
                    $islti1p3 || (!empty($requiredcustomparam) && !empty($currentconfig['customparameters']) &&
                    stripos($currentconfig['customparameters'], $requiredcustomparam) !== false)
                ) {
                    // Append matches, so we can filter later.
                    $idmatches[] = ['id' => $type->id, 'islti1p3' => $islti1p3];
                } else if (empty($requiredcustomparam)) {
                    $idmatches[] = ['id' => $type->id, 'islti1p3' => $islti1p3];
                }
            }
        }

        foreach ($idmatches as $item) {
            if ($item['islti1p3'] == 1) {
                return $item['id'];
            }
        }

        return !empty($idmatches[0]['id']) ? $idmatches[0]['id'] : null;
    }

    /**
     * Get the tool url of the pre-configured LTI tool that matched the Panopto server a course is provisioned to.
     *  If multiple LTI tools are configured to a single server this will get the first one.
     *
     * @param int $courseid - the id of the course we are targeting in moodle.
     * @param array $requiredcustomparam - custom parameters array.
     * @return string the tool url of the first matching tool
     */
    public static function get_course_tool_url($courseid, $requiredcustomparam) {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/lti/locallib.php');

        $targetservername = self::get_target_server_name($courseid);
        $ltitooltypes = !empty($targetservername)
            ? $DB->get_records('lti_types', ['tooldomain' => $targetservername], 'name')
            : self::get_filtered_lti_tool_types();

        $urlmatches = [];
        foreach ($ltitooltypes as $type) {
            $type->config = lti_get_config(
                (object)[
                    'typeid' => $type->id,
                ]
            );

            $config = lti_get_type_type_config($type->id);
            $islti1p3 = $config->lti_ltiversion === LTI_VERSION_1P3;

            if (
                self::tool_url_matches_server($type->config['toolurl'] ?? '', $targetservername) &&
                $type->state == LTI_TOOL_STATE_CONFIGURED
            ) {
                $currentconfig = lti_get_type_config($type->id);

                if (self::is_folio_tool($currentconfig)) {
                    continue;
                }

                if (
                    $islti1p3 || (!empty($currentconfig['customparameters']) &&
                    strpos($currentconfig['customparameters'], $requiredcustomparam) !== false)
                ) {
                    $urlmatches[] = ['url' => $type->config['toolurl'], 'islti1p3' => $islti1p3];
                }
            }
        }

        foreach ($urlmatches as $item) {
            if ($item['islti1p3'] == 1) {
                return $item['url'];
            }
        }

        return !empty($urlmatches[0]['url']) ? $urlmatches[0]['url'] : null;
    }

    /**
     * Launch an external tool activity.
     *
     * @param  stdClass $instance the external tool activity settings
     * @return string The HTML code containing the javascript code for the launch
     */
    public static function launch_tool($instance) {
        [$endpoint, $params] = self::get_launch_data($instance);

        $debuglaunch = ( $instance->debuglaunch == 1 );

        $content = lti_post_launch_html($params, $endpoint, $debuglaunch);

        return $content;
    }

    /**
     * Return the launch data required for opening the external tool.
     *
     * @param  stdClass $instance the external tool activity settings
     * @param  string $nonce  the nonce value to use (applies to LTI 1.3 only)
     * @return array the endpoint URL and parameters (including the signature)
     * @since  Moodle 3.0
     */
    public static function get_launch_data($instance, $nonce = '') {
        global $PAGE, $CFG, $USER;

        if (empty($CFG)) {
            require_once(dirname(__FILE__) . '/../../../../../config.php');
            require_login();
        }

        require_once($CFG->dirroot . '/mod/lti/lib.php');
        require_once($CFG->dirroot . '/mod/lti/locallib.php');

        if (empty($instance->typeid)) {
            $tool = lti_get_tool_by_url_match($instance->toolurl, $instance->course);
            if ($tool) {
                $typeid = $tool->id;
                $ltiversion = isset($tool->ltiversion) ? $tool->ltiversion : LTI_VERSION_1;
            } else {
                $tool = lti_get_tool_by_url_match($instance->securetoolurl, $instance->course);
                if ($tool) {
                    $typeid = $tool->id;
                    $ltiversion = isset($tool->ltiversion) ? $tool->ltiversion : LTI_VERSION_1;
                } else {
                    $typeid = null;
                    $ltiversion = LTI_VERSION_1;
                }
            }
        } else {
            $typeid = $instance->typeid;
            $tool = lti_get_type($typeid);
            $ltiversion = isset($tool->ltiversion) ? $tool->ltiversion : LTI_VERSION_1;
        }

        if ($typeid) {
            $typeconfig = lti_get_type_config($typeid);
        } else {
            // There is no admin configuration for this tool. Use configuration in the lti instance record plus some defaults.
            $typeconfig = (array)$instance;

            $typeconfig['sendname'] = $instance->instructorchoicesendname;
            $typeconfig['sendemailaddr'] = $instance->instructorchoicesendemailaddr;
            $typeconfig['customparameters'] = $instance->instructorcustomparameters;
            $typeconfig['acceptgrades'] = $instance->instructorchoiceacceptgrades;
            $typeconfig['allowroster'] = $instance->instructorchoiceallowroster;
            $typeconfig['forcessl'] = '0';
        }

        // Default the organizationid if not specified.
        if (empty($typeconfig['organizationid'])) {
            $urlparts = parse_url($CFG->wwwroot);

            $typeconfig['organizationid'] = $urlparts['host'];
        }

        // Setup LTI 1.3 specific parameters.
        $lti1p3params = new stdClass();
        $ltiversion1p3 = defined('LTI_VERSION_1P3') && ($ltiversion === LTI_VERSION_1P3);

        if (isset($tool->toolproxyid)) {
            $toolproxy = lti_get_tool_proxy($tool->toolproxyid);
            $key = $toolproxy->guid;
            $secret = $toolproxy->secret;

            if ($ltiversion1p3) {
                if (!empty($toolproxy->public_keyset_url)) {
                    $lti1p3params->lti_publickeyset = $toolproxy->public_keyset_url;
                }
                $lti1p3params->lti_keytype = LTI_JWK_KEYSET;

                if (!empty($toolproxy->initiatelogin)) {
                    $lti1p3params->lti_initiatelogin = $toolproxy->initiatelogin;
                }
                if (!empty($toolproxy->redirection_uris)) {
                    $lti1p3params->lti_redirectionuris = $toolproxy->redirection_uris;
                }
            }
        } else {
            $toolproxy = null;
            if (!empty($instance->resourcekey)) {
                $key = $instance->resourcekey;
            } else if ($ltiversion1p3) {
                $key = $tool->clientid;
                if (!empty($typeconfig['publickeyset'])) {
                    $lti1p3params->lti_publickeyset = $typeconfig['publickeyset'];
                }
                $lti1p3params->lti_keytype = $typeconfig['keytype'] ?? LTI_JWK_KEYSET;

                if (!empty($typeconfig['initiatelogin'])) {
                    $lti1p3params->lti_initiatelogin = $typeconfig['initiatelogin'];
                }
                if (!empty($typeconfig['redirectionuris'])) {
                    $lti1p3params->lti_redirectionuris = $typeconfig['redirectionuris'];
                }
            } else if (!empty($typeconfig['resourcekey'])) {
                $key = $typeconfig['resourcekey'];
            } else {
                $key = '';
            }
            if (!empty($instance->password)) {
                $secret = $instance->password;
            } else if (!empty($typeconfig['password'])) {
                $secret = $typeconfig['password'];
            } else {
                $secret = '';
            }
        }

        $endpoint = !empty($instance->toolurl) ? $instance->toolurl : $typeconfig['toolurl'];
        $endpoint = trim($endpoint);

        // If the current request is using SSL and a secure tool URL is specified, use it.
        if (lti_request_is_using_ssl() && !empty($instance->securetoolurl)) {
            $endpoint = trim($instance->securetoolurl);
        }

        // If SSL is forced, use the secure tool url if specified. Otherwise, make sure https is on the normal launch URL.
        if (isset($typeconfig['forcessl']) && ($typeconfig['forcessl'] == '1')) {
            if (!empty($instance->securetoolurl)) {
                $endpoint = trim($instance->securetoolurl);
            }

            $endpoint = lti_ensure_url_is_https($endpoint);
        } else {
            if (!strstr($endpoint, '://')) {
                $endpoint = 'http://' . $endpoint;
            }
        }

        $orgid = $typeconfig['organizationid'];

        $course = $PAGE->course;
        $islti2 = isset($tool->toolproxyid);

        if (!property_exists($instance, 'course')) {
            $instance->course = $course->id;
        }

        $allparams = lti_build_request($instance, $typeconfig, $course, $typeid, $islti2);

        if (property_exists($instance, 'custom')) {
            foreach ($instance->custom as $customkey => $customvalue) {
                $allparams['custom_' . $customkey] = $customvalue;
            }
        }

        if ($islti2) {
            $requestparams = lti_build_request_lti2($tool, $allparams);
        } else {
            $requestparams = $allparams;
        }

        // This is needed to make the lti tool support moodle v3.5.0.
        if (function_exists('lti_build_standard_message')) {
            $requestparams = array_merge($requestparams, lti_build_standard_message($instance, $orgid, $ltiversion));
        } else {
            $requestparams = array_merge($requestparams, lti_build_standard_request($instance, $orgid, $islti2));
        }

        $customstr = '';
        if (isset($typeconfig['customparameters'])) {
            $customstr = $typeconfig['customparameters'];
        }
        $requestparams = array_merge($requestparams, (array)$lti1p3params, lti_build_custom_parameters(
            $toolproxy,
            $tool,
            $instance,
            $allparams,
            $customstr,
            $instance->instructorcustomparameters,
            $islti2
        ));

        $launchcontainer = lti_get_launch_container($instance, $typeconfig);

        // The sesskey is required here, not leaked: this is the launch presentation return URL, and
        // core's mod/lti/return.php calls require_sesskey(). Built exactly as core's
        // lti_get_launch_data() builds it; without it the return from every launch would fail.
        $returnurlparams = ['course' => $course->id,
                                 'launch_container' => $launchcontainer,
                                 'instanceid' => $instance->typeid,
                                 'sesskey' => sesskey()];

        // Add the return URL. We send the launch container along to help us avoid frames-within-frames when the user returns.
        $url = new \moodle_url('/mod/lti/return.php', $returnurlparams);
        $returnurl = $url->out(false);

        if (isset($typeconfig['forcessl']) && ($typeconfig['forcessl'] == '1')) {
            $returnurl = lti_ensure_url_is_https($returnurl);
        }

        $target = '';
        switch ($launchcontainer) {
            case LTI_LAUNCH_CONTAINER_EMBED:
            case LTI_LAUNCH_CONTAINER_EMBED_NO_BLOCKS:
                $target = 'iframe';
                break;
            case LTI_LAUNCH_CONTAINER_REPLACE_MOODLE_WINDOW:
                $target = 'frame';
                break;
            case LTI_LAUNCH_CONTAINER_WINDOW:
                $target = 'window';
                break;
        }
        if (!empty($target)) {
            $requestparams['launch_presentation_document_target'] = $target;
        }

        $requestparams['launch_presentation_return_url'] = $returnurl;

        // Add the parameters configured by the LTI services.
        if ($typeid && !$islti2) {
            $services = lti_get_services();
            foreach ($services as $service) {
                $serviceparameters = $service->get_launch_parameters(
                    'basic-lti-launch-request',
                    $course->id,
                    $USER->id,
                    $typeid,
                    $instance->typeid
                );
                foreach ($serviceparameters as $paramkey => $paramvalue) {
                    $requestparams['custom_' . $paramkey] = lti_parse_custom_parameter(
                        $toolproxy,
                        $tool,
                        $requestparams,
                        $paramvalue,
                        $islti2
                    );
                }
            }
        }

        // Allow request params to be updated by sub-plugins.
        $plugins = core_component::get_plugin_list('ltisource');
        foreach (array_keys($plugins) as $plugin) {
            $pluginparams = component_callback(
                'ltisource_' . $plugin,
                'before_launch',
                [$instance, $endpoint, $requestparams],
                []
            );

            if (!empty($pluginparams) && is_array($pluginparams)) {
                $requestparams = array_merge($requestparams, $pluginparams);
            }
        }

        if ((!empty($key) && !empty($secret)) || $ltiversion1p3) {
            // Lti_sign_jwt was not added until 3.7 so we need to support the original style of processing this.
            if (defined('LTI_VERSION_1P3')) {
                if ($ltiversion !== LTI_VERSION_1P3) {
                    $params = lti_sign_parameters($requestparams, $endpoint, 'POST', $key, $secret);
                } else {
                    $params = lti_sign_jwt($requestparams, $endpoint, $key, $typeid, $nonce);
                }
            } else {
                $params = lti_sign_parameters($requestparams, $endpoint, 'POST', $key, $secret);
            }
            $endpointurl = new \moodle_url($endpoint);
            $endpointparams = $endpointurl->params();

            // Strip querystring params in endpoint url from $params to avoid duplication.
            if (!empty($endpointparams) && !empty($params)) {
                foreach (array_keys($endpointparams) as $paramname) {
                    if (isset($params[$paramname])) {
                        unset($params[$paramname]);
                    }
                }
            }
        } else {
            // If no key and secret, do the launch unsigned.
            $returnurlparams['unsigned'] = '1';
            $params = $requestparams;
        }

        return [$endpoint, $params];
    }

    /**
     * Get pre-configured LTI tool that matched the Panopto server a course is provisioned to.
     * If multiple LTI tools are configured to a single server this will get the first one.
     * This also returns latest LTI version first if there are multiple configured for the same server.
     *
     * @param int $courseid - the id of the course we are targeting in moodle.
     * @return object $type - first matching tool
     */
    public static function get_course_tool($courseid) {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/lti/locallib.php');

        $targetservername = self::get_target_server_name($courseid);
        $ltitooltypes = !empty($targetservername)
            ? $DB->get_records('lti_types', ['tooldomain' => $targetservername], 'name')
            : self::get_filtered_lti_tool_types();

        $idmatches = [];
        foreach ($ltitooltypes as $type) {
            $type->config = lti_get_config(
                (object)[
                    'typeid' => $type->id,
                ]
            );

            $config = lti_get_type_type_config($type->id);
            $islti1p3 = $config->lti_ltiversion === LTI_VERSION_1P3;

            if (
                self::tool_url_matches_server($type->config['toolurl'] ?? '', $targetservername) &&
                $type->state == LTI_TOOL_STATE_CONFIGURED
            ) {
                $currentconfig = lti_get_type_config($type->id);

                if (self::is_folio_tool($currentconfig)) {
                    continue;
                }

                if (
                    $islti1p3 || (!empty($currentconfig['customparameters']) &&
                    strpos($currentconfig['customparameters'], 'panopto_course_embed_tool') !== false)
                ) {
                    // Append matches, so we can filter later.
                    $idmatches[] = ['type' => $type, 'islti1p3' => $islti1p3];
                }
            }
        }

        foreach ($idmatches as $item) {
            if ($item['islti1p3'] == 1) {
                return $item['type'];
            }
        }

        return !empty($idmatches[0]['type']) ? $idmatches[0]['type'] : null;
    }

    /**
     * Builds a standard LTI Content-Item selection request.
     *
     * @param int $id The tool type ID.
     * @param stdClass $course The course object.
     * @param moodle_url $returnurl The return URL in the tool consumer (TC) that the tool provider (TP)
     *                              will use to return the Content-Item message.
     * @param string $title The tool's title, if available.
     * @param string $text The text to display to represent the content item.
     *      This value may be a long description of the content item.
     * @param array $mediatypes Array of MIME types types supported by the TC.
     *      If empty, the TC will support ltilink by default.
     * @param array $presentationtargets Array of ways in which the selected content item(s)
     *      can be requested to be opened (via the presentationDocumentTarget element for a returned content item).
     *      If empty, "frame", "iframe", and "window" will be supported by default.
     * @param bool $autocreate Indicates whether any content items returned
     *      by the TP would be automatically persisted without.
     * @param bool $multiple Indicates whether the user should be permitted to select more than one item.
     *  False by default. any option for the user to cancel the operation. False by default.
     * @param bool $unsigned Indicates whether the TC is willing to accept an unsigned return message, or not.
     *      A signed message should always be required when
     *      the content item is being created automatically in the
     *      TC without further interaction from the user. False by default.
     * @param bool $canconfirm Flag for can_confirm parameter. False by default.
     * @param bool $copyadvice Indicates whether the TC is able and willing to make
     *      a local copy of a content item. False by default.
     * @param string $nonce
     * @param string $pluginname The name of the Panopto plug-in being used here.
     *      Some plug-ins need extra custom parameters to work properly.
     * @return stdClass The object containing the signed request parameters
     *      and the URL to the TP's Content-Item selection interface.
     * @throws moodle_exception When the LTI tool type does not exist.`
     * @throws coding_exception For invalid media type and presentation target parameters.
     */
    public static function build_content_item_selection_request(
        $id,
        $course,
        moodle_url $returnurl,
        $title = '',
        $text = '',
        $mediatypes = [],
        $presentationtargets = [],
        $autocreate = false,
        $multiple = true,
        $unsigned = false,
        $canconfirm = false,
        $copyadvice = false,
        $nonce = '',
        $pluginname = ''
    ) {
        global $USER, $CFG;
        require_once($CFG->dirroot . '/mod/lti/locallib.php');

        $tool = lti_get_type($id);
        // Validate parameters.
        if (!$tool) {
            throw new moodle_exception('errortooltypenotfound', 'mod_lti');
        }
        if (!is_array($mediatypes)) {
            throw new coding_exception('The list of accepted media types should be in an array');
        }
        if (!is_array($presentationtargets)) {
            throw new coding_exception('The list of accepted presentation targets should be in an array');
        }

        // Check title. If empty, use the tool's name.
        if (empty($title)) {
            $title = $tool->name;
        }

        $typeconfig = lti_get_type_config($id);
        $key = '';
        $secret = '';
        $islti2 = false;
        $islti13 = false;
        if (isset($tool->toolproxyid)) {
            $islti2 = true;
            $toolproxy = lti_get_tool_proxy($tool->toolproxyid);
            $key = $toolproxy->guid;
            $secret = $toolproxy->secret;
        } else {
            $islti13 = $tool->ltiversion === LTI_VERSION_1P3;
            $toolproxy = null;
            if ($islti13 && !empty($tool->clientid)) {
                $key = $tool->clientid;
            } else if (!$islti13 && !empty($typeconfig['resourcekey'])) {
                $key = $typeconfig['resourcekey'];
            }
            if (!empty($typeconfig['password'])) {
                $secret = $typeconfig['password'];
            }
        }
        $tool->enabledcapability = '';
        if (!empty($typeconfig['enabledcapability_ContentItemSelectionRequest'])) {
            $tool->enabledcapability = $typeconfig['enabledcapability_ContentItemSelectionRequest'];
        }

        $tool->parameter = '';
        if (!empty($typeconfig['parameter_ContentItemSelectionRequest'])) {
            $tool->parameter = $typeconfig['parameter_ContentItemSelectionRequest'];
        }

        // Set the tool URL.
        if (!empty($typeconfig['toolurl_ContentItemSelectionRequest'])) {
            $toolurl = new moodle_url($typeconfig['toolurl_ContentItemSelectionRequest']);
        } else {
            $toolurl = new moodle_url($typeconfig['toolurl']);
        }

        // Check if SSL is forced.
        if (!empty($typeconfig['forcessl'])) {
            // Make sure the tool URL is set to https.
            if (strtolower($toolurl->get_scheme()) === 'http') {
                $toolurl->set_scheme('https');
            }
            // Make sure the return URL is set to https.
            if (strtolower($returnurl->get_scheme()) === 'http') {
                $returnurl->set_scheme('https');
            }
        }
        $toolurlout = $toolurl->out(false);

        // Get base request parameters.
        $instance = new stdClass();
        $instance->course = $course->id;
        $requestparams = lti_build_request($instance, $typeconfig, $course, $id, $islti2);

        // Get LTI2-specific request parameters and merge to the request parameters if applicable.
        if ($islti2) {
            $lti2params = lti_build_request_lti2($tool, $requestparams);
            $requestparams = array_merge($requestparams, $lti2params);
        }

        // Get standard request parameters and merge to the request parameters.
        $orgid = lti_get_organizationid($typeconfig);
        $standardparams = lti_build_standard_message(null, $orgid, $tool->ltiversion, 'ContentItemSelectionRequest');
        $requestparams = array_merge($requestparams, $standardparams);

        // Get custom request parameters and merge to the request parameters.
        $customstr = '';
        if (!empty($typeconfig['customparameters'])) {
            $customstr = $typeconfig['customparameters'];
        }

        switch ($pluginname) {
            // We need to add the custom parameter that initiates the student submission behavior here.
            case 'mod_panoptosubmission':
                $submissioncustomparam = "panopto_assignment_submission_content_item=true\npanopto_student_submission_tool=true";
                $customstr = empty($customstr) ? $submissioncustomparam : $customstr . "\n" . $submissioncustomparam;
                $customstr .= "\ngrading_not_supported=true";
                break;
            case 'mod_panoptocourseembed':
            case 'atto_panoptoltibutton':
            case 'tiny_panoptoltibutton':
                $customstr .= "\ngrading_not_supported=true";
                break;
            default:
                $customstr = '';
                break;
        }

        $customparams = lti_build_custom_parameters($toolproxy, $tool, $instance, $requestparams, $customstr, '', $islti2);
        $requestparams = array_merge($requestparams, $customparams);

        // Add the parameters configured by the LTI services.
        if ($id && !$islti2) {
            $services = lti_get_services();
            foreach ($services as $service) {
                $serviceparameters = $service->get_launch_parameters(
                    'ContentItemSelectionRequest',
                    $course->id,
                    $USER->id,
                    $id
                );
                foreach ($serviceparameters as $paramkey => $paramvalue) {
                    $requestparams['custom_' . $paramkey] = lti_parse_custom_parameter(
                        $toolproxy,
                        $tool,
                        $requestparams,
                        $paramvalue,
                        $islti2
                    );
                }
            }
        }

        // Allow request params to be updated by sub-plugins.
        $plugins = core_component::get_plugin_list('ltisource');
        foreach (array_keys($plugins) as $plugin) {
            $pluginparams = component_callback(
                'ltisource_' . $plugin,
                'before_launch',
                [$instance, $toolurlout, $requestparams],
                []
            );

            if (!empty($pluginparams) && is_array($pluginparams)) {
                $requestparams = array_merge($requestparams, $pluginparams);
            }
        }

        if (!$islti13) {
            // Media types. Set to ltilink by default if empty.
            if (empty($mediatypes)) {
                $mediatypes = [
                    'application/vnd.ims.lti.v1.ltilink',
                ];
            }
            $requestparams['accept_media_types'] = implode(',', $mediatypes);
        } else {
            // Only LTI links are currently supported.
            $requestparams['accept_types'] = 'ltiResourceLink';
        }

        // Presentation targets. Supports frame, iframe, window by default if empty.
        if (empty($presentationtargets)) {
            $presentationtargets = [
                'frame',
                'iframe',
                'window',
            ];
        }
        $requestparams['accept_presentation_document_targets'] = implode(',', $presentationtargets);

        // Other request parameters.
        $requestparams['accept_copy_advice'] = $copyadvice === true ? 'true' : 'false';
        $requestparams['accept_multiple'] = $multiple === true ? 'true' : 'false';
        $requestparams['accept_unsigned'] = $unsigned === true ? 'true' : 'false';
        $requestparams['auto_create'] = $autocreate === true ? 'true' : 'false';
        $requestparams['can_confirm'] = $canconfirm === true ? 'true' : 'false';
        $requestparams['content_item_return_url'] = $returnurl->out(false);
        $requestparams['title'] = $title;
        $requestparams['text'] = $text;
        if (!$islti13) {
            $signedparams = lti_sign_parameters($requestparams, $toolurlout, 'POST', $key, $secret);
        } else {
            $signedparams = lti_sign_jwt($requestparams, $toolurlout, $key, $id, $nonce);
        }
        $toolurlparams = $toolurl->params();

        // Strip querystring params in endpoint url from $signedparams to avoid duplication.
        if (!empty($toolurlparams) && !empty($signedparams)) {
            foreach (array_keys($toolurlparams) as $paramname) {
                if (isset($signedparams[$paramname])) {
                    unset($signedparams[$paramname]);
                }
            }
        }

        // Check for params that should not be passed. Unset if they are set.
        $unwantedparams = [
            'resource_link_id',
            'resource_link_title',
            'resource_link_description',
            'launch_presentation_return_url',
            'lis_result_sourcedid',
        ];
        foreach ($unwantedparams as $param) {
            if (isset($signedparams[$param])) {
                unset($signedparams[$param]);
            }
        }

        // Prepare result object.
        $result = new stdClass();
        $result->params = $signedparams;
        $result->url = $toolurlout;

        return $result;
    }

    /**
     * Ensure the user is logged in, or perform a cross-site repost if cookies were blocked.
     *
     * Mirrors mod/lti/contentitem_return.php behaviour for SameSite compatibility when
     * external tools POST back to Moodle without the session cookie.
     *
     * @param int|null $courseid If set, require_login() is called for this course after authentication.
     * @param context|null $repostcontext Context for the repost page; system context if null.
     */
    public static function require_login_or_repost(?int $courseid = null, ?context $repostcontext = null): void {
        global $PAGE, $_POST;

        // The superglobals are deliberate, exactly as in core's mod/lti/contentitem_return.php.
        // The repost marker must be removed from $_POST itself, because LTI 1.1 OAuth verification
        // builds its signature base from $_POST. The repost page must post back to the exact request
        // URI, query string included, since that carries the sesskey and ids the receiving page reads.
        if (!empty($_POST['repost'])) {
            unset($_POST['repost']);
        } else if (!isloggedin()) {
            header_remove('Set-Cookie');
            $PAGE->set_pagelayout('popup');
            $PAGE->set_context($repostcontext ?? context_system::instance());
            $output = $PAGE->get_renderer('mod_lti');
            $page = new \mod_lti\output\repost_crosssite_page($_SERVER['REQUEST_URI'], $_POST);
            echo $output->header();
            echo $output->render($page);
            echo $output->footer();
            exit;
        }

        if ($courseid !== null) {
            require_login($courseid);
        }
    }

    /**
     * Returns true or false depending on if the active user is enrolled in a context
     *
     * @param object $targetcontext the context we are checking enrollment for
     * @return bool true or false if the user is enrolled in the context
     */
    public static function is_active_user_enrolled($targetcontext) {
        global $USER;

        return is_enrolled($targetcontext, $USER, 'mod/assign:submit');
    }

    /**
     * Resolves the course an editor-embedded launch belongs to.
     *
     * The embed markup passes the course explicitly. Content embedded by older versions of the
     * editor plugins did not, so fall back to reading the id out of the referer - but only when
     * the referer is a course view page on this site. An unanchored search would let
     * https://evil.example/course/view.php?id=99 pick the course, and with it the tool lookup
     * and the context any capability is checked against
     *
     * Used by the TinyMCE button plugin. The Atto plugin keeps a local copy of this logic so it
     * remains installable against older block releases that do not ship this helper.
     *
     * @return array [$courseid, $course, $context]; [0, null, null] when no course resolves
     */
    public static function resolve_launch_course() {
        global $CFG, $DB, $PAGE;

        $courseidparam = optional_param('course', 0, PARAM_INT);

        if ($courseidparam <= 0) {
            // Get_local_referer() returns '' for any referer that is not on this site.
            $referer = get_local_referer(false);

            if (strpos($referer, $CFG->wwwroot . '/course/view.php') === 0) {
                $components = parse_url($referer);
                parse_str($components['query'] ?? '', $results);

                // Below is_scalar guards against ?id[]=1, which would make clean_param throw.
                if (!empty($results['id']) && is_scalar($results['id'])) {
                    $courseidparam = clean_param($results['id'], PARAM_INT);
                }
            }
        }

        if ($courseidparam <= 0) {
            return [0, null, null];
        }

        $course = $DB->get_record('course', ['id' => $courseidparam], '*', MUST_EXIST);
        $context = context_course::instance($course->id);
        $PAGE->set_context($context);

        return [$course->id, $course, $context];
    }

    /**
     * Returns target server name either if block exists or from course embed settings.
     *
     * @param string $courseid
     * @return string
     */
    private static function get_target_server_name($courseid) {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/lti/locallib.php');

        $targetservername = null;

        // Check if the Panopto block exists for the course.
        $blockexists = $DB->get_record('block', ['name' => 'panopto'], 'name');
        if (!empty($blockexists)) {
            // Get the targeted server associated with the course if provisioned.
            $targetservername = $DB->get_field('block_panopto_foldermap', 'panopto_server', ['moodleid' => $courseid]);
        }

        // If we still don't have a target server, get the automatic_operation_target_server value from the block config.
        if (empty($targetservername)) {
            $targetservername = get_config('block_panopto', 'automatic_operation_target_server');
        }

        // If the course is not provisioned with the Panopto block, retrieve the default Panopto server FQDN.
        if (empty($targetservername)) {
            $targetservername = get_config('mod_panoptocourseembed', 'default_panopto_server');
        }

        return $targetservername;
    }

    /**
     * Returns true when an LTI tool URL belongs to the given Panopto server
     *
     * Matching is on the URL host, with the same dot-anchored comparison SSO uses. A substring
     * search of the whole URL accepted hosts such as demo.panopto.com.attacker.example, and also
     * URLs that only mentioned the server in the path or query.
     *
     * @param string $toolurl the tool's configured launch URL
     * @param string $servername a Panopto server name
     * @return bool true when the tool URL host belongs to that server
     */
    public static function tool_url_matches_server($toolurl, $servername) {
        global $CFG;
        require_once($CFG->dirroot . '/blocks/panopto/lib/block_panopto_lib.php');

        if (!is_string($toolurl) || $toolurl === '' || !is_string($servername) || $servername === '') {
            return false;
        }

        $host = parse_url($toolurl, PHP_URL_HOST);
        if (empty($host)) {
            return false;
        }

        return panopto_host_matches_server($host, $servername);
    }

    /**
     * Returns true if a pre-configured LTI tool is a Folio tool. Folio tools live on the same
     * Panopto host but redirect the launch to the Folio service, so they must never be picked
     * up by the Panopto content tool lookups.
     *
     * @param array $typeconfig the tool type config as returned by lti_get_type_config.
     * @return bool true if the tool is configured with the folio_embed custom parameter.
     */
    private static function is_folio_tool($typeconfig) {
        return !empty($typeconfig['customparameters'])
            && stripos($typeconfig['customparameters'], 'folio_embed') !== false;
    }

    /**
     * Return filtered lti tool types
     * @return mixed
     */
    private static function get_filtered_lti_tool_types() {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/lti/locallib.php');

        $sql = "
            SELECT *
            FROM {lti_types} lt
            WHERE lt.state = :state
            AND (
                lt.baseurl LIKE :panopto_com_pattern
                OR lt.baseurl LIKE :panopto_eu_pattern
            )";

        // Since we don't have the target server, use the base URL for filtering purposes.
        $params = [
            'state' => LTI_TOOL_STATE_CONFIGURED,
            'panopto_com_pattern' => '%.panopto.com%',
            'panopto_eu_pattern' => '%.panopto.eu%',
        ];

        $tooltypes = $DB->get_records_sql($sql, $params);

        return $tooltypes;
    }

    /**
     * Copy only the content-item parameters a playback launch may echo.
     *
     * @param string $customdata JSON object from the launch request or the login hint.
     * @param string $pluginname Plugin that owns the launch, used to choose the allowlist.
     * @return stdClass Parameters safe to place on the LTI launch.
     */
    public static function filter_launch_custom_parameters($customdata, $pluginname) {
        $filtered = new stdClass();
        if (!is_string($customdata) || $customdata === '') {
            return $filtered;
        }

        $decoded = json_decode($customdata, true);
        if (!is_array($decoded)) {
            return $filtered;
        }

        foreach (self::launch_custom_parameter_rules($pluginname) as $key => $pattern) {
            if (!array_key_exists($key, $decoded) || !is_scalar($decoded[$key])) {
                continue;
            }

            $value = $decoded[$key];
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            } else {
                $value = (string) $value;
            }

            if ($value === '' || preg_match($pattern, $value) !== 1) {
                continue;
            }

            if ($key === 'use_panopto_interactive_view') {
                $value = strtolower($value);
            }
            $filtered->$key = $value;
        }

        return $filtered;
    }

    /**
     * Build the lti_message_hint for a playback launch.
     *
     * The payload is one JSON field after the plugin name.
     *
     * @param string $pluginname Plugin component name stored in the hint.
     * @param string $callback Content-item callback. Empty for a playback launch.
     * @param string|int $toolid Preconfigured tool id.
     * @param string $resourcelinkid Resource link id for the launch.
     * @param string $contenturl Launch URL. Empty when the tool's configured endpoint is used.
     * @param stdClass $custom Filtered custom parameters.
     * @return string Hint value passed to lti_initiate_login().
     */
    public static function build_playback_launch_hint(
        $pluginname,
        $callback,
        $toolid,
        $resourcelinkid,
        $contenturl,
        $custom
    ) {
        $json = json_encode([
            'callback' => is_string($callback) ? $callback : '',
            'toolid' => (string) $toolid,
            'resourcelinkid' => is_scalar($resourcelinkid) ? (string) $resourcelinkid : '',
            'contenturl' => is_string($contenturl) ? $contenturl : '',
            'custom' => ($custom instanceof stdClass) ? $custom : new stdClass(),
        ]);
        if ($json === false) {
            $json = '{"callback":"","toolid":"","resourcelinkid":"","contenturl":"","custom":{}}';
        }

        return $pluginname . ',' . $json;
    }

    /**
     * Read a playback hint, or a legacy comma-separated hint.
     *
     * Rejoin legacy JSON so commas inside it stay intact.
     *
     * @param string $hint Raw lti_message_hint cmid value.
     * @return array Plugin name, callback, tool id, resource link id, content URL, filtered custom.
     */
    public static function parse_playback_launch_hint($hint) {
        $pluginname = '';
        $callback = '';
        $toolid = '';
        $resourcelinkid = '';
        $contenturl = '';
        $custom = new stdClass();

        if (!is_string($hint) || $hint === '') {
            return [$pluginname, $callback, $toolid, $resourcelinkid, $contenturl, $custom];
        }

        // Split once, so commas inside the JSON payload stay intact.
        $parts = explode(',', $hint, 2);
        $pluginname = $parts[0];

        if (isset($parts[1])) {
            $payload = json_decode($parts[1], true);
            if (is_array($payload) && array_key_exists('contenturl', $payload)) {
                $callback = (isset($payload['callback']) && is_string($payload['callback']))
                    ? $payload['callback'] : '';
                $toolid = isset($payload['toolid']) && is_scalar($payload['toolid'])
                    ? (string) $payload['toolid'] : '';
                $resourcelinkid = (isset($payload['resourcelinkid']) && is_string($payload['resourcelinkid']))
                    ? $payload['resourcelinkid'] : '';
                $contenturl = (isset($payload['contenturl']) && is_string($payload['contenturl']))
                    ? $payload['contenturl'] : '';
                $custom = self::filter_launch_custom_parameters(
                    json_encode($payload['custom'] ?? new stdClass()),
                    $pluginname
                );
                return [$pluginname, $callback, $toolid, $resourcelinkid, $contenturl, $custom];
            }
        }

        $parts = explode(',', $hint);
        $callback = isset($parts[1]) ? trim($parts[1]) : '';
        $toolid = $parts[2] ?? '';
        $resourcelinkid = $parts[3] ?? '';

        $contenturl = $parts[4] ?? '';
        $customjson = (count($parts) > 5) ? implode(',', array_slice($parts, 5)) : '';
        $custom = self::filter_launch_custom_parameters($customjson, $pluginname);

        return [$pluginname, $callback, $toolid, $resourcelinkid, $contenturl, $custom];
    }

    /**
     * Allowlist of content-item custom parameters for one plugin.
     *
     * @param string $pluginname Plugin component name.
     * @return array Parameter name => value pattern.
     */
    private static function launch_custom_parameter_rules($pluginname) {
        $guidpattern = '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/';

        // Panopto sends context_playlist instead of context_delivery when a playlist is selected.
        $rules = [
            'context_delivery' => $guidpattern,
            'context_playlist' => $guidpattern,
        ];

        if ($pluginname === 'tiny_panoptoltibutton' || $pluginname === 'atto_panoptoltibutton') {
            $rules['use_panopto_interactive_view'] = '/^(?:true|false)$/i';
        }

        return $rules;
    }
}
