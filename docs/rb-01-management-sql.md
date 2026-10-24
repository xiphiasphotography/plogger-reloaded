# RB-01: bound management IDs and whole-selection validation

Status on 2026-10-22: **implementation corrected; native MySQL/MariaDB and
PostgreSQL verification pending. RB-01 is not marked resolved.**

This patch is limited to management deletion/move input and related queries.
It makes no schema/configuration change and does not migrate or edit production
data. Existing function names/signatures, authorization and CSRF guards remain.

## Corrections

- `delete_album()` validates its ID before any query, binds the album lookup,
  child-picture lookup and DELETE values through the existing PDO executor.
- `delete_collection()`, `delete_picture()`, `move_album()` and
  `move_picture()` reject malformed source/destination IDs before reads, writes
  or file operations. Their direct value-bearing queries and thumbnail resets
  now use bound parameters/shared prepared update helpers.
- Shared `plogger_db_id()` accepts positive integer values or decimal digit
  strings within PHP_INT_MAX; leading zeroes normalize. Zero/negative IDs,
  whitespace, suffixes, SQL-looking strings, exponent/decimal notation,
  arrays, booleans, floats, null and overflowing strings are rejected.
- `plogger_db_id_list()` validates a complete plain list before returning any
  IDs and removes duplicates. A malformed later item cannot cause an earlier
  valid item to be deleted first. Empty selections keep the normal message.
- The management endpoint preflights single-delete/comment IDs, the whole bulk
  selection and move destination before performing even a single-comment write.
  Selection values and write flags come from the protected POST body.
  Invalid input returns HTTP 400 instead of silently coercing it to a valid ID.
- Direct function calls receive the existing error-array shape for invalid IDs.
  Valid, missing IDs return ordinary not-found errors; missing move sources or
  destinations stop before path operations.

No atomicity guarantee for valid batches was added: unrelated filesystem or
database failures during legitimate operations retain the inherited behavior.
This change validates ID shape before processing, not a transactional bulk job.

## Neighboring query review

| Path | Result |
| --- | --- |
| Album deletion | Lookup, child selection and row deletion bind IDs |
| Collection deletion | Collection/child album lookup and deletion bind IDs |
| Picture deletion | Picture/comment DELETEs and thumbnail resets bind values |
| Album moves | Source/destination/child-photo lookups bind IDs; updates use the prepared helper |
| Picture moves | Destination lookup and thumbnail resets bind values; path/parent updates use the prepared helper |
| Single-comment deletion | Existing bound query retained; ID now strictly validated before writes |
| Existing get_picture_by_id() | Already binds its lookup value |
| Existing get_album_by_id()/get_collection_by_id() | Internally interpolate integer-cast lookup IDs; these call sites now supply validated integers, so arbitrary input cannot reach their SQL syntax |

The last two read helpers were reviewed, not globally refactored. This is not
a claim that the entire application has completed prepared-query conversion.
Table prefixes still pass the existing identifier validator. No driver-specific
SQL or changed column/table definitions were introduced. Positional value
binding and the existing MySQL ANSI identifier mode/PostgreSQL quoting remain.

## Regression evidence

Before implementation, the new test failed because a malformed album ID reached
a PDO guard; its prepare method threw before any statement/SQL/filesystem action.
The complete-list helper test also failed because the helper did not exist.
No unsafe deletion was executed to reproduce the failure.

`tests/ManagementSelectionTest.php` now verifies:

- All five entry functions reject malformed values, including bad move
  destinations, without even preparing a database statement.
- List validation rejects nested/associative arrays and mixed valid/invalid IDs.
- An isolated SQLite fixture rejects an injection value, then deletes only the
  selected empty album using bound statements. An unselected album row,
  directory and synthetic sentinel retain their values/bytes.
  SQLite is a local regression fixture, **not native-engine compatibility proof**.

`tests/HttpCompatibilityTest.php` adds authenticated, valid-CSRF requests for
single and mixed selections, scalar/nested/associative selections, malformed
move destinations and combined comment/bulk submissions. Invalid requests must
return 400 with no write-trace entry, unchanged original/thumbnail directory and
file hashes, and unchanged native table snapshots when native mode is enabled.

The additional native-only positive test creates four disposable empty albums,
deletes one singly and two in a batch (including a duplicate ID), and verifies
the unselected album/sentinel and seeded original remain unchanged. It is part
of the existing MySQL/MariaDB and PostgreSQL HTTP CI matrices, including runs
after the supported fixture upgrades. It was not executed locally.

## Checks actually executed

| Check | Result |
| --- | --- |
| Full PHPUnit, PHP 8.2.34 | 115 tests, 2,774 assertions, ten skips, passed |
| Full PHPUnit, PHP 8.3.35 | 115 tests, 2,774 assertions, ten skips, passed |
| Full PHPUnit, PHP 8.4.26 | 115 tests, 2,774 assertions, ten skips, passed |
| Focused RB-01 unit/HTTP run, PHP 8.4 | Four tests, 653 assertions, passed |
| RB-01 unit class, PHP 8.5.11 | Three tests, 127 assertions, passed |
| Full PHPUnit, PHP 8.5.11 | 115 tests, 2,769 assertions; two errors, one failure, two warnings, ten deprecation reports and ten skips; exit 2 |
| Full strict lint, PHP 8.4 | 188 PHP files, zero failures |
| Changed PHP files, PHP 8.2/8.3 | All five passed syntax checks |
| Changed production files/new unit class, PHP 8.5 | Passed syntax checks; pre-existing HTTP harness deprecations remain under RB-02 |
| Tooling/security PHPStan level 6, PHP 8.4 | Passed |
| Full application PHPStan level 6 | Still 663 findings; exit 1, not addressed under RB-01 |
| Native Chrome/static nginx regression fixtures | Two tests, 118 assertions, passed |
| CRLF-aware diff check | Passed |

The full suite includes the historical XSS/CSRF, passwords, upload/ZIP,
metadata/import, thumbnails/downloads, CDN, theme API and disabled-module tests.
PHP 8.5 failures remain the previously observed RB-02 diagnostics in
phpThumb/security/HTTP and GIF handling; error reporting was not suppressed.

Ten default skips are eight native database cases plus Chrome/nginx.
The latter two were run explicitly on disposable localhost fixtures with approved
process execution outside the restrictive Windows sandbox; no browser sandbox
was disabled. Native server binaries and Composer remain unavailable on PATH.
No production/default-port database was contacted. PHP 8.6 was not run.

Commands used with the explicit local PHP version:

```text
php -d extension=pdo_sqlite vendor/phpunit/phpunit/phpunit --do-not-cache-result
php -d extension=pdo_sqlite vendor/phpunit/phpunit/phpunit --filter "ManagementSelectionTest|testManagementRejects" --do-not-cache-result
php vendor/phpunit/phpunit/phpunit tests/ManagementSelectionTest.php --do-not-cache-result
php tools/lint.php
php -l FILE
php vendor/phpstan/phpstan/phpstan analyse --no-progress
php vendor/phpstan/phpstan/phpstan analyse --configuration=phpstan.security.neon --memory-limit=512M --no-progress
php vendor/phpstan/phpstan/phpstan analyse --configuration=phpstan.application.neon --memory-limit=512M --no-progress --error-format=json
git -c core.whitespace=cr-at-eol diff --check
```

The full/targeted PHP 8.5 runs used the audit's temporary PHPRC enabling required
extensions; global/production configuration was untouched.

## Closing RB-01

Run the existing container-free native database CI or its documented local
equivalent on dedicated localhost high ports, using generated fixture databases.
Confirm both new HTTP tests pass on both engines, including after fixture
upgrades, with valid/invalid tokens and unchanged unselected rows/files.
The fixture's nativeConnection()/installer guards must remain enabled.

Only attach passing native results and mark RB-01 resolved after that evidence
exists. The release stays NO-GO; RB-02 through RB-08 remain outside this patch.
No release ZIP, commit, push, schema migration or production change was made.

## Native verification completed, 2026-10-23/24

The missing native evidence now exists: MariaDB 10.6.26 and PostgreSQL 17.11
pass the full patched PHP matrix and all three post-upgrade HTTP datasets,
including rejection before mutation and positive selected-album deletion.
See [results and commands](rb05-08-validation.md). RB-01 is resolved; this does
not close original-schema provenance, licensing or other release blockers.
