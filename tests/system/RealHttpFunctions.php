<?php

namespace AEHRC\AdvancedFhirOntologyExternalModule {

    /**
     * Real, curl-based http_get()/http_post()/sameHostUrl() for the system tests -
     * these make actual outbound HTTPS requests to the public FHIR servers under
     * test. Deliberately minimal (no proxy support, no file_get_contents fallback):
     * good enough to exercise this module's real integration against a real server,
     * not a faithful reimplementation of REDCap core's http_get()/http_post().
     *
     * Defined in this namespace so the module's unqualified http_get()/http_post()
     * calls resolve here via PHP's namespaced-function-fallback - the same
     * mechanism the unit tests' FakeHttpTransport relies on, just with a real
     * implementation instead of a fake one.
     */

    function http_get($url, $timeout = null, $basic_auth_user_pass = '', $headers = [], $user_agent = null)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $seconds = is_numeric($timeout) ? (int)$timeout : 30;
        curl_setopt($ch, CURLOPT_TIMEOUT, $seconds);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $seconds);
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        if ($basic_auth_user_pass !== '') {
            curl_setopt($ch, CURLOPT_USERPWD, $basic_auth_user_pass);
        }
        if ($user_agent !== null) {
            curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
        }
        $response = curl_exec($ch);
        $failed = curl_errno($ch) !== 0;
        curl_close($ch);
        return $failed ? false : $response;
    }

    function http_post($url, $postData = [], $timeout = null, $content_type = 'application/x-www-form-urlencoded', $basic_auth_user_pass = '', $headers = [], &$info = null)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        // Passing an array straight to CURLOPT_POSTFIELDS makes curl build a
        // multipart/form-data body regardless of $content_type - build the
        // string ourselves so the body actually matches the declared type.
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($postData) ? http_build_query($postData) : $postData);
        $seconds = is_numeric($timeout) ? (int)$timeout : 30;
        curl_setopt($ch, CURLOPT_TIMEOUT, $seconds);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $seconds);
        $fullHeaders = $headers;
        $hasContentType = false;
        foreach ($headers as $header) {
            if (stripos($header, 'content-type:') === 0) {
                $hasContentType = true;
                break;
            }
        }
        if (!$hasContentType) {
            $fullHeaders[] = 'Content-Type: ' . $content_type;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $fullHeaders);
        if ($basic_auth_user_pass !== '') {
            curl_setopt($ch, CURLOPT_USERPWD, $basic_auth_user_pass);
        }
        $response = curl_exec($ch);
        $info = curl_getinfo($ch);
        $failed = curl_errno($ch) !== 0;
        curl_close($ch);
        return $failed ? false : $response;
    }

    function sameHostUrl($url)
    {
        return true;
    }
}
