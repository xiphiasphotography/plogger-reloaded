# Synthetic upgrade fixture provenance

All records and configuration values are generated test data. No photographs,
credentials, production dump or existing site configuration are included.

`fork-mysql.sql` and `fork-pgsql.sql` snapshot the inherited installer definitions
also present in the adjacent plogger_php7/php8 trees, before Phase 4 adds version
tracking. The fork schemas are identical; separate source manifests/configuration
examples keep the two upgrade paths explicit. Snapshot files are independent
test inputs, not read from the current installer during tests.

`rc1-mysql.sql` models the audited pre-CDN structural profile. It is not an
authenticated dump from an original RC1 release. Official archive/source lookup
was unsuccessful during this task; replace/compare this fixture only after an
authoritative source is obtained and reviewed. `rc1-pgsql.sql` is a synthetic
logical projection for exercising the common migration code: RC1 was MySQL-only.

The literal `*-config.php` files are parsed as data, never included/executed.
Tests substitute localhost high ports and generated database names. SQL uses a
validated `{prefix}` placeholder and prepared statements; records are supplied
by `upgrade-seed.php`. Tests intentionally exercise a 32-character password-field
variant, old MD5, preserved dates/text/IDs, all core relationships and comments,
interrupted tracking DDL, concurrency, backup gates and reruns.

`UpgradeDatabaseTest` refuses unnamed drivers/default ports, creates only
`plogger_phase4_ci_<random>` databases and cleans up its own names. HTTP fixtures
use their existing `plogger_phase1_ci_<random>` isolation guard and now drop their
own database on teardown. Set `PLOGGER_TEST_UPGRADE_FROM=rc1|php7|php8` to run the
complete existing HTTP suite against a seeded/upgraded installation. Without
that variable, the same suite exercises a fresh installation.
