# RB-05 through RB-08 acceptance follow-up, 2026-10-23/24

**NO-GO.** These are executed development/fixture results, not a production
deployment approval. Work started at `ac78dee` on `phase-1-php-compatibility`.
Pre-existing AGENTS/staging UNC documentation edits were preserved. No existing
staging database, deployed webroot, configuration or uploaded originals were used
or changed. No commit, push, release archive, tag or publication was performed.

## Corrections

- Full application PHPStan level 6 now passes with the same configured scope and
  PHP 8.2 target. The checked working tree initially had 510 findings (the earlier
  audit's 663 is historical). Documented real parameter/return contracts, array
  shapes and nullable legacy returns; removed unreachable branches after fatal
  query failures. No baseline, ignores, weakened rule level or blanket mixed
  contracts were added. Validation boundaries still accept untrusted inputs;
  PDO rows and diagnostic helpers document their genuinely variable values.
- Preserved public theme names, native signatures and defaults. Renamed only the
  import page's private album-menu helper to avoid a collision with other admin
  pages. Recursive import listing retains both legacy result shapes and now
  handles a missing directory without an `opendir()` warning.
- Fixed phpThumb's unset Unix cache directory passing null to filesystem APIs.
  The new regression failed under E_ALL before the fix and passes afterward.
- The installer now serializes every database connection value with
  `var_export()`. A quote/backslash round-trip regression failed on the old
  generated PHP and passes on the corrected configuration. No real config was
  generated or replaced.
- Reproduced the fresh-install frontend CDN error independently on Windows and
  FPM: the installer initialized `cdn_url` to the gallery's localhost URL, which
  the public-CDN validator rejects. New installs now leave this optional field
  empty, using the application origin. A native fixture assertion runs before
  any HTTP test can change it. Existing database values are not rewritten.
- Added native database assertions for camera, capture date, exposure, focal
  length, aperture and ISO after FTP import, and camera/date/ISO after multipart
  upload. Generated EXIF images remain byte-identical. These fixtures do not
  explain every previously reported failure with other image metadata.
- Full application analysis is now mandatory in `composer check` and CI,
  including the separate prerelease job; `continue-on-error` was removed.

## Executed native database checks

Private portable Windows clusters: MariaDB **10.6.26**, loopback port **33336**;
PostgreSQL **17.11**, loopback port **33337**. Fixture accounts are passwordless
only inside these disposable clusters. No default-port/site database was
contacted. Each test creates randomly named, guarded fixture databases.

| Runtime | MariaDB full suite | PostgreSQL full suite |
| --- | --- | --- |
| PHP 8.2.35 | 130 tests / 3,246 assertions / 6 skips, exit 0 | 130 / 3,246 / 6, exit 0 |
| PHP 8.3.36 | 130 / 3,246 / 6, exit 0 | 130 / 3,246 / 6, exit 0 |
| PHP 8.4.27 | 130 / 3,246 / 6, exit 0 | 130 / 3,246 / 6, exit 0 |
| PHP 8.5.12 | 130 / 3,246 / 6, exit 0 | 130 / 3,246 / 6, exit 0 |
| Experimental PHP 8.6.0RC4 | 130 / 3,246 / 6, exit 0 | 130 / 3,246 / 6, exit 0 |

The six skips are the opt-in Linux FPM stack, browser and static-nginx tests,
plus three HTTP cases that deliberately require the synthetic PDO double
(mutation tracing, injected field payload rows and missing paths). Those cases
are exercised separately, not treated as native database evidence.

All three source datasets run on each engine through `UpgradeDatabaseTest` and
`NativeRestoreTest`. Checks include backup confirmation, dry-run, version/plan
guards, locks, interruption/recovery, repeated upgrades becoming no-ops, retained
counts/relationships, source rows unchanged by restore, and original/config/theme
hashes. Real `mariadb-dump`/`mariadb` and `pg_dump`/`psql` restore into separately
created databases before upgrading restored copies. No mock database supplies
these results. Fresh-install tests call the real installer/schema functions;
they are not a complete interactive browser installation.

Additionally, PHP 8.4.27 ran the full HTTP class after each RC1/php7/php8 upgrade
on both engines: **35 tests / 1,949 assertions / 4 skips, exit 0**, six runs.
The native album-deletion and malformed-selection regressions pass in these
runs and the native matrix, supplying the previously missing RB-01 evidence.

**Fixture limitation:** RC1 remains modeled, not authenticated against an
original release archive/schema. Its PostgreSQL projection is synthetic because
original RC1 was MySQL-only. Obtain a trustworthy original archive, its full
license and installer/schema, and compare the fixture before claiming original
RC1 upgrade acceptance. Test a permission-reviewed, anonymized real installation
restore separately; never point these create/drop fixtures at a site's database.

## Executed Linux web stack checks

A separate acceptance copy was made under `/tmp/plogger-acceptance-*` in WSL
`Ubuntu-22.04`. This is neither the Linux Git checkout nor the existing
`localhost:8080` webroot. A new MariaDB **10.6.23** datadir listened only on
**127.0.0.1:33338**. Existing services were not reconfigured or restarted.

The opt-in `tests/fixtures/fpm-stack.php` launches private foreground nginx
**1.30.5**, Apache **2.4.52** and PHP-FPM **8.4.27**, with new loopback ports and
per-test configuration. It uses the actual shipped Apache/nginx security
snippets. Backend readiness is checked before nginx starts. Workers run as
**www-data**. No Docker, Node or extra application dependency is used.

For fresh, RC1, php7 and php8 fixtures, each FPM HTTP run passed **31 tests /
2,002 assertions / 3 skips**, exit 0. The three skips are the mock-only HTTP
cases described above. Four tests requiring a temporarily exposed installer/
upgrader or the application-level 403 response are excluded explicitly; the
normal server policy instead blocks these private resources at the HTTP layer.
Native installer and web/CLI upgrader tests run separately on the PHP CLI server.

Verified through the real stack:

- Login, legacy hash upgrade, throttling, same-origin/foreign-origin CSRF,
  selections, stored-description escaping and authenticated database writes.
- Multipart upload, unsafe filename rejection, FTP metadata preview/import,
  exact EXIF persistence, source hashes, thumbnails/cache repair, ZIP/downloads,
  bundled themes/feeds, CDN routing and disabled-by-default optional ordering.
- Config, internals, development directories, uploads and execution probes
  under images/thumbs/uploads/themes return 403/404 at **both nginx and the
  direct Apache backend**. PHP source and the harmless execution marker are
  absent from responses, including PATH_INFO probes.
- A custom session directory outside the webroot, mode 0700, contains real
  session files. FPM reports 60 seconds and 256 MB without bootstrap overrides.
- A disposable self-signed certificate is explicitly trusted by the HTTPS
  client; peer and hostname verification stay enabled. The TLS frontend uses
  a separate loopback Apache virtual host with HTTPS set locally, not an
  arbitrary client-forwarded header. HTTPS session cookies contain Secure,
  HttpOnly and SameSite=Lax.
- PHP `mail()` is routed to a private local capture executable. Missing CSRF
  produces no message. A valid reset request produces a matching stored key,
  preserves the password until confirmation, and the reset key is single-use.
  No email is delivered externally; SMTP deliverability is not verified.

The certificate/key, reset mail and session files are temporary fixture data,
never Git content. The helper stops only its own processes and removes private
session/mail/TLS material during teardown.

## Repeating the checks

Additional final checks: the synthetic suite passes on all five builds with
**130 tests / 2,849 assertions / 14 explicit skips** per build. These include
the mock-only security payload cases omitted in native mode. Tooling, focused
security and full application PHPStan pass on Windows; full application analysis
also passes in the Linux acceptance copy. Native Chrome/static-nginx tests pass
on PHP 8.4.27 (**2 tests / 118 assertions**). All five bundled theme scans report
zero errors/warnings. Public CSS totals 2,868 gzip bytes and admin CSS 2,884
(sum of separately compressed files). Composer validates the unchanged dependency
lock, with the existing missing-license metadata warning retained while rights
are unresolved. These checks do not audit every dependency or certify WCAG.

Use patched PHP binaries and development dependencies already installed from
the reviewed lock. Keep E_ALL and PHPUnit's warning/deprecation failures enabled.
See `docs/development.md` and the container-free native CI job for private cluster
initialization. Never reuse the staging database account for fixture automation.

```sh
php tools/lint.php
php vendor/phpstan/phpstan/phpstan analyse --no-progress
php vendor/phpstan/phpstan/phpstan analyse -c phpstan.security.neon --memory-limit=512M --no-progress
php vendor/phpstan/phpstan/phpstan analyse -c phpstan.application.neon --memory-limit=512M --no-progress
php vendor/phpunit/phpunit/phpunit --do-not-cache-result --display-skipped

# Only after creating the separate, disposable cluster on this high port:
PLOGGER_TEST_DRIVER=mysql PLOGGER_TEST_PORT=33336 PLOGGER_TEST_USER=root \
 PLOGGER_TEST_DUMP=/path/to/mariadb-dump PLOGGER_TEST_CLIENT=/path/to/mariadb \
 php vendor/phpunit/phpunit/phpunit --do-not-cache-result --display-skipped
# PostgreSQL equivalent: driver=pgsql, port=33337, user=fixture, pg_dump/psql.

# Ubuntu acceptance copy only; run as its unprivileged www-data owner.
# Requires the documented /usr/sbin binaries and /usr/bin/openssl, plus pcntl.
PLOGGER_TEST_FPM_STACK=1 PLOGGER_TEST_DRIVER=mysql PLOGGER_TEST_PORT=33338 \
 PLOGGER_TEST_USER=root php vendor/phpunit/phpunit/phpunit --do-not-cache-result \
 --display-skipped --filter 'HttpCompatibilityTest::test(?!PrivateAndMalformed|InstallerReports|UpgradeWebAndCli|FreshInstaller)'
# Repeat with PLOGGER_TEST_UPGRADE_FROM=rc1, php7 and php8.
```

Copy only to a newly created acceptance directory, excluding `.git`, `.local`,
`.env*`, real config, images, thumbs and uploads. The HTTP tests generate their
own images/config/data. This copy deliberately includes tests/vendor tooling
and **must not be deployed as a release**. Use the separate runtime-only rsync
workflow in [staging-environment.md](staging-environment.md) for actual deployment.

## Redistribution evidence and missing inputs

The 109 SHA-256 entries in [asset-provenance.csv](asset-provenance.csv) match the
current files. Hash identity is not a license grant. No notices were removed,
no assets relicensed and no rights-holder messages were sent.

| Component | Verified notice; remaining requirement |
| --- | --- |
| Original Plogger/forks | Root/fork GPLv3 texts match; inherited readme states GPL2 and references absent LICENSE.txt. Original project HTTPS retrieval timed out. Obtain an authenticated original release with the actual grant, including whether GPL2 is only or later. A copied license text cannot establish that grant. |
| CAPTCHA | Embedded Constantinos Neophytou CC BY-NC-SA 3.0 notice imposes a noncommercial restriction. Obtain a compatible rights-holder grant or approve a separately scoped replacement before distribution. |
| CAPTCHA font | Embedded metadata identifies Mana Mana, Jenny Barck (1997); no font license/URL in name IDs 13/14. Obtain redistribution terms or replace with a reviewed licensed font. |
| gettext | Embedded Danilo Segan GPL-2.0-or-later grant verified; preserve headers and corresponding GPL obligations. |
| phpThumb | Explicit GPLv3 option and license texts verified; preserve GIF/Yamasoft and Unsharp Mask notices too. This is not a blanket clearance for other gallery assets. |
| Font Awesome 5.12.1 | Upstream grants OFL fonts, CC BY 4 icons and MIT code; preserve attribution. Compared local assets differ from the exact-tag upstream downloads, including font metadata; reconcile source/build identity rather than infer a missing grant from hash differences. |
| Prototype/Lightbox/artwork | MIT-style Prototype and CC BY 2.5 Lightbox notices present; complete exact-version license/attribution and author/source mapping for every remaining asset. |
| External theme fonts/Bootstrap | Review pinned theme-specific remote URLs and notices; these are not core dependencies or automatic redistribution clearance. |

Primary references: [Font Awesome 5.12.1 license](https://raw.githubusercontent.com/FortAwesome/Font-Awesome/5.12.1/LICENSE.txt),
[CC BY-NC-SA 3.0 terms](https://creativecommons.org/licenses/by-nc-sa/3.0/),
and retained component source/license files. Original upstream and GNU FAQ
fetches timed out during this follow-up; no unavailable page is cited as evidence.

## Remaining gates

**RB-06 is resolved for the configured full-application analysis gate.** RB-05
remains open for authoritative RC1 provenance and real-install/browser acceptance.
RB-07 remains open for the rights evidence above. RB-08 remains open for a manual
keyboard/screen-reader/zoom review, realistic-volume 60-second/256-MB acceptance,
and private `dgp2026`, production rewrite/CDN policy and historical metadata input.
Automated Chrome component checks do not replace those reviews. Captured local
mail does not verify an external mail relay. Existing maintainer staging reports
still require their own recheck; do not change stored CDN values automatically.

Hosted CI was not triggered by this uncommitted work. The previous red run is
not relabeled green. Composer's upstream RC4 deprecation remains separate RB-02
evidence. No release packaging is permitted while these gates remain open.

A repeated limited key-pattern scan examined 410 tracked files (2 MiB per-file
cap): zero AWS/GitHub/private-key marker matches. This is not a full-history,
entropy or personal-data audit. The new fixture/helper/report files were also
reviewed; they contain code for generating test material, not real credentials.
All 109 asset hashes match, but rights remain unresolved. No changed path is an
original photograph or real configuration. `git -c core.whitespace=cr-at-eol
diff --check` passes; this repository's CRLF working files require the explicit
CR-at-EOL setting. Final strict lint covers 193 files on each tested build.
Private fixture database servers were stopped after testing; the existing
localhost:8080 services and deployment were not stopped or changed.

## Files changed

- Runtime corrections/contracts: `plog-admin/includes/install-functions.php`,
  `plog-admin/plog-admin-functions.php`, `plog-admin/plog-admin.php`,
  `plog-admin/plog-feedback.php`, `plog-admin/plog-import.php`,
  `plog-admin/plog-manage.php`, `plog-admin/plog-plugins.php`,
  `plog-admin/plog-themes.php`, `plog-admin/plog-upload.php`, `plog-download.php`,
  `plog-globals.php`, `plog-load-config.php`, `plog-remote.php`, `plogger.php`,
  `plog-includes/plog-database.php`, `plog-includes/plog-functions.php`,
  `plog-includes/plog-tag-functions.php`, and
  `plog-includes/lib/phpthumb/phpthumb.class.php`.
- Tests: `tests/PhpCompatibilityTest.php`, `tests/HttpCompatibilityTest.php`,
  `tests/fixtures/native-install.php`, new `tests/fixtures/fpm-stack.php`.
- Tooling: `composer.json`, `phpstan.neon`, `.github/workflows/checks.yml`.
- Documentation: `AGENTS.md`, `README.md`, `CREDITS.md`, `CHANGELOG.md`,
  `ROADMAP.md`, `UPGRADING.md`, `RELEASE-BLOCKERS.md`, this report,
  `docs/rb-01-management-sql.md`, `docs/release-readiness-audit.md`,
  `docs/security-checklist.md`, `docs/staging-environment.md`.

The pre-existing UNC documentation additions in AGENTS/staging documentation
were retained. Composer dependencies/lock, schemas and photo originals were not
changed by this follow-up. Migration tests operate only on generated databases.
