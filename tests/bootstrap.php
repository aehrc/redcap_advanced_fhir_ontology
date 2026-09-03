<?php

// $_SESSION isn't initialized under the CLI SAPI PHPUnit runs under - the module's
// OAuth2 token caching reads/writes it directly.
$_SESSION = $_SESSION ?? [];

require_once __DIR__ . '/support/RedcapFakes.php';
require_once __DIR__ . '/../AdvancedFhirOntologyExternalModule.php';
