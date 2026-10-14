# Plogger Reloaded repository audit

Date: 2026-10-14. Scope: the existing checkout, with read-only comparisons to the adjacent php7/php8 forks. Only this report was created; no fixes, configuration changes, database operations, deployments or commits were performed.

## Evidence and limitations

**Updated development direction:** this report preserves the original source findings and historical recommendations. The updated `AGENTS.md` supersedes the blanket library-replacement recommendation below: Phase 3 removes Exifer and PclZip for native PHP EXIF/IPTC and ZipArchive functionality, with separate native XMP handling where required, but retains and modernizes gettext and phpThumb. Phase 1 compatibility repairs do not implement those replacements. Plogger UI remains Phase 6 work. See [ROADMAP.md](ROADMAP.md) and [Phase 1 validation](docs/phase-1-validation.md) for subsequent implementation status and checks; the baseline limitations below describe the original audit, not the current tooling state.

`AGENTS.md` was read completely, including sections 16–19. Findings below distinguish confirmed source defects from runtime consequences that still require tests. This is a static audit, not a penetration test or certification of PHP/database compatibility.

- Inventory: 130 PHP files. Searches used `rg --no-ignore` because ordinary searches omitted relevant files in this environment.
- `.git/HEAD` identifies `main`; `.git/config` identifies the Plogger Reloaded GitHub origin. `git status --short` failed with an ownership/safe-directory error. Command-scoped safe-directory attempts also failed with installed Git 2.35.2. No global Git configuration was changed. Initial cleanliness and inherited commit history therefore remain unverified.
- `php -v` failed: PHP is unavailable on `PATH`; Composer was also not found through command discovery. A broader PHP executable search was cancelled without finding a usable executable. No PHP lint, PHPUnit, PHPStan, web smoke tests or PHP 8.1–8.4 execution was completed.
- No root Composer manifest, PHPUnit/PHPStan configuration, test suite, upgrade fixtures or GitHub Actions workflows were found. The Composer manifest inside phpThumb describes that library only.
- No running database or staging credentials were supplied. MySQL/MariaDB and PostgreSQL installation, SQL behavior, upgrades and performance remain untested.
- Runtime PHP claims were checked against the official [PHP 8.0 migration guide](https://www.php.net/manual/en/migration80.incompatible.php), [PHP 8.2 deprecations](https://www.php.net/manual/en/migration82.deprecated.php), and [PDO PostgreSQL DSN documentation](https://www.php.net/pdo-pgsql.connection). No CVE attribution is claimed without a separate advisory/version/reachability review.

## Actual structure and requirements

| Area | Existing implementation |
| --- | --- |
| Public entry points | `index.php`, `gallery.php`, `plogger.php`; RSS, XML, comments, print, download, thumbnail and remote PHP endpoints at the root |
| Administration | `plog-admin/`: login, upload, FTP import, management, feedback, options, themes, plugins, RPC and previews; web installer and one-shot upgrader |
| Shared code | `plog-includes/plog-functions.php`, tag functions, CAPTCHA, JavaScript and bundled libraries |
| Content | `plog-content/images`, `thumbs`, `uploads`, `translations`, `plugins`, `themes` |
| Themes/plugins | Themes `default`, `lucid`, `air`, `xip-bs`; plugins `latest-images`, `latest-comments`, `random-images` |
| Configuration | Root sample config; root loader/globals; an additional stale loader under `plog-admin/` |
| Documentation | `README.md`, original `readme.html`, `AGENTS.md`, `LICENSE`, library/plugin documents |

This is procedural PHP with PDO and filesystem storage, without a framework or frontend build system. README still presents the php8 fork as unfinished and claims PHP 8.0 work; `readme.html:55–60` still advertises PHP 4/MySQL 3.23. Neither is evidence of supported Reloaded requirements. PHP 8.1 is the target in AGENTS.md, not an established working minimum.

The current installer explicitly checks PDO, GD and sessions (`plog-admin/includes/install-functions.php:84–115`). A deployment also needs the selected `pdo_mysql` or `pdo_pgsql` driver, writable content/cache directories, usable sessions, file uploads and zlib for ZIP functionality. CAPTCHA uses a TTF font and GD text rendering. Mail is used for installation/reset/notification flows. Exifer is a bundled parser, so native EXIF is not the current core metadata requirement. ZipArchive, finfo and bounded XMP support are proposed replacements, not implemented requirements. Optional phpThumb ImageMagick paths do not establish that Imagick is necessary. Extension capability and PHP-FPM settings must be verified on staging.

`plog-globals.php:5–11` attempts to raise runtime to 300 seconds and memory to 128 MB when below its thresholds. This conflicts with the planned 60-second request budget; it does not prove the host permits those overrides. Custom `PLOGGER_SESSION_SAVE_PATH` is already honored.

## Verification of AGENTS.md baseline assumptions

| Assumption | Result |
| --- | --- |
| Listed layout and four bundled themes | Confirmed; additional public comment/print endpoints and bundled plugins also exist. |
| GPS curly-brace offset | Confirmed at `plog-includes/lib/exifer1_7/makers/gps.php:82`. |
| GIF curly-brace offsets | Confirmed at `plog-includes/lib/phpthumb/phpthumb.gif.php:1096–1098`. |
| PclZip fatal construct | Confirmed `&func_get_args()` at `plog-includes/lib/pclzip-2-4/pclzip.lib.php:233`; no local lint reproduction. |
| Demo fatal file | Curly-brace offset confirmed at `phpthumb/demo/phpThumb.demo.gallery.php:64`; the previously reported write-context fatal is not the construct at that line. Demo directory remains present. |
| php7-only admin loader offset | Confirmed in adjacent php7 loader at line 98; Reloaded uses square brackets. Reloaded's duplicate loader still has stale local includes and HTTP-only URL logic. |
| MD5 authentication | Confirmed in admin, remote, installer and reset paths. No password_hash/password_verify migration found. |
| Password-change quote bug | Confirmed source mismatch at `plog-admin/plog-options.php:63` versus `plog-admin/plog-admin.php:13`: options hashes PDO's quoted, trimmed password; login hashes the submitted password. Driver-backed failing test remains required before fixing. |
| No CSRF anywhere | Too broad: public comments validate a session token (`plog-comment.php:61`), generated by `plogger_get_form_token()`. Reviewed admin writes lack equivalent validation. |
| Approximately 279 request reads | Stale metric. A lexical scan found 563 GET/POST/REQUEST occurrences across all PHP, including libraries and non-read uses; this is not 563 distinct vulnerabilities. |
| SQL concatenation | Confirmed. Many values are quoted or IDs cast; concatenation alone does not establish an exploitable injection in every query. |
| Three eval sites | Confirmed at gettext line 201 and PclZip lines 1525/1757; reachability is discussed below. |
| Sessions unhardened | Application cookie hardening absent. Admin login already calls `session_regenerate_id()`; production cookie policy may additionally come from php.ini. |
| Unsafe manual upgrader; missing CDN migration | Confirmed. |
| CDN only affects theme assets | Confirmed for existing URL construction. `CDN_URL` is also defined in `plogger.php:112–116`, but image/thumb generation uses gallery_url. |
| php7/php8 schema equivalence | Installer source is byte-identical in Reloaded and both adjacent forks (SHA-256 `F10C91074198BBC69CC505DF5327A356CBD0CB53F1F0361986548039AE70402A`). Actual deployed schemas remain unverified. |

## PHP 8.1–8.4 compatibility

Confirmed source blockers affecting the entire target range:

1. Removed curly-brace offsets in GPS, GIF and demo files listed above. GPS is conditionally included by Exifer (`exif.php:827`); phpThumb conditionally includes the GIF parser (`phpthumb.class.php:4419`). A conditional include limits exposure, not the syntax defect.
2. PclZip's reference to a built-in function result at line 233 prevents the ZIP upload path from working. Its class also retains `function PclZip(...)` at line 159 without a modern constructor, so merely repairing syntax is insufficient.
3. `plog-remote.php:29` uses a class-name constructor for `response`, without `__construct`; initialization does not occur automatically on PHP 8. `add_image()` additionally calls `add_picture()` with four arguments although its declaration requires five (`plog-admin/plog-admin-functions.php:236`), producing an argument-count failure when that route reaches the call.
4. `plog-thumb.php:9` requires nonexistent `plog-include/plog-functions.php`, loads globals without the normal configuration loader, and queries hardcoded `plogger_pictures`. This endpoint is broken independently of PHP version and table-prefix configuration.

Warnings/deprecations and candidates needing execution:

- `plog-admin/plog-import.php:9` puts optional `$type` before required `$preselect`; signature deprecation and omitted-argument behavior need coverage.
- `${...}` interpolation appears throughout installer/admin/remote SQL and output; deprecated from PHP 8.2. Dynamic properties in gettext (`gettext.php:83–85`, cached header/table properties) and `response::set_key()` also require review for 8.2–8.4.
- `generate_exif_table()` trims every DB field (`plog-functions.php:247`) although `exif_date_taken` is nullable. Passing null to internal string functions needs PHP 8.1+ regression coverage.
- Unguarded `$_SERVER['HTTPS']` (`plog-load-config.php:95`, installer configuration), request keys in RPC/comments/uploads and extension lookups in `get_files()` can generate warnings on ordinary missing/malformed input.
- Exifer camera construction can leave `$exifMakeModel` unset before use; malformed EXIF and wrong types require fixtures. `$desc` can be overwritten by fallback after deliberate clearing.
- Removed APIs, stricter internal argument types, GD object handling and library-specific arithmetic require complete lint and runtime scans. This audit does not claim exhaustive 8.3/8.4 compatibility or absence of additional defects.

The magic-quotes calls are correctly behind a PHP version guard (`plog-functions.php:1122–1138`). Import-file MD5 keys are identifiers, not password protection. Gettext streams/readers already have modern constructor wrappers; they should not be mistaken for PclZip's constructor defect.

## Critical security risks

| Priority | Evidence and impact |
| --- | --- |
| Critical: unauthenticated remote writes | `plog-remote.php` dispatches `new-album` and `add-item` without a session/authorization check. `login()` only sets response status and establishes no authenticated state. Album creation is a confirmed authorization defect; the upload route currently also has the argument-count blocker above. Repairing that blocker alone would expose its unchecked write path. |
| Critical: unsafe upload storage | `add_picture():236–310` moves files into public originals before decoding metadata or generating thumbnails. `$ext_array` is used for collision checks, not to reject extensions. Admin upload checks filename extension, but no central content/decode/pixel validation exists. ZIP upload extracts arbitrary entry types to public uploads (`plog-upload.php:51–80`), without an application allowlist or expansion budget. Script execution depends on server routing, which was not tested. |
| Critical: destructive upgrader | `_upgrade.php:3–40` has no authentication, CSRF, dry-run, backup gate or lock. Once configured, a web request runs DDL. EXIF dates are held only in memory while the old column is dropped/recreated; interruption can lose them. Repeated runs fail on duplicate columns. |
| High: admin CSRF / unsafe methods | Options, upload/import, management, feedback, themes and RPC perform writes without session-bound form-token checks. Feedback consumes `$_REQUEST` for delete/approve (`plog-feedback.php:13–40`), admitting GET-style mutation. Login/logout also lack CSRF protection. |
| High: stored/reflected XSS | Raw caption/description getters default to unescaped values (`plog-functions.php:2636`, `2697`); bundled picture templates echo these directly. EXIF ImageDescription feeds these fields. Options inserts CDN text into an HTML attribute without escaping (`plog-options.php:176`), RPC echoes edited content directly, and breadcrumbs interpolate names/captions. HTML data currently mixes text and markup, so escaping changes require compatibility tests. |
| High: weak authentication/reset | Unsalted MD5, short non-cryptographic password generation (`plog-functions.php:8–14`), no observed login throttling, reset through GET and emailed plaintext passwords. Reset key is not cleared immediately upon successful reset; it is cleared at subsequent successful login (`plog-admin.php:16–19`, reset branch). Installer also displays/emails plaintext credentials. |
| High: SQL safety gaps | `run_query():569` calls PDO::query, not prepared execution. Album/collection queries interpolate caller-provided sort expressions (`plog-functions.php:2138–2140`, `2208–2210`); other query paths do validate sort choices. Search manipulates already-quoted terms (`2010–2050`), creating fragile SQL semantics. Exact unauthenticated injection exploit chains remain unverified; parameterization plus identifier whitelists is mandatory. |
| High: deployment defenses missing | `plog-content/images/.htaccess` contains only BEGIN/END markers; no repository-wide deny-execution nginx/Apache examples were found. Dynamic library endpoints and demos ship in public directories. Full-image hiding relies on generated Apache rules, ineffective by itself on nginx. |
| Medium/high: resource/path exposure | Recursive `get_files():32` follows directories without explicit symlink/root containment or pagination. Downloads assemble files and ZIP data in memory with only allow_dl as a policy gate (`plog-download.php`), allowing expensive public requests when enabled. Thumbnail cache writes use direct RenderToFile and timestamp validity, without application atomic replacement or complete source/options cache identity. |

Gettext eval executes a translated plural expression, but ordinary `plog_tr()` uses `translate()`, and no core caller of `ngettext()` was found outside the library. PclZip eval is conditional on callbacks; current ZIP extraction passes no callbacks and presently cannot load on PHP 8. Therefore eval existence is confirmed, while attacker-controlled reachable execution is not demonstrated. Audit or disable these paths before exposure; do not label them proven unauthenticated RCE without a call/data-flow test.

Cookie flags are not explicitly set in globals/CAPTCHA. Respect the existing custom session path when centralizing policy. Detailed SQL/exception output is exposed when debug is enabled; the standalone upgrader prints exception details unconditionally. No correlation-ID logging design exists. `plog-comment.php` accepts an arbitrary redirect destination; same-origin redirect validation is needed.

## Database implementation and integrity

`connect_db():513` constructs mysql/pgsql PDO connections, enables exceptions and associative fetches, and replaces MySQL session sql_mode with ANSI to permit double-quoted identifiers. It uses charset utf8, not utf8mb4. MySQL port is intentionally ignored; PostgreSQL incorrectly uses `dbport` in both connection and installer connection check (`3092`), where PDO documents `port`.

Installer `create_tables():342–557` defines six tables per driver: collections, albums, pictures, comments, config, thumbnail_config. MySQL uses auto_increment, fixed-length text, MyISAM and inline indexes. PostgreSQL uses serial, text defaults, separate indexes and inet comment IPs. No relationship foreign keys or schema_version exists. Original dimensions are not persisted in either picture schema. MySQL config admin_password is varchar(64), requiring expansion before general password_hash adoption; PostgreSQL uses text.

Confirmed installer/database defects:

- `maybe_add_index():29` inspects information_schema columns instead of index definitions; re-running PostgreSQL index creation can collide. Names are not prefix-scoped.
- Installation and table/column detection filter table name without database/schema scope (`is_plogger_installed():490`, installer helpers). Another schema with matching names can produce false positives. Installation detection does not verify the complete expected schema or populated configuration.
- Failed `run_query(..., false)` can be dereferenced as a statement in detection helpers instead of handling false.
- `configure_plogger():632–641` omits a comma between gallery_url and cdn_url in its existing-config UPDATE. New configuration sets CDN to the gallery URL rather than the planned empty optional default.
- MySQL NOT NULL fields without defaults are omitted by configuration INSERT. Success depends on effective SQL mode; strict-mode staging must cover this rather than assuming default acceptance.
- SELECT rowCount is relied on throughout. Replace with explicit fetching/count queries where needed; cross-driver correctness is not established by PDO alone.
- Installer schema defaults/lengths differ logically between drivers; MyISAM offers no application transaction safety. Recovery plans cannot depend on transaction rollback of current data changes or MySQL DDL.

Existing timestamp driver helper and PostgreSQL sequence naming (`getTimeFn()`, `getSerialObj()`) demonstrate intentional backend support, not tested equivalence. Test fresh install and RC1/php7/php8 upgrade on both drivers, including non-default prefixes, namespaces, interrupted/repeated execution and counts/relationships/photo-byte preservation. PostgreSQL fixtures represent each source's logical input schema; original RC1 is MySQL-only, so any cross-driver conversion must be explicitly defined rather than claimed as historical support.

## Installer and upgrades

`_install.php` has an already-installed check and a browser setup/config-download flow, not a protected migration runner. It relies on session-held install values; proceed requests without them can fail. It creates sample collection/album records. Config generation (`install-functions.php:688`) escapes password quotes/backslashes but concatenates other fields directly into executable PHP strings; quoted host/user/database values can produce invalid or injected generated configuration. Validate and serialize every value safely in a future change.

The only upgrader is manual, MySQL-specific `_upgrade.php`; it adds show_exif and converts EXIF dates. It does not add cdn_url, detect source version, support original pre-RC1 versions, compare schemas, generate proposed configs, check themes or backfill in batches. README explicitly warns against repeated runs and requires manual config/schema edits. No upgrade CLI, migration directory, backup/restore test, exclusive guard, diagnostics command or UPGRADING.md exists.

Database impact of this audit: none. Future schema work needs password-column capacity, schema-version/migration state and reviewed optional feature fields, with installer/migration parity. Do not run the existing upgrade script on a real installation as an audit check.

## Gallery API and themes

Embedding APIs `the_plogger_head()` / `the_plogger_gallery()` exist in `plogger.php`; template routing falls back to default templates. The de facto API includes loop functions, current_picture/current_album globals, URL constants, configuration globals, clean-mode getter variants, translation hooks and output-producing helpers. Wrappers, theme-check tooling and docs/theme-api.md are absent.

Navigation/detail signatures already accept HTML icons (`plog-functions.php:3038–3064`), and breadcrumbs return list-based markup. README acknowledges Font Awesome-related API changes. Existing function names alone cannot establish output compatibility with original themes. Preserve default arguments and escaping/markup expectations through explicit wrappers or versioned documentation when changing them.

All four bundled themes call the current gallery functions; no runtime compatibility claim is possible without rendering. `xip-bs/head.php` loads Bootstrap 4.4.1, Font Awesome and Google Fonts, and interpolates gallery URL into JSON-LD without JSON serialization. Legacy theme CSS/markup may not match altered helper output. `dgp2026` is absent. RSS, XML, print, embedded use, admin preview and the broken thumbnail endpoint need smoke coverage alongside normal gallery views.

## Metadata, CDN and Oypo

### EXIF/IPTC

`add_picture()` loads Exifer after the original has been moved, persists selected camera/exposure/date fields and falls back to IFD0 ImageDescription for both caption and description (`plog-admin/plog-admin-functions.php:310–355`). Copyright is read but not included in the INSERT. No shared metadata adapter, encoding normalization, configurable provenance/mapping or bounded backfill exists.

The reported 24-word caption behavior is inaccurate in this checkout: `tokenTruncate():16–29` calculates a width but returns `implode(array_slice($parts, 0, 1))`, the first split token. Its intended width parameter measures characters, not words. Confirm desired legacy behavior with the maintainer before preserving or changing this defect. Both fallback fields apply nl2br before storage, mixing line-break data with HTML.

Import preview fields are empty (`plog-import.php:313–314`); no EXIF facts/provenance are shown. Clearing an input triggers fallback during add_picture rather than preserving an explicit blank. Exifer exposes raw IPTC bytes (`exif.php:954–960`); these are not mapped to editable title/description. APP1 XMP is explicitly skipped (`932–941`), so structured XMP support is absent. Large folders are recursively scanned and sorted in full; JavaScript thumbnail progress is not a bounded metadata/import job.

### CDN

Both installer schemas contain cdn_url. Root loader applies it to theme_url; plogger.php defines CDN_URL. Thumbnails and full images still use gallery_url (`plog-functions.php:657`, `664`, `689`, `2778`). Options saves the string without scheme/host/credentials/fragment validation or trailing-slash normalization. Static asset paths are concatenated without consistent per-segment encoding. There is no synchronization/static-host deployment document. Preserve local path/URL semantics and authenticated/dynamic routes when centralizing public asset URLs.

### Oypo

A case-insensitive source search found no Oypo integration, album ID field or order-button implementation in this checkout. AGENTS.md's instruction to remove existing Oypo code is an unverified private-code assumption, not a confirmed core finding. No generic picture-action contract or eligibility setting exists. Do not invent provider URLs or dimension thresholds; obtain the original private integration and determine whether it already has a database field.

## Libraries, documentation and licensing

Bundled dependencies include Exifer 1.7, PclZip 2.4, PHP-gettext, phpThumb (class identifies `1.7.18-202207181544`, not README's 1.7.14), Prototype 1.4.0, legacy Lightbox, Bootstrap 4.4.1, Font Awesome 5 and an additional hand-written ZIP class in plog-download.php. Their age, broad file-processing surfaces and confirmed syntax defects justify replacement/review; this is not a claim that every dependency has a verified CVE.

Root LICENSE is GPLv3 and byte-identical in both adjacent forks. `readme.html:114` says GPL2 and links nonexistent LICENSE.txt. Without the original licensing grant/history, GPLv3 provenance remains unresolved. No CREDITS.md exists. Preserve original authors, wuffleton and xiphiasphotography plus component notices.

Confirmed notices requiring a release provenance inventory:

- Exifer and PHP-gettext: GPL version 2 or later, in source headers.
- PclZip: GNU LGPL; bundled gnu-lgpl.txt is version 2.1.
- phpThumb: multiple licensing options and bundled notices; document the selected compatible option.
- Lightbox: Creative Commons Attribution 2.5 header.
- Font Awesome: icons CC BY 4.0, fonts SIL OFL 1.1, code MIT notice.
- **Release blocker needing review:** CAPTCHA header (`plog-includes/plog-captcha.php:6–7`) declares CC Attribution-Noncommercial-Share Alike 3.0. Its noncommercial restriction raises a direct conflict with unrestricted GPL redistribution; establish permission/provenance or replace it. This is a source-notice finding, not a completed legal determination.
- Fonts, icons, images, Prototype and the custom ZIP implementation require full attribution/license tracing too.

README is still the fork README, and readme.html is original RC1 documentation. ROADMAP.md, CONTRIBUTING.md, CREDITS.md, UPGRADING.md, CHANGELOG.md and the requested docs/ guides are absent. No security checklist, native CI matrix or documented local checks exist. No Docker infrastructure was used or introduced.

## Recommended implementation order and dependencies

1. **Phase 0:** establish a review branch, provenance inventory, native PHP/Composer setup, minimal PHPUnit/PHPStan harness and container-free CI. Capture synthetic existing behavior and source fixtures. License resolution blocks publication, even if coding continues.
2. **Phase 1:** add reproductions for syntax/constructor/argument and thumbnail endpoint defects; restore PHP 8.1–8.4 execution, address normal-request warnings and run install/gallery smoke tests on both drivers. Keep an explicit security blocker list; PHP compatibility does not make this safe to publish.
3. **Phase 2:** prioritize remote authorization and upload execution prevention, then prepared PDO queries/identifier validation, password capacity and transparent MD5 migration, reset hardening, CSRF/method enforcement, escaping, sessions and throttling. Audit direct-entry scripts, including installers/library demos. Add failing tests before bug fixes. Block reachable unsafe eval before public exposure.
4. **Phase 3:** replace ZIP, thumbnail, metadata and translation libraries behind tested adapters. Preserve EXIF return shape and theme output contracts; handle orientation/encoding/resource limits without modifying originals. Security may require temporarily disabling unsafe routes earlier, without advancing feature work.
5. **Phase 4:** design canonical schemas and numbered migrations together; build protected CLI/web runners, inspect-only diagnostics, dry-run, verified backup gate, proposed config migration, locks/recovery and theme report. Run all source fixtures on both drivers and verify repeated/interrupted upgrades, counts and unchanged original bytes. Restore staging backups before claiming upgrade readiness.
6. **Phase 5:** metadata preview depends on the phase-3 adapter and confirmed mappings; CDN depends on secure centralized URL construction and known static mirroring; Oypo depends on the generic hook, dimensions, migrations and supplied provider contract. No phase-5 feature work before phase 2 passes.
7. **Phase 6:** modernize responsive themes after compatibility/security boundaries are stable. Beta requires phases 0–4, licensing and release gates; label pending features explicitly. Full release additionally requires the requested feature/compatibility checks.

Password migration depends on hash-column capacity and shared handling across login/reset/remote routes. The upgrade runner depends on secure authentication/CSRF, safe config handling, canonical schemas and metadata adapters for batch work. Theme checking depends on an explicit public API inventory and supplements runtime tests. CDN and Oypo must never bypass private/download authorization. Neither schema changes nor a code rollback substitute for a tested database/files restore.

## Inputs and unresolved assumptions

- TODO: supply dgp2026 and current Oypo/import modifications, including existing Oypo schema fields and provider photo identifiers.
- TODO: specify minimum pixel rule, orientation/aspect policy, metadata caption/description precedence, truncation and deliberate-clearing behavior.
- TODO: confirm pretty URLs/nginx rules, album/photo counts and representative folder sizes.
- TODO: confirm CDN mirror directory/URL layout, synchronization process and whether downloads or originals have access restrictions.
- TODO: identify required remote/external integrations before disabling legacy endpoints.
- TODO: clarify original and fork licensing grants, CAPTCHA permissions and other bundled assets before release.
- TODO: provide an appropriate native staging/test environment and PHP executables for validation. The reported production nginx/PHP/MariaDB versions, budgets and custom session configuration were not independently verified.
- Default remains a staging copy on PHP 8.3 before upgrade. No PHP 7.4 bridge is assumed; request one explicitly if needed.

Source recognition based only on DB_TYPE/cdn_url cannot reliably distinguish the php7 and php8 forks; their installer schemas match. Treat them as a shared legacy schema family unless additional trusted version evidence exists. Original RC1/pre-RC1 fixtures and historic behavior remain missing. Git ancestry, deployment defenses, installed extensions, live schema drift, actual theme compatibility, exploitable SQL/path chains and the complete advisory inventory remain unverified.

## Validation and stop point

Completed: source inventory/searches and focused code review; full instruction review; adjacent-fork schema/license hash comparisons; official PHP documentation checks. No live services or photo data were changed. The report contains source locations and review prerequisites, not claimed passing implementation tests.

Not run: PHP lint (no usable CLI located), PHPUnit/PHPStan (no configured project runners), Composer validation (no root manifest/tool), PHP/database matrix, installer/upgrade/security/browser smoke tests and staging restore (no test environment). Git status/diff checks are blocked by repository ownership protection. A separate filesystem hash comparison is used to confirm existing working files remain unchanged; report whitespace is checked directly.

Stop here: do not implement fixes, change AGENTS.md/ROADMAP.md, migrate data, install infrastructure, commit, push or publish as part of this audit.
