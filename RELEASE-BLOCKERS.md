# Release blockers — v1.0.0-beta.1

**Decision: NO-GO, updated 2026-10-24. No release ZIP, tag or published release was created.**

Initial audited baseline: `35e11ad`; current follow-up patch is based on
`ac78dee`, branch `phase-1-php-compatibility`. Maintainer edits were preserved.
See [initial audit](docs/release-readiness-audit.md),
[current commands/results](docs/rb05-08-validation.md) and [draft notes](CHANGELOG.md).
Passing fixtures does not establish upstream provenance or production acceptance.

## Mandatory blockers

| ID | Finding | Required closure evidence |
| --- | --- | --- |
| RB-01 | RESOLVED: native deletion/selection verification completed | Both real engines pass security regressions, including post-upgrade HTTP runs. |
| RB-02 | OPEN: local patched/native matrix passes; hosted CI and RC4 Composer deprecation remain | Re-run hosted CI and recheck RC4 with a fixed upstream Composer. |
| RB-03 | RESOLVED: policy and tooling reconciled | Composer/lock, stable/prerelease CI, PHPStan and current docs agree. |
| RB-04 | RESOLVED for local runtime baseline | Patched official builds retested; deployment acceptance remains RB-08. |
| RB-05 | OPEN: native checks pass; provenance/real-install acceptance incomplete | Require authoritative RC1 schema/license and reviewed real-install/browser restore acceptance. |
| RB-06 | RESOLVED: full application PHPStan level 6 passes | Same scope, no baseline or blanket suppressions; enforced by composer check and CI. |
| RB-07 | OPEN: redistribution provenance unresolved | Require original GPL grant, compatible CAPTCHA/font rights and complete asset attribution. |
| RB-08 | OPEN: real stack passes; manual/representative acceptance incomplete | Complete manual accessibility, realistic volume, private dgp2026 and actual rewrite/CDN/metadata policies. |

### RB-01: implementation and native verification completed

At the original baseline the album multi-selection path passed unchecked IDs
to an interpolated deletion SELECT. A capture-only reproduction intercepted
`0' OR 'fixture'='fixture` before any database or filesystem operation; no
real exploit or deletion was attempted.

The earlier patch validates complete selections/direct delete and move calls
before processing and binds album/collection/picture deletion and move values.
Regression tests reject malicious IDs, mixed batches, arrays and invalid move
destinations before writes or filesystem changes. Its initial verification lacked
native servers; that gap is now closed.

MariaDB 10.6.26 and PostgreSQL 17.11 pass the full patched PHP matrix and all
three post-upgrade HTTP runs, including positive selected-album deletion.
**RB-01 is RESOLVED** by [executed native evidence](docs/rb05-08-validation.md).
See [patch scope and earlier reproduction](docs/rb-01-management-sql.md).
This does not certify every query against unknown vulnerabilities.

### RB-02/RB-03: compatibility follow-up

**RB-02 remains OPEN for acceptance**, with the observed application/test
failures corrected. Patched PHP 8.2.35, 8.3.36, 8.4.27 and 8.5.12 each pass
130 tests / 2,849 assertions in the current synthetic suite, and the native
matrix passes 130 tests / 3,246 assertions with six explicit skips. Experimental
8.6.0RC4 passes separately; it is not a supported
production release. Native Chrome/static nginx checks additionally pass on all
five runtimes (two tests / 118 assertions each). E_ALL remains enabled; warnings,
notices and deprecations fail PHPUnit. The original failed 8.5.11 audit is retained
in the initial report rather than treated as current evidence.

At that follow-up, eight native cases remained skipped and FPM was unverified.
The [current native/FPM evidence](docs/rb05-08-validation.md) now supersedes
those local gaps. Hosted CI has not been rerun for the working patch.
[Workflow run #11](https://github.com/xiphiasphotography/plogger-reloaded/actions/runs/39854526866)
failed in all stable PHP unit jobs and native-database jobs; the stable unit
logs report `HttpCompatibilityTest::tearDownAfterClass` warnings from
`phpthumb.class.php:1144`. The static-CDN job passed. Composer 2.10.3 emits an
upstream MB_ONIGURUMA_VERSION deprecation on RC4, although its platform check
exits 0. Do not suppress it or describe RC4 as fully clean tooling. Recheck with
a fixed upstream Composer and obtain passing hosted CI before closing RB-02.

**RB-03 RESOLVED for policy/configuration:** Composer/lock now require
`>=8.2 <8.6 || 8.6.0-RC4`, excluding 8.1 and untested 8.6 GA. CI pins the four
patched stable versions, with a separate RC4 job; PHPStan targets 80200. Current
setup/upgrade/contributor documents agree. Composer validated the lock, and its
platform requirements pass on all five builds. No dependency versions changed.
Hosted CI run #11 failed and remains required release evidence; see the
[run details](https://github.com/xiphiasphotography/plogger-reloaded/actions/runs/39854526866).
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

### RB-05: native verification completed; provenance/real-install gates OPEN

Real MariaDB 10.6.26 and PostgreSQL 17.11 each pass 130 tests / 3,246 assertions
with six explicit skips on PHP 8.2.35, 8.3.36, 8.4.27, 8.5.12 and separately
experimental 8.6.0RC4. Native fresh schema, RC1/php7/php8 migrations, no-op
reruns, interrupted recovery, locks, counts, image hashes and external dump/client
restore round trips were executed. All three post-upgrade HTTP runs pass on each
engine (35 tests / 1,949 assertions / four skips). No site database was contacted.

RC1 remains a modeled fixture, and its PostgreSQL version a synthetic projection.
Obtain a trustworthy original release/schema with its actual license grant and
compare it against the fixture. Complete a restricted interactive fresh install
and a reviewed real-install restore/upgrade, including the private reference
theme. Native fixture success does not authenticate upstream upgrade provenance.

### RB-06: full application PHPStan level 6 RESOLVED

Tooling, security and full application configurations all report zero errors.
The working baseline had 510 findings; the earlier 663 count is historical.
Contracts now describe real legacy return shapes and validation boundaries;
unreachable branches and the import helper name collision were corrected.
No baseline, ignore comments, rule weakening or blanket mixed contracts were
introduced. The application gate is mandatory in Composer and stable/prerelease
CI. The CI workflow was edited but not pushed or run remotely in this task.

### RB-08: verified disposable web stack; remaining acceptance OPEN

WSL nginx 1.30.5 → Apache 2.4.52 → PHP-FPM 8.4.27 as www-data, with a separate
MariaDB 10.6.23 cluster, passes 31 HTTP tests / 2,002 assertions / three mock-only
skips after fresh installation and each of the three fixture upgrades. Both
HTTP layers deny private resources and content execution; verified TLS cookies,
private custom sessions, local captured reset mail, metadata persistence and
60-second/256-MB configuration checks pass. Existing localhost:8080 staging
configuration, database and uploaded content were not changed.

Still required: manual keyboard/screen-reader/zoom acceptance, representative
large-directory/resource testing, private dgp2026 compatibility, real CDN mirror
and rewrite policies, historical metadata mapping, and real-install restore
acceptance. External SMTP delivery was not tested. The prior staging reports
need their own follow-up: fresh-install CDN initialization is corrected, but
existing stored CDN values are untouched; generated EXIF upload/import fixtures
persist correctly, without establishing the cause of every real-image failure.
See [full evidence and repeat commands](docs/rb05-08-validation.md).
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
