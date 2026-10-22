# PHP 8.2–8.6 compatibility follow-up — 2026-10-22

Scope: RB-02, RB-03 and RB-04 on `phase-1-php-compatibility`, baseline
`35e11ad` plus the preserved maintainer/audit/RB-01 working-tree changes.
No commit, push, deployment, database migration or original-photo change.
**Release remains NO-GO.** This supersedes the old runtime results in the
[initial audit](release-readiness-audit.md), not its other acceptance gaps.

## Executed matrix

Official Windows NTS x64 PHP builds were downloaded to a temporary directory,
SHA-256 checked against the official
[stable manifest](https://downloads.php.net/~windows/releases/releases.json)
and [QA manifest](https://downloads.php.net/~windows/qa/releases.json), then
run using explicit executable paths. No production PHP installation was replaced.
Temporary ini files enabled E_ALL/display_errors, UTC, 256 MB, GD, EXIF,
fileinfo, mbstring, ZipArchive, PDO MySQL/PostgreSQL/SQLite and OpenSSL.

| Runtime | Classification | PHPUnit | Strict lint | Native Chrome + static nginx |
| --- | --- | --- | --- | --- |
| 8.2.35 | Patched stable | 118 tests / 2,792 assertions / 10 skips | 191 files, clean | 2 tests / 118 assertions, pass |
| 8.3.36 | Patched stable | 118 / 2,792 / 10 skips | 191 files, clean | 2 / 118, pass |
| 8.4.27 | Patched stable | 118 / 2,792 / 10 skips | 191 files, clean | 2 / 118, pass |
| 8.5.12 | Patched stable | 118 / 2,792 / 10 skips | 191 files, clean | 2 / 118, pass |
| 8.6.0RC4 | Experimental prerelease only | 118 / 2,792 / 10 skips | 191 files, clean | 2 / 118, pass |

All matrix PHPUnit/lint commands exit 0 without warning, notice or deprecation
reports. The default ten skips are eight native database persistence/upgrade
cases plus Chrome and nginx, which were executed separately as shown above.
No native database case is claimed as passed: no isolated MariaDB/PostgreSQL
server is available locally. PDO extension presence does not establish driver
integration. RC1/php7/php8 native upgrade acceptance remains pending.

The full suite covers historical reflected/stored XSS, CSRF and SQL-selection
guards, login, synthetic installer/gallery/admin/upload/FTP/thumbnail/download
flows, EXIF/IPTC/XMP, ZipArchive, retained gettext/phpThumb, CDN, Oypo eligibility,
theme API and migration doubles. These are isolated fixtures, not production or
PHP-FPM acceptance. Browser checks are component checks, not a complete manual
accessibility audit. Native nginx checks test the static CDN policy only.

SHA-256 of the downloaded ZIP archives:

```text
8.2.35   798ffa4b9ce22a820d57048e9695df7245bf917ed067917f256942a10918a8cc
8.3.36   3c9242622c7402b47c38d2ec2ed779cef3a290fd6f2bde6c35f9c482699e7900
8.4.27   93bd8a815a52156606fb906d0573437908c61f96e2cc52c03a51e36a41bf5236
8.5.12   2ab242af38d76bdf417cde5ec048b1bcd76a5a40f39a3d15d5f046f38571c477
8.6.0RC4 68026900957f18f61d49726224ff0efc39bb0710c7e8adab94b7032f34974d9f
```

## Repairs and regression evidence

- Reproduced phpThumb's null array-key deprecation under strict E_ALL before
  editing; the new regression failed. Reject null before cache lookup, retaining
  false for absent paths and true for existing paths. The regression now passes.
- Replace deprecated, ineffective `imagedestroy()` calls with releasing the GD
  object reference in ingestion, CAPTCHA, GIF handling and test fixtures. Existing
  image/metadata/thumbnail/ZIP regressions pass on every listed runtime.
- Use `(float)` in CAPTCHA and a colon for the remote endpoint switch label.
- Replace the deprecated magic HTTP response-header variable with stream metadata
  in fixtures. An initial fallback failed on 8.2/8.3; the final stream approach
  passes authentication, cookies, redirects, multipart rejection and CDN tests
  across all five builds.
- Add runtime policy tests distinguishing patched stable versions, RC4, old
  patches, unsupported versions and unverified vendor strings. PHPUnit now fails
  on notices and deprecations as well as warnings/risky tests; E_ALL is retained.

Policy/configuration changes: Composer root and lock platform constraint
`>=8.2 <8.6 || 8.6.0-RC4`; PHPStan minimum target 80200; four patched stable
CI jobs and native database matrix, plus a separate RC4 job. Current setup,
upgrade, contributor and release documentation now distinguish stable/prerelease.
Historical phase reports retain their original evidence rather than being
rewritten as newly executed tests. See [runtime requirements](runtime-support.md).

## Tooling checks actually executed

- Composer **2.10.3**, downloaded to temporary storage with published SHA-256
  verification. `update --lock --no-install --no-scripts --no-plugins` on 8.5.12:
  exit 0, no package versions changed. No runtime dependency added.
- `composer validate --no-check-publish`: exit 0; existing missing-license
  recommendation remains because provenance is unresolved.
- Locked `composer install --dry-run --no-scripts --no-plugins` on 8.5.12:
  exit 0, nothing to install/update/remove. The initial sandbox metadata fetch
  was unavailable; the authorized rerun completed without that network warning.
- `composer audit --locked --no-interaction`: exit 0, no advisories. Initial
  sandbox network access failed; rerun with authorized network access succeeded.
- `composer check-platform-reqs --lock`: exit 0 on all five runtimes. **Composer
  itself emits an RC4 deprecation for MB_ONIGURUMA_VERSION in its PHAR**. This
  output is not suppressed; RC4 development tooling is not fully clean. Recheck
  with an upstream fixed Composer before clearing that prerelease gate.
- Tooling and focused security PHPStan level 6: pass on all five runtimes.
  Full application level 6 on 8.5.12: exit 1, **663 findings**; no suppression
  baseline or lowered analysis level was introduced.
- Extension diagnostics and static theme checks for default, air, lucid, xip-bs
  and reloaded: pass without reported errors/warnings on all five runtimes.
- Reviewed the combined diff; no image/thumb/config file changes. A limited
  added-line credential-pattern scan found no matches. Normal `git diff --check`
  flags inherited CRLF as trailing whitespace; the documented
  `git -c core.whitespace=cr-at-eol diff --check` passes. No wholesale line-ending
  rewrite or full secret-history audit was performed.

Commands used each explicitly selected PHP executable:

```text
php tools/check-runtime.php
php vendor/phpunit/phpunit/phpunit --do-not-cache-result --display-skipped
php tools/lint.php
php vendor/phpstan/phpstan/phpstan analyse --memory-limit=512M --no-progress
php vendor/phpstan/phpstan/phpstan analyse -c phpstan.security.neon --memory-limit=512M --no-progress
php composer.phar check-platform-reqs --lock
# Set PLOGGER_TEST_BROWSER and PLOGGER_TEST_NGINX to reviewed local binaries:
php vendor/phpunit/phpunit/phpunit --do-not-cache-result --filter 'UiBrowserTest|CdnNginxTest'
```

Chrome used the installed native browser; nginx used native 1.30.6. Both operated
on disposable localhost fixtures. Hosted GitHub Actions, Linux/FPM deployments,
real database backends, vendor backport exceptions, PHP 8.6 GA and other unlisted
runtime versions were **not** verified. Review all remaining
[release blockers](../RELEASE-BLOCKERS.md) before any packaging.

## Files changed for this follow-up

Configuration: `.github/workflows/checks.yml`, `composer.json`, `composer.lock`,
`phpstan.neon`, `phpunit.xml.dist`. Diagnostics/tests: `tools/check-runtime.php`,
`tools/runtime-policy.php`, `tests/RuntimePolicyTest.php`, `tests/bootstrap.php`,
`tests/PhpCompatibilityTest.php`, `tests/HttpCompatibilityTest.php`,
`tests/CdnNginxTest.php`, `tests/ArchiveSecurityTest.php`, `tests/MetadataTest.php`,
`tests/OypoTest.php`, `tests/SecurityInputTest.php`, `tests/ThumbnailLibraryTest.php`,
`tests/UpgradeDatabaseTest.php`.

Application compatibility: `plog-remote.php`, `plog-includes/plog-captcha.php`,
`plog-includes/plog-security.php`,
`plog-includes/lib/phpthumb/phpthumb.class.php`,
`plog-includes/lib/phpthumb/phpthumb.gif.php`.

Documentation: `AGENTS.md` (stable/prerelease policy clarification only),
`README.md`, `CONTRIBUTING.md`, `UPGRADING.md`, `ROADMAP.md`, `CHANGELOG.md`,
`RELEASE-BLOCKERS.md`, `docs/development.md`, `docs/runtime-support.md`,
`docs/security-checklist.md`, `docs/release-readiness-audit.md` and this report.
Pre-existing RB-01 source changes, CREDITS edits and other maintainer changes
were preserved; their presence in the combined diff is not this task's scope.
