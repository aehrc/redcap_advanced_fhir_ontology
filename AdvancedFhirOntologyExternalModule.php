<?php
/**
 *
 *
 * CSIRO Open Source Software Licence Agreement (variation of the BSD / MIT License)
 * Copyright (c) 2018, Commonwealth Scientific and Industrial Research Organisation (CSIRO) ABN 41 687 119 230.
 * All rights reserved. CSIRO is willing to grant you a licence to this FhirOntologyAutocompleteModule on the following terms, except where otherwise indicated for third party material.
 * Redistribution and use of this software in source and binary forms, with or without modification, are permitted provided that the following conditions are met:
 * Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
 * Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
 * Neither the name of CSIRO nor the names of its contributors may be used to endorse or promote products derived from this software without specific prior written permission of CSIRO.
 * EXCEPT AS EXPRESSLY STATED IN THIS AGREEMENT AND TO THE FULL EXTENT PERMITTED BY APPLICABLE LAW, THE SOFTWARE IS PROVIDED "AS-IS". CSIRO MAKES NO REPRESENTATIONS, WARRANTIES OR CONDITIONS OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO ANY REPRESENTATIONS, WARRANTIES OR CONDITIONS REGARDING THE CONTENTS OR ACCURACY OF THE SOFTWARE, OR OF TITLE, MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE, NON-INFRINGEMENT, THE ABSENCE OF LATENT OR OTHER DEFECTS, OR THE PRESENCE OR ABSENCE OF ERRORS, WHETHER OR NOT DISCOVERABLE.
 * TO THE FULL EXTENT PERMITTED BY APPLICABLE LAW, IN NO EVENT SHALL CSIRO BE LIABLE ON ANY LEGAL THEORY (INCLUDING, WITHOUT LIMITATION, IN AN ACTION FOR BREACH OF CONTRACT, NEGLIGENCE OR OTHERWISE) FOR ANY CLAIM, LOSS, DAMAGES OR OTHER LIABILITY HOWSOEVER INCURRED.  WITHOUT LIMITING THE SCOPE OF THE PREVIOUS SENTENCE THE EXCLUSION OF LIABILITY SHALL INCLUDE: LOSS OF PRODUCTION OR OPERATION TIME, LOSS, DAMAGE OR CORRUPTION OF DATA OR RECORDS; OR LOSS OF ANTICIPATED SAVINGS, OPPORTUNITY, REVENUE, PROFIT OR GOODWILL, OR OTHER ECONOMIC LOSS; OR ANY SPECIAL, INCIDENTAL, INDIRECT, CONSEQUENTIAL, PUNITIVE OR EXEMPLARY DAMAGES, ARISING OUT OF OR IN CONNECTION WITH THIS AGREEMENT, ACCESS OF THE SOFTWARE OR ANY OTHER DEALINGS WITH THE SOFTWARE, EVEN IF CSIRO HAS BEEN ADVISED OF THE POSSIBILITY OF SUCH CLAIM, LOSS, DAMAGES OR OTHER LIABILITY.
 * APPLICABLE LEGISLATION SUCH AS THE AUSTRALIAN CONSUMER LAW MAY APPLY REPRESENTATIONS, WARRANTIES, OR CONDITIONS, OR IMPOSES OBLIGATIONS OR LIABILITY ON CSIRO THAT CANNOT BE EXCLUDED, RESTRICTED OR MODIFIED TO THE FULL EXTENT SET OUT IN THE EXPRESS TERMS OF THIS CLAUSE ABOVE "CONSUMER GUARANTEES".  TO THE EXTENT THAT SUCH CONSUMER GUARANTEES CONTINUE TO APPLY, THEN TO THE FULL EXTENT PERMITTED BY THE APPLICABLE LEGISLATION, THE LIABILITY OF CSIRO UNDER THE RELEVANT CONSUMER GUARANTEE IS LIMITED (WHERE PERMITTED AT CSIRO'S OPTION) TO ONE OF FOLLOWING REMEDIES OR SUBSTANTIALLY EQUIVALENT REMEDIES:
 * (a)               THE REPLACEMENT OF THE SOFTWARE, THE SUPPLY OF EQUIVALENT SOFTWARE, OR SUPPLYING RELEVANT SERVICES AGAIN;
 * (b)               THE REPAIR OF THE SOFTWARE;
 * (c)               THE PAYMENT OF THE COST OF REPLACING THE SOFTWARE, OF ACQUIRING EQUIVALENT SOFTWARE, HAVING THE RELEVANT SERVICES SUPPLIED AGAIN, OR HAVING THE SOFTWARE REPAIRED.
 * IN THIS CLAUSE, CSIRO INCLUDES ANY THIRD PARTY AUTHOR OR OWNER OF ANY PART OF THE SOFTWARE OR MATERIAL DISTRIBUTED WITH IT.  CSIRO MAY ENFORCE ANY RIGHTS ON BEHALF OF THE RELEVANT THIRD PARTY.
 * Third Party Components
 * The following third party components are distributed with the Software.  You agree to comply with the licence terms for these components as part of accessing the Software.  Other third party software may also be identified in separate files distributed with the Software.
 *
 *
 *
 */

namespace AEHRC\AdvancedFhirOntologyExternalModule;

use ExternalModules\AbstractExternalModule;
use ExternalModules\ExternalModules;


class AdvancedFhirOntologyExternalModule extends AbstractExternalModule implements \OntologyProvider
{

    public function __construct()
    {
        parent::__construct();
        // register with OntologyManager
        $manager = \OntologyManager::getOntologyManager();
        $manager->addProvider($this);
    }

    public function redcap_every_page_before_render($project_id)
    {
        // don't need to do anything, just trigger the constructor so the provider is available.
    }



    public function validateSettings($settings)
    {
        $errors = '';



        $ontologyIds = array();
        $ontologyNames = array();
        $ontologyIdValues = $settings['ontology-id'];
        $ontologyNameValues = $settings['ontology-name'];
        $codeTemplates = $settings['code-template'];
        $displayTemplates = $settings['display-template'];
        $rnrFlags = $settings['return-no-result'];
        $rnrLabels = $settings['no-result-label'];
        $rnrCodes = $settings['no-result-code'];
        $fhirUrls = $settings['fhir-api-url'];
        $authTypes = $settings['authentication-type'];
        $authEndpoints = $settings['cc-token-endpoint'];
        $clientIds = $settings['cc-client-id'];
        $clientSecrets = $settings['cc-client-secret'];
        $valueSetTypes = $settings['valueset-type'];
        $valuesets = $settings['valueset'];
        $priorityCodes = $settings['priority-codes'];
        $priorityMaxFetches = $settings['priority-max-fetch'];
        $bannedCodes = $settings['banned-codes'];



        foreach ($ontologyIdValues as $key => $id) {

            // check id is valid and not duplicated.
            if ($id != strip_tags($id)
                || strpos($id, "'") !== false
                || strpos($id, '"') !== false
            ) {
                $errors .= "Ontology ID has illegal characters - " . $id . "\n";
            }
            if (array_key_exists($id, $ontologyIds)){
                $errors .= "Ontology ID has duplicates - " . $id . "\n";
            }
            else {
                $ontologyIds[$id] = true;
            }

            // check name is valid and not duplicated.
            $name = $ontologyNameValues[$key];
            if ($name != strip_tags($name)) {
                $errors .= "Ontology Id " . $id . " - Ontology Name has illegal characters - " . $name . "\n";
            }
            if (array_key_exists($name, $ontologyNames)){
                $errors .= "Ontology Id " . $id . " - Ontology name is a duplicate - " . $name . "\n";
            }
            else {
                $ontologyNames[$name] = true;
            }

            // check code template is valid and contains ${CODE}
            $codeTemplate = $codeTemplates[$key];
            if ($codeTemplate != strip_tags($codeTemplate)) {
                $errors .= "Ontology Id " . $id . " - Code template has illegal characters - " . $codeTemplate . "\n";
            }
            if (strpos($codeTemplate, '${CODE}') === false){
                $errors .= "Ontology Id " . $id . ' - Code template should contain "${CODE}" : ' . $codeTemplate . "\n";
            }

            // check display template is valid and contains ${CODE} or ${DISPLAY}
            $displayTemplate = $displayTemplates[$key];
            if ($displayTemplate != strip_tags($displayTemplate)) {
                $errors .= "Ontology Id " . $id . " - Display template has illegal characters - " . $displayTemplate . "\n";
            }
            if (strpos($displayTemplate, '${CODE}') === false && strpos($displayTemplate, '${DISPLAY}') === false){
                $errors .= "Ontology Id " . $id . ' - Display template should contain either "${CODE}" or "${DISPLAY}" : ' . $displayTemplate . "\n";
            }

            // test return no result settings
            $rnr = $rnrFlags[$key];
            if ($rnr) {
                // check we have a code and label
                $label = trim($rnrLabels[$key]);
                $code = trim($rnrCodes[$key]);
                if ($label === '') {
                    $errors .= "Ontology Id " . $id . " - No Result Label is required\n";
                } else if ($label != strip_tags($label)) {
                    $errors .= "Ontology Id " . $id . " - No Results Label has illegal characters - " . $label . "\n";
                }

                if ($code === '') {
                    $errors .= "Ontology Id " . $id . " - No Result Code is required\n";
                } else if ($code != strip_tags($code)
                    || strpos($code, "'") !== false
                    || strpos($code, '"') !== false
                ) {
                    $errors .= "Ontology Id " . $id . " - No Results Code has illegal characters - " . $code . "\n";
                }
            }

            // test fhir urls settings
            $fhirUrl = $fhirUrls[$key];
            if (!$fhirUrl){
                $errors .= "Ontology Id " . $id . " - FHIR API URL is required.\n";
            }
            else {
                $strlen = strlen($fhirUrl);
                if ('/' === $fhirUrl[$strlen - 1]) {
                    // remove trailing /
                    $fhirUrl = substr($fhirUrl, 0, $strlen - 1);
                }
                $metadata = $this->httpGet($fhirUrl . '/metadata', ['User-Agent: Redcap']);
                if ($metadata === FALSE) {
                    $errors .= "Ontology Id " . $ontologyIdValues[$key] . " - Failed to get metadata for fhir server at '" . $fhirUrl . "'\n";
                }
                $authType = $authTypes[$key];
                if ($authType === 'cc') {
                    $authEndpoint = $authEndpoints[$key];
                    $clientId = $clientIds[$key];
                    $clientSecret = $clientSecrets[$key];

                    // get the access token
                    $params = array(
                        'grant_type' => 'client_credentials'
                    );
                    $headers = ['User-Agent: Redcap', 'Authorization: Basic ' . base64_encode($clientId . ':' . $clientSecret)];

                    try {
                        $response = $this->httpPost($authEndpoint, $params, 'application/x-www-form-urlencoded', $headers);
                        if ($response === false) {
                            $r = implode("", $http_response_header);
                            $errors .= "Ontology Id " . $id . " - Failed to get Authentication Token for fhir server at '" . $authEndpoint . "' response = false, r='" . $r . "'\n";
                        } else {
                            $responseJson = json_decode($response, true);
                            if (!array_key_exists('access_token', $responseJson)) {
                                $errors .= "Ontology Id " . $id . " - Failed to get Authentication Token for fhir server at '" . $authEndpoint . "'$response\n";
                            }
                        }
                    } catch (\Exception $e) {
                        $errors .= "Ontology Id " . $id . " - Failed to get Authentication Token for fhir server at '" . $authEndpoint . "' got exception $e\n";
                    }
                }
            }

            // test valueset
            $valueSetType = $valueSetTypes[$key];
            $valueSetValue = $valuesets[$key];
            if ($valueSetType === 'url'){
                // valueset should not be blank.
                if (!$valueSetValue){
                    $errors .= "Ontology Id " . $id . " - Valueset is required.";
                }
            } else if ($valueSetType === 'resource'){
                // valueset should be valid json
                $resource = json_decode($valueSetValue);
                if (is_null($resource)) {
                    $errors .= "Ontology Id " . $id . " - Invalid JSon : " . json_last_error_msg() . "\n";
                }
            }

            // test priority max fetch
            $priorityMaxFetch = trim($priorityMaxFetches[$key]);
            if ($priorityMaxFetch){
                if (!!ctype_digit($priorityMaxFetch)){
                    $errors .= "Ontology Id " . $id . " - Priority Max Fetch should be an integer - " . $priorityMaxFetch . "\n";
                }
            }
        }

        return $errors;
    }


    /**
     * return the name of the ontology service as it will be display on the service selection
     * drop down.
     */
    public function getProviderName()
    {
        return 'Advanced FHIR Ontologies';
    }


    /**
     * return the prefex used to denote ontologies provided by this provider.
     */
    public function getServicePrefix()
    {
        return 'ADVFHIR';
    }

    /**
     * Search API with a search term for a given ontology
     * Returns array of results with Notation as key and PrefLabel as value.
     */
    public function searchOntology($category, $search_term, $result_limit)
    {
        $siteCategories = $this->getSubSettings('site-category-list');
        $thisCategory = null;

        foreach ($siteCategories as $cat) {
            if ($cat['ontology-id'] === $category){
                $thisCategory = $cat;
                break;
            }
        }
        $results = array();
        // Set 20 as default limit
        $result_limit = (is_numeric($result_limit) ? $result_limit : 20);

        if ($thisCategory != null){

            $priorityFetchAdd = $thisCategory['$priority-max-fetch'];
            $fetchLimit = $result_limit + ($priorityFetchAdd ? (int)$priorityFetchAdd : 0);

            $headers = ['User-Agent: Redcap'];

            $fhirAuthType = $thisCategory['authentication-type'];

            if ('cc' === $fhirAuthType){
                $tokenEndpoint = $thisCategory['cc-token-endpoint'];
                $clientId = $thisCategory['cc-client-id'];
                $clientSecret = $thisCategory['cc-client-secret'];

                $authToken = $this->getClientCredentialsToken($category, $tokenEndpoint, $clientId, $clientSecret);
                if ($authToken !== false) {
                    $headers[] = 'Authorization: Bearer ' . $authToken;
                }
            }

            $fhirServerUrl = $thisCategory['fhir-api-url'];

            $valueSetType = $thisCategory['valueset-type'];
            $valueSet = $thisCategory['valueset'];

            if ('url' === $valueSetType) {

                //  Base URL + “/ValueSet/$expand?identifier=VS_ID&filter=SEARCH_TERM”
                // need to escape the $expand in the url!
                $url = $fhirServerUrl . "/ValueSet/\$expand?" . http_build_query(array(
                        'url' => $valueSet,
                        'filter' => $search_term,
                        'count' => $fetchLimit
                    ));

                $json = $this->httpGet($url, $headers);
            }
            else {
                // valueset is json
                $resource = json_decode($valueSet, true);
                $contentType = "application/json";

                $postData = [
                    "resourceType" => "Parameters",
                    "parameter" => [
                        ["name" => "filter", "valueString" => $search_term],
                        ["name" => "_count", "valueInteger" => $fetchLimit],
                        ["name" => "valueSet", "resource" =>  $resource]
                    ]
                ];
                $postData = json_encode($postData, JSON_UNESCAPED_SLASHES);

                $url = $fhirServerUrl . '/ValueSet/$expand';
                $json = $this->httpPost($url, $postData, $contentType, $headers);
            }

            $codeTemplate = $thisCategory['code-template'];
            $displayTemplate = $thisCategory['display-template'];
            $templateKeys = ['${CODE}', '${SYSTEM}', '${DISPLAY}'];
            $allPriorityCodes = $thisCategory['priority-codes'];
            $priorityCodes = preg_split("/\r\n|\n|\r/", $allPriorityCodes);
            $allBannedCodes = $thisCategory['banned-codes'];
            $bannedCodes = preg_split("/\r\n|\n|\r/", $allBannedCodes);

            // Parse the JSON into an array
            $list = json_decode($json, true);
            $expansion = $list['expansion'];

            if (is_array($list) && isset($expansion['contains'])) {
                // Loop through results
                $core_results = array();
                $key_results = array();
                $hideChoice = $this->getHideChoice();
                foreach ($expansion['contains'] as $this_item) {
                    $code = $this_item['code'];
                    $display = $this_item['display'];
                    $system = $this_item['system'];
                    if (in_array($code, $bannedCodes)){
                        // code is banned, skip it
                        continue;
                    }
                    if (in_array($code, $hideChoice)){
                        // code is in hide choide, skip it
                        continue;
                    }
                    $sortKey = array_search($code, $priorityCodes);
                    if ($sortKey === false){
                        $sortKey = count($priorityCodes);
                    }
                    $key_results[] = $sortKey;
                    $core_results[] = [$code, $system, $display];
                }
                // sort to put priority codes first
                array_multisort($key_results, SORT_ASC, $core_results);
                foreach ($core_results as $index=>$r) {
                    if ($index >= $result_limit){
                        // not interested in more results
                        break;
                    }
                    $finalCode = str_replace($templateKeys, $r, $codeTemplate);
                    $finalDisplay = str_replace($templateKeys, $r, $displayTemplate);
                    $results[$finalCode] = $finalDisplay;
                }
            }
        }


        if (!$results) {
            // no results found
            $return_no_result = $thisCategory['return-no-result'];
            if ($return_no_result) {
                $no_result_label = $thisCategory['no-result-label'];
                $no_result_code = $thisCategory['no-result-code'];
                $results[$no_result_code] = $no_result_label;
            }
        }
        // Return array of results
        return array_slice($results, 0, $result_limit, true);
    }

    function getHideChoice()
    {
        $codesToHide=[];
        if (isset($_GET['field'])){
            $field = $_GET['field'];
            if (isset($Proj->metadata[$_GET['field']])) {
                $annotations = $Proj->metadata[$field]['field_annotation'];
            }
            else if (isset($_GET['pid'])){
                $project_id = $_GET['pid'];
                $dd_array = \REDCap::getDataDictionary($project_id, 'array', false, array($field));
                $annotations = $dd_array[$field]['field_annotation'];
            }
            if ($annotations) {
                $offset = 0;
                while (preg_match("/@HIDECHOICE='([^']*)'/", $annotations, $matches, PREG_OFFSET_CAPTURE, $offset) === 1){
                    $listedCodesStr = $matches[1][0];
                    $listedCodes = explode(',', $listedCodesStr);
                    foreach($listedCodes as $code){
                        array_push($codesToHide, trim($code));
                    }
                    $offset = $matches[0][1] + strlen($matches[0][0]);
                }
            }
        }

        return $codesToHide;
    }

    /**
     * Return a string which will be placed in the online designer for
     * selecting an ontology for the service.
     * When an ontology is selected it should make a javascript call to
     * update_ontology_selection($service, $category)
     *
     * The provider may include a javascript function
     * <service>_ontology_changed(service, category)
     * which will be called when the ontology selection is changed. This function
     * would update any UI elements is the service matches or clear the UI elemements
     * if they do not.
     */
    public function getOnlineDesignerSection()
    {
        $siteCategories = $this->getSubSettings('site-category-list');

        $categories = [];

        $categoryList = '';
        foreach ($siteCategories as $cat) {
            $category = $cat['ontology-id'];
            $name = $cat['ontology-name'];
            $categoryList .= "<option value='{$category}'>{$name}</option>\n";
        }

        $onlineDesignerHtml = <<<EOD
<script type="text/javascript">
  function ADVFHIR_ontology_changed(service, category){
    var newSelection = ('ADVFHIR' === service) ? category : '';
    $('#advfhir_ontology_category').val(newSelection);
  }
  
</script>
<div style='margin-bottom:3px;'>
  Select Advanced FHIR Ontology to use:
</div>
<select id='advfhir_ontology_category' name='advfhir_ontology_category' 
            onchange="update_ontology_selection('ADVFHIR', this.options[this.selectedIndex].value)"
            class='x-form-text x-form-field' style='width:330px;max-width:330px;'>
        {$categoryList}
</select>
EOD;
        return $onlineDesignerHtml;
    }

    public function getLabelForValue($category, $value)
    {
        return $value;
    }




    public function httpGet($fullUrl, $headers)
    {
        // if curl isn't install the default version of http_get in init_functions doesn't include the headers.
        if (function_exists('curl_init') || empty($headers)) {
            return http_get($fullUrl, null, '', $headers, null);
        }
        if (ini_get('allow_url_fopen')) {
            // Set http array for file_get_contents
            $headerText = '';
            foreach ($headers as $hvalue) {
                $headerText .= $hvalue . "\r\n";
            }
            $http_array = array('method' => 'GET', 'header' => $headerText);
            // If using a proxy
            if (!sameHostUrl($fullUrl) && PROXY_HOSTNAME != '') {
                $http_array['proxy'] = str_replace(array('http://', 'https://'), array('tcp://', 'tcp://'), PROXY_HOSTNAME);
                $http_array['request_fulluri'] = true;
                if (PROXY_USERNAME_PASSWORD != '') {
                    $proxy_auth = "Proxy-Authorization: Basic " . base64_encode(PROXY_USERNAME_PASSWORD);
                    if (isset($http_array['header'])) {
                        $http_array['header'] .= $proxy_auth . "\r\n";
                    } else {
                        $http_array['header'] = $proxy_auth . "\r\n";
                    }
                }
            }
            // Use file_get_contents
            $content = @file_get_contents($fullUrl, false, stream_context_create(array('http' => $http_array)));
        } else {
            $content = false;
        }
        // Return the response
        return $content;
    }

    public function httpPost($fullUrl, $postData, $contentType, $headers)
    {
        // if curl isn't install the default version of http_post in init_functions doesn't include the headers.
        // but the curl version will overwrite the content type header if other headers are included.
        if (function_exists('curl_init') && !empty($headers)
            && $contentType && $contentType != 'application/x-www-form-urlencoded'){
            $fullHeaders = $headers;
            $fullHeaders[] = 'Content-type: '.$contentType;
            return http_post($fullUrl, $postData, null, $contentType, '', $fullHeaders);
        }
        else if (function_exists('curl_init') || empty($headers)) {
            return http_post($fullUrl, $postData, null, $contentType, '', $headers);
        }
        // If params are given as an array, then convert to query string format, else leave as is
        if ($contentType == 'application/json') {
            // Send as JSON data
            $param_string = (is_array($postData)) ? json_encode($postData) : $postData;
        } elseif ($contentType == 'application/x-www-form-urlencoded') {
            // Send as Form encoded data
            $param_string = (is_array($postData)) ? http_build_query($postData, '', '&') : $postData;
        } else {
            // Send params as is (e.g., Soap XML string)
            $param_string = $postData;
        }
        if (ini_get('allow_url_fopen')) {
            // Set http array for file_get_contents
            // Set http array for file_get_contents
            $headerText = '';
            foreach ($headers as $hvalue) {
                $headerText .= $hvalue . "\r\n";
            }

            $http_array = array('method' => 'POST',
                'header' => "Content-type: $contentType" . "\r\n" . $headerText . "Content-Length: " . strlen($param_string) . "\r\n",
                'content' => $param_string
            );
            // If using a proxy
            if (!sameHostUrl($fullUrl) && PROXY_HOSTNAME != '') {
                $http_array['proxy'] = str_replace(array('http://', 'https://'), array('tcp://', 'tcp://'), PROXY_HOSTNAME);
                $http_array['request_fulluri'] = true;
                if (PROXY_USERNAME_PASSWORD != '') {
                    $http_array['header'] .= "Proxy-Authorization: Basic " . base64_encode(PROXY_USERNAME_PASSWORD) . "\r\n";
                }
            }

            // Use file_get_contents
            $content = @file_get_contents($fullUrl, false, stream_context_create(array('http' => $http_array)));

            // Return the content
            if ($content !== false) {
                return $content;
            } // If no content, check the headers to see if it's hiding there (why? not sure, but it happens)
            else {
                $content = implode("", $http_response_header);
                //  If header is a true header, then return false, else return the content found in the header
                return (substr($content, 0, 5) == 'HTTP/') ? false : $content;
            }
        }
        return false;
    }

    public function getClientCredentialsToken($category, $tokenEndpoint, $clientId, $clientSecret)
    {
        $now = time();
        $expireKey = 'ADVFHIR_' + $tokenEndpoint + '_TOKEN_EXPIRES';
        $tokenKey = 'ADVFHIR_' + $tokenEndpoint + '_TOKEN';
        if (array_key_exists($expireKey, $_SESSION) &&
            array_key_exists($tokenKey, $_SESSION)) {
            $expire = $_SESSION[$expireKey];
            if ($now < $expire) {
                // not expired.
                return $_SESSION[$tokenKey];
            }
        }

        // get the access token
        $params = array(
            'grant_type' => 'client_credentials'
        );
        $headers = ['User-Agent: Redcap', 'Authorization: Basic ' . base64_encode($clientId . ':' . $clientSecret)];

        $clear = true;
        try {
            $response = $this->httpPost($tokenEndpoint, $params, 'application/x-www-form-urlencoded', $headers);
            $responseJson = json_decode($response, true);
            if (array_key_exists('access_token', $responseJson)) {
                $clear = false;
                $_SESSION[$tokenKey] = $responseJson['access_token'];
                if (array_key_exists('expires_in', $responseJson)) {
                    $_SESSION[$expireKey] = $now + ($responseJson['expires_in'] * 1000);
                } else {
                    $_SESSION[$expireKey] = $now + (60 * 60 * 1000);
                }
            } elseif (array_key_exists('error', $responseJson)) {
                error_log("Failed to negotiate auth token : " . $responseJson['error'] . " - " . $responseJson['error_description']);
            } else {
                error_log("Failed to negotiate auth token : " . $response);
            }
        } catch (\Exception $e) {
            $error_code = $e->getCode();
            $error_message = $e->getMessage();
            error_log("Failed to negotiate auth token : {$error_code} - {$error_message}");
        }
        if ($clear) {
            unset($_SESSION[$expireKey]);
            unset($_SESSION[$tokenKey]);
            return false;
        }
        return $_SESSION[$tokenKey];
    }

}

