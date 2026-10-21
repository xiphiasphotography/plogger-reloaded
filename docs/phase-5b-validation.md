# Phase 5b validation — 2026-10-18

Scope: optional ordering infrastructure, generic photo actions/album fields,
original dimension persistence, additive schema migration 0004 and resumable
dimension backfill. No Phase 6 work, production migration, photo replacement or
live provider request was performed. Oypo activation remains blocked on the
maintainer's current integration code/identifiers and exact pixel policy.

## Local environment and checks

Windows PowerShell; explicitly selected `C:/php/8.4.5/php.exe` because the default
PATH PHP is 8.5.11, outside the supported matrix. A temporary PHPRC file enabled
the installed mbstring, GD, EXIF, fileinfo, PDO MySQL/PostgreSQL/SQLite, ZipArchive
and OpenSSL extensions; no extensions or development packages were downloaded.
Composer was not on PATH; the existing vendor tools were invoked directly.

Commands (using that PHP executable and temporary PHPRC):

```text
php vendor/phpunit/phpunit/phpunit --display-skipped
php tools/lint.php
php vendor/phpstan/phpstan/phpstan analyse --no-progress
php vendor/phpstan/phpstan/phpstan analyse --configuration=phpstan.security.neon --memory-limit=512M --no-progress
php vendor/phpstan/phpstan/phpstan analyse --configuration=phpstan.application.neon --memory-limit=512M --no-progress --error-format=json
php tools/theme-check.php plog-content/themes/default
php tools/theme-check.php plog-content/themes/air
php tools/theme-check.php plog-content/themes/lucid
php tools/theme-check.php plog-content/themes/xip-bs
git -c core.whitespace=cr-at-eol diff --check
```

The whitespace check accepts the repository's inherited CRLF endings instead of
rewriting large upstream files. Plain `git diff --check` was also inspected;
its trailing-whitespace reports concern CR at those inherited line endings.

## Coverage and limits

- Unit tests exercise eligibility boundaries for both rules, missing/invalid
  configuration, album ID validation/clearing, encoded path segments, rejected
  URL schemes/credentials/control characters and escaped labels/attributes.
- HTTP fixtures exercise enabled non-live ordering output, disabled behavior,
  optional album fields, invalid submitted IDs, forged backfill CSRF, direct
  module access refusal and the safe maintenance post-login destination.
- Upload/import fixtures assert saved original dimensions. SQLite-backed batch
  tests cover preview, cursor continuation, repeat execution, failed files,
  traversal refusal and original byte hashes. A SQLite metadata double exercises
  idempotent migration decisions, preserved existing values and private column
  conflicts for both driver metadata paths; it is not native database coverage.
- Existing native RC1/php7/php8 fixture tests now cover the v3-to-v4 step,
  interruption after DDL and nullable new fields while preserving old values.
  Those native tests were not executable locally without an isolated database.
- All four bundled theme scans report no syntax errors and recognize the new
  hook. Approximation warnings remain for translation/security helpers supplied
  by includes (`plog_tr`, `plogger_escape`, `plogger_script_json`). Existing HTTP
  theme rendering and public API signature checks remain in the suite.

Eight local skips require native nginx or an isolated native database: static
nginx behavior, persistent password reset, exact database values, native album
CSRF, web/CLI upgrade persistence and the three source upgrade datasets. Neither
native database driver, PHP 8.1/8.2/8.3, the hosted CI matrix, actual Oypo, private
dgp2026 nor nginx/PHP-FPM staging performance was verified in this task.

Final results: PHPUnit **105 tests, 1,888 assertions, eight skips, no failures**;
strict lint **171 PHP files, zero syntax failures**; tooling and security PHPStan
**level 6, zero errors**; all four theme scans **zero errors**; the CRLF-aware
diff check **passed**. The full application PHPStan report remains nonblocking
with **664 unresolved findings** (zero general errors, 664 file findings); it is
not a clean whole-application analysis. New module/backfill files have no
findings in that report. No suppressions or generated baseline were added.

Before activation: verify an external backup and staging restore, inspect any
private Oypo fields, apply the reviewed schema plan, preview/apply dimension
batches, supply and review the real URL adapter/pixel rule, and verify the
private theme. No production configuration, data or deployment was changed.

## Revalidation — 2026-10-19

The requested implementation was already present in commit `5b8379b`. Review
confirmed the optional module, generic action hook, explicit configuration,
additive migration and bounded dimension backfill; no duplicate implementation
or additional schema change was needed. Ordering still requires both a reviewed
URL adapter and an explicit positive pixel policy. No provider URL or production
threshold was supplied or invented.

Using the existing temporary local PHP installations (8.1.34, 8.2.34, 8.3.35 and
8.4.26), ran the full PHPUnit suite separately on each version:

```text
php -d extension=pdo_sqlite vendor/phpunit/phpunit/phpunit --do-not-cache-result
```

Each run passed: **105 tests, 1,888 assertions, eight skips**. SQLite was enabled
only for isolated synthetic backfill/migration tests; these do not establish
native MySQL/MariaDB or PostgreSQL DDL compatibility. The skips requiring native
database/nginx fixtures remain outstanding. No production configuration or
photographs were used.

Tooling and security PHPStan level 6 passed on PHP 8.4.26. All four bundled theme
scans reported zero errors and three existing approximation warnings each.
Syntax checks passed for all 171 PHP files on every supported PHP version.
The dimension CLI help command also passed without opening a database.
The earlier whole-application PHPStan findings remain unresolved; that optional
report was not rerun during this revalidation. Actual Oypo integration, the
private theme, native database upgrades and staging performance still need the
maintainer inputs and isolated staging checks listed above.
