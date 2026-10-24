# Final release readiness audit — 2026-10-22

**2026-10-23/24 follow-up:** [RB-05–08 executed validation](rb05-08-validation.md)
supersedes the initial environment limitations below. Real MariaDB/PostgreSQL
upgrade/restore matrices and WSL nginx→Apache→PHP-FPM workflows passed; full
application PHPStan level 6 now passes without suppressions. RB-01 and RB-06
are closed. RC1 provenance, redistribution rights and remaining manual/private
deployment acceptance keep the release **NO-GO**. The initial audit remains
below as historical evidence, not a current assertion that servers are absent.

**Compatibility follow-up:** [RB-02/03/04 validation](php-82-86-validation.md)
records subsequent code/tooling repairs and passing local patched PHP
8.2.35/8.3.36/8.4.27/8.5.12 and experimental 8.6.0RC4 checks. The old version
results and environment description below are retained as the initial audit
record. Native database/staging gates and RC4 Composer deprecation remain open;
the release decision is still NO-GO.

**NO-GO for v1.0.0-beta.1.** [Release blockers](../RELEASE-BLOCKERS.md) are the
controlling decision record. [Release notes](../CHANGELOG.md) are an unreleased
draft. The original audit snapshot made documentation changes only; application
code, databases, configuration and original photographs were not changed.

## Staging acceptance follow-up — 2026-10-22

The maintainer reported that Ubuntu 22.04.5 / nginx 1.30.5 / Apache 2.4.52 /
PHP-FPM 8.4.27 / MariaDB 10.6.23 staging returned HTTP 403 for the admin login
POST to `/plog-admin/plog-upload.php`. Chrome sent `Origin: null`; the admin
guard's `Referrer-Policy: no-referrer` was inconsistent with the required
same-origin form workflow. The admin response policy is now `same-origin`.
POST-only enforcement, strict Origin and `Sec-Fetch-Site` checks, SameSite=Lax
cookies, and session-bound CSRF-token validation remain in place.

Added an HTTP regression that submits the real login route with a valid token:
the literal null and foreign origins are rejected, while a matching same-origin
Origin succeeds. It also asserts that the login form response sets
`Referrer-Policy: same-origin`. Local test results and unavailable PHP runtimes
are recorded here: the focused HTTP regressions (3 tests, 157 assertions) and
`CsrfTest` (3 tests, 28 assertions) passed on local PHP 8.4.5 and 8.5.11, and
changed-file syntax checks and tooling/security PHPStan passed. These local
builds are below the current security patch floors. PHP 8.2/8.3 and current
patched runtime builds were unavailable, so this is not full supported-matrix
verification.
The reported Ubuntu 8.4.27 staging request was not independently rerun from this
environment. The release decision remains **NO-GO** until every acceptance gate
passes. No release, tag or ZIP was created.

The push triggered [GitHub Actions run #11](https://github.com/xiphiasphotography/plogger-reloaded/actions/runs/39854526866),
which completed red. All four stable PHP unit jobs (8.2.35, 8.3.36, 8.4.27 and
8.5.12) and the PHP 8.6 prerelease job failed; the stable unit logs report an
`HttpCompatibilityTest::tearDownAfterClass` failure from runtime warnings in
`phpthumb.class.php:1144`. The static-CDN job passed; the native-database jobs
failed. The focused CSRF regressions passed locally on the two available PHP
series, but this workflow is not a green PHP-matrix verification.

Initial-audit baseline: `35e11ad`, branch `phase-1-php-compatibility`. The pre-existing
maintainer edit to AGENTS.md changes the requested range to PHP 8.2–8.6 and was
not edited/reset. No commits, pushes, tags, migrations or deployments were made
during that original audit.

## Environment and commands actually executed

Windows PowerShell. Existing local PHP 8.2.34, 8.3.35 and 8.4.26 installations
have GD, EXIF, fileinfo, mbstring, ZipArchive and PDO MySQL/PostgreSQL. SQLite was
enabled for isolated migration/backfill unit doubles using
`-d extension=pdo_sqlite`. Existing PHP 8.5.11 was configured with the same
extensions using a temporary PHPRC/php.ini; no production php.ini was edited.
The first 8.5 runner attempt lacked mbstring; after correcting that temporary
configuration the complete suite ran and failed as reported below.

No PHP 8.6 executable, native MariaDB/MySQL/PostgreSQL server or Composer command
was available in the checked local locations. No new runtime/server was installed.
PHP 8.6 is presently a
[prerelease (8.6.0RC4)](https://www.php.net/pre-release-builds.php).
Current PHP security requirements were checked against the
[official PHP advisory](https://www.php.net/archive/2026.php), not inferred from
the installed binaries.

Commands below used each explicitly selected PHP executable where applicable:

```text
git status --short
git branch --show-current
php -v
php --ini
php -m
php -d extension=pdo_sqlite vendor/phpunit/phpunit/phpunit --do-not-cache-result --display-skipped
php vendor/phpunit/phpunit/phpunit --do-not-cache-result --display-deprecations --display-skipped
php tools/lint.php
php vendor/phpstan/phpstan/phpstan analyse --no-progress
php vendor/phpstan/phpstan/phpstan analyse --configuration=phpstan.security.neon --memory-limit=512M --no-progress
php vendor/phpstan/phpstan/phpstan analyse --configuration=phpstan.application.neon --memory-limit=512M --no-progress --error-format=json
php tools/check-extensions.php
php tools/theme-check.php plog-content/themes/NAME
php tools/css-size.php
git -c core.whitespace=cr-at-eol diff --check
```

The second PHPUnit command was the configured PHP 8.5 run; the first was used
for 8.2–8.4. Normal E_ALL behavior was retained. PHP 8.5 test/lint logs and the
application JSON report were captured under the temporary
`plogger-release-audit-20261022` directory, not in a deployable directory.

## Results

| Check | Actual result |
| --- | --- |
| PHP 8.2.34 full PHPUnit | 110 tests, 2,121 assertions, nine skips, exit 0 |
| PHP 8.3.35 full PHPUnit | 110 tests, 2,121 assertions, nine skips, exit 0 |
| PHP 8.4.26 full PHPUnit | 110 tests, 2,121 assertions, nine skips, exit 0 |
| PHP 8.5.11 full PHPUnit | 110 tests, 2,116 assertions, two errors, one failure, two warnings, ten deprecation reports, nine skips; exit 2 |
| Strict lint on 8.2/8.3/8.4 | 187 PHP files per version, zero failures |
| Strict lint on 8.5 | 187 files; five diagnostic occurrences, exit 1 |
| PHP 8.6 | Not run: no local binary; stable support not established |
| Tooling PHPStan level 6, PHP 8.4 | Passed |
| Focused security/UI PHPStan level 6, PHP 8.4 | Passed |
| Full application PHPStan level 6 | 663 file findings, zero general errors, exit 1 |
| Required extension diagnostic, PHP 8.4 | Passed; mysql/pgsql drivers present |
| default/air/lucid/xip-bs/reloaded static scans | All five: zero errors, zero warnings |
| Native Chrome + static nginx, PHP 8.4 | Two tests, 118 assertions, passed |
| SQL query capture | Unsafe album-deletion interpolation confirmed; no database opened |
| Limited tracked-file credential scan | 397 files, zero matches; no file excluded by the 2 MiB size cap |
| Composer inventory | Zero runtime packages, 27 development packages |
| Public Packagist advisory lookup | Four historical/current PHPUnit advisories returned; none of their ranges include locked 10.5.66; no other queried package had an advisory returned |

PHPStan's largest groups are plog-functions.php (270), plog-admin-functions.php
(154), install-functions.php (50), plog-options.php (44), plog-tag-functions.php
(33), plog-remote.php (22), and plog-themes.php (19). These are findings to triage,
not a count of proven exploitable vulnerabilities. No suppression baseline was
generated.

### What the passing suite covers

The executed full suite includes the historical login `r` allowlist/output
checks, `jump-menu`/unknown query parameter serialization, stored descriptions,
names/captions/metadata, session/CSRF guards, cross-origin requests, password
verification/MD5 upgrade behavior and login throttling. It exercises upload
content/filename checks, ZIP traversal/bomb/CRC rejection, FTP import/preview and
manual metadata edits, native EXIF/IPTC/XMP compatibility shapes, orientation,
gettext catalogs/plurals, retained phpThumb resizing/cache behavior, downloads,
CDN URL normalization/routing, optional Oypo eligibility/disabled behavior,
dimension backfill, inherited public API signatures and all bundled themes.

These checks pass on the three tested 8.2–8.4 versions. They do not prove every
endpoint safe: the audit's new SQL finding demonstrates a gap in the existing
regression coverage. The retained libraries and image pipeline are not clean on
8.5. Specifically, HttpCompatibilityTest teardown reports runtime diagnostics,
the legacy authentication HTTP test fails, and the GIF pixel-plotter test errors.
PHP 8.5 lint diagnostics also occur in CAPTCHA, the remote endpoint and HTTP test
header handling. See RB-02 for exact repair areas.

Nine default skips comprise seven native persistence/upgrade cases plus browser
and nginx cases. Browser/nginx were then run explicitly with
`PLOGGER_TEST_BROWSER` and `PLOGGER_TEST_NGINX`, using installed native Chrome
and nginx 1.30.6. Sandbox process restrictions required approved execution
outside the restricted Windows process sandbox; the browser sandbox was not
disabled. The fixture servers/profiles were disposable and localhost-only.
The seven remaining skips cover password reset persistence, exact stored values,
native authenticated album CSRF, web/CLI upgrade persistence and the three
RC1/php7/php8 upgrade datasets.

### SQL capture reproduction

A temporary standalone PHP file supplied only `plogger_db_table()` and a
`run_query()` stub, included plog-admin-functions.php, then called:

```php
delete_album("0' OR 'fixture'='fixture");
```

The stub recorded the first SELECT and immediately threw, so no deletion,
database connection, SQL execution or photo access occurred. The recorded WHERE
clause was `a.id = '0' OR 'fixture'='fixture'` and parameters were null.
Source review traced the uncast multi-selection request path to that function.
This is an unbound-input finding, not a claim of a demonstrated live exploit.

## Dependencies, licenses and data review

Exifer, PclZip and phpThumb demo directories are absent. A PHP-source search,
excluding vendor, found no `eval(...)` calls or retired-directory references.
Native EXIF/IPTC/XML and ZipArchive paths are present. The standalone phpThumb
HTTP entry point returns 403. The
[upstream phpThumb release page](https://github.com/JamesHeinrich/phpThumb/releases)
lists v1.7.24 and its CVE-2025-52994 repair; this does not certify every retained
feature or the local patches. Local 8.5 results still require work.

Composer JSON/lock were inspected. Composer validate/audit were not run because
Composer is unavailable; no install/update or lock rewrite was performed.
Instead, the official public
[Packagist security advisory API](https://packagist.org/api/security-advisories/)
was queried for all 27 locked development package names. The four returned
advisories affect PHPUnit ranges outside 10.5.66; this was checked against the
returned ranges. This lookup does not audit bundled libraries, external theme
CDNs, unknown vulnerabilities or Composer/platform consistency.

License review read the root/fork license identity and specific retained source
notices. Root and both adjacent fork LICENSE files have identical SHA-256.
gettext explicitly allows GPL-2.0-or-later; phpThumb offers GPLv3. The original
GPL2-versus-root-GPLv3 conflict, missing upstream LICENSE.txt, noncommercial
CAPTCHA notice and incomplete font/image/asset provenance remain unresolved.
See CREDITS.md and RB-07; no legal clearance or new license grant is asserted.

The limited credential scan read all 397 tracked files and checked selected
AWS access-key, GitHub token and private-key marker patterns, printing filenames
only for matches (none). It is not an entropy/full-history/personal-data scan.
Tracked originals/uploads/thumbs contain only directory guards; no real
plog-config.php, .env, PEM or key files were listed. Upgrade SQL/config fixtures
are explicitly synthetic. Existing theme preview images/fonts still require
provenance clearance; they are not evidence of a customer-data clearance audit.

## UI, performance and staging limits

Native component tests cover exact 360/768/1280-pixel frames, one/multiple grid
columns, overflow containment, focus/skip links, reduced-motion preference and
embedding-host style isolation. HTTP tests render the actual public/admin PHP.
This is not a complete end-to-end keyboard, screen-reader or visual audit.

Measured separately gzip-9 compressed files: public Reloaded CSS **2,868 bytes**;
Admin UI **2,884 bytes**, excluding the retained lightbox CSS.
No nginx/PHP-FPM load, 60-second/256-MB large-folder test, mail delivery, real CDN
synchronization/TLS or staging restore was performed. Initial-audit notes predate
run #11, which failed and is not claimed green. No native database server,
dgp2026 or real Oypo contract was used.
Oypo stays disabled; no order request was sent.

## Required next audit

Fix RB-01 and PHP 8.5 errors with failing regressions first. Align the approved
PHP range and exercise 8.6 prerelease builds separately from stable claims.
Use patched PHP runtimes, native disposable database fixtures for every source,
an authoritative RC1 schema and a staging restore. Resolve license provenance,
complete deployment/accessibility/resource checks and inspect release contents
with a reviewed allowlist and full secrets/data review. Only then prepare the
ZIP/checksum and approve release notes. This audit deliberately leaves the
application patch, deployment, tagging and publication untouched.
