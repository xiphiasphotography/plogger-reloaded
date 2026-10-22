# Release blockers — v1.0.0-beta.1

**Decision: NO-GO, 2026-10-22. No release ZIP, tag or published release was created.**

Audited development baseline: commit `35e11ad` on `phase-1-php-compatibility`,
with the maintainer's uncommitted AGENTS.md update requesting PHP 8.2–8.6.
That update was preserved. See [executed checks](docs/release-readiness-audit.md)
and [draft release notes](CHANGELOG.md). Passing synthetic tests is not release
acceptance.

## Mandatory blockers

| ID | Finding | Required closure evidence |
| --- | --- | --- |
| RB-01 | Code corrected; native database verification pending — still OPEN | Bound deletion/move queries and whole-selection regressions are implemented. Require passing native MySQL/MariaDB and PostgreSQL results before closure; see [RB-01 evidence](docs/rb-01-management-sql.md). |
| RB-02 | Local patched matrix passes; native/hosted acceptance and RC4 Composer deprecation remain OPEN | Complete native-driver checks and hosted CI; recheck RC4 with an upstream fixed Composer. See compatibility evidence below. |
| RB-03 | RESOLVED: policy and tooling reconciled | Composer/lock, stable/prerelease CI, PHPStan and current docs agree; five-build local evidence recorded below. |
| RB-04 | RESOLVED for local test runtime baseline | Retested on patched official builds; security patch floors and reviewed vendor-backport exceptions documented. Deployment acceptance remains RB-08. |
| RB-05 | Native database installation/upgrade gates unverified | Run fresh installs and RC1/php7/php8 fixtures on MySQL/MariaDB and PostgreSQL, including reruns, interrupted recovery, record counts and original hashes. |
| RB-06 | Full application PHPStan level 6 fails | Review/fix 663 findings without a suppression baseline; retain focused security checks. |
| RB-07 | Redistribution provenance unresolved | Resolve original GPL grant, CAPTCHA licensing, fonts/assets and complete third-party attribution before redistribution. |
| RB-08 | Real deployment/security/restore acceptance incomplete | Execute Apache/nginx + PHP-FPM deny-rule matrix, HTTPS/custom session storage, mail/reset, restore, accessibility, resource and reference-theme checks on disposable staging. |

### RB-01: implementation corrected; verification incomplete

At the audited `35e11ad` baseline, `plog-admin/plog-manage.php` passed entries of
`$_REQUEST['selected']` to `delete_album()` for album deletion.
`plog-admin/plog-admin-functions.php:1093` interpolated `$del_id` in SQL.
The single-item shortcut cast its ID, but the multi-selection path did not.

A capture-only CLI reproduction passed the harmless string
`0' OR 'fixture'='fixture` to `delete_album()`. A stub intercepted its
**first SELECT**, recorded `parameters: null` and the resulting
`a.id = '0' OR 'fixture'='fixture'`, then threw before any database or
filesystem operation. No real database exploit or deletion was attempted.
Authentication and CSRF do not make interpolated SQL safe. Audit neighboring
collection/picture/move/delete queries as part of the correction.
Calling PDO prepare on an already interpolated query does not bind its values.

The RB-01 patch now strictly validates complete POST selections and direct
delete/move calls before processing, binds album/collection/picture deletion and
move query values, and retains prepared thumbnail updates. Regression tests
reject malicious single IDs, mixed batches, nested arrays and invalid move
destinations without writes or file/directory changes.

PHP 8.2–8.4 each pass 115 tests/2,774 assertions (ten default skips). Focused
RB-01 tests pass; native MySQL/MariaDB and PostgreSQL servers are unavailable
locally. A positive native deletion regression is added to the existing driver
matrix but has not run here. **RB-01 remains OPEN until both engines pass.**
See [full patch scope and executed evidence](docs/rb-01-management-sql.md).

### RB-02/RB-03: compatibility follow-up

**RB-02 remains OPEN for acceptance**, with the observed application/test
failures corrected. Patched PHP 8.2.35, 8.3.36, 8.4.27 and 8.5.12 each pass
118 tests / 2,792 assertions, ten default skips, and strict lint of 191 files.
Experimental 8.6.0RC4 passes the same checks separately; it is not a supported
production release. Native Chrome/static nginx checks additionally pass on all
five runtimes (two tests / 118 assertions each). E_ALL remains enabled; warnings,
notices and deprecations fail PHPUnit. The original failed 8.5.11 audit is retained
in the initial report rather than treated as current evidence.

Eight native database cases remain skipped; hosted CI and real PHP-FPM acceptance
are unverified. Composer 2.10.3 emits an upstream MB_ONIGURUMA_VERSION deprecation
on RC4, although its platform check exits 0. Do not suppress it or describe RC4
as fully clean tooling. Recheck with a fixed upstream Composer and complete the
native matrix before closing RB-02.

**RB-03 RESOLVED for policy/configuration:** Composer/lock now require
`>=8.2 <8.6 || 8.6.0-RC4`, excluding 8.1 and untested 8.6 GA. CI pins the four
patched stable versions, with a separate RC4 job; PHPStan targets 80200. Current
setup/upgrade/contributor documents agree. Composer validated the lock, and its
platform requirements pass on all five builds. No dependency versions changed.
Hosted CI itself has not been run and remains required release evidence.
See [commands, hashes, fixes and limitations](docs/php-82-86-validation.md).

### RB-04: runtime security — local test baseline RESOLVED

The local compatibility runs now use official patched **8.2.35, 8.3.36, 8.4.27
and 8.5.12**, verified against published archive SHA-256 values. This replaces
the original audit's older binaries; no production runtime was changed.
The [2026-10-21 advisory](https://www.php.net/archive/2026.php) and
[runtime policy](docs/runtime-support.md) establish minimum patch requirements,
ongoing security updates and the evidence required for a vendor backport exception.
No exception was verified. RC4 is isolated prerelease testing, never a production
recommendation. A passing patch-floor diagnostic does not certify deployment or
untested future versions. Host/FPM acceptance remains RB-08; release stays blocked.

### RB-05: database and upgrade provenance

PDO drivers are available; native server binaries were not found on PATH,
under S:/Development or in the checked standard installation locations.
No production/default-port database was contacted. Eight persistence/native
upgrade tests remain skipped. SQLite metadata doubles do not verify native DDL.
The original RC1 fixture is explicitly modeled, not an authenticated upstream
release dump; the PostgreSQL RC1 fixture is a synthetic projection because RC1
was MySQL-only. Obtain authoritative RC1 schema evidence and test a staging
restore before claiming supported upgrades.

### RB-07: licenses and assets

Root LICENSE is GPLv3 and byte-identical to the adjacent php7/php8 forks
(SHA-256 `230184f60bae2feaf244f10a8bac053c8ff33a183bcc365b4d8b876d2b7f4809`).
But `readme.html:114` states GPL2 and references absent LICENSE.txt.
The retained CAPTCHA header is CC BY-NC-SA 3.0. Neither fact is resolved by
copying a GPLv3 text into the root. Retained gettext grants GPL-2.0-or-later;
phpThumb offers a GPLv3 option. Prototype, Lightbox, fonts/icons/images and their
specific attribution obligations still need the complete provenance checklist.
No code was relicensed and no license headers were removed.

## Pending optional features and limits

Oypo remains disabled: real provider URL/identifiers and pixel policy are missing.
This is **not independently a beta blocker** if the feature is explicitly
documented as unavailable and its disabled-by-default behavior remains tested.
The private dgp2026 theme, historical metadata mapping, production URL rules,
CDN mirror/access policy and realistic volume remain maintainer/staging inputs.

The limited credential scan found no matches for selected AWS/GitHub/private-key
patterns in 397 tracked files, and no real config or image originals are tracked.
It is not a full Git-history, entropy, personal-data or binary provenance audit.
Complete that review and a reviewed package allowlist before generating a release.
Do not include vendor tooling, fixtures, caches, local configuration or user content.
