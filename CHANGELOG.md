# Changelog

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
