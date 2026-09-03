# Changelog

## [0.4.0](https://github.com/aehrc/redcap_advanced_fhir_ontology/compare/v0.3.0...v0.4.0) (2026-09-03)


### Bug Fixes

* correct priority-max-fetch key and null-category crash; add PHPUnit tests ([#12](https://github.com/aehrc/redcap_advanced_fhir_ontology/issues/12)) ([1b6234d](https://github.com/aehrc/redcap_advanced_fhir_ontology/commit/1b6234de8ccc7b22e220652c329bc6dcaf4047b4))
* force v0.4.0 as the release version ([#10](https://github.com/aehrc/redcap_advanced_fhir_ontology/issues/10)) ([713ae6c](https://github.com/aehrc/redcap_advanced_fhir_ontology/commit/713ae6c51827f14c408a6696b45bf56eb49742be))
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
