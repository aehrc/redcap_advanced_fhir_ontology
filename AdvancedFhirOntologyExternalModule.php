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

require_once __DIR__ . '/FhirRequestPolicy.php';


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
        $basicUserIds = $settings['basic-user-id'];
        $basicUserPasswords = $settings['basic-user-password'];
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
                $headers = ['User-Agent: Redcap'];
                $authType = $authTypes[$key];
                if ($authType === 'basic') {
                    $authUser = $basicUserIds[$key];
                    $authPassword = $basicUserPasswords[$key];
                    $headers[] = 'Authorization: Basic ' . base64_encode($authUser . ':' . $authPassword);
                }
                $metadata = $this->httpGet($fhirUrl . '/metadata', $headers, $fhirUrl);
                if ($metadata === FALSE) {
                    $errors .= "Ontology Id " . $ontologyIdValues[$key] . " - Failed to get metadata for fhir server at '" . $fhirUrl . "'\n";
                }

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
                        $response = $this->httpPost($authEndpoint, $params, 'application/x-www-form-urlencoded', $headers, $authEndpoint);
                        if ($response === false) {
                            $r = isset($http_response_header) ? implode("", $http_response_header) : '';
                            $errors .= "Ontology Id " . $id . " - Failed to get Authentication Token for fhir server at '" . $authEndpoint . "' response = false, r='" . $r . "'\n";
                        } else {
                            // a false or unparseable response decodes to null, and array_key_exists(null)
                            // is a fatal TypeError on PHP 8
                            $responseJson = is_string($response) ? json_decode($response, true) : null;
                            if (!is_array($responseJson)) {
                                $errors .= "Ontology Id " . $id . " - Failed to get Authentication Token for fhir server at '" . $authEndpoint . "' - no parseable response\n";
                            } else if (!array_key_exists('access_token', $responseJson)) {
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
        $fhirFailed = false;
        // Set 20 as default limit
        $result_limit = (is_numeric($result_limit) ? $result_limit : 20);

        if ($thisCategory !== null){

            $priorityFetchAdd = $thisCategory['priority-max-fetch'];
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
            elseif ('basic' === $fhirAuthType){
                $userId = $thisCategory['basic-user-id'];
                $userPassword = $thisCategory['basic-user-password'];
                $headers[] = 'Authorization: Basic ' . base64_encode($userId . ':' . $userPassword);
            }

            $fhirServerUrl = $thisCategory['fhir-api-url'];

            $valueSetType = $thisCategory['valueset-type'];
            $valueSet = $thisCategory['valueset'];
            $language = $thisCategory['fhir-display-language'];
            // Without this, every search sends the typed text to the FHIR server as a
            // filter, so nothing appears unless it happens to textually match the
            // server's display wording. For a small, fully-enumerated ValueSet this
            // makes it hard to browse - with return-all set, the filter is omitted
            // entirely (fetching the full/default expansion instead) and matches are
            // ranked locally below. Intended for small ValueSets only: it fetches the
            // entire expansion on every keystroke rather than a filtered subset.
            $returnAll = !empty($thisCategory['return-all']);

            $fhirFailed = false;
            $json = false;
            if ($this->isCircuitOpen($category)) {
                // This category's server has failed repeatedly - fail fast rather
                // than tying up a web server process on a request we already
                // expect to time out.
                $fhirFailed = true;
            }
            else {
                $startedAt = microtime(true);
                if ('url' === $valueSetType) {

                    //  Base URL + “/ValueSet/$expand?identifier=VS_ID&filter=SEARCH_TERM”
                    // need to escape the $expand in the url!
                    $expandParams = ['url' => $valueSet, 'count' => $fetchLimit];
                    if (!$returnAll) {
                        $expandParams['filter'] = $search_term;
                    }
                    if (!empty($language)){
                        $expandParams['displayLanguage'] = $language;
                    }
                    $url = $fhirServerUrl . "/ValueSet/\$expand?" . http_build_query($expandParams);

                    $json = $this->httpGet($url, $headers, $fhirServerUrl);
                }
                else {
                    // valueset is json
                    $resource = json_decode($valueSet, true);
                    $contentType = "application/json";

                    $postData = [
                        "resourceType" => "Parameters",
                        "parameter" => [
                            // 'count', not '_count' - $expand is a FHIR *operation*, not a
                            // plain resource search, so its count parameter is 'count' as
                            // defined by its OperationDefinition. '_count' is the REST
                            // search-result modifier used by plain searches; Ontoserver
                            // silently ignores it here rather than rejecting the request,
                            // so it had no effect at all (confirmed live against the real
                            // configured Ontoserver for redcap_fhir_ontology_provider's
                            // identical POST-based $expand call).
                            ["name" => "count", "valueInteger" => $fetchLimit],
                            ["name" => "valueSet", "resource" =>  $resource],
                        ]
                    ];
                    if (!$returnAll) {
                        array_unshift($postData['parameter'], ["name" => "filter", "valueString" => $search_term]);
                    }
                    if (!empty($language)){
                        $postData['parameter'][] = ["name" => 'displayLanguage', "valueCode" => $language];
                    }
                    $postData = json_encode($postData, JSON_UNESCAPED_SLASHES);

                    $url = $fhirServerUrl . '/ValueSet/$expand';
                    $json = $this->httpPost($url, $postData, $contentType, $headers, $fhirServerUrl);
                }
                if ($json === false) {
                    $fhirFailed = true;
                    $this->recordFhirFailureIfSlow($category, microtime(true) - $startedAt);
                }
                else {
                    $this->recordFhirSuccess($category);
                }
            }

            $codeTemplate = $thisCategory['code-template'];
            $displayTemplate = $thisCategory['display-template'];
            $templateKeys = ['${CODE}', '${SYSTEM}', '${DISPLAY}'];
            $allPriorityCodes = $thisCategory['priority-codes'];
            $priorityCodes = preg_split("/\r\n|\n|\r/", $allPriorityCodes);
            $allBannedCodes = $thisCategory['banned-codes'];
            $bannedCodes = preg_split("/\r\n|\n|\r/", $allBannedCodes);

            // Parse the JSON into an array
            $list = is_string($json) ? json_decode($json, true) : null;
            if (is_array($list) && isset($list['expansion']['contains'])) {
                $expansion = $list['expansion'];
                // Loop through results
                $core_results = array();
                $key_results = array();
                // Only meaningful for return-all: with a real filter sent to the
                // server, every returned entry already matches the search term, so
                // this key is uniform and doesn't affect sort order. With return-all,
                // the full unfiltered expansion comes back - entries matching the
                // typed text (code or display, case-insensitively) rank ahead of
                // non-matches, same priority-first/match-second order as
                // redcap_fhir_ontology_provider's @FHIR-ONTOLOGY-OPTIONS return-all.
                $matchKey_results = array();
                $hideChoice = $this->getHideChoice();
                foreach ($expansion['contains'] as $this_item) {
                    // code, display and system are not guaranteed present by FHIR
                    $code = isset($this_item['code']) ? $this_item['code'] : '';
                    $display = isset($this_item['display']) ? $this_item['display'] : $code;
                    $system = isset($this_item['system']) ? $this_item['system'] : '';
                    if ('' === $code) {
                        // nothing storable without a code - skip rather than templating it in
                        continue;
                    }
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
                    // Only computed for return-all: confirmed live against the real
                    // Ontoserver that filter=... does not do literal substring
                    // matching - a filter for "heart attack" legitimately returns
                    // "Myocardial infarction" (its real synonym), which this stripos()
                    // check would never recognize as a match. Applying this ranking
                    // unconditionally would silently demote that legitimately-relevant,
                    // server-matched result behind a less relevant one that merely
                    // contains the literal substring (e.g. "Fear of heart attack") -
                    // degrading the server's own relevance ranking for every normal
                    // (non-return-all) search, not just return-all's. With return-all,
                    // there's no server-side relevance signal to preserve in the first
                    // place (filter was omitted entirely), so this local heuristic is
                    // the only ranking available and only applies there.
                    $isMatch = !$returnAll
                        || ($search_term === '')
                        || (stripos($code, $search_term) !== false)
                        || (stripos($display, $search_term) !== false);
                    $matchKey_results[] = $isMatch ? 0 : 1;
                    $core_results[] = [$code, $system, $display];
                }
                // sort to put priority codes first, then (return-all only) matches
                // ahead of non-matches; array_multisort is stable (guaranteed since
                // PHP 8.0, this module's own floor), so ties keep their original
                // (server-returned) relative order.
                array_multisort($key_results, SORT_ASC, $matchKey_results, SORT_ASC, $core_results);
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


        if (!$results && $thisCategory !== null && !$fhirFailed) {
            // no results found - unknown category already returns empty above,
            // nothing to fall back to. Also skipped when the FHIR call itself
            // failed (breaker open, transport failure) - that's not a genuine
            // "no matches" result, so it shouldn't be presented as one.
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

    /**
     * Returns the field currently being searched's raw field_annotation
     * string, or null if there isn't one (or no field is being searched at
     * all). $Proj->metadata[$field] stores this under the raw DB column name
     * 'misc' - unlike REDCap::getDataDictionary()'s returned array, which
     * normalises it to 'field_annotation' (see Classes/MetaData.php's
     * getDataDictionaryHeaders()). An earlier version of this fast path both
     * read 'field_annotation' from $Proj->metadata (the wrong key) and never
     * pulled $Proj in via `global $Proj;` at all, so $Proj was always null
     * here and the fast path could never run in the first place - every
     * request fell through to the slower getDataDictionary() branch, or
     * silently found nothing at all if $_GET['pid'] wasn't set either.
     */
    private function getFieldAnnotation()
    {
        global $Proj;
        if (!isset($_GET['field'])) {
            return null;
        }
        $field = $_GET['field'];
        $project_id = isset($_GET['pid']) ? $_GET['pid'] : null;
        if (($project_id === null || (isset($Proj->project_id) && (string)$Proj->project_id === (string)$project_id))
                && isset($Proj->metadata[$field])) {
            return isset($Proj->metadata[$field]['misc']) ? $Proj->metadata[$field]['misc'] : null;
        }
        if ($project_id !== null) {
            $dd_array = \REDCap::getDataDictionary($project_id, 'array', false, array($field));
            return isset($dd_array[$field]['field_annotation']) ? $dd_array[$field]['field_annotation'] : null;
        }
        return null;
    }

    function getHideChoice()
    {
        $codesToHide=[];
        $annotations = $this->getFieldAnnotation();
        if ($annotations) {
            // @HIDECHOICE is also REDCap core's own built-in action tag (for a
            // different purpose, on real choice fields); reusing its name here
            // means this module's own use of it can never be registered in
            // REDCap's "@ Action Tags" popup (a module tag colliding with a
            // built-in one is silently dropped from that list, not shown -
            // see Design/action_tag_explain.php). @ADVANCED-FHIR-ONTOLOGY-HIDECHOICE
            // is a second, non-colliding tag name recognized for the same
            // purpose; both are supported and merged so existing fields using
            // @HIDECHOICE keep working unchanged.
            foreach (['@HIDECHOICE', '@ADVANCED-FHIR-ONTOLOGY-HIDECHOICE'] as $tagName) {
                $offset = 0;
                while (preg_match("/" . preg_quote($tagName, '/') . "='([^']*)'/", $annotations, $matches, PREG_OFFSET_CAPTURE, $offset) === 1){
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

        $onlineDesignerJsUrl = $this->getUrl('js/online-designer.js');
        $onlineDesignerHtml = <<<EOD
<script src="{$onlineDesignerJsUrl}"></script>
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




    /**
     * Maximum number of seconds allowed to connect to *and* fully complete a
     * request to the FHIR server. Applied as both curl's connect timeout and its
     * total-time timeout (see curlGetWithTotalTimeout()/curlPostWithTotalTimeout()),
     * so a server that accepts the connection and then stalls can no longer hold a
     * web server process open indefinitely - REDCap core's own http_get()/
     * http_post() helpers only ever set the connect timeout, confirmed by reading
     * Config/init_functions.php, which is why this module makes its own curl calls
     * instead of delegating to them.
     */
    public function getFhirTimeout()
    {
        return FhirRequestPolicy::resolveTimeout($this->getSystemSetting('fhir-timeout'));
    }

    /**
     * True while $category's circuit breaker is open, i.e. that category's own FHIR
     * server has failed repeatedly and we should fail fast instead of dialing out
     * again. Ported from redcap_fhir_ontology_provider, scoped per-category rather
     * than site-wide - unlike that module, a single site-wide fhir-api-url, this
     * module lets each category point at a completely different FHIR server, so one
     * category's dead server must not fail-fast every other category's healthy one.
     *
     * Once the open window elapses a caller that observes it re-arms the window
     * before returning false, so callers arriving behind it keep failing fast while
     * it probes the server. This is best-effort, not a guarantee - see
     * redcap_fhir_ontology_provider's identical isCircuitOpen() for the full
     * read/write-race caveat, which applies unchanged here.
     */
    public function isCircuitOpen($category)
    {
        $openUntil = $this->getSystemSetting('fhir_breaker_open_until_' . $category);
        $now = time();
        if (FhirRequestPolicy::isOpen($openUntil, $now)) {
            return true;
        }
        if (FhirRequestPolicy::needsRearm($openUntil, $now)) {
            $this->setSystemSetting('fhir_breaker_open_until_' . $category, $now + FhirRequestPolicy::BREAKER_OPEN_SECONDS);
        }
        return false;
    }

    /** Counts a failure for $category and opens its breaker once enough have accumulated. */
    public function recordFhirFailure($category)
    {
        $failures = FhirRequestPolicy::nextFailureCount($this->getSystemSetting('fhir_breaker_failures_' . $category));
        $this->setSystemSetting('fhir_breaker_failures_' . $category, $failures);
        if (FhirRequestPolicy::opensBreaker($failures)) {
            $this->setSystemSetting('fhir_breaker_open_until_' . $category, time() + FhirRequestPolicy::BREAKER_OPEN_SECONDS);
        }
    }

    /**
     * A failure only indicates server health if the call actually hung. A fast
     * rejection (e.g. a malformed valueset returning 4xx) must not trip the breaker
     * for every other project using this same category.
     */
    public function recordFhirFailureIfSlow($category, $elapsedSeconds)
    {
        if (FhirRequestPolicy::countsAsFailure($elapsedSeconds, $this->getFhirTimeout())) {
            $this->recordFhirFailure($category);
        }
    }

    public function recordFhirSuccess($category)
    {
        // only write when there is state to clear, so a healthy server costs no writes
        if ($this->getSystemSetting('fhir_breaker_failures_' . $category)) {
            $this->setSystemSetting('fhir_breaker_failures_' . $category, 0);
            $this->setSystemSetting('fhir_breaker_open_until_' . $category, 0);
        }
    }

    /**
     * Returns $url with any embedded userinfo (user:pass@) stripped, for safe
     * inclusion in log messages. Falls back to the original value if it cannot
     * be parsed as a URL.
     */
    private function urlForLogging($url)
    {
        if (!is_string($url) || '' === $url) {
            return (string)$url;
        }
        $parts = parse_url($url);
        if (!is_array($parts) || (!isset($parts['user']) && !isset($parts['pass']))) {
            return $url;
        }
        $result = '';
        if (isset($parts['scheme'])) {
            $result .= $parts['scheme'] . '://';
        }
        if (isset($parts['host'])) {
            $result .= $parts['host'];
        }
        if (isset($parts['port'])) {
            $result .= ':' . $parts['port'];
        }
        if (isset($parts['path'])) {
            $result .= $parts['path'];
        }
        if (isset($parts['query'])) {
            $result .= '?' . $parts['query'];
        }
        return $result;
    }

    /**
     * @param string $base Every URL this module builds before calling httpGet() is
     *   checked against this with FhirRequestPolicy::isWithinBase(), so a malformed
     *   or hostile setting cannot make the module request a path or host outside
     *   the caller's intended server. Callers validating a candidate URL that has
     *   not been saved yet pass the same value as both $fullUrl and $base, which
     *   validates well-formedness only (isWithinBase(x, x) is trivially true), not
     *   containment.
     */
    public function httpGet($fullUrl, $headers, $base)
    {
        if (!FhirRequestPolicy::isWithinBase($fullUrl, $base)) {
            error_log('AdvancedFhirOntologyExternalModule: httpGet refused URL outside configured base - url='
                . $this->urlForLogging($fullUrl) . ' base=' . $this->urlForLogging($base));
            return false;
        }
        $timeout = $this->getFhirTimeout();
        $curlResult = $this->curlGetWithTotalTimeout($fullUrl, $headers, $timeout);
        if ($curlResult !== null) {
            return $curlResult;
        }
        // curl unavailable, or curl's own http_code was inconclusive (0) - REDCap
        // core's own http_get() falls through to file_get_contents in exactly the
        // same case, and its stream-context 'timeout' option is already a true
        // end-to-end limit (unlike curl's CONNECTTIMEOUT-only default), so no
        // further change is needed on this path.
        if (ini_get('allow_url_fopen')) {
            // Set http array for file_get_contents
            $headerText = '';
            foreach ($headers as $hvalue) {
                $headerText .= $hvalue . "\r\n";
            }
            $http_array = array('method' => 'GET', 'header' => $headerText, 'timeout' => $timeout);
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

    /**
     * This module's own copy of REDCap core's http_get()'s curl path (see
     * Config/init_functions.php), kept intentionally close to its option set,
     * with one addition: CURLOPT_TIMEOUT, bounding the entire request rather than
     * just the connect phase. Existing purely to close that one gap - if REDCap
     * core's own curl handling changes, this module's copy does not follow it
     * automatically and would need updating to match.
     *
     * @return string|false|null Response body on success; false on a definite
     *   failure (curl reported 404/407/5xx); null if curl is unavailable, or
     *   curl could not complete the request at all (http_code 0) - callers
     *   should fall back to something else in that case, matching core's own
     *   http_get()'s fallback to file_get_contents in exactly the same situation.
     */
    private function curlGetWithTotalTimeout($fullUrl, $headers, $timeout)
    {
        if (!function_exists('curl_init')) {
            return null;
        }
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_AUTOREFERER, true);
        curl_setopt($curl, CURLOPT_MAXREDIRS, 10);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_VERBOSE, 0);
        curl_setopt($curl, CURLOPT_URL, $fullUrl);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPGET, true);
        if (!sameHostUrl($fullUrl)) {
            curl_setopt($curl, CURLOPT_PROXY, PROXY_HOSTNAME);
            curl_setopt($curl, CURLOPT_PROXYUSERPWD, PROXY_USERNAME_PASSWORD);
        }
        curl_setopt($curl, CURLOPT_FRESH_CONNECT, 1);
        if (is_numeric($timeout)) {
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $timeout);
            // The one addition over core's own http_get(): bounds the whole
            // request, not just the connect phase.
            curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
        }
        if (!empty($headers) && is_array($headers)) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        }
        $response = curl_exec($curl);
        $info = curl_getinfo($curl);
        curl_close($curl);
        if (isset($info['http_code']) && ($info['http_code'] == 404 || $info['http_code'] == 407 || $info['http_code'] >= 500)) {
            return false;
        }
        if (isset($info['http_code']) && $info['http_code'] != 0) {
            return $response;
        }
        return null;
    }

    /** @param string $base See httpGet()'s docblock - identical containment check. */
    public function httpPost($fullUrl, $postData, $contentType, $headers, $base)
    {
        if (!FhirRequestPolicy::isWithinBase($fullUrl, $base)) {
            error_log('AdvancedFhirOntologyExternalModule: httpPost refused URL outside configured base - url='
                . $this->urlForLogging($fullUrl) . ' base=' . $this->urlForLogging($base));
            return false;
        }
        $timeout = $this->getFhirTimeout();
        $curlResult = $this->curlPostWithTotalTimeout($fullUrl, $postData, $contentType, $headers, $timeout);
        if ($curlResult !== null) {
            return $curlResult;
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
                'content' => $param_string,
                'timeout' => $timeout
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

    /**
     * This module's own copy of REDCap core's http_post()'s curl path (see
     * Config/init_functions.php), kept intentionally close to its option set,
     * with two differences: CURLOPT_TIMEOUT (see curlGetWithTotalTimeout()'s
     * docblock - the same reasoning applies here), and building the final header
     * list once rather than in two passes. Core's own http_post() first sets
     * CURLOPT_HTTPHEADER for the content-type header (when not form-urlencoded),
     * then - if custom headers are also present - overwrites it entirely with
     * just those headers (curl_setopt() replaces, it does not merge), silently
     * dropping the content-type header in that case; this module's own httpPost()
     * used to work around exactly that by appending its own 'Content-type'
     * header onto $headers before calling core's http_post(). Building the list
     * once here removes the need for that workaround.
     *
     * @return string|false|null Same meaning as curlGetWithTotalTimeout()'s
     *   return value - see its docblock.
     */
    private function curlPostWithTotalTimeout($fullUrl, $postData, $contentType, $headers, $timeout)
    {
        if (!function_exists('curl_init')) {
            return null;
        }
        if ($contentType == 'application/json') {
            $paramString = (is_array($postData)) ? json_encode($postData) : $postData;
        } elseif ($contentType == 'application/x-www-form-urlencoded') {
            $paramString = (is_array($postData)) ? http_build_query($postData, '', '&') : $postData;
        } else {
            $paramString = $postData;
        }
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_VERBOSE, 0);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_AUTOREFERER, true);
        curl_setopt($curl, CURLOPT_MAXREDIRS, 10);
        curl_setopt($curl, CURLOPT_URL, $fullUrl);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($curl, CURLOPT_POSTFIELDS, $paramString);
        if (!sameHostUrl($fullUrl)) {
            curl_setopt($curl, CURLOPT_PROXY, PROXY_HOSTNAME);
            curl_setopt($curl, CURLOPT_PROXYUSERPWD, PROXY_USERNAME_PASSWORD);
        }
        curl_setopt($curl, CURLOPT_FRESH_CONNECT, 1);
        if (is_numeric($timeout)) {
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $timeout);
            curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
        }
        $finalHeaders = ($contentType && $contentType !== 'application/x-www-form-urlencoded')
            ? ["Content-Type: $contentType", "Content-Length: " . strlen($paramString)]
            : [];
        if (!empty($headers) && is_array($headers)) {
            $finalHeaders = array_merge($finalHeaders, $headers);
        }
        if (!empty($finalHeaders)) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $finalHeaders);
        }
        $response = curl_exec($curl);
        $info = curl_getinfo($curl);
        curl_close($curl);
        if (isset($info['http_code']) && ($info['http_code'] == 404 || $info['http_code'] == 407 || $info['http_code'] >= 500)) {
            return false;
        }
        if (isset($info['http_code']) && $info['http_code'] != 0) {
            return $response;
        }
        return null;
    }

    public function getClientCredentialsToken($category, $tokenEndpoint, $clientId, $clientSecret)
    {
        $now = time();
        // Keyed by both the endpoint and the client ID (not just the endpoint) -
        // two categories can share a token endpoint while authenticating as
        // different clients (different scopes/permissions on the auth server),
        // and without the client ID in the key, the second category to run in a
        // session would silently reuse the first category's cached token instead
        // of authenticating as itself. Hashed rather than concatenated as plain
        // text so an unusual client ID (e.g. containing spaces or unicode) can't
        // produce a key that collides with a differently-built one.
        $cacheKeySuffix = hash('sha256', $tokenEndpoint . "\0" . $clientId);
        $expireKey = 'ADVFHIR_' . $cacheKeySuffix . '_TOKEN_EXPIRES';
        $tokenKey = 'ADVFHIR_' . $cacheKeySuffix . '_TOKEN';
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
            $response = $this->httpPost($tokenEndpoint, $params, 'application/x-www-form-urlencoded', $headers, $tokenEndpoint);
            // a false or unparseable response decodes to null, and array_key_exists(null)
            // is a fatal TypeError on PHP 8
            $responseJson = is_string($response) ? json_decode($response, true) : null;
            if (!is_array($responseJson)) {
                error_log("Failed to negotiate auth token : no parseable response from " . $tokenEndpoint);
            } elseif (array_key_exists('access_token', $responseJson)) {
                $clear = false;
                $_SESSION[$tokenKey] = $responseJson['access_token'];
                // expires_in is SECONDS (RFC 6749) and $now is seconds - the previous
                // * 1000 cached a 3600s token for roughly 41 days. Renew early by
                // margin = min(60, floor(lifetime / 2)): a minute early for normal
                // lifetimes, halfway through for very short ones, and never an expiry
                // beyond the real one.
                $lifetime = array_key_exists('expires_in', $responseJson)
                    ? (int)$responseJson['expires_in']
                    : 3600;
                if ($lifetime < 1) {
                    $lifetime = 1;
                }
                $margin = (int)min(60, floor($lifetime / 2));
                $_SESSION[$expireKey] = $now + $lifetime - $margin;
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

