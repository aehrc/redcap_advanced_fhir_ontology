# Changelog

## [1.0.0](https://github.com/aehrc/redcap_advanced_fhir_ontology/compare/v0.3.0...v1.0.0) (2026-09-10)


### ⚠ BREAKING CHANGES

* php-version-min is 8.0.0 (raised from 5.4.0 in PR #6, "chore: upgrade to EM framework version 16"). Sites running PHP older than 8.0 cannot install or enable this module version.

### Bug Fixes

* add circuit breaker, outbound-request origin scoping, and a true end-to-end request timeout ([#20](https://github.com/aehrc/redcap_advanced_fhir_ontology/issues/20)) ([231d5ed](https://github.com/aehrc/redcap_advanced_fhir_ontology/commit/231d5eda2318c08e06b038a8e7b42bae8a197a4d))
* correct priority-max-fetch key and null-category crash; add PHPUnit tests ([#12](https://github.com/aehrc/redcap_advanced_fhir_ontology/issues/12)) ([1b6234d](https://github.com/aehrc/redcap_advanced_fhir_ontology/commit/1b6234de8ccc7b22e220652c329bc6dcaf4047b4))
* document the PHP 8.0 minimum and flag it as the breaking change it was ([#15](https://github.com/aehrc/redcap_advanced_fhir_ontology/issues/15)) ([17da7b4](https://github.com/aehrc/redcap_advanced_fhir_ontology/commit/17da7b4691d034163b617033fbc6f3fc0c9689de))
* force v0.4.0 as the release version ([#10](https://github.com/aehrc/redcap_advanced_fhir_ontology/issues/10)) ([713ae6c](https://github.com/aehrc/redcap_advanced_fhir_ontology/commit/713ae6c51827f14c408a6696b45bf56eb49742be))
* force v1.0.0 as the release version ([#16](https://github.com/aehrc/redcap_advanced_fhir_ontology/issues/16)) ([9c75507](https://github.com/aehrc/redcap_advanced_fhir_ontology/commit/9c75507d510578d1da7da95591721901beeae2f0))
* force v1.0.0 as the release version, take 2 ([#17](https://github.com/aehrc/redcap_advanced_fhir_ontology/issues/17)) ([2cff50a](https://github.com/aehrc/redcap_advanced_fhir_ontology/commit/2cff50a0556e12fae26d63bb4af9d24d14e43c72))
* key the OAuth2 token cache by client ID, not just the token endpoint ([#21](https://github.com/aehrc/redcap_advanced_fhir_ontology/issues/21)) ([61ff826](https://github.com/aehrc/redcap_advanced_fhir_ontology/commit/61ff8260318af2d12fc15682daa2a910fd4dcf45))
* repair @HIDECHOICE, send correct $expand count param, add return-all ([#18](https://github.com/aehrc/redcap_advanced_fhir_ontology/issues/18)) ([190f683](https://github.com/aehrc/redcap_advanced_fhir_ontology/commit/190f683a5bd6493d10c8bac16efabc73750ff8b3))
* security and performance remediation ([#7](https://github.com/aehrc/redcap_advanced_fhir_ontology/issues/7)) ([cd67f4c](https://github.com/aehrc/redcap_advanced_fhir_ontology/commit/cd67f4c598d7881ebc4642b0a8abda27542c3014))

## [0.3] - 2023-07-07
- Add support for Basic Auth as an authentication type for the FHIR server
- Add support for a display language parameter, passed to `ValueSet/$expand`
- Add a Spanish translation
- Fix `json_last_error_msg()` usage requiring PHP 5.5 rather than the declared 5.4 minimum

## [0.2] - 2022-09-06
- Add basic `@HIDECHOICE` support (copied from the Simple Ontology Provider module)
- Add a `User-Agent` header, since some FHIR servers (e.g. SNOMED's) reject requests without one
- Work around an `http_post` bug where a custom header combined with a custom content type caused the content type to be silently overwritten

## [0.1] - 2022-04-26
- Initial release
