# Advanced FHIR Ontology External Module

The sections below give the full story behind each version's changes - why, not just what. For a terser,
automatically generated commit-by-commit record, see [CHANGELOG.md](./CHANGELOG.md).

## Requirements

- PHP 8.0.0 or later
- REDCap 8.8.1 or later, on External Module framework version 16 or later

As part of release 8.8.1 of REDCap an extension point was added to allow external modules to become an 
*'Ontology Provider'*. These act like the existing BioPortal ontology mechanism, but allow alternative sources.
The main function of an ontology provider is to take a search term and return some match of code + display.
You can see more information on implementing an Ontology Provider at the [Simple Ontology Provider](https://github.com/aehrc/redcap_simple_ontology_provider) external module home.

This module is a specialised version of the [Fhir Ontology Autocomplete Module](https://github.com/aehrc/redcap_fhir_ontology_provider) which allows for some advanced behaviour.
It allows:
 - The use of a custom ValueSet resource when making a search request.
 - Access to different FHIR servers for different ontologies.
 - The ability to ban certain codes, so they are never returned.
 - The ability to mark certain codes as priority, so they will appear earlier on the search results.
 - A template for defining the code and display used by the ontology.
 - The ability to browse a small, fully-enumerated ValueSet's full contents instead of requiring the typed text to
   match the server's wording.

The original FHIR ontology external module provides code for searching for FHIR valuesets, mapping a single ontology
to the valueset url. This allows for easier finding and setting of a valueset to use, but most other settings are defined
in the modules system settings. All ontologies use the same fhir server, they all have the same no result settings and
all have a code which is a concatenation of the FHIR code, display and system. This is because some FHIR valusets contain
codes from more then one code system, and a code + system is required to uniquely identify the codes.

In this plugin, all settings are controlled by the site administrator, each ontology that is available must be fully
defined in the system settings, with no 'helper' mechanisms to search for a valueset (hence the advanced label).

### Version 0.4 changes

This is a security and performance release, carried over from the same audit applied to the
[Fhir Ontology Autocomplete Module](https://github.com/aehrc/redcap_fhir_ontology_provider). There are no new features.

- ***Requests to the FHIR server now time out*** - A new site-wide `FHIR request timeout (seconds)` setting (default 10)
  bounds how long REDCap waits for any configured FHIR server or OAuth2 token endpoint. Previously there was no limit, so
  a slow or restarting server could hold web server processes open and make all of REDCap unresponsive.
- ***Fixed OAuth2 client-credentials authentication crashing on PHP 8*** - `getClientCredentialsToken()` built its session
  cache keys with the `+` operator instead of `.` (string concatenation), which throws a fatal error on PHP 8. Any
  ontology category using `OAuth2 Client Credentials` authentication would fail outright. Fixed to use `.`.
- ***Fixed OAuth2 token expiry calculation*** - Token lifetimes were treated as milliseconds rather than seconds, so an
  expired token could be reused for weeks, causing lookups to fail silently. Tokens are now also renewed slightly before
  their real expiry rather than exactly at it.
- ***Credential fields now masked in the configuration page*** - The OAuth2 Client Secret and Basic Auth User Password
  fields now render masked instead of in plain text. **This is display masking only** - it does not encrypt the value at
  rest; the External Modules framework still stores these as plain text. The stored value does **not** migrate: after
  upgrading, re-enter the client secret / password for each ontology category, or lookups will fail silently (an empty
  result list with no error shown).
- ***More robust error handling*** - Responses that are not valid JSON, and expansions missing `code`, `display`, or
  `system`, are now handled explicitly instead of producing PHP warnings or storing a malformed code.

**Deploying:** place this version in a new directory `modules/advanced_fhir_ontology_v0.4` alongside the existing
version rather than overwriting it, and re-enter any OAuth2 Client Secret / Basic Auth User Password values after
switching to it.

## Release History
- ***0.1*** - Initial Release (Apr 26, 2022)
- ***0.2*** - Add @HIDECHOICE support, Bug Fixes (Sep 6, 2022)
- ***0.3*** - Add support for Basic Auth, Add support for display language parameter.
- ***0.4*** - Security and performance release: FHIR request timeout, OAuth2 PHP 8 crash fix, OAuth2 token expiry fix,
  credential masking, more robust error handling.

## Using the module
The module code needs to be placed in a directory `modules/advanced_fhir_ontology_v0.4`

The module should then show up as an external module.

The module has one site-wide setting, ***FHIR request timeout (seconds)***, applied to every configured ontology
category - see Version 0.4 changes above.

The module settings defines a list of site ontologies. There is a `+` button for adding additional ontologies.

For each ontology the following settings are available.
  * ***Ontology ID*** - The internal ID to use for the ontology, this needs to be unique and is used as the `category`
    stored against a field using this ontology. The value should be alpha-numeric, spaces are allowed but not advised.
  * ***Ontology Name*** - The name of the ontology as presented in the Designer when selecting the ontology for a field.
  * ***Code Template*** - The template to use to generate the code for a value from the ontology. The code is what is
    stored in the REDCap database for the value of the field. The display associated with the code is stored in a lookup
    table, which is read when the value is display to the user or placed into a report. The code template used 
    in the original FHIR ontology provider is `"${CODE}|${DISPLAY}|${SYSTEM}"`. The simpliest template would be `"${CODE}"`.
    The template could be used to add a prefix/postfix such as `"SCT_${CODE}"`. The template will replace the following 
    strings with the values returned from the FHIR terminology server.
      * ***${CODE}*** - The code for the value. 
      * ***${SYSTEM}*** - The code system `system` url. Some FHIR value sets are composed of values from multiple code systems,
        making the code + system required for a unique coding. If the valueset being used only contains values from a single
        code system, the system could be removed from the template.
      * ***${DISPLAY}*** - The dislay for the value.
  * ***Display Template*** - The template to use to generate the display for a value from the ontology. The code is what is
    stored in the REDCap database for the value of the field. The display associated with the code is stored in a lookup
    table, which is read when the value is display to the user or placed into a report. The display template used
    in the original FHIR ontology provider is `"${DISPLAY}"`. The template will replace the following
    strings with the values returned from the FHIR terminology server.
      * ***${CODE}*** - The code for the value.
      * ***${SYSTEM}*** - The code system `system` url. Some FHIR value sets are composed of values from multiple code systems,
        making the code + system required for a unique coding. If the valueset being used only contains values from a single
        code system, the system could be removed from the template.
      * ***${DISPLAY}*** - The dislay for the value.
  * ***Return all values regardless of search text*** - Browse a short, fully-enumerated ValueSet without needing to
    guess its exact wording: instead of sending the typed text to the FHIR server as a `filter`, the field's search
    fetches the ValueSet's full/default expansion and ranks entries locally, matching ones first. Intended only for
    small ValueSets - it fetches the entire expansion on every search keystroke, so setting it on a large ValueSet
    (SNOMED CT, etc.) would be slow and wasteful. See
    [Return all values regardless of search text](#return-all-values-regardless-of-search-text) below for the full
    explanation, including its interaction with `Return 'No Results Found'`.
  * ***Return 'No Results Found'*** - A flag to indicate if a special 'No Result Found' should be returned if the search
    finds no matches. If this is selected then the `"No Result Label"` and `"No Result Code"` settings are required. A
    no result found code is useful to create branching logic to allow for the entry of a free text value if no associated
    code is found.
  * ***No Results Label*** - The display value for the special value returned if the `return no results found` option is
    enabled. The Label cannot contain html markup.
  * ***No Results Code*** - The value for the special value returned if the `return no results found` option is enabled.
    The code cannot contain html markup, a single or double quote.
  * ***FHIR API URL*** - The URL for the FHIR terminology server. A trailing `/` should not be included. For example
    `https://r4.ontoserver.csiro.au/fhir` which an Australian server with the Australian edition of SNOMED CT as its 
    default. The server also contains LOINC and other code systems. The following tool can be used to explore a FHIR
    terminology server: ***[Shrimp](https://ontoserver.csiro.au/shrimp)***
  * ***Display Language*** - Use alternative display language when making requests to the FHIR server. The value will be
    used in ValueSet/$expand operations. Supplied value should match BCP-47 standard. For example 'en' for english, 'es'
    for spanish. Leave blank to use default language of FHIR server.
  * ***Authentication Type*** - The authentication to use when communicating with the FHIR server. This can be either `none`,
    `OAuth2 Client Credentials` or `Basic Auth`. The client credentials flow uses a client id and secret to obtain an
    access token. 
  * ***OAuth2 token endpoint*** - The token endpoint used to obtain the access token. This is required for 
    `Oauth2 Client Credentials` authentication type. Required when `Authentication Type` is `OAuth2 Client Credentials`.
  * ***OAuth2 Client Id*** - The client id to use to fetch an access token. This is required for 
    `Oauth2 Client Credentials` authentication type. Required when `Authentication Type` is `OAuth2 Client Credentials`.
  * ***OAuth2 Client Secret*** - The client secret to use to fetch an access token. This is required for 
    `Oauth2 Client Credentials` authentication type. Required when `Authentication Type` is `OAuth2 Client Credentials`.
  * ***Basic Auth User Id*** - The user id to pass in an `Authorization: Basic` header when making requests to the FHIR
    server. Required when `Authentication Type` is `Basic Auth`.
  * ***Basic Auth User Password*** - The user password to pass in an `Authorization: Basic` header when making requests
    to the FHIR server. Required when `Authentication Type` is `Basic Auth`.
  * ***ValueSet Type*** - What form the valueset field takes, this can either be `ValueSet URL` in which case the 
    ValueSet setting is the url of the valueset to use. Alternatively the type can be `ValueSet Resource (JSON)` in 
    which case the ValueSet setting should be a JSON representation of the ValueSet Resource. Using a ValueSet resource
    allows the valueset not to exist on the server, but instead be constructed on demand.
  * ***ValueSet*** - Based on the ValueSet Type, this is either the url for the valueset (including implicit valuset urls
    e.g. http://snomed.info/sct?fhir_vs=refset/30513011000036104) or a ValueSet resource json.
  * ***Priority Codes*** - A new line separated list of codes which should be displayed higher in the return set of codes.
  * ***Priority Max Fetch*** - The number of additional codes to return from the FHIR server to allow the priority codes
    to be in the return set. Normally the first 20 matches are returned from FHR Server, if the Max Fetch is set to 5 then
    25 matches are returned from the FHIR server. This is meant to allow the priority codes to appear in the list so they
    can be moved up the list.
  * ***Banned Codes*** - A new line separated list of codes which will be excluded from return results.


### ValueSet


#### Implicit ValueSet

Implicit value sets are those whose specification can be predicted based on the grammar of the underlying code system, 
and the known structure of the URL that identifies them. Both SNOMED CT and LOINC define implicit value sets. 

LOINC defines implicit value set for answer lists and take the form `http://loinc.org/vs/[answerListId]`

For example `http://loinc.org/vs/LL2495-1` will give answers for `Mental Status - NSRAS`.
A possible sequence for finding an answer set id, is to first find the question of interest at loinc.org, then find the
normative answer list with the code description. 

e.g
search loinc.org for  `alcohol consumption`
Find the question `How often do you have 6 or more drinks on 1 occasion` at https://loinc.org/68520-6/
The page contains the Normative Answer List with a code of LL2181-7, and lists all the possible answers and their codes.
We would then use the ValueSet URL of `http://loinc.org/vs/LL2181-7`

SNOMED CT has three common sets of implicit value sets defined: By Subsumption, By Reference Set and Expression Constraint.

A SNOMED CT implicit value set URL has two parts:
- the base URL is either "http://snomed.info/sct", or the URI for the edition version, in the format specified by the IHTSDO the SNOMED CT URI Specification
- a query portion that specifies the scope of the content

"http://snomed.info/sct" should be understood to mean an unspecified edition/version. This defines an incomplete value set whose actual membership will depend on the particular edition used when it is expanded. If no version or edition is specified, the terminology service SHALL use the latest version available for its default edition (or the international edition, if no other edition is the default).

The default terminology service for this module, `https://r4.ontoserver.csiro.au/fhir` is an Australian server and has the Australian edition of SNOMEDCT as its default.

To define an edition and version the url is `http://snomed.info/sct/<edition>/version/<version>`. To get the latest version of an edition then `http://snomed.info/sct/<edition>` is used.

A list of known editions can be found at https://confluence.ihtsdotools.org/display/DOC/List+of+SNOMED+CT+Edition+URIs

For the second part of the URL (the query part), the 4 possible values are:
- *?fhir_vs* - all Concept IDs in the edition/version. If the base URI is http://snomed.info/sct, this means all possible SNOMED CT concepts
- *?fhir_vs=isa/[sctid]* - all concept IDs that are subsumed by the specified Concept.
- *?fhir_vs=refset* - all concept ids that correspond to real references sets defined in the specified SNOMED CT edition
- *?fhir_vs=refset/[sctid]* - all concept IDs in the specified reference set
- *?fhir_vs=ecl/[ecl]* - all concept ids that match the supplied (URI-encoded) expression constraint

To find out more about the expression language see here [SNOMED CT Expression Constraint Language](http://snomed.org/ecl)


Again lets use an example.
We want a valueset that has the snomed code for the type of cancer.
Using shrimp we see that the base concept 363346000 - Malignant neoplastic disease, has children that represent malignant
tumours, so a possible valueset url would be `http://snomed.info/sct?fhir_vs=isa/363346000`

Alternatively there is a reference set for `Neoplasm and/or hamartoma` which is 32570371000036100 giving a url of
`http://snomed.info/sct?fhir_vs=refset/32570371000036100`

If we want to restrict the codes to only those which involve the lung we could go to the shrimp ecl editor and come up
with a query that looks like this

`< 363346000|Malignant neoplastic disease| : {
363698007|Finding site| = << 39607008|Lung structure|
}`

Which translates to find concepts which are decendants of `363346000|Malignant neoplastic disease|` and also contain
a `363698007|Finding site|` equal to `39607008|Lung structure|` or one of its descendants. i.e. cancer found in the lungs.

With ecl, the names of concepts found inside '|' symbols can be removed, leaving us with a url of 
`http://snomed.info/sct?fhir_vs=ecl/<363346000:363698007=<<39607008`

Technically the original FHIR ontology autocomplete module can already do both reference set and isa value sets and
includes a mechanism for searching for them. It doesn't allow for ecl based urls. 

#### ValueSet Resource

A ValueSet resource is normally defined with a set of codes or with a collection of rules specifying how ValueSet is
formed. For SNOMED CT these rules will look similar to implicit valueset, for example include all descendants of the
small intestines body structure but exclude descendents of the terminal ileum body structure.

```json
{
  "resourceType" : "ValueSet",
  "compose" : {
    "include" : [ {
      "system" : "http://snomed.info/sct",
      "filter" : [ {
        "property" : "concept",
        "op" : "is-a",
        "value" : "30315005"
      } ]
    }],
    "exclude" : [ {
      "system" : "http://snomed.info/sct",
      "filter" : [ {
        "property" : "concept",
        "op" : "is-a",
        "value" : "85774003"
      } ]
    }]
  }
}
```

Here is the resource that is used by the original FHIR ontology autocomplete module to look for loinc answer sets:

```json
{
  "resourceType" : "ValueSet",
  "compose" : {
    "include" : [ {
      "system" : "http://loinc.org",
      "filter" : [ {
        "property" : "parent",
        "op" : "=",
        "value" : "LL"
      } ]
    }]
  }
}
```


Finally using a resource allows for extensions supported by the FHIR server to be used. Consider the following ValueSet

```json
{
   "resourceType" : "ValueSet",
   "compose" : {
      "include" : [ {
         "system" : "http://snomed.info/sct",
         "filter" : [ {
            "property" : "concept",
            "op" : "is-a",
            "value" : "30560011000036108"
         } ],
         "extension" : [ {
            "url" : "http://ontoserver.csiro.au/profiles/boost",
            "valueDecimal" : 0.01
         } ]
      }, {
         "system" : "http://snomed.info/sct",
         "filter" : [ {
            "property" : "concept",
            "op" : "is-a",
            "value" : "30404011000036106"
         } ],
         "extension" : [ {
            "url" : "http://ontoserver.csiro.au/profiles/boost",
            "valueDecimal" : 10.01
         } ]
      }, {
         "valueSet": [
            "http://snomed.info/sct?fhir_vs=refset/30513011000036104"
         ],
         "extension" : [ {
            "url" : "http://ontoserver.csiro.au/profiles/boost",
            "valueDecimal" : 5.0
         } ]
      } ]
   }
}
```

It includes the use of an extension that weights values in different sets to be returned with higher importance. In the
examples the ValueSet includes codes which are childen of `30560011000036108 |trade product|` and 
`30404011000036106 |trade product pack|` as well as codes from the '30513011000036104|medicinal product pack|' reference
set. With weightings of 0.01, 10.01 and 5.0 respectively, making trade product pack codes of highest importance.


### @HIDECHOICE Support
As part of the 0.2 release extra functionality has been added to this module for it to consider the `@HIDECHOICE`
action tag. This action tag is available for choice fields to indicate a choice should not be shown. This
can be achieved at a global level in this module by using the banned codes settings. The
@HIDECHOICE action tag however is specified at a field level. So the value will only be hidden for the field the
action tag is specified for. The set of values to hide is defined using a comma separated list of code for the
values which should be hidden. The module considers all @HIDECHOICE entries found in the annotations property of the
field.
```text
@HIDECHOICE='code1,code2'
```

**Fixed: `@HIDECHOICE` was silently ignored on every real autocomplete search.** The in-memory fast path never
pulled REDCap's `$Proj` in via `global $Proj;` at all, so it could never actually run - every request either fell
through to a full `getDataDictionary()` reload, or silently found nothing if the request didn't carry a project id
either. Even if it had run, it read the field's annotation from `$Proj->metadata[$field]['field_annotation']`, but
REDCap's real in-memory project metadata stores it under the raw DB column name `misc` - `field_annotation` is a
key name that only exists in `REDCap::getDataDictionary()`'s own returned array. `@HIDECHOICE` had been broken
since it was introduced in 0.2; it only ever appeared to work in this module's own test suite, whose fakes made
the same `field_annotation` mistake.

**Added: `@ADVANCED-FHIR-ONTOLOGY-HIDECHOICE`, a second tag name for the same purpose.** `@HIDECHOICE` is also
REDCap's own built-in action tag (for a different purpose, on real choice fields), and a module-provided action
tag whose name collides with a built-in one is silently dropped from REDCap's own "@ Action Tags" popup rather
than shown - so this module's repurposing of `@HIDECHOICE` could never be documented there.
`@ADVANCED-FHIR-ONTOLOGY-HIDECHOICE` is a new, non-colliding tag name recognized for exactly the same purpose,
registered in that popup; both names are supported and can be freely mixed on the same field:
```text
@ADVANCED-FHIR-ONTOLOGY-HIDECHOICE='code1,code2'
```

**Piping is not supported, and not currently possible, in `@HIDECHOICE`'s or `@ADVANCED-FHIR-ONTOLOGY-HIDECHOICE`'s
argument** (e.g. `@HIDECHOICE='[other_field]'` to hide a code chosen by another field's answer). REDCap core's own
built-in `@HIDECHOICE` resolves piping in its argument via `Piping::replaceVariablesInLabel($text, $record,
$event_id, $instance, ...)`, which needs to know which record is currently being edited. This module's field-level
tags are both read from inside `DataEntry/web_service_auto_suggest.php` - the same real endpoint every search on
the field hits - and that endpoint's request never carries a record, event, or instance identifier at all; REDCap
core's own front-end JS only ever sends `term`, `field`, and `pid` to it. There is no record context available to
pipe against from here, regardless of how this module parses the tag, so this isn't a missing feature so much as a
limitation of the integration point itself - it would only become possible if a future REDCap version started
including record context in that request.

### Return all values regardless of search text
Every autocomplete search sends the typed text to the FHIR server as a `filter`, so nothing appears unless the
typed text happens to textually match the server's `display` wording for an entry. For a small, fully-enumerated
ValueSet (a handful of values, e.g. a frequency-of-use scale), this makes it hard to actually browse the options.
Checking **Return all values regardless of search text** for a category removes that requirement: the field's
search instead fetches the ValueSet's full/default expansion (the `filter` parameter is omitted entirely) and
ranks entries locally - anything whose code or display matches the typed text sorts first (after priority codes,
which still take precedence), everything else follows. **This option is intended for small ValueSets only.** It
fetches the entire expansion on every search keystroke rather than a filtered subset, so setting it on a large
ValueSet (SNOMED CT, etc.) would be slow and wasteful. Checking it also means `Return 'No Results Found'` above
will never actually trigger, as long as at least one non-banned, non-hidden value is configured for the category
(unlike a plain filtered search, this always has something to show).

### Fixed: JSON/resource ValueSet searches could return far more results than requested
For a category with `ValueSet Type` set to `ValueSet Resource (JSON)`, the `ValueSet/$expand` request sent
`_count` to limit how many entries the FHIR server returns. `$expand` is a FHIR *operation*, not a plain resource
search, so its count parameter is `count` as defined by its own OperationDefinition; `_count` is the REST
search-result modifier used by plain searches, and the configured FHIR server silently ignored it here rather
than rejecting the request - confirmed live against a real, broad SNOMED CT category configured this way, which
previously returned far more entries than requested. Fixed to send `count`; the URL-based `ValueSet Type` already
used the correct parameter name.

### Added: circuit breaker, outbound-request origin scoping, and a true end-to-end request timeout
This module's own 0.4 release ("carried over from the same audit applied to the Fhir Ontology Autocomplete Module")
picked up that sibling module's request-timeout, OAuth2, and credential-masking fixes, but not further hardening
changes that module received in a later security audit - this closes that gap.

- ***Circuit breaker for terminology server failures*** - After 3 consecutive *slow* failures for a given category
  (calls that consume most of the timeout before failing), that category's own requests stop hitting its FHIR
  server for 60 seconds and return no results immediately, then let a trial request through to check for
  recovery. A fast failure (e.g. a quick 4xx response) neither counts towards this nor resets the count. Scoped
  per category, not site-wide: unlike `redcap_fhir_ontology_provider`'s single site-wide FHIR server, each category
  here can point at a completely different server, so one category's dead server must not fail-fast every other
  category's healthy one. As with that module's own breaker, this is a best-effort stampede guard (the read and
  write are separate round trips with no lock between them), not a precise counter or a guard against a request
  that never returns at all - PHP's own execution time limit kills that case first, before it's ever recorded.
- ***Outbound FHIR requests are now constrained to their category's configured server*** - Every URL this module
  builds before sending a request is checked against that category's own `FHIR API URL` (or, for an OAuth2 token
  request, the category's own token endpoint): it must address the same origin and sit at or below its path. A
  request that would fall outside that (for example, one built from a malformed or hostile setting) is refused
  rather than sent. See `FhirRequestPolicy::isWithinBase()` for exactly what this does and does not cover - it is
  not a general SSRF guard (no DNS resolution, and it doesn't see redirects this module's own curl calls may still
  follow after this check passes, since `CURLOPT_FOLLOWLOCATION` is on).
- ***`FHIR request timeout (seconds)` now bounds the entire request, not just connecting*** - Confirmed by reading
  REDCap core's `Config/init_functions.php`: `http_get()`/`http_post()` only ever set curl's connect timeout, never
  its total-time timeout, so a server that accepted the connection and then stalled could still hold a web server
  process open indefinitely. This module now makes its own curl calls instead of delegating to those core helpers,
  deliberately kept close to their existing option sets, with `CURLOPT_TIMEOUT` added on top. The `file_get_contents`
  fallback used when curl is unavailable already had a true end-to-end limit and is unaffected.
- ***Known limitation, not addressed here: TLS certificate verification is disabled on every outbound HTTPS
  request*** (`CURLOPT_SSL_VERIFYPEER` is off in `curlGetWithTotalTimeout()`/`curlPostWithTotalTimeout()`). This is
  inherited unchanged from REDCap core's own `http_get()`/`http_post()`, which this module's curl calls
  deliberately mirror - confirmed identical in `Config/init_functions.php`. A network-position attacker able to
  intercept the connection to a configured FHIR server or OAuth2 token endpoint can present any certificate and
  read or alter the request/response, including the OAuth2 client secret and bearer token. Left as-is rather than
  fixed unilaterally in this module alone: enabling verification here would diverge from core's own default and
  could break legitimate deployments using an internal CA or self-signed certificate for an internal-network FHIR
  server, without a corresponding opt-out setting to fall back on.

### Fixed: OAuth2 token cache could be shared across categories with different credentials
`getClientCredentialsToken()` caches the fetched access token in `$_SESSION`, keyed by the token endpoint. Since
each category can configure its own `cc-client-id`/`cc-client-secret`, two categories sharing the same token
endpoint but different credentials would have the second one silently reuse the first one's cached token instead
of authenticating as itself - a real cross-category authorization mix-up if the two clients have different scopes
or permissions, not just a caching inefficiency. The cache key is now derived from the token endpoint *and* the
client ID together.

### FHIR Display Language Support
As part of the 0.3 release an extra configuration option `Display Language` has been added. If this is provided it will
be passed as a parameter to calls to the ValueSet/$expand operation. This parameter specifies the language to be used 
for description in the expansions. According to the FHIR specification this should follow BCP-47 standard, where a 2
letter country abbreviation is used, with extra specificity used given using a dash and code. For example `en` is 
english, while `en-AU` would specify the Australian version of english. How the FHIR server will respond to this
parameter may be different depending on the server. If the language is not available it may just return what it has,
or it may return no display value, just codes and system.

This setting can be used with the SnomedCT hosted fhir server https://snowstorm-fhir.snomedtools.org/fhir/
but it is important to specify the edition of snomed to use. Setting a display language of 'es' and using a
valueset url of http://snomed.info/sct/449081005?fhir_vs will show values in Spanish. But the default edition
on the server is 900000000000207008 (International Edition) and at the time of writing does not return terms in
Spanish with the display language set to 'es'. Instead terms are returned with no display and likely will not work
with the external module. Some of the editions available can be found here: 
https://confluence.ihtsdotools.org/display/DOCEXTPG/4.4.2+Edition+URI+Examples

For the best result test the url with the display language using postman or a similar tool before setting the values in
the configuration of this module.