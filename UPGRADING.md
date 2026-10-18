# Installing and upgrading Plogger Reloaded

Use **PHP 8.1–8.4**; start with PHP 8.3 on a restricted staging domain. There is
no PHP 7.4 bridge. First move a php7-based staging copy to supported PHP, then
run these tools. Keep production offline from this procedure until a separately
approved release and staging acceptance. Git/Plesk deployment never runs migrations.

## Fresh installation

Enable PDO with the chosen MySQL/MariaDB or PostgreSQL driver, GD, fileinfo,
mbstring, EXIF, ZipArchive and sessions. Check CLI and FPM separately with
`php tools/check-extensions.php`. See [development](docs/development.md) and
[deployment](docs/deployment.md) for the traditional PHP/Plesk setup.
Use an empty, dedicated staging database and visit `plog-admin/_install.php`.
Preserve the generated private configuration. The installer uses the shared
schema definition and initializes schema version 4 and its migration ledger.
Restrict the installer again after setup. Runtime Composer/Node/Docker is not
required. Do not use the fresh installer to repair an existing gallery.

## Back up and establish a rollback point

1. Freeze writes and restrict the staging copy. Keep old gallery/admin processes
   from writing during inspection and migration. The new loader returns HTTP
   503 until its schema is upgraded; old deployed code cannot honor that guard.
2. Externally back up **all prefixed database tables**, including custom/plugin
   tables, and the original photographs, configuration, uploads, custom themes,
   plugins and current code revision. Retain the matching thumbnail tree if it
   is needed for rollback. Keep backups outside the served tree and repository.
3. Restore both backups to a separate disposable location and verify counts,
   relationships, credentials, representative original-file hashes, file
   ownership/permissions and the active theme. Record the backup identifiers,
   timestamp and matching code revision. A database dump alone is insufficient.
4. Confirm adequate temporary/disk space and database ALTER/CREATE/INSERT/UPDATE
   privileges. The read-only diagnostic verifies reads and reports disk space;
   it does not probe writes or guarantee privileges, free database storage or
   the duration of DDL on your server. Resolve index conflicts before proceeding.

The mandatory backup flags/checkboxes are an **operator attestation** of these
external checks. The tool cannot verify an off-site backup or prove a restore
was tested. Never use placeholder confirmations on a real installation.
Examples below name a staging configuration and illustrative backup identifiers;
replace them with the actual staging path and verified backup references.

## Common dry-run and apply workflow

The independent bootstrap reads literal `define()` configuration without
executing PHP. Native PHP tokenizer (normally enabled) is required for safe
configuration/theme inspection; no third-party parser is required at runtime.
It defaults missing original-style driver/port constants to
MySQL/default port. Existing constants, credentials, prefix and session path
remain intact. Dynamic configuration/includes/environment expressions are
refused, not executed; prepare and review a literal configuration on staging
first. No connection credentials or password hashes are printed in the report.

```text
php plog-admin/upgrade-cli.php --config=/srv/staging/plogger/plog-config.php --dry-run
php plog-admin/upgrade-cli.php --config=/srv/staging/plogger/plog-config.php --doctor
```

No apply flag means dry-run. Inspect detected schema family/version, pending
steps, column types, indexes/conflicts, counts/orphans, sampled original paths,
active-theme syntax/call warnings and free space. The diagnostic is not a full
filesystem audit, permission test, performance guarantee or theme certification.
Run `php tools/theme-check.php /srv/staging/plogger/plog-content/themes/THEME`
for a separate read-only theme report. Possible missing functions may come from
an include/plugin; review them manually. Syntax errors block applying.

Copy the dry-run's `plan_hash`. Applying requires that reviewed hash; changed
schema, configuration, theme code or counts invalidate it. Only after reviewing
the report and verifying the external backups:

```text
php plog-admin/upgrade-cli.php --config=/srv/staging/plogger/plog-config.php --apply --plan=REVIEWED_PLAN_HASH --backup-database=VERIFIED-DB-BACKUP-ID --backup-files=VERIFIED-FILES-BACKUP-ID --restore-tested --confirm --propose-config
```

Do not put passwords in these flags. CLI applies at most the three current
numbered steps. A database advisory lock excludes another runner for the same
database/schema/prefix. DDL is intentionally small and additive; missing-index
creation can still lock large tables. Measure DDL on a representative staging
copy; use the CLI/DBA maintenance window if web request limits are insufficient.
No photo/thumbnail regeneration, EXIF/dimension backfill or destructive table
conversion is part of Phase 4.

`--propose-config` creates `plog-config.php.proposed.php` with restrictive 0600
permissions. The original configuration, its credentials and permissions are
never replaced. Review the proposal offline; do not serve it as a download.
If desired, install it manually after preserving the original permissions,
ownership and backup. A different existing proposal is refused rather than
overwritten. PHP-generated string literals safely preserve quotes/backslashes.
The proposed file is ignored by Git and covered by the supplied server denials.

## From original Plogger / RC1

1. Copy the complete backed-up site to restricted staging on PHP 8.3. Preserve
   its original literal configuration; absent driver/port constants are normal.
2. Deploy reviewed Reloaded application code while preserving config, originals,
   thumbnails, uploads and custom theme/plugin directories. Run the common
   dry-run. A known pre-CDN schema is reported as `original/RC1-compatible`.
3. Review the additive CDN/EXIF settings and any password-column widening, apply
   with the backup gate, review the optional configuration proposal, then run
   the post-upgrade checks below.

Detection is structural, not a claim to know an exact historical release.
Unknown/incomplete tables, missing primary keys, unsupported types and legacy
EXIF-date **text columns** stop with a review requirement. No old date column is
dropped, guessed, coerced or silently cleared. Older/custom schemas outside the
verified structural profile require an explicit reviewed conversion on staging
before this runner will proceed. An authoritative original RC1 artifact remains
a fixture-provenance TODO; the current RC1 test is modeled from the audited
legacy layout and is labeled as such. No claim of all pre-RC1 versions is made.

## From plogger_php7

1. Back up, restore to a restricted test domain and switch the copy to PHP 8.3
   **before** running Reloaded. Keep any custom session save path.
2. Preserve `cdn_url`, passwords, album/photo paths and the private theme. Run
   the common dry-run; the PDO fork family is `php7/php8-compatible`.
3. Apply with verified-backup confirmation. Test existing administrator login:
   migration does not rewrite hashes; the existing successful-login mechanism
   transparently upgrades legacy MD5. Failed logins do not change credentials.

The private `dgp2026` theme and production-specific nginx rules are still needed
for first-site staging acceptance. Do not substitute a bundled-theme smoke test
for that acceptance.

## From plogger_php8

Use the same backed-up staging, dry-run, confirmation and verification sequence.
The php7 and php8 fork schemas are identical: the tool reports that fact rather
than inventing a fork-specific version from indistinguishable columns. Existing
CDN values and custom columns/tables are retained.

## PostgreSQL

The same runner uses native PDO PostgreSQL statements, current-schema-scoped
inspection, advisory locks and idempotent DDL. Prefixes are validated separately
from bound values. Original Plogger was MySQL-only: the RC1 PostgreSQL fixture is
an explicitly **synthetic logical projection**, not evidence of an original
PostgreSQL release or a cross-engine data conversion. Switching database engines
or search paths is outside this upgrader's scope.

## Optional web workflow

CLI is preferred. For a restricted staging copy only, temporarily add the
literal `define('PLOGGER_ENABLE_UPGRADE_WEB', true);` and a reviewed server-side
exception for `plog-admin/_upgrade.php`. Keep all other private/configuration,
upload, includes and migration-directory denials from [server examples](docs/server/).
The shipped default remains denied. Use HTTPS and the existing custom session
path. No public unauthenticated upgrade endpoint is enabled automatically.

Log in to the standalone upgrader using the existing administrator credentials.
It uses hardened session cookies, session-ID regeneration, a 15-minute upgrade
session and login throttling. Login and apply require session-bound CSRF tokens;
cross-origin/invalid-token requests fail before writes. GET never applies a step.
Inspect the dry-run, confirm both backups and the tested restore, then apply one
numbered step per request. Progress/version is persisted after each successful
step, and the next request requires a new review of the updated plan. Changed
schema/theme/configuration evidence invalidates an old preview. On completion
the upgrade authorization is cleared. Remove the opt-in constant, restore the
webserver denial and archive review evidence outside the webroot.

## Migrations, interruption and rollback

`plog-admin/migrations/0001_tracking.php` adds the config `schema_version` and
prefixed `schema_migrations` ledger. `0002_configuration.php` adds missing
`cdn_url`/`show_exif` and widens password storage only when shorter than 64
characters. `0003_finalize.php` validates compatibility and adds missing known
secondary indexes without replacing conflicting indexes. `0004_photograph_actions.php` adds nullable optional Oypo album IDs and original pixel dimensions on both drivers; it never backfills or modifies files. Existing Reloaded schema version 3 installations use the same reviewed backup-gated workflow to reach version 4. See [optional Oypo and dimension backfill](docs/oypo.md). Existing IDs, records,
sort settings, metadata, hashes, administrator identity and themes are preserved.

The ledger stores migration-file checksums, pre-step schema/count/relationship
evidence and hashed backup references. Versions are recorded only after the
step and integrity checks succeed. Applied migration files must remain immutable.
Missing/changed history or a future version is refused. Rerunning a completed
upgrade does no DDL or data changes. An interrupted additive step can be resumed:
existing columns/indexes are checked before creation; an inserted ledger row
without a version advance is checked before completing the checkpoint.

MySQL/MariaDB DDL auto-commits and inherited MyISAM tables do not provide reliable
transaction rollback. PostgreSQL support does not change that cross-engine
recovery policy. If an integrity check fails or the site is incomplete, stop
writes, save redacted diagnostics, and restore **both** the matching database
and files/config/code backup to a clean staging location. Verify the restore
before any separate production decision. Reverting Git alone cannot undo DDL.
Do not delete new columns, edit ledger rows or re-run the old destructive script
to force success. There is no automatic destructive down migration.

After completion compare pre/post counts, orphan reports and representative
original hashes; verify login/password upgrade, collection/album management,
image upload, FTP import, ZIP download, thumbnails/cache, feeds and all used
themes. Check PHP/server logs and the [security checklist](docs/security-checklist.md).
Keep the site restricted until real nginx/Apache/FPM/Plesk and private-theme
acceptance, backup/restore verification and remaining release gates are complete.
