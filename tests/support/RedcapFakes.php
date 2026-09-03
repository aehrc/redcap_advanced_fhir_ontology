<?php

/**
 * Test-only fakes for the REDCap External Modules framework, with real (if minimal)
 * behavior - unlike stubs/redcap-em-framework.phpstub, which is signatures-only for
 * Psalm. These must never be loaded outside tests: they're deliberately simplistic
 * (in-memory arrays, no validation) and would be actively wrong as production code.
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

namespace AEHRC\AdvancedFhirOntologyExternalModule {

    /**
     * Records every http_get()/http_post() call the module makes and returns a
     * configurable canned response - so a test can assert both what was sent
     * (e.g. the timeout actually threaded through) and control what comes back.
     */
    class FakeHttpTransport
    {
        /** @var list<array<string, mixed>> */
        public static array $calls = [];

        /** @var string|false */
        public static $response = false;

        public static function reset(): void
        {
            self::$calls = [];
            self::$response = false;
        }
    }

    // Namespaced function fallback: PHP looks for a function in the CURRENT
    // namespace before falling back to the global one, so defining these here
    // shadows the real http_get()/http_post()/sameHostUrl() for this module's
    // code specifically, without touching production code at all.

    function http_get($url, $timeout = null, $basic_auth_user_pass = '', $headers = [], $user_agent = null)
    {
        FakeHttpTransport::$calls[] = ['method' => 'GET', 'url' => $url, 'timeout' => $timeout, 'headers' => $headers];
        return FakeHttpTransport::$response;
    }

    function http_post($url, $params = [], $timeout = null, $content_type = 'application/x-www-form-urlencoded', $basic_auth_user_pass = '', $headers = [], &$info = null)
    {
        FakeHttpTransport::$calls[] = ['method' => 'POST', 'url' => $url, 'timeout' => $timeout, 'headers' => $headers, 'params' => $params];
        return FakeHttpTransport::$response;
    }

    function sameHostUrl($url)
    {
        return true;
    }
}
