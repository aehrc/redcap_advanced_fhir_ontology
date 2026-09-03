<?php

/**
 * Test-only fakes for the REDCap External Modules framework classes, with real (if
 * minimal) behavior - unlike stubs/redcap-em-framework.phpstub, which is
 * signatures-only for Psalm. Shared by both the unit and system test bootstraps.
 * These must never be loaded outside tests: they're deliberately simplistic
 * (in-memory arrays, no validation) and would be actively wrong as production code.
 *
 * Deliberately has no http_get()/http_post()/sameHostUrl() - those differ between
 * the unit tests (faked, see FakeHttpTransport.php) and the system tests (real,
 * see tests/system/RealHttpFunctions.php), so they live separately.
 */

namespace ExternalModules {
    abstract class AbstractExternalModule
    {
        /** @var array<string, mixed> */
        public array $systemSettings = [];

        /** @var array<string, list<array<string, mixed>>> */
        public array $subSettings = [];

        public function __construct() {}

        public function getSystemSetting($key)
        {
            return $this->systemSettings[$key] ?? null;
        }

        public function getSubSettings($key, $project_id = null)
        {
            return $this->subSettings[$key] ?? [];
        }
    }

    class ExternalModules {}
}

namespace {

    class REDCap
    {
        public static function escapeHtml($value)
        {
            return htmlspecialchars((string)$value, ENT_QUOTES);
        }

        public static function getDataDictionary($project_id, $format = 'array', $numeric = false, $fields = null, $forms = null)
        {
            return [];
        }
    }

    interface OntologyProvider
    {
        public function searchOntology($category, $search_term, $result_limit);
        public function getServicePrefix();
        public function getProviderName();
        public function getLabelForValue($category, $value);
        public function getOnlineDesignerSection();
    }

    class OntologyManager
    {
        private static ?OntologyManager $instance = null;

        /** @var list<object> */
        public array $providers = [];

        public static function getOntologyManager(): self
        {
            return self::$instance ??= new self();
        }

        /** Test-only: the real framework has no equivalent - this is a process-wide
         *  singleton, so without a reset every test's module registers into the
         *  same $providers list for the lifetime of the PHPUnit run. */
        public static function resetForTests(): void
        {
            self::$instance = null;
        }

        public function addProvider($provider): void
        {
            $this->providers[] = $provider;
        }
    }

    class Project
    {
        public $project_id;
        public $metadata = [];
    }
}
