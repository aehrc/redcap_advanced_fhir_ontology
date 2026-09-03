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

    /**
     * Dedicated marker for PublicFhirServerTest's "was the right bootstrap
     * loaded" guard - deliberately not piggybacking on realHttpTimeoutSeconds()
     * or any other helper below, so refactoring this file's internals can never
     * silently break that guard.
     */
    function usingRealHttpTransport()
    {
        return true;
    }

    function realHttpTimeoutSeconds($timeout)
    {
        $seconds = is_numeric($timeout) ? (int)$timeout : 30;
        // 0 (or negative) reaching curl means "never time out" - the opposite of
        // what a bounded, non-blocking system-test job needs. Not reachable via
        // the module's own getFhirTimeout() (which already guards this), but
        // this helper shouldn't rely on every caller doing that.
        return $seconds > 0 ? $seconds : 30;
    }

    function http_get($url, $timeout = null, $basic_auth_user_pass = '', $headers = [], $user_agent = null)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $seconds = realHttpTimeoutSeconds($timeout);
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
        // A non-2xx response (error page, OperationOutcome, rate-limiting) is not
        // a curl-level failure - curl_errno stays 0 - but it's not usable data
        // either. Treating it as success would let searchOntology() silently
        // return [] with no signal of *why*, indistinguishable from a real
        // regression or a genuinely empty match set.
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $failed = curl_errno($ch) !== 0 || $httpCode < 200 || $httpCode >= 300;
        curl_close($ch);
        return $failed ? false : $response;
    }

    function http_post($url, $postData = [], $timeout = null, $content_type = 'application/x-www-form-urlencoded', $basic_auth_user_pass = '', $headers = [], &$info = null)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        // Encode to match the declared content type rather than always
        // form-encoding an array body - not reachable via the module's current
        // two call sites (they always pair array+form-urlencoded or
        // json-string+application/json), but a mismatched body/Content-Type
        // shouldn't be possible to construct here even for a future caller.
        if (is_array($postData)) {
            $body = $content_type === 'application/json' ? json_encode($postData) : http_build_query($postData);
        } else {
            $body = $postData;
        }
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        $seconds = realHttpTimeoutSeconds($timeout);
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
        $httpCode = (int)($info['http_code'] ?? 0);
        $failed = curl_errno($ch) !== 0 || $httpCode < 200 || $httpCode >= 300;
        curl_close($ch);
        return $failed ? false : $response;
    }

    function sameHostUrl($url)
    {
        return true;
    }
}
