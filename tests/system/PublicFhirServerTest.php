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
 * fhir.loinc.org is not covered here - it requires real Basic Auth credentials
 * this suite doesn't have. snowstorm-fhir.snomedtools.org is also not covered:
 * confirmed by hand that it 302-redirects every request (regardless of headers
 * sent, including a browser-like User-Agent) to a "denied.html?reason=browser"
 * page - it blocks automated/non-browser traffic, almost certainly by IP or TLS
 * fingerprint rather than anything in the request itself. That's a structural
 * incompatibility with CI, not a transient failure, so it would be a permanently
 * broken check rather than an occasionally-flaky one - not worth attempting to
 * work around.
 */
final class PublicFhirServerTest extends TestCase
{
    /** SNOMED CT "Clinical finding" - a broad, stable top-level concept guaranteed
     *  to exist and have descendants on any real SNOMED CT terminology server. */
    private const SNOMED_ISA_VALUESET = 'http://snomed.info/sct?fhir_vs=isa/404684003';

    private function category(string $fhirApiUrl): array
    {
        return [
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
        ];
    }

    public function testCanFetchFromOntoserver(): void
    {
        $module = new AdvancedFhirOntologyExternalModule();
        $module->systemSettings['fhir-timeout'] = '15';
        $module->subSettings['site-category-list'] = [$this->category('https://tx.ontoserver.csiro.au/fhir')];

        $results = $module->searchOntology('system-test', '', 5);

        $this->assertNotEmpty($results, 'expected at least one result from a broad SNOMED CT isa expansion');
    }
}
