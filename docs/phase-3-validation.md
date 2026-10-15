# Phase 3 validation

Date: 2026-10-15. Branch: `phase-1-php-compatibility`. Scope: library modernization
only, preserving the uncommitted Phase 2 continuation. No deployment, commit,
production database/schema change, migration or original-image modification.

## Changes

- Remove the complete `plog-includes/lib/exifer1_7/` and
  `plog-includes/lib/pclzip-2-4/` directories (10 tracked files), including all
  runtime/test/static-analysis references. Historical audit and phase reports
  remain historical evidence.
- Add `plog-includes/plog-metadata.php` and `plog-includes/plog-zip.php`.
  Update `plog-admin/plog-admin-functions.php`, `plog-admin/plog-upload.php`,
  `plog-admin/includes/install-functions.php`, `plog-download.php`,
  `plog-includes/plog-security.php` and `plog-includes/plog-functions.php` for the
  replacements, requirements, bounded imports and compatible image processing.
- Retain/harden `plog-includes/lib/gettext/gettext.php`; retain/patch
  `plog-includes/lib/phpthumb/phpthumb.class.php` and `phpthumb.gif.php`;
  reduce `phpThumb.php` to its disabled-endpoint stub.
- Add `tests/MetadataTest.php`, `tests/GettextCatalogTest.php`,
  `tests/ThumbnailLibraryTest.php`; update `tests/ArchiveSecurityTest.php`,
  `tests/PhpCompatibilityTest.php`, `tests/HttpCompatibilityTest.php`.
- Add `tools/check-extensions.php`; update `.github/workflows/checks.yml` and
  `phpstan.security.neon`. Composer remains development-only, unchanged.
- Update README.md, ROADMAP.md, CREDITS.md, docs/development.md,
  docs/security.md; add docs/metadata.md, docs/libraries.md and this report.

Pre-existing Phase 2 modifications include `tests/SecurityInputTest.php` and
`docs/phase-2-validation.md`, plus changes to some shared files above. They are
preserved, not attributed to library replacement.

See [metadata mapping](metadata.md) and [library review](libraries.md) for exact
limits, retained interfaces, licenses, upstream review and behavior changes.

## Executed checks

Supported local binaries: PHP 8.1.34, 8.2.34, 8.3.35 and 8.4.26. All checks use
these binaries rather than the unsupported PHP version on the default PATH.

| Check | Result |
| --- | --- |
| `php tools/lint.php`, each supported PHP | 143 PHP files, zero syntax failures |
| Full PHPUnit suite, each supported PHP | Pass: 73 discovered tests, 1,408 assertions, 3 intentional skips per run; no warnings/deprecations |
| Native MariaDB 10.6.24 HTTP/install suite, PHP 8.1–8.4 | 24 discovered tests, 640 assertions, 4 intentional skips per run |
| Native PostgreSQL 16.15 HTTP/install suite, PHP 8.1–8.4 | Pass: 24 discovered tests, 640 assertions, 4 intentional skips per run |
| PHPStan tooling, level 6 | Pass |
| PHPStan security/native adapters, level 6, no baseline | Pass |
| Full application PHPStan, level 6 | 689 reported findings; unresolved legacy application acceptance gap |
| Composer validate | Valid; existing missing-license warning remains intentional pending provenance resolution |
| Read-only extension diagnostic | GD, EXIF, ZIP, fileinfo, mbstring, PDO, sessions available; MySQL/PGSQL PDO drivers present |
| Git whitespace / scope / retired-library reference checks | Pass with CRLF-aware whitespace check; no runtime references to retired library paths; no content/config changes |

Native tests use generated `plogger_phase1_ci_<random>` databases on localhost
high ports and a temporary website containing generated JPEGs. Both SQL engines
run strict/native PDO preparations. Tests cover installer, login, administration,
upload, FTP preview/commit, ZIP upload/preview/commit, original-byte downloads,
thumbnail caching, bundled themes, RSS/XML, and earlier XSS/CSRF regressions.
Stored EXIF camera/date/exposure/aperture/focal length/flash/ISO and user-edited
description are checked against both engines. No real configuration/photos are
copied into fixtures.

Library regressions cover synthetic EXIF/IPTC/GPS/lens/capture date, all eight
orientation dimensions and corner colors, corrupt/missing metadata, UTF-8 and
legacy text, preserved legacy display shapes, original hashes, square crop,
transparency/no enlargement, GD GIF conversion with a shell-metacharacter filename,
cache reuse/quality invalidation/corrupt-derivative regeneration, both MO byte
orders and plurals, malformed MO lengths/offsets, ZIP traversal/device names,
symlinks, encryption, duplicate names, count/ratio limits, CRC/truncation and
non-image content. Bundled-library token scans find no PHP eval tokens.

The default suite intentionally skips three native-persistence checks that run
in the database matrix. Native runs intentionally skip four PDO-test-double
injection scenarios covered by the default suite. Skips are not reported as
executed browser/database tests.

## Remaining limits and release gates

- No actual nginx/Apache/PHP-FPM/Plesk or browser staging run, production mail
  delivery, private dgp2026 theme, live upgrade/restore or actual GitHub Actions
  execution. Existing Phase 2/4 release gates remain open.
- GD raster fixtures pass; ImageMagick and all possible camera maker notes,
  animated images, WebP/AVIF configurations and deployment filesystem/concurrency
  behavior are not exhaustively tested. Keep PHP/GD/libzip patched on staging.
- No real translation catalogs ship. Synthetic UTF-8 MO fixtures verify parser
  compatibility; maintainer-provided catalogs/locales still need staging checks.
- XMP field mapping, import-prefill/user-clearing semantics, metadata backfill
  and cache cleanup policy are deferred to their scheduled work. Existing
  metadata-free/blank-field behavior is documented rather than silently changed.
- Original Plogger/CAPTCHA/assets licensing blockers remain; retained gettext
  and phpThumb notices/options are reviewed in libraries.md. This is not legal
  clearance or a claim of a complete penetration test.
