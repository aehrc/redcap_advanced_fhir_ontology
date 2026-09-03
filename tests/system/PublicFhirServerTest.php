<?php

namespace AEHRC\AdvancedFhirOntologyExternalModule;

use PHPUnit\Framework\TestCase;

/**
 * System tests: make real network calls to public FHIR terminology servers
 * already named in this module's README, verifying we can actually fetch and
 * parse real data from them - not just that our own logic is correct in
 * isolation (that's what the unit test suite covers).
 *
 * Deliberately excluded from the default `vendor/bin/phpunit` run (see
 * phpunit.system.xml) and run on a schedule rather than per-PR/push: a public
 * server being slow or briefly down is not this module's bug, and should never
 * block someone's unrelated change.
 *
 * Run this file via `vendor/bin/phpunit -c phpunit.system.xml` - NOT by path
 * (e.g. `vendor/bin/phpunit tests/system/PublicFhirServerTest.php`), which
 * silently falls back to phpunit.xml/tests/bootstrap.php and loads the unit
 * suite's fake HTTP transport instead of the real one, producing a confusing
 * "expected at least one result" failure even though the real server is fine.
 * setUp() below checks for this and fails with a clear message instead.
 *
 * fhir.loinc.org is not covered here - it requires real Basic Auth credentials
 * this suite doesn't have. Basic/OAuth2 auth-header code paths are therefore
 * also untested against a real secured server for the same reason - there's no
 * public FHIR terminology server with throwaway credentials to test against.
 * snowstorm-fhir.snomedtools.org is also not covered: confirmed by hand that it
 * 302-redirects every request (regardless of headers sent, including a
 * browser-like User-Agent) to a "denied.html?reason=browser" page - it blocks
 * automated/non-browser traffic, almost certainly by IP or TLS fingerprint
 * rather than anything in the request itself. That's a structural
 * incompatibility with CI, not a transient failure, so it would be a
 * permanently broken check rather than an occasionally-flaky one - not worth
 * attempting to work around.
 */
final class PublicFhirServerTest extends TestCase
{
    /** SNOMED CT "Clinical finding" - a broad, stable top-level concept guaranteed
     *  to exist and have descendants on any real SNOMED CT terminology server. */
    private const SNOMED_ISA_VALUESET = 'http://snomed.info/sct?fhir_vs=isa/404684003';

    /** Same concept, expressed as a ValueSet resource instead of an implicit
     *  isa URL - drives the POST/JSON $expand code path instead of GET. */
    private const SNOMED_ISA_RESOURCE = '{"resourceType":"ValueSet","compose":{"include":[{"system":"http://snomed.info/sct","filter":[{"property":"concept","op":"is-a","value":"404684003"}]}]}}';

    protected function setUp(): void
    {
        if (!function_exists(__NAMESPACE__ . '\\usingRealHttpTransport')) {
            $this->fail(
                'RealHttpFunctions.php was not loaded, so http_get()/http_post() are ' .
                'still the unit suite\'s fakes. Run this suite via ' .
                '`vendor/bin/phpunit -c phpunit.system.xml`, not by file path with ' .
                'the default config.'
            );
        }

        // Same process-wide singleton issue as the unit suite - reset before
        // constructing so this doesn't accumulate across tests/runs either.
        \OntologyManager::resetForTests();

        // Dormant today (both tests use authentication-type 'none'), but
        // getClientCredentialsToken() caches OAuth tokens into $_SESSION keyed
        // by token endpoint - without this, a token cached by one test would
        // leak into a later one the moment a 'cc'/'basic' test is added.
        $_SESSION = [];
    }

    private function category(string $fhirApiUrl, array $overrides = []): array
    {
        return array_merge([
            'ontology-id' => 'system-test',
            'ontology-name' => 'System Test',
            'code-template' => '${CODE}',
            'display-template' => '${DISPLAY}',
            'fhir-display-language' => '',
            'return-no-result' => false,
            'no-result-label' => '',
            'no-result-code' => '',
            'fhir-api-url' => $fhirApiUrl,
            'authentication-type' => 'none',
            'valueset-type' => 'url',
            'valueset' => self::SNOMED_ISA_VALUESET,
            'priority-codes' => '',
            'priority-max-fetch' => '',
            'banned-codes' => '',
        ], $overrides);
    }

    public function testCanFetchFromOntoserver(): void
    {
        $module = new AdvancedFhirOntologyExternalModule();
        $module->systemSettings['fhir-timeout'] = '15';
        $module->subSettings['site-category-list'] = [$this->category('https://tx.ontoserver.csiro.au/fhir')];

        $results = $module->searchOntology('system-test', '', 5);

        $this->assertNotEmpty($results, 'expected at least one result from a broad SNOMED CT isa expansion');
    }

    public function testCanFetchFromOntoserverViaPostWithResourceValueSet(): void
    {
        // Exercises the other half of searchOntology(): a JSON ValueSet resource
        // POSTed as a FHIR Parameters body, instead of an implicit valueset URL
        // fetched via GET. Needs a non-empty filter - the server rejects an
        // empty valueString in the Parameters body (unlike the GET path, where
        // an empty query param is just ignored).
        $module = new AdvancedFhirOntologyExternalModule();
        $module->systemSettings['fhir-timeout'] = '15';
        $module->subSettings['site-category-list'] = [$this->category('https://tx.ontoserver.csiro.au/fhir', [
            'valueset-type' => 'resource',
            'valueset' => self::SNOMED_ISA_RESOURCE,
        ])];

        $results = $module->searchOntology('system-test', 'heart', 5);

        $this->assertNotEmpty($results, 'expected at least one result from a POSTed SNOMED CT ValueSet resource');
    }
}
