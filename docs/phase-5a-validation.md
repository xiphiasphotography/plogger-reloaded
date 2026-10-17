# Phase 5a metadata and FTP import validation

Implementation scope: metadata previews, shared ingestion priorities and bounded
FTP import. No schema migration, existing photograph update, production operation,
commit, push or deployment was performed. The Phase 4 work already present at the
start of this continuation was preserved.

## Changes

- `plog-includes/plog-metadata.php`: shared independent caption/description
  priorities, explicit-empty manual overrides, native bounded JPEG XMP parsing
  and original dimensions; existing EXIF/IPTC/GPS compatibility data retained.
- `plog-includes/plog-import.php` (new): bounded directory discovery, session-local
  cached previews, continuation state, source identity/containment checks and
  legacy Windows hash compatibility.
- `plog-admin/plog-import.php`: editable escaped fields and provenance, metadata
  facts/warnings, bounded import, retry/unselected edit preservation, progress
  and continuation controls. Existing destination suggestions and selection
  controls retained; unbounded recursive cleanup removed. No folders are deleted.
- `plog-admin/plog-thumb.php`: AJAX previews use registered sources rather than
  rescanning the entire uploads tree for every thumbnail.
- `plog-admin/plog-admin-functions.php`: resolve fields before moving files,
  preserve deliberate blanks/newlines and use a prepared album lookup.
- `plog-admin/plog-upload.php`, `plog-remote.php`: distinguish missing fields
  from deliberately empty strings when calling the shared ingestion policy.
- `plog-config-sample.php`: commented optional mapping/batch configuration.
- `tests/ImportMetadataTest.php` (new), `tests/HttpCompatibilityTest.php`:
  metadata/security/batch/form regressions and optional native persistence assertions.
- `phpstan.security.neon`, `phpstan.application.neon`: include the new helper.
- `README.md`, `ROADMAP.md`, `docs/metadata.md`, this file: status, mapping,
  intentional behavior changes, limits and validation evidence.

## Executed checks

Official Windows PHP CLI builds were downloaded to temporary tools; the machine's
default PHP 8.5 is outside the support matrix and was not used for acceptance.
PHPUnit 10.5.66 and PHPStan 2.3.1 from the existing development vendor directory
were used. Tests run on disposable website copies with generated synthetic images,
independent session storage and a PDO test double, never site configuration/data.

| Check | Result |
| --- | --- |
| Full PHPUnit, PHP 8.1.34 / 8.2.34 / 8.3.35 / 8.4.26 | Pass on each: 91 discovered tests, 1,582 assertions, 7 native-only skips; no runtime warnings/deprecations |
| `php tools/lint.php`, all four PHP builds | Pass on each: 161 PHP files, zero syntax failures |
| Metadata/import unit regressions, PHP 8.3 | Pass: 6 tests, 67 assertions (also included in the full matrix) |
| HTTP regressions, PHP 8.3 | Pass: 27 tests, 894 assertions, 4 native-only skips (also included in the full matrix) |
| Tooling PHPStan level 6 | Pass |
| Shared security/metadata/import PHPStan level 6 | Pass, no suppressions |
| Full application PHPStan level 6 | Fails: 663 application findings remain; see remaining acceptance work |
| Required-extension CLI diagnostics, PHP 8.3 | Pass; both PDO driver extensions available |
| CRLF-aware `git -c core.whitespace=cr-at-eol diff --check` | Pass; default diff check flags inherited CRLF endings as trailing whitespace |
| Content/config diff review | No tracked original-photo, thumbnail, production-config, binary, secret or cache changes |

Native MariaDB/PostgreSQL
servers and Composer CLI are unavailable in this current environment; the seven
native-only skips are not reported as passing database tests. CI already includes
both real database engines across PHP 8.1–8.4 and will execute the added native
persistence assertions, but CI has not been run from this patch.

Regression coverage includes separate configurable priorities, repeated/empty
metadata, deliberate blank/manual whitespace/UTF-8/multiline fields, Windows-1252
IPTC, XMP namespaces/arrays/language alternatives, malformed/hostile XML, metadata
facts, missing/corrupt metadata, escaped script/event-handler/textarea fixtures,
safe theme rendering, source changes/expiry/forged keys, bounded discovery with
1,005 directory entries, paginated HTTP previews, validation retries and preservation
of edits to unselected files. Synthetic originals are hash-checked before/after
preview/import. Existing reflected XSS, stored-description XSS, CSRF, upload,
archive, thumbnail, authentication, gallery/theme/API and upgrade regressions remain.

## Remaining acceptance work

- Run native database fixtures on both engines; verify the new persistence
  assertions for exact edited descriptions and explicitly empty captions.
  The picture schema is unchanged and the touched lookup uses native PDO
  placeholders; this is not a claim of a fresh real-engine run.
- Run nginx/PHP-FPM/browser staging checks and measure representative large/remote
  FTP directories against 60 s / 256 MB. Directory cursor reopening must skip
  earlier names; concurrent FTP changes may require restarting discovery. Individual
  decode operations can exceed the between-file time target on a slow server.
- Supply historical custom import code to confirm provisional mapping/truncation
  policy; supply the private dgp2026 theme for compatibility testing. No mapping
  from that unavailable code was invented.
- XMP is limited to standard JPEG APP1 title/description. Extended packets,
  sidecars and other image containers remain outside this implementation.
- Full legacy application PHPStan acceptance and earlier security/license/release
  gates remain open. No suppressed baseline or new runtime dependency was added.

Stop after Phase 5a. Oypo, CDN and Phase 6 UI work are untouched.
