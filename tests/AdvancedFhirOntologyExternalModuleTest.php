<?php

namespace AEHRC\AdvancedFhirOntologyExternalModule;

use PHPUnit\Framework\TestCase;

final class AdvancedFhirOntologyExternalModuleTest extends TestCase
{
    private AdvancedFhirOntologyExternalModule $module;

    protected function setUp(): void
    {
        // OntologyManager is a process-wide singleton in the real framework too;
        // reset before constructing so each test's module doesn't pile onto every
        // prior test's registration for the lifetime of the PHPUnit run.
        \OntologyManager::resetForTests();
        $this->module = new AdvancedFhirOntologyExternalModule();
        FakeHttpTransport::reset();
        $_SESSION = [];
        \REDCap::$getDataDictionaryCallCount = 0;
        \REDCap::$dataDictionary = [];
        unset($_GET['field'], $_GET['pid']);
        $GLOBALS['Proj'] = null;
    }

    /** Minimal valid category row; each test overrides only what it cares about. */
    private function category(array $overrides = []): array
    {
        return array_merge([
            'ontology-id' => 'test-cat',
            'ontology-name' => 'Test Category',
            'code-template' => '${CODE}',
            'display-template' => '${DISPLAY}',
            'fhir-display-language' => '',
            'return-no-result' => false,
            'no-result-label' => '',
            'no-result-code' => '',
            'fhir-api-url' => 'https://example.test/fhir',
            'authentication-type' => 'none',
            'valueset-type' => 'url',
            'valueset' => 'http://example.test/vs',
            'priority-codes' => '',
            'priority-max-fetch' => '',
            'banned-codes' => '',
        ], $overrides);
    }

    // --- getFhirTimeout() ---

    public function testFhirTimeoutDefaultsWhenSettingBlank(): void
    {
        $this->assertSame(FhirRequestPolicy::DEFAULT_TIMEOUT, $this->module->getFhirTimeout());
    }

    public function testFhirTimeoutDefaultsWhenSettingNotNumeric(): void
    {
        $this->module->systemSettings['fhir-timeout'] = 'not-a-number';
        $this->assertSame(FhirRequestPolicy::DEFAULT_TIMEOUT, $this->module->getFhirTimeout());
    }

    public function testFhirTimeoutDefaultsWhenSettingZeroOrNegative(): void
    {
        $this->module->systemSettings['fhir-timeout'] = '0';
        $this->assertSame(FhirRequestPolicy::DEFAULT_TIMEOUT, $this->module->getFhirTimeout());

        $this->module->systemSettings['fhir-timeout'] = '-5';
        $this->assertSame(FhirRequestPolicy::DEFAULT_TIMEOUT, $this->module->getFhirTimeout());
    }

    public function testFhirTimeoutUsesConfiguredValue(): void
    {
        $this->module->systemSettings['fhir-timeout'] = '25';
        $this->assertSame(25, $this->module->getFhirTimeout());
    }

    // --- getClientCredentialsToken() ---
    // Regression coverage for three real historical bugs in this method: the
    // '+' vs '.' string-concatenation crash, the expires_in seconds-vs-ms bug,
    // and the PHP 8 TypeError on a non-JSON/failed response.

    public function testTokenIsFetchedCachedAndExpiryIsInSecondsNotMilliseconds(): void
    {
        FakeHttpTransport::$response = json_encode(['access_token' => 'tok-1', 'expires_in' => 3600]);

        $before = time();
        $token = $this->module->getClientCredentialsToken('cat', 'https://example.test/token', 'id', 'secret');
        $after = time();

        $this->assertSame('tok-1', $token);
        $this->assertCount(1, FakeHttpTransport::$calls, 'should fetch a token on first call');

        // Regression: expires_in (seconds) was previously multiplied by 1000,
        // caching a 3600s token for ~41 days instead of ~1 hour. The cache key is
        // a hash of endpoint+clientId (see testDifferentClientIdsOnTheSameTokenEndpointDoNotShareACachedToken),
        // so it's found by scanning $_SESSION for the one *_TOKEN_EXPIRES key
        // this test itself just created, rather than asserting its literal name.
        $expireKeys = array_filter(array_keys($_SESSION), fn($k) => str_starts_with($k, 'ADVFHIR_') && str_ends_with($k, '_TOKEN_EXPIRES'));
        $this->assertCount(1, $expireKeys);
        $expireKey = reset($expireKeys);
        $this->assertGreaterThanOrEqual($before + 3600 - 60, $_SESSION[$expireKey]);
        $this->assertLessThanOrEqual($after + 3600, $_SESSION[$expireKey]);
    }

    public function testDifferentClientIdsOnTheSameTokenEndpointDoNotShareACachedToken(): void
    {
        // Regression: the cache key used to be keyed by token endpoint alone, so
        // a second category authenticating against the same endpoint with a
        // different client ID would silently be handed the first category's
        // cached token - a real cross-category identity/authorization mix-up,
        // not just a cache-efficiency bug.
        FakeHttpTransport::$response = json_encode(['access_token' => 'tok-for-client-a', 'expires_in' => 3600]);
        $tokenA = $this->module->getClientCredentialsToken('cat-a', 'https://example.test/token', 'client-a', 'secret-a');
        $this->assertCount(1, FakeHttpTransport::$calls);

        FakeHttpTransport::$response = json_encode(['access_token' => 'tok-for-client-b', 'expires_in' => 3600]);
        $tokenB = $this->module->getClientCredentialsToken('cat-b', 'https://example.test/token', 'client-b', 'secret-b');

        $this->assertSame('tok-for-client-a', $tokenA);
        $this->assertSame('tok-for-client-b', $tokenB);
        $this->assertCount(2, FakeHttpTransport::$calls, 'a different client ID must trigger its own fetch, not reuse the other client\'s cached token');
    }

    public function testSameClientIdWithDifferentSecretsOnTheSameTokenEndpointDoNotShareACachedToken(): void
    {
        // Regression: the client ID alone isn't enough either - two categories
        // configured with the same client ID but a different secret (e.g. a
        // mistyped or partially-rotated secret) must not share a token. If they
        // did, the second category's own secret would never actually be
        // exercised, and its misconfiguration would silently succeed off the
        // back of the first category's valid credentials instead of surfacing
        // as an auth failure.
        FakeHttpTransport::$response = json_encode(['access_token' => 'tok-for-secret-a', 'expires_in' => 3600]);
        $tokenA = $this->module->getClientCredentialsToken('cat-a', 'https://example.test/token', 'shared-client', 'secret-a');
        $this->assertCount(1, FakeHttpTransport::$calls);

        FakeHttpTransport::$response = json_encode(['access_token' => 'tok-for-secret-b', 'expires_in' => 3600]);
        $tokenB = $this->module->getClientCredentialsToken('cat-b', 'https://example.test/token', 'shared-client', 'secret-b');

        $this->assertSame('tok-for-secret-a', $tokenA);
        $this->assertSame('tok-for-secret-b', $tokenB);
        $this->assertCount(2, FakeHttpTransport::$calls, 'a different secret must trigger its own fetch, not reuse the other category\'s cached token');
    }

    public function testCachedUnexpiredTokenIsReusedWithoutRefetching(): void
    {
        FakeHttpTransport::$response = json_encode(['access_token' => 'tok-1', 'expires_in' => 3600]);
        $this->module->getClientCredentialsToken('cat', 'https://example.test/token', 'id', 'secret');
        $this->assertCount(1, FakeHttpTransport::$calls);

        $token = $this->module->getClientCredentialsToken('cat', 'https://example.test/token', 'id', 'secret');

        $this->assertSame('tok-1', $token);
        $this->assertCount(1, FakeHttpTransport::$calls, 'a cached, unexpired token must not trigger a second fetch');
    }

    public function testMalformedResponseDoesNotCrashAndReturnsFalse(): void
    {
        // Not valid JSON - decodes to null, and array_key_exists(null) is a
        // fatal TypeError on PHP 8 if the is_array() guard regresses.
        FakeHttpTransport::$response = 'not json';

        $token = $this->module->getClientCredentialsToken('cat', 'https://example.test/token', 'id', 'secret');

        $this->assertFalse($token);
    }

    public function testFailedHttpCallDoesNotCrashAndReturnsFalse(): void
    {
        FakeHttpTransport::$response = false;

        $token = $this->module->getClientCredentialsToken('cat', 'https://example.test/token', 'id', 'secret');

        $this->assertFalse($token);
    }

    // --- searchOntology() ---

    public function testSearchOntologyReturnsEmptyForUnknownCategory(): void
    {
        $this->module->subSettings['site-category-list'] = [$this->category()];

        $results = $this->module->searchOntology('does-not-exist', 'term', 20);

        $this->assertSame([], $results);
    }

    public function testSearchOntologyReturnsConfiguredNoResultFallbackForKnownEmptyCategory(): void
    {
        $this->module->subSettings['site-category-list'] = [$this->category([
            'return-no-result' => true,
            'no-result-label' => 'No Results Found',
            'no-result-code' => '_NRF_',
        ])];
        FakeHttpTransport::$response = json_encode(['expansion' => ['contains' => []]]);

        $results = $this->module->searchOntology('test-cat', 'term', 20);

        // Distinguishes this from the unknown-category case above: a *known*
        // category with zero real matches should still get its configured
        // fallback, not just an empty result.
        $this->assertSame(['_NRF_' => 'No Results Found'], $results);
    }

    public function testSearchOntologySkipsEntriesWithNoCodeAndDefaultsMissingDisplayToCode(): void
    {
        $this->module->subSettings['site-category-list'] = [$this->category()];
        FakeHttpTransport::$response = json_encode([
            'expansion' => [
                'contains' => [
                    ['system' => 'http://example.test', 'display' => 'Has Everything'],
                    ['code' => 'C1', 'system' => 'http://example.test'],
                ],
            ],
        ]);

        $results = $this->module->searchOntology('test-cat', 'term', 20);

        // The entry with no 'code' at all must be skipped, not templated in as "|system".
        $this->assertCount(1, $results);
        $this->assertArrayHasKey('C1', $results);
        // Missing 'display' falls back to the code, per the null-safety fix.
        $this->assertSame('C1', $results['C1']);
    }

    public function testSearchOntologyFiltersBannedCodes(): void
    {
        $this->module->subSettings['site-category-list'] = [$this->category(['banned-codes' => "C1\nC2"])];
        FakeHttpTransport::$response = json_encode([
            'expansion' => [
                'contains' => [
                    ['code' => 'C1', 'system' => 'sys', 'display' => 'Banned'],
                    ['code' => 'C3', 'system' => 'sys', 'display' => 'Allowed'],
                ],
            ],
        ]);

        $results = $this->module->searchOntology('test-cat', 'term', 20);

        $this->assertArrayNotHasKey('C1', $results);
        $this->assertArrayHasKey('C3', $results);
    }

    public function testSearchOntologyAddsPriorityMaxFetchToTheRequestedCount(): void
    {
        $this->module->subSettings['site-category-list'] = [$this->category(['priority-max-fetch' => '5'])];
        FakeHttpTransport::$response = json_encode(['expansion' => ['contains' => []]]);

        $this->module->searchOntology('test-cat', 'term', 20);

        $this->assertCount(1, FakeHttpTransport::$calls);
        parse_str(parse_url(FakeHttpTransport::$calls[0]['url'], PHP_URL_QUERY), $query);
        $this->assertSame('25', $query['count'], 'result_limit (20) + priority-max-fetch (5) should be requested');
    }

    public function testSearchOntologyUrlRequestSendsCountNotUnderscoreCount(): void
    {
        // $expand is a FHIR *operation*, not a plain resource search - its count
        // parameter is 'count', not '_count' (the REST search-result modifier
        // used by plain searches). Confirmed live: Ontoserver silently ignores
        // '_count' here rather than rejecting the request, so it has no effect.
        $this->module->subSettings['site-category-list'] = [$this->category()];
        FakeHttpTransport::$response = json_encode(['expansion' => ['contains' => []]]);

        $this->module->searchOntology('test-cat', 'term', 20);

        parse_str(parse_url(FakeHttpTransport::$calls[0]['url'], PHP_URL_QUERY), $query);
        $this->assertArrayHasKey('count', $query);
        $this->assertArrayNotHasKey('_count', $query);
    }

    public function testSearchOntologyJsonRequestSendsCountNotUnderscoreCount(): void
    {
        $this->module->subSettings['site-category-list'] = [$this->category([
            'valueset-type' => 'resource',
            'valueset' => json_encode(['resourceType' => 'ValueSet']),
        ])];
        FakeHttpTransport::$response = json_encode(['expansion' => ['contains' => []]]);

        $this->module->searchOntology('test-cat', 'term', 20);

        $sentParams = json_decode(FakeHttpTransport::$calls[0]['params'], true);
        $names = array_column($sentParams['parameter'], 'name');
        $this->assertContains('count', $names);
        $this->assertNotContains('_count', $names);
    }

    // --- return-all ---
    // Without this option, an entry matching none of the search words is
    // dropped entirely by the FHIR server's own filter - fine for a large
    // list, but for a short, fully-enumerated one (e.g. a frequency scale) it
    // means a user must already know a value's exact wording to find it at
    // all. With it set, the filter is omitted from the request (fetching the
    // full/default expansion instead) and matches are ranked locally.

    public function testSearchOntologyReturnAllOmitsFilterFromUrlRequest(): void
    {
        $this->module->subSettings['site-category-list'] = [$this->category(['return-all' => true])];
        FakeHttpTransport::$response = json_encode(['expansion' => ['contains' => []]]);

        $this->module->searchOntology('test-cat', 'term', 20);

        parse_str(parse_url(FakeHttpTransport::$calls[0]['url'], PHP_URL_QUERY), $query);
        $this->assertArrayNotHasKey('filter', $query);
    }

    public function testSearchOntologyReturnAllOmitsFilterFromJsonRequest(): void
    {
        $this->module->subSettings['site-category-list'] = [$this->category([
            'return-all' => true,
            'valueset-type' => 'resource',
            'valueset' => json_encode(['resourceType' => 'ValueSet']),
        ])];
        FakeHttpTransport::$response = json_encode(['expansion' => ['contains' => []]]);

        $this->module->searchOntology('test-cat', 'term', 20);

        $sentParams = json_decode(FakeHttpTransport::$calls[0]['params'], true);
        $names = array_column($sentParams['parameter'], 'name');
        $this->assertNotContains('filter', $names);
    }

    public function testSearchOntologyWithoutReturnAllStillSendsFilter(): void
    {
        $this->module->subSettings['site-category-list'] = [$this->category()];
        FakeHttpTransport::$response = json_encode(['expansion' => ['contains' => []]]);

        $this->module->searchOntology('test-cat', 'term', 20);

        parse_str(parse_url(FakeHttpTransport::$calls[0]['url'], PHP_URL_QUERY), $query);
        $this->assertSame('term', $query['filter']);
    }

    public function testSearchOntologyWithoutReturnAllPreservesServerOrderEvenForNonSubstringMatches(): void
    {
        // Regression: the match-key ranking must only apply when return-all is
        // set. The real FHIR server's filter doesn't do literal substring
        // matching (e.g. filter=heart attack legitimately returns "Myocardial
        // infarction", its real synonym, with no literal "heart"/"attack" in
        // the display) - applying stripos()-based ranking unconditionally
        // would demote a legitimately-relevant server match behind a less
        // relevant one that merely contains the literal substring, silently
        // degrading the server's own relevance ranking for every normal
        // search, not just return-all's.
        $this->module->subSettings['site-category-list'] = [$this->category()];
        FakeHttpTransport::$response = json_encode([
            'expansion' => [
                'contains' => [
                    ['code' => 'C1', 'system' => 'sys', 'display' => 'Myocardial infarction'],
                    ['code' => 'C2', 'system' => 'sys', 'display' => 'Fear of heart attack'],
                ],
            ],
        ]);

        $results = $this->module->searchOntology('test-cat', 'heart attack', 20);

        // Server-returned order must be preserved - C1 first, despite not
        // containing the literal search term anywhere.
        $this->assertSame(['C1', 'C2'], array_keys($results));
    }

    public function testSearchOntologyReturnAllRanksMatchesFirst(): void
    {
        $this->module->subSettings['site-category-list'] = [$this->category(['return-all' => true])];
        FakeHttpTransport::$response = json_encode([
            'expansion' => [
                'contains' => [
                    ['code' => 'C1', 'system' => 'sys', 'display' => 'Never'],
                    ['code' => 'C2', 'system' => 'sys', 'display' => 'Rarely'],
                    ['code' => 'C3', 'system' => 'sys', 'display' => 'Weekly'],
                ],
            ],
        ]);

        $results = $this->module->searchOntology('test-cat', 'week', 20);

        // 'week' only matches "Weekly" - it must sort first despite not
        // being the first entry the server returned, with the rest keeping
        // their original relative order after it.
        $this->assertSame(['C3', 'C1', 'C2'], array_keys($results));
    }

    public function testSearchOntologyReturnAllStillPrioritizesPriorityCodesFirst(): void
    {
        $this->module->subSettings['site-category-list'] = [$this->category([
            'return-all' => true,
            'priority-codes' => 'C2',
        ])];
        FakeHttpTransport::$response = json_encode([
            'expansion' => [
                'contains' => [
                    ['code' => 'C1', 'system' => 'sys', 'display' => 'Weekly'],
                    ['code' => 'C2', 'system' => 'sys', 'display' => 'Monthly'],
                ],
            ],
        ]);

        // Search term matches C1's display ("Weekly") but C2 is the priority code.
        $results = $this->module->searchOntology('test-cat', 'week', 20);

        // Priority (C2) must still sort first, even though it doesn't match the term.
        $this->assertSame(['C2', 'C1'], array_keys($results));
    }

    public function testSearchOntologyThreadsConfiguredTimeoutIntoTheHttpCall(): void
    {
        $this->module->systemSettings['fhir-timeout'] = '7';
        $this->module->subSettings['site-category-list'] = [$this->category()];
        FakeHttpTransport::$response = json_encode(['expansion' => ['contains' => []]]);

        $this->module->searchOntology('test-cat', 'term', 20);

        $this->assertCount(1, FakeHttpTransport::$calls);
        $this->assertSame(7, FakeHttpTransport::$calls[0]['timeout']);
    }

    // --- true end-to-end request timeout ---
    // REDCap core's http_get()/http_post() only ever set curl's *connect*
    // timeout (confirmed by reading Config/init_functions.php) - a server that
    // accepts the connection and then stalls could still hold a web server
    // process open indefinitely. This module now makes its own curl calls
    // instead of delegating to core's, specifically to also set CURLOPT_TIMEOUT.

    public function testHttpGetSetsBothConnectAndTotalTimeout(): void
    {
        $this->module->systemSettings['fhir-timeout'] = '7';
        FakeHttpTransport::$response = 'ok';

        $this->module->httpGet('https://example.test/fhir/metadata', ['User-Agent: Redcap'], 'https://example.test/fhir');

        $this->assertCount(1, FakeHttpTransport::$calls);
        $this->assertSame(7, FakeHttpTransport::$calls[0]['timeout'], 'connect timeout');
        $this->assertSame(7, FakeHttpTransport::$calls[0]['total_timeout'], 'the new end-to-end timeout');
    }

    public function testHttpPostSetsBothConnectAndTotalTimeout(): void
    {
        $this->module->systemSettings['fhir-timeout'] = '7';
        FakeHttpTransport::$response = 'ok';

        $this->module->httpPost(
            'https://example.test/fhir/ValueSet/$expand',
            '{}',
            'application/json',
            ['User-Agent: Redcap'],
            'https://example.test/fhir'
        );

        $this->assertCount(1, FakeHttpTransport::$calls);
        $this->assertSame(7, FakeHttpTransport::$calls[0]['timeout'], 'connect timeout');
        $this->assertSame(7, FakeHttpTransport::$calls[0]['total_timeout'], 'the new end-to-end timeout');
    }

    public function testHttpPostSendsContentTypeHeaderAlongsideCustomHeaders(): void
    {
        // Regression: REDCap core's own http_post() sets CURLOPT_HTTPHEADER for
        // the content-type header, then - if custom headers are also present -
        // overwrites it entirely with just those (curl_setopt() replaces, not
        // merges), silently dropping the content-type header. This module's
        // curlPostWithTotalTimeout() builds the header list once instead.
        FakeHttpTransport::$response = 'ok';

        $this->module->httpPost(
            'https://example.test/fhir/ValueSet/$expand',
            '{"resourceType":"Parameters"}',
            'application/json',
            ['User-Agent: Redcap'],
            'https://example.test/fhir'
        );

        $sentHeaders = FakeHttpTransport::$calls[0]['headers'];
        $this->assertContains('Content-Type: application/json', $sentHeaders);
        $this->assertContains('User-Agent: Redcap', $sentHeaders);
    }

    // --- circuit breaker / origin-scoping ---
    // Ported from redcap_fhir_ontology_provider, which received this hardening in
    // an earlier security audit that this sibling module never got - ported here
    // per-category (isCircuitOpen($category) etc.) rather than site-wide, since
    // this module lets each category point at a completely different FHIR
    // server: one category's dead server must not fail-fast every other
    // category's healthy one. FhirRequestPolicyTest covers the underlying pure
    // logic exhaustively; these confirm it's actually wired into this module.

    public function testCircuitClosedByDefault(): void
    {
        $this->assertFalse($this->module->isCircuitOpen('test-cat'));
    }

    public function testCircuitOpensAfterThreeFailuresForThatCategoryOnly(): void
    {
        $this->module->recordFhirFailure('test-cat');
        $this->module->recordFhirFailure('test-cat');
        $this->assertFalse($this->module->isCircuitOpen('test-cat'), 'two failures must not open the breaker');
        $this->module->recordFhirFailure('test-cat');
        $this->assertTrue($this->module->isCircuitOpen('test-cat'), 'three failures must open the breaker');

        // A different category's own breaker must be unaffected - one dead FHIR
        // server must not fail-fast every other category's healthy one.
        $this->assertFalse($this->module->isCircuitOpen('other-cat'));
    }

    public function testRecordFhirFailureIfSlowIgnoresFastFailures(): void
    {
        $this->module->systemSettings['fhir-timeout'] = '10';
        // 0.2s against a 10s timeout is nowhere near the 80% slow-call threshold.
        $this->module->recordFhirFailureIfSlow('test-cat', 0.2);
        $this->module->recordFhirFailureIfSlow('test-cat', 0.2);
        $this->module->recordFhirFailureIfSlow('test-cat', 0.2);
        $this->assertFalse($this->module->isCircuitOpen('test-cat'), 'fast (e.g. 4xx) failures must never trip the breaker');
    }

    public function testRecordFhirFailureIfSlowCountsSlowFailures(): void
    {
        $this->module->systemSettings['fhir-timeout'] = '10';
        $this->module->recordFhirFailureIfSlow('test-cat', 9.0);
        $this->module->recordFhirFailureIfSlow('test-cat', 9.0);
        $this->module->recordFhirFailureIfSlow('test-cat', 9.0);
        $this->assertTrue($this->module->isCircuitOpen('test-cat'));
    }

    public function testRecordFhirSuccessClearsFailureCount(): void
    {
        $this->module->recordFhirFailure('test-cat');
        $this->module->recordFhirFailure('test-cat');
        $this->module->recordFhirSuccess('test-cat');
        $this->module->recordFhirFailure('test-cat');
        $this->module->recordFhirFailure('test-cat');
        $this->assertFalse($this->module->isCircuitOpen('test-cat'), 'a success must reset the count, not just add to it');
    }

    public function testSearchOntologyFailsFastWithoutCallingHttpWhenBreakerOpen(): void
    {
        $this->module->recordFhirFailure('test-cat');
        $this->module->recordFhirFailure('test-cat');
        $this->module->recordFhirFailure('test-cat');
        $this->module->subSettings['site-category-list'] = [$this->category()];

        $results = $this->module->searchOntology('test-cat', 'term', 20);

        $this->assertSame([], $results);
        $this->assertCount(0, FakeHttpTransport::$calls, 'an open breaker must fail fast without dialing out');
    }

    public function testSearchOntologyDoesNotReturnNoResultFallbackWhenBreakerOpen(): void
    {
        // An open breaker is a fetch failure, not a genuine "no matches" result -
        // it must not be presented as one.
        $this->module->recordFhirFailure('test-cat');
        $this->module->recordFhirFailure('test-cat');
        $this->module->recordFhirFailure('test-cat');
        $this->module->subSettings['site-category-list'] = [$this->category([
            'return-no-result' => true,
            'no-result-label' => 'No Results Found',
            'no-result-code' => '_NRF_',
        ])];

        $results = $this->module->searchOntology('test-cat', 'term', 20);

        $this->assertSame([], $results);
    }

    public function testHttpGetRefusesUrlOutsideConfiguredBase(): void
    {
        FakeHttpTransport::$response = 'should never be reached';

        $result = $this->module->httpGet('https://evil.example.test/steal', ['User-Agent: Redcap'], 'https://example.test/fhir');

        $this->assertFalse($result);
        $this->assertCount(0, FakeHttpTransport::$calls, 'a disallowed URL must never reach the transport');
    }

    // --- getHideChoice() / getFieldAnnotation() ---
    // Regression coverage: an earlier version of getFieldAnnotation() (a) never
    // pulled $Proj in via `global $Proj;` at all, so the in-memory fast path
    // could never run (every request fell through to a full dictionary reload,
    // or silently found nothing if $_GET['pid'] wasn't set), and (b) read the
    // wrong key even when it did - $Proj->metadata[$field] stores the
    // annotation under the raw DB column name 'misc', not 'field_annotation'
    // (that name only exists in getDataDictionary()'s own returned array).
    // Confirmed live: @HIDECHOICE had silently never worked from a real
    // request despite being saved correctly, because of exactly this.

    public function testGetHideChoiceUsesInMemoryProjectMetadataFastPath(): void
    {
        $project = new \Project();
        $project->project_id = '17';
        $project->metadata['my_field']['misc'] = "@HIDECHOICE='A,B'";
        $GLOBALS['Proj'] = $project;
        $_GET['field'] = 'my_field';
        $_GET['pid'] = '17';

        $hidden = $this->module->getHideChoice();

        $this->assertSame(['A', 'B'], $hidden);
        $this->assertSame(0, \REDCap::$getDataDictionaryCallCount, 'the in-memory fast path must not fall through to getDataDictionary()');
    }

    public function testGetHideChoiceFallsBackToDataDictionaryWhenProjMismatchesRequestedPid(): void
    {
        $project = new \Project();
        $project->project_id = '17'; // a different project than requested
        $project->metadata['my_field']['misc'] = "@HIDECHOICE='WRONG'";
        $GLOBALS['Proj'] = $project;
        \REDCap::$dataDictionary = ['my_field' => ['field_annotation' => "@HIDECHOICE='A'"]];
        $_GET['field'] = 'my_field';
        $_GET['pid'] = '99';

        $hidden = $this->module->getHideChoice();

        $this->assertSame(['A'], $hidden);
        $this->assertSame(1, \REDCap::$getDataDictionaryCallCount);
    }

    public function testGetHideChoiceReturnsEmptyWhenNoFieldRequested(): void
    {
        $this->assertSame([], $this->module->getHideChoice());
    }

    // --- @ADVANCED-FHIR-ONTOLOGY-HIDECHOICE ---
    // A second, non-colliding tag name for the same purpose as @HIDECHOICE -
    // @HIDECHOICE is also REDCap's own built-in action tag (for a different
    // purpose, on real choice fields), so a module tag reusing that name can
    // never be registered in REDCap's own "@ Action Tags" popup.

    public function testGetHideChoiceRecognizesAdvancedFhirOntologyHideChoiceTag(): void
    {
        $project = new \Project();
        $project->metadata['my_field']['misc'] = "@ADVANCED-FHIR-ONTOLOGY-HIDECHOICE='X,Y'";
        $GLOBALS['Proj'] = $project;
        $_GET['field'] = 'my_field';

        $hidden = $this->module->getHideChoice();

        $this->assertSame(['X', 'Y'], $hidden);
    }

    public function testGetHideChoiceMergesBothTagNamesWhenBothPresent(): void
    {
        $project = new \Project();
        $project->metadata['my_field']['misc'] = "@HIDECHOICE='A' @ADVANCED-FHIR-ONTOLOGY-HIDECHOICE='B'";
        $GLOBALS['Proj'] = $project;
        $_GET['field'] = 'my_field';

        $hidden = $this->module->getHideChoice();

        $this->assertSame(['A', 'B'], $hidden);
    }

    // --- getOnlineDesignerSection() ---
    // Regression coverage for the js/online-designer.js extraction: this
    // method's heredoc was never exercised by any test before, so a broken
    // interpolation or an inline <script> creeping back in would only have
    // been caught by a manual browser check.

    public function testOnlineDesignerSectionLoadsExtractedJsFileNotInlineScript(): void
    {
        $html = $this->module->getOnlineDesignerSection();

        $this->assertStringContainsString('<script src="FAKE_MODULE_URL/js/online-designer.js"></script>', $html);
        $this->assertStringNotContainsString('function ADVFHIR_ontology_changed', $html);
    }

    public function testOnlineDesignerSectionListsConfiguredCategories(): void
    {
        $this->module->subSettings['site-category-list'] = [$this->category(['ontology-id' => 'cat1', 'ontology-name' => 'Category One'])];

        $html = $this->module->getOnlineDesignerSection();

        $this->assertStringContainsString("<option value='cat1'>Category One</option>", $html);
    }
}
