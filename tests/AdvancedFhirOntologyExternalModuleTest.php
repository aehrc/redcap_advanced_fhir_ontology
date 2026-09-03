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
        $this->assertSame(AdvancedFhirOntologyExternalModule::DEFAULT_TIMEOUT, $this->module->getFhirTimeout());
    }

    public function testFhirTimeoutDefaultsWhenSettingNotNumeric(): void
    {
        $this->module->systemSettings['fhir-timeout'] = 'not-a-number';
        $this->assertSame(AdvancedFhirOntologyExternalModule::DEFAULT_TIMEOUT, $this->module->getFhirTimeout());
    }

    public function testFhirTimeoutDefaultsWhenSettingZeroOrNegative(): void
    {
        $this->module->systemSettings['fhir-timeout'] = '0';
        $this->assertSame(AdvancedFhirOntologyExternalModule::DEFAULT_TIMEOUT, $this->module->getFhirTimeout());

        $this->module->systemSettings['fhir-timeout'] = '-5';
        $this->assertSame(AdvancedFhirOntologyExternalModule::DEFAULT_TIMEOUT, $this->module->getFhirTimeout());
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
        // caching a 3600s token for ~41 days instead of ~1 hour.
        $expireKey = 'ADVFHIR_https://example.test/token_TOKEN_EXPIRES';
        $this->assertGreaterThanOrEqual($before + 3600 - 60, $_SESSION[$expireKey]);
        $this->assertLessThanOrEqual($after + 3600, $_SESSION[$expireKey]);
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

    public function testSearchOntologyThreadsConfiguredTimeoutIntoTheHttpCall(): void
    {
        $this->module->systemSettings['fhir-timeout'] = '7';
        $this->module->subSettings['site-category-list'] = [$this->category()];
        FakeHttpTransport::$response = json_encode(['expansion' => ['contains' => []]]);

        $this->module->searchOntology('test-cat', 'term', 20);

        $this->assertCount(1, FakeHttpTransport::$calls);
        $this->assertSame(7, FakeHttpTransport::$calls[0]['timeout']);
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
