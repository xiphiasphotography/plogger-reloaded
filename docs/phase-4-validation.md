# Phase 4 implementation and validation

Date: 2026-10-16. Branch: `phase-1-php-compatibility`. No production database,
configuration, original photograph, upload, thumbnail or custom theme was
modified. No commit, push, deployment or production migration was performed.

## Implemented

- Standalone literal-only config reader and shared PDO upgrade bootstrap;
  original-style absent driver/port constants default safely to MySQL/default
  port. No old PHP configuration is evaluated by the tools.
- Structural source-family detection, required columns/types/primary keys,
  current-schema-scoped metadata, prefix/identifier validation, index conflict
  checks, counts/orphans, original-path samples and active-theme static checks.
  Indistinguishable php7/php8 schemas are reported as a family.
- Three numbered additive migrations, config schema version 3, checksum/evidence
  ledger, database advisory locking, successful-step version recording,
  interruption recovery and no-op reruns. No table/column drop or photo rewrite
  is performed by the runner. Existing records, settings and hashes are retained.
- Shared fresh-install schema/version initialization; old/unversioned galleries
  receive HTTP 503 from the new gallery loader until upgrade completion.
- Read-only default/doctor CLI, mandatory reviewed plan hash and database plus
  originals/config backup/restore attestations before apply. Optional restrictive
  configuration proposal never overwrites the original file.
- Default-denied opt-in web tool with independent legacy-credential verification,
  throttling, hardened sessions, CSRF/origin checks, expiring authorization,
  session-bound reviewed plans, one step per request and authorization clearing
  on completion. Legacy MD5 is still upgraded by normal successful login after
  migration; no forced password reset or migration-time hash rewrite.
- Container-free CI migration fixtures and post-upgrade HTTP smoke jobs; read-only
  theme-check CLI; installation/upgrade/rollback instructions in UPGRADING.md.

## Verified paths and test results

Local PHP binaries: 8.1.34, 8.2.34, 8.3.35 and 8.4.26. Native servers: MariaDB
10.6.24 and PostgreSQL 16.15 on localhost high ports, with generated database
names and synthetic website/image fixtures only. MySQL Server was not separately
available; MySQL SQL/PDO support is implemented but the tested MySQL-family
server is MariaDB.

| Path/check | Executed result |
| --- | --- |
| Strict lint, every supported PHP | Pass: 159 PHP files, zero syntax failures |
| Full default PHPUnit, every supported PHP | Pass: 83 discovered tests, 1,428 assertions, 7 intentional native-only skips per run; no warnings/deprecations |
| Final upgrade/CLI fixtures: rc1 model, php7, php8; each driver × PHP 8.1–8.4 | Pass: 3 tests / 182 assertions per combination (24 source cases across 8 runs) |
| Fresh installer + native HTTP + source upgrade regressions, each driver × PHP 8.1–8.4 | Pass: initial combined matrix 28 tests / 855 assertions / 4 intentional test-double-only skips; final PHP 8.3 rerun on both drivers 28 tests / 861 assertions / 4 skips; final CLI additions also verified in the separate all-version upgrade matrix above |
| PHP 8.3 HTTP suite after each of rc1 model, php7 and php8 upgrades, each driver | Pass: 25 tests / 679 assertions / 4 intentional skips per run; fork PostgreSQL fixtures also rechecked with inherited index names |
| PHPStan tooling level 6 | Pass |
| PHPStan security, upgrade engine/config/schema/theme checker and both new entry points, level 6, no baseline | Pass |
| Full application PHPStan level 6 | 665 unresolved legacy findings; still an acceptance gap, no suppression baseline |
| Composer validate | Valid; inherited missing-license metadata warning remains pending provenance resolution |
| Whitespace, content/config scope and secret review | Pass: CRLF-aware diff check, no original/content/config changes or real secrets; fixture SQL exceptions limited to the fixture directory |

Native tokenizer absence produces an actionable error; a separate regression
checks that normalized migration checksums survive Git CRLF/LF conversions.
The native fixture tests run the actual CLI for dry-run, missing-plan rejection,
reviewed apply and protected configuration proposals. They compare exact
pre/post collection/album/photo/comment/thumbnail configuration records and
original config values, verify old password access and unchanged JPEG hashes,
exercise a short password-column variant, missing EXIF display setting,
interrupted tracking DDL, competing runner lock, stale plans, malformed backup
confirmations, version checkpoints, repeated apply and incomplete schema refusal.

HTTP checks cover standalone upgrade opt-in, unauthenticated/missing-CSRF/
cross-origin rejection, authenticated preview, missing backup rejection, every
web progress step, completion/session invalidation, CLI doctor/dry-run, and the
existing installer/gallery/admin/upload/FTP import/ZIP/thumbnail/cache/theme/
feed/security smoke coverage. Theme tests tokenize code without executing it.
The inherited 106 theme function signatures remain covered.

Default-suite native skips are deliberate: three earlier persistence scenarios,
three source upgrade cases and the standalone upgrade HTTP flow. Native HTTP
runs skip four synthetic PDO-double injection scenarios covered by the default
suite. A skipped scenario is not counted as a verified live database/browser case.

## Fixture provenance and remaining acceptance gates

The fork SQL definitions and inherited PostgreSQL secondary-index names were
checked against the adjacent php7/php8 installer source. Separate manifests and
literal configuration examples preserve both fork paths. All rows are synthetic.
The RC1 fixture models the audited pre-CDN profile; it is **not an authenticated
original release dump**. Source/archive lookup did not yield an authoritative
RC1 artifact. Its PostgreSQL variant is a synthetic logical projection because
original Plogger was MySQL-only; no cross-engine conversion is implemented.
See [fixture provenance](../tests/fixtures/upgrades/README.md).

Consequently Phase 4 **implementation is present, but full real-world acceptance
remains open** for authoritative RC1 provenance, unknown older/custom schemas,
actual GitHub Actions execution, nginx/Apache/PHP-FPM/Plesk/browser staging,
verified external backup/restore, representative production-size performance
and the private dgp2026 theme. The configured CI runs all upgrade HTTP sources
on all four PHP versions; locally post-upgrade full HTTP coverage was limited
to PHP 8.3 while migration/CLI tests and fresh HTTP ran on all four versions.

Legacy text EXIF-date columns, unsupported/missing fields or primary keys,
conflicting indexes, malformed/dynamic config, missing original samples,
future versions and changed migration history are refused rather than silently
coerced/repaired. Resolve those cases through an explicitly reviewed staging
conversion; no original date column is dropped. Config proposals have 0600
permissions; retain/review original ownership and permissions before any manual
replacement. Backup confirmations attest to operator verification, not an
automatically inspected off-site backup.

DDL/index creation can lock large tables or outlast an FPM request; the web tool
limits progress to one migration step and supports resuming but cannot guarantee
an arbitrary server's DDL timing. No backfill/regeneration batch runs in Phase 4.
The source tree still has separate Phase 2/static-analysis, staging and license
release blockers. No public-release readiness is claimed and Phase 5 is not begun.

Both owned fixture database servers were shut down after testing; connection
probes confirmed ports 33336 and 33337 were closed.

## Changed files

- Runtime: `plog-admin/_upgrade.php`, `plog-admin/upgrade-cli.php`,
  `plog-admin/includes/install-functions.php`, `plog-globals.php`,
  `plog-load-config.php`; new `plog-includes/plog-upgrade.php`,
  `plog-upgrade-config.php`, `plog-schema.php`, `plog-theme-check.php`.
- Migrations: `plog-admin/migrations/0001_tracking.php`,
  `0002_configuration.php`, `0003_finalize.php`.
- Tests: `tests/UpgradeConfigTest.php`, `UpgradeDatabaseTest.php`,
  `ThemeCheckTest.php`, `HttpCompatibilityTest.php`,
  `tests/fixtures/FixtureDatabase.php`, `native-install.php`, `upgrade-seed.php`;
  `tests/fixtures/upgrades/` contains rc1/php7/php8 JSON manifests and config
  examples, four independent SQL fixtures and README.md.
- Tooling/config: `tools/theme-check.php`, `.github/workflows/checks.yml`,
  `.gitignore`, `phpstan.neon`, `phpstan.security.neon`,
  `phpstan.application.neon`.
- Documentation: `UPGRADING.md`, `README.md`, `ROADMAP.md`,
  `docs/development.md`, `docs/deployment.md`, `docs/security.md`,
  `docs/server/apache-security.conf`, `docs/server/nginx-security.conf`, this file.
