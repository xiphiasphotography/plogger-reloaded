# Phase 1 changes and validation

Date: 2026-10-14. Branch: `phase-1-php-compatibility`. Phase 0's uncommitted files were retained. AUDIT.md and AGENTS.md remain unchanged; this document records the implementation delta rather than rewriting the historical audit.

## Changes

| Files/area | Repair |
| --- | --- |
| Exifer GPS and phpThumb GIF | Replace removed curly offsets; bound short GPS strings; pass variables rather than assignment expressions to by-reference GIF parameters |
| PclZip | Fix func_get_args reference fatal, add a PHP 8 constructor while retaining the old method, replace the removed ereg call with a tested preg_match path |
| gettext streams/reader | Preserve StringReader constructor input, normalize null input to an empty string, declare cached properties, handle empty/invalid catalogs and empty file reads; guard seek/close on missing or closed file resources |
| Exifer main parser | Retain the library; provide a conditional gettext fallback where the native extension is absent; reject undersized APP1 markers before invalid fread lengths |
| phpThumb class | Check output GdImage objects correctly; avoid passing an unset cache directory to string functions |
| Root globals/config loader and legacy admin loader | Guard missing server keys, initialize debug scope correctly, fetch config rows instead of relying on SELECT rowCount, and delegate the obsolete duplicate loader to the shared loader |
| Shared gallery functions | Handle nullable EXIF fields, missing image dimensions/thumbnail configuration and fractional PostgreSQL epoch values; preserve public names/defaults/return contracts |
| All four bundled themes | Guard unavailable thumbnail width/height offsets; valid image markup/behavior remains unchanged and the helper still returns false when unavailable |
| Installer/functions | Guard missing proceed-session/setup state and HTTPS; correct PostgreSQL port DSNs, scope metadata inspection to the current schema/database, handle failed metadata queries, detect existing indexes correctly, repair configuration UPDATE syntax, and bind repaired statements |
| Album creation/admin/import/upload/RPC | Accept nullable album descriptions, skip extensionless files without warnings, preserve request arrays while stripping string values, supply missing action/default values and reject malformed RPC field offsets |
| Root thumbnail endpoint | Use normal configuration bootstrap and configured table prefix; bind picture ID, validate thumbnail type, return 404 for absent records/images instead of dereferencing false |
| Remote protocol | Add constructor/property declarations, remove deprecated interpolation, give its conflicting album helper a distinct internal name and supply the fifth add_picture argument; require a successful remote login session before writes |
| ZIP downloads/RSS/feedback | Initialize empty-directory ZIP fields, remove an unused extra argument, supply a missing download type, use backend-appropriate prepared RSS timestamp lookup and explicitly convert fractional epochs for display |
| Development tooling | Generate composer.lock, remove the audited lint allowance, reject compile deprecations, ignore deleted inventory files, add tests/API fixture and container-free native database CI jobs |

No complete library replacement, metadata mapping change, new gallery feature, schema migration or production operation was performed. Installer table definitions are retained. Only newly repaired SQL paths are parameterized; the existing concatenated-query inventory still belongs to Phase 2. The run_query extension adds an optional fourth argument and preserves all old argument names/defaults.

**Protective behavior change:** repaired remote write routes require the authenticated remote session cookie. Existing remote clients must retain that cookie after login. This prevents the PHP compatibility fixes from making broken unauthenticated writes usable; it does not complete remote/browser CSRF, throttling or upload auditing.

## Actual checks

Temporary native PHP 8.1.34, 8.2.34, 8.3.35 and 8.4.26 CLIs were used, with GD/EXIF and both PDO drivers enabled. PHP downloads and Composer were checked against official published SHA-256 values. MariaDB 10.6.24 was downloaded from its official archive with its published checksum verified. PostgreSQL 16.15 came from the official EDB binary archive over HTTPS (local SHA-256 `25e6fcdfb8caec38691bf461125e7564508760666f7b8e5dc6a5f0818f58f81e`; no independent published checksum was provided). Nothing was installed as a system service or added to the installed PHP configuration.

| Check | Result |
| --- | --- |
| Strict PHP lint, each supported version | 132 existing/inventoried PHP files, zero syntax failures or compile deprecations |
| PHPUnit, each supported version | 24 tests, 401 assertions passed |
| Real MariaDB 10.6.24 HTTP/installer fixtures, each version | 7 executed tests, 106 assertions passed; one PDO-double-only missing-path injection test skipped |
| Real PostgreSQL 16.15 HTTP/installer fixtures, each version | 7 executed tests, 106 assertions passed; same deliberate missing-path injection skip |
| Theme API signature regression | 106 inherited signatures captured from the original Git revision; names, parameter order/defaults and old calls preserved |
| Composer install/lock/platform checks | Passed on PHP 8.3; development tools only |
| Composer validation | Valid; expected recommendation warning because license provenance is unresolved and no speculative license field was added |
| Tooling PHPStan level 3 | Passed |
| Application PHPStan level 3 | Ran with 512 MB; **165 findings remain**, without a blanket baseline. Includes 118 possible undefined-variable findings, 35 unresolved constants, three argument-count findings, and additional symbol/PHPDoc/control-flow findings. Include/global inference contributes to this report; these are not all proven runtime bugs |
| Diff whitespace | `git -c core.whitespace=cr-at-eol diff --check` passes; plain diff check treats inherited CRLF line endings as trailing whitespace |

Regression reproductions were run against an isolated archive of original Git sources. They confirmed failing StringReader/catalog behavior, PclZip/GPS syntax fatals, failed GD output rendering and the obsolete demo directory. Additional failing tests caught absent native gettext, by-reference GIF assignments, undersized APP1 data, missing-image theme offsets, fractional PostgreSQL dates and nullable album descriptions before their corresponding repairs.

The continuation reproduced a fatal `fseek()` call on a missing translation file and a missing `SERVER_SOFTWARE` warning before repairing them. FileReader now tolerates seek/close on unavailable resources, and administration server information defaults to an empty server header and translated GD-unavailable text. Library tests explicitly enable E_ALL; an isolated `php -n` regression covers missing GD. Strict lint, PHPUnit and both native database matrices were rerun across PHP 8.1–8.4, with tooling/application PHPStan rerun on PHP 8.3. Temporary database processes were stopped and both test ports verified closed.

The HTTP harness serves a generated application copy on localhost, with synthetic JPEG/GIF fixtures. It tests installer setup and missing-session handling, native installer functions/config/schema creation, sample collection/album creation, admin login/options/manage/feedback/plugins/RPC defaults, multipart upload, FTP import preview/commit, small/large thumbnails, missing picture IDs, ZIP download, all four themes, RSS/XML and remote authorization/protocol initialization. The original synthetic photo hash is checked after image operations. Missing image paths are injected only through the PDO double; no user original is renamed or deleted.

Native databases are random `plogger_phase1_ci_*` databases inside new temporary clusters bound to localhost high ports. No production/site config, credentials, databases or image content were read or changed. Test clusters/processes are shut down after the validation matrix; their temporary binaries/data remain outside the repository for inspection.

## Remaining acceptance and release work

- GitHub Actions jobs are configured but have not been executed: no push was authorized. Native Ubuntu service startup commands still need that runner check.
- Complete the browser config-generation/download/install path on nginx/PHP-FPM or Apache staging, including actual mail, permissions and the maintainer's custom session path. The automated native fixture invokes installer functions; it is not a full browser setup wizard or Plesk deployment exercise.
- Validate embedded gallery integration, pretty URL host rules, representative large/corrupt camera metadata, archive edge cases and the private dgp2026 theme. The private theme and production routing inputs are absent.
- Review and address the application PHPStan findings, including legacy path/error branches. The passing fixtures do not certify every possible legacy input or optional bundled-library feature.
- Phase 2 security blockers remain: MD5/password-change bug, broader SQL preparation, admin CSRF/XSS, upload/archive defenses, session hardening/throttling and unsafe eval. The destructive legacy upgrader remains untouched and must not run against live data.
- Phase 3 will fully remove Exifer and PclZip in favor of native PHP EXIF/IPTC and ZipArchive functionality, with separate native XMP handling where required. Gettext and phpThumb are retained for auditing and modernization, preserving translation APIs, thumbnail behavior and caching. Their Phase 1 compatibility patches are interim repairs, not replacements. Licensing/provenance resolution remains pending. No public release readiness is claimed.

No commit, push, deployment, release, production migration or original-photo operation was performed. Stop after Phase 1; final staging/static-analysis acceptance remains pending.
