# AGENTS.md: Plogger Reloaded

Instructions for AI coding agents (Codex, etc.) and human contributors working on this repo.

## 1. Project in one paragraph

**Plogger Reloaded** is a maintained continuation of Plogger, the simple self-hosted PHP photo gallery (collections > albums > photos). It starts from the `plogger_php8` fork (which itself builds on the PDO fork by wuffleton, which builds on the original Plogger 1.0-RC1 from 2009). Goals: runs cleanly on PHP 8.1 to 8.4, is secure by default, and can be upgraded in place from **original Plogger, `plogger_php7` and `plogger_php8`**. Simplicity is a feature: do not turn this into a framework.

- License: **GPLv3 is the current project assumption, not a completed provenance audit**. Before publication, inspect the original and fork license files and embedded third-party notices. Preserve copyright headers and compatible obligations; maintain `LICENSE` and `CREDITS.md` (original Plogger authors, wuffleton, xiphiasphotography). Never silently relicense upstream code.
- Language of code, comments, commits and docs: **English**. The maintainer speaks Dutch; UI strings go through `plog_tr()` so translations stay possible.
- Minimum PHP: **8.1**. CI matrix: 8.1, 8.2, 8.3, 8.4.
- Databases: **MySQL/MariaDB** (primary; production is MariaDB 10.6) and **PostgreSQL** (already supported via `PLOGGER_DB_TYPE`). Do not break either.

## 2. Ground rules

1. **Never commit secrets.** No real `plog-config.php`, passwords, DB dumps with real data, or customer photos. `plog-config-sample.php` is the only config in git.
2. **Small, reviewable commits**, one concern each, imperative subject line ("Replace MD5 login check with password_verify"). Work on branches, open PRs against `main`.
3. **Every behaviour change gets a test** (PHPUnit) or, for installer/upgrade paths, a CI fixture run. Fix a bug: add a failing test first.
4. **Run before every commit:** `php -l` on changed files, PHPStan (level raised per phase, see section 5), PHPUnit.
5. **Do not change the theme API silently.** Existing themes call functions in `plog-includes/plog-functions.php`. If a function is renamed or its output changes, keep the old name as a thin wrapper (deprecated, but working). See section 6.
6. **Upgrade scripts must be idempotent, support dry-run, and never destroy data.** Running them twice must be harmless.
7. **Never modify or delete original photos** under `plog-content/images`. Database migrations must not mutate `plog-content/images` or `plog-content/thumbs`. Any separately approved thumbnail regeneration writes derived files only and must support dry-run, restart and safe replacement.
8. **Prepared statements only** for any new or touched query. No new string-concatenated SQL.
9. **No new runtime dependency without a reason** noted in the PR. Prefer PHP built-ins (`ZipArchive`, `exif_read_data`, `password_hash`) where they meet requirements. Retain and audit the bundled gettext and phpThumb functionality as specified in phase 3; do not replace them merely to reduce dependency count. The new UI must not depend on Bootstrap, jQuery, Node.js or a frontend build step (see section 20).
10. **Ask rather than guess** when something depends on the maintainer's production setup (see section 9).

## 3. Baseline repo observations (from prior `plogger_php8` review; re-verify)

Layout (top level): `plogger.php` (integration entry: `the_plogger_head()`, `the_plogger_gallery()`), `gallery.php`, `index.php`, `plog-admin/`, `plog-includes/`, `plog-content/` (images, thumbs, themes `air`, `default`, `lucid`, `xip-bs`), `plog-config-sample.php`, `plog-load-config.php`, `plog-globals.php`, `plog-rss.php`, `plog-xml.php`, `plog-remote.php`, `plog-thumb.php`, `plog-download.php`.

Known problems to investigate (reported from a previous static scan and `php -l` on PHP 8.3; paths and line numbers may have changed):

| Problem | Where |
|---|---|
| Fatal on PHP 8: `$x{...}` string offset syntax | `plog-includes/lib/exifer1_7/makers/gps.php:82`, `plog-includes/lib/phpthumb/phpthumb.gif.php:1096` |
| Fatal on PHP 8: "Cannot use result of built-in function in write context" | `plog-includes/lib/pclzip-2-4/pclzip.lib.php:233` |
| Demo file with the same fatal | `plog-includes/lib/phpthumb/demo/phpThumb.demo.gallery.php:64` (delete the whole `demo/` dir) |
| `plogger_php7` only: curly-brace offset | `plog-admin/plog-load-config.php:98` (already fixed in php8) |
| Admin password stored/compared as plain **MD5** | `plog-admin/plog-admin.php:13`, `plog-remote.php:61`, `plog-admin/plog-options.php:63` |
| **Likely bug:** password change stores `md5($PLOGGER_DBH->quote(pw))`, login compares `md5($_POST['plog_password'])`. The extra quotes from `quote()` may make the hashes differ and lock the admin out. **Verify with a test first**, then fix as part of the hashing migration | `plog-admin/plog-options.php:63` |
| No CSRF protection anywhere | all admin forms/actions |
| ~279 direct `$_GET/$_POST/$_REQUEST` reads | everywhere; centralise gradually |
| SQL built by string concatenation; `quote()` results possibly wrapped in extra quotes | `plog-includes/plog-functions.php`, `plog-admin/*.php` |
| `eval()` | `plog-includes/lib/gettext/gettext.php:201` (audit/remove unsafe path while retaining translation functionality); `pclzip.lib.php:1525,1757` (removed with PclZip) |
| `session_start()` with no cookie hardening | `plog-globals.php:47`, `plog-includes/plog-captcha.php:21` |
| `_upgrade.php` needs hand-edited DB credentials and is not idempotent; `cdn_url` column must be added manually for upgrades | `plog-admin/_upgrade.php` |
| `cdn_url` only applied to **theme assets**, not to images/thumbs | `plog-load-config.php:114` |

Not problems (leave alone): `md5()` used for import file keys (`plog-import.php`, `plog-upload.php`, `plog-admin/plog-thumb.php`) is not security-relevant; `get_magic_quotes_gpc()` is already behind a version check (can be deleted, low priority). The commented-out `DEBUG Exif` block in `add_picture()` is harmless (delete when touching that file).

Config table: install code creates the schema in `plog-admin/includes/install-functions.php` (note `cdn_url` column at ~line 415 for MySQL and ~517 for PostgreSQL).

## 4. Upgrade requirements (hard requirement)

Reloaded must upgrade in place from three sources:

| Source | How to recognise it |
|---|---|
| Original Plogger 1.0-RC1 or older | no `PLOGGER_DB_TYPE` in config, mysql-only, no `cdn_url` column |
| `plogger_php7` fork | PDO, has `PLOGGER_DB_TYPE`, has `cdn_url` |
| `plogger_php8` fork | same schema as php7 fork |

Requirements for the upgrader (`plog-admin/_upgrade.php`, plus a CLI entry `php plog-admin/upgrade-cli.php`):

1. **Auto-detect** source version from existing config file and DB. No hand-editing of the script. Introduce a `schema_version` value in the config table; all future migrations key off it.
2. **Dry-run first** (`--dry-run` / checkbox): list every change it would make.
3. **Backup gate:** before any change, require a verified external backup of both the prefixed Plogger database tables **and originals/config**. Optionally offer a safe database-export helper; do not assume `mysqldump` exists or that a DB dump covers image files. Explicit confirmation and a rollback procedure are required.
4. **Numbered, idempotent migrations** (`migrations/0001_*.php`, ...). Each checks whether it already applied.
5. **Config migration:** handle missing constants (`PLOGGER_DB_TYPE`, `PLOGGER_DB_PORT`) using a safe PHP-aware approach, write a proposed new config next to the old one without replacing it silently, and preserve credentials/permissions. Never concatenate arbitrary untrusted config into executable PHP.
6. **Passwords:** keep existing MD5 hashes working; on first successful login, verify with MD5, then immediately re-store with `password_hash()`. No forced reset.
7. **Batch work** (thumbnail regeneration, EXIF backfill, dimension backfill) runs in chunks with a progress UI. Production limits to respect: **60 s runtime, 256 MB memory**.
8. **Theme check:** scan the active theme for calls to functions that no longer exist and report them before the upgrade starts (section 6).
9. **Docs:** `UPGRADING.md` with three short paths ("from RC1", "from php7 fork", "from php8 fork") plus rollback instructions.
10. **CI fixtures:** keep small sample installs (config + SQL dump, no real data) for each of the three sources; CI upgrades each and runs smoke checks.

### First real-world target: `degroteprijs`

The maintainer's main production site is the first upgrade that must work:
- Server: nginx 1.30, **PHP 7.4.33 (FPM)**, MariaDB 10.6, GD, sessions in a custom `session.save_path`.
- Base: `plogger_php7` fork. Custom theme: **`dgp2026`** (lives in the private repo `degroteprijs`, not in this repo).
- Upgrade order on their side: backup, copy to test domain on PHP 8.3, upgrade there, verify, then switch production. The default upgrade is a **staging copy moved to PHP 8.3 before running Reloaded**; no direct execution on PHP 7.4 is promised. Write a PHP 7.4 bridge only if the maintainer explicitly requests it.

## 5. Roadmap

Each phase is a milestone with an acceptance check. Do phases in order; do not start features before phase 2 is done.

### Phase 0: Repo setup
- [ ] Repo `plogger-reloaded` created from `plogger_php8` (keep history), README with status line, `CREDITS.md`, `CONTRIBUTING.md`.
- [ ] **No Docker or container tooling.** Develop in VSCode with locally installed PHP CLI, Composer and the maintainer’s existing web/PHP/database environment (or a separate Plesk staging domain). Document required PHP extensions, configuration and manual setup in `docs/development.md`. Never require Docker Desktop, Compose or container images.
- [ ] CI (GitHub Actions): `php -l`, PHPStan, PHPUnit and supported PHP/database coverage using a **container-free** runner setup (native services where supported). Keep a documented local equivalent for developers. If a specific CI runner cannot provide native PostgreSQL/MariaDB, document the gap instead of introducing Docker.
- **Done when:** a fresh clone installs via the web installer on a regular PHP/nginx or Apache staging environment without Docker; CLI linting and tests can be run from VSCode/terminal.

### Phase 1: Runs on PHP 8.3 with no fatals
- [ ] Fix or replace the four fatal files from section 3; delete `phpthumb/demo/`.
- [ ] Run with `error_reporting(E_ALL)`; fix deprecations, undefined variables/indexes, `null` passed to string functions.
- [ ] Smoke test: install, create collection/album, upload, import via FTP folder, thumbnails, login, theme switch, RSS, download.
- **Done when:** `php -l` clean on all files and the smoke test passes on 8.1 and 8.4 as well.

### Phase 2: Security (blocks any public release)
- [ ] Central DB layer; all queries become prepared statements; remove double-quoting.
- [ ] `password_hash()`/`password_verify()` with transparent MD5 upgrade; reproduce and fix the password-change bug with a test.
- [ ] CSRF tokens on every admin form and state-changing action.
- [ ] Output escaping everywhere (XSS); central input accessor replacing raw `$_GET/$_POST`. Explicitly close the three legacy XSS regression cases documented in section 12 (admin `r`, public `jump-menu`/unknown query parameters, and stored descriptions).
- [ ] Uploads: validate by content (finfo + image decode), safe filenames, no execution in `plog-content` (ship nginx and Apache config snippets in `docs/`).
- [ ] Sessions: `httponly`, `secure` (when HTTPS), `samesite=Lax`, regenerate on login; respect a custom `session.save_path`.
- [ ] Simple admin login throttling.
- [ ] Disable/remove any *reachable unsafe* `eval()` path before public exposure; if full library replacement remains in phase 3, maintain a documented release blocker until those paths are gone.
- **Done when:** automated tests and a manual checklist (SQLi, XSS, CSRF, upload of `.php`, path traversal in import) pass, PHPStan runs at level 6+, and no reachable unsafe `eval()` path remains.

### Phase 3: Selective bundled-library modernization
- [ ] **Completely remove PclZip 2.4** (`plog-includes/lib/pclzip-2-4/`) and every reference/runtime dependency. Replace required archive creation and extraction with PHP's `ZipArchive` (`ext-zip`). Preserve relevant ZIP behavior, validate archive entry paths and sizes, defend against Zip Slip/zip bombs, and test existing download/import workflows. Check `ext-zip` before ZIP operations, with clear installation guidance.
- [ ] **Completely remove Exifer** (`plog-includes/lib/exifer1_7/`) and every Exifer include, reference and runtime dependency. Replace EXIF extraction exclusively with PHP's **built-in EXIF extension**, principally `exif_read_data()`; use `exif_imagetype()` for type identification where appropriate and `exif_thumbnail()` only if embedded thumbnails are actually needed. **Do not replace Exifer with another third-party EXIF library.**
- [ ] Implement a shared metadata adapter for upload, FTP-import preview/commit and backfill. Preserve legacy `$exif[...]` keys and consumed value shapes through an adapter, not by retaining Exifer. Support orientation, capture dates, camera/lens, exposure and GPS values when present; normalize types and encodings safely. Keep original image files unchanged.
- [ ] Parse IPTC separately using PHP's built-in `getimagesize()` (APP13 data) and `iptcparse()`. PHP's EXIF extension is **not** an IPTC or general XMP parser. For explicitly required XMP fields, use a bounded, safe implementation based on built-in PHP XML facilities (`XMLReader`/`DOMDocument`), with external entity/network access disabled; no third-party image metadata library.
- [ ] Require/check the `exif` extension in the installer, CLI diagnostics, documentation and CI test matrix; present a clear actionable error when unavailable. Add regression fixtures for EXIF, IPTC, GPS, orientation, missing/corrupt metadata and the legacy `$exif` compatibility shape; test on PHP 8.1–8.4 where available.
- [ ] **Retain gettext translation functionality and the existing `plog_tr()` API.** Audit the bundled gettext implementation and translation formats for security, licensing, and PHP 8.1–8.4 compatibility; investigate native PHP `gettext` as a possible implementation **only** if existing translations, locale behavior, fallbacks, and plural forms remain compatible. Do not remove or replace the bundled gettext library automatically. Remove or safely eliminate any reachable unsafe `eval()` path before public release, even if the library is retained.
- [ ] **Retain phpThumb image processing and thumbnail behavior.** Inventory the exact phpThumb features, invocation paths, cache settings, formats, resize/crop/orientation rules, and theme-facing integrations that Plogger uses. Audit the bundled version for PHP 8.1–8.4, known security issues, licensing and maintained upstream versions. Patch/modernize it or use an appropriate maintained compatible version after verification; do **not** replace phpThumb wholesale with a custom GD/Imagick processor solely to remove a dependency. Remove obsolete demos, unnecessary public entry points and unused unsafe code; test outputs and cache behavior against representative originals. GD/Imagick may still be required as phpThumb backends where supported.
- **Done when:** Exifer and PclZip are completely removed, native EXIF and ZipArchive replacements pass regression tests, gettext and phpThumb functionality and public interfaces remain compatible, all retained third-party code is reviewed for licensing and vulnerabilities, and no reachable unsafe `eval()` or PHP 8.x fatal remains.

### Phase 4: Upgrade path (section 4)
- [ ] `schema_version`, migration runner, dry-run, backup gate, config migration, password migration, batch UI, theme check, fixtures for all three sources, `UPGRADING.md`.
- **Done when:** CI upgrades RC1, php7 and php8 fixtures and the result passes the phase 1 smoke test.

### Phase 5: Features requested by the maintainer
**5a. EXIF in the import screen.**
Current behaviour: the FTP import screen (`plog-admin/plog-import.php`, around lines 300 to 320) lists files with an empty caption `<input>` and description `<textarea>`; EXIF `ImageDescription` is only applied later inside `add_picture()` (`plog-admin/plog-admin-functions.php` ~340 to 350) when those fields are empty, truncated to 24 words for the caption. The maintainer previously built a custom variant that fills title and description from EXIF on FTP import.
Wanted: read EXIF/IPTC **when the import screen is rendered** and **prefill caption and description in the form**, so the user sees and can edit them before importing.
- Use **separate, configurable mappings** for caption/title and description. Suggested caption fallback: IPTC ObjectName (2:05) -> IPTC Headline (2:105) -> XMP `dc:title` -> EXIF `ImageDescription`. Suggested description fallback: IPTC Caption/Abstract (2:120) -> XMP `dc:description` -> EXIF `ImageDescription`. These are provisional until the custom import code is supplied; never copy the same field into both values unless that matches an explicitly defined fallback. Show the selected source next to each editable field.
- Show a few EXIF facts per file in the list (date taken, camera) so the user can sort/recognise files.
- Server-side import keeps the same fallback so non-JS and API imports behave the same.
- Must handle missing/broken EXIF, non-UTF-8 text (convert to UTF-8), multi-line descriptions (store as before; keep `nl2br` behaviour consistent with themes), and large folders (lazy/batched reads within the 60 s limit).
- **Done when:** import screen shows prefilled, editable values; unit tests cover JPEG with/without EXIF, bad encoding, and empty strings.

**5b. Oypo ordering module (optional, outside the core).**
Remove the existing Oypo-specific code from core. Re-add as an optional module using a small hook ("render under picture"):
- New nullable column `oypo_album_id` on the albums table (migration + installer + PostgreSQL variant); field on the album edit screen.
- Setting: minimum pixel size (long edge or width x height; owner to decide, see section 9).
- Store original image dimensions (`getimagesize`) at upload/import; backfill for existing photos in a batch job. Prefer existing schema fields if adequate and avoid redundant columns.
- Show the order button only if the album has an Oypo ID **and** the original is large enough.
- Escape and URL-encode every value in the link. Link format: **owner will supply the current code; do not invent it.**
- Keep the hook generic so another print service could be added later without touching core.

**5c. CDN URL.**
Keep `cdn_url` (already in the schema of the php7/php8 forks, so removing it would complicate upgrades). It must work for the maintainer's setup: a separate static host (nginx, no PHP) that serves only files, and also for S3/R2/Bunny/Cloudflare-style hosts.
- Apply `cdn_url` to **public theme assets, thumbnails and full images** using one URL-generation function (today reportedly only theme assets). Preserve fallbacks when the CDN is empty; never rewrite local filesystem paths.
- Validate on save: empty or a well-formed public `http(s)://host/path/`, normalised to a trailing slash. Reject credentials, fragments, control characters and unsafe URL schemes; URL-encode individual path segments.
- Never use the CDN URL for admin pages, downloads that must be authenticated, or the upload form.
- Document the expected directory mirroring (`plog-content/images`, `plog-content/thumbs`, `plog-content/themes`) and how files get there (rsync, bucket sync) in `docs/cdn.md`.

### Phase 6: Modernise and release
- [ ] Create **Plogger UI**, a small first-party, dependency-free CSS library shared by the new `reloaded` public theme and the refreshed administration; use native CSS Grid/Flexbox and progressive enhancement (section 20). Do **not** migrate `xip-bs` to Bootstrap 5 as a prerequisite.
- [ ] Build a responsive `reloaded` theme on Plogger UI and keep all bundled legacy themes working. A future Bootstrap 5 theme may ship its **own** local `bootstrap5.css` (and theme-specific JS only if actually needed); Bootstrap must never become a core or admin dependency.
- [ ] Installer cleanup (HTTPS detection and relative paths already exist).
- [ ] Release `v1.0.0-beta.1` only after phases 0–4 and all security release gates pass; mark phase 5 features as pending if incomplete. Release `v1.0.0` after phases 5/6 and final compatibility checks. Provide a ready-to-upload zip, checksums and release notes.

## 6. Theme compatibility

Existing themes must keep working. The owner's custom theme **`dgp2026`** is the reference case (not in this repo; the owner will provide a copy of `plog-content/themes/dgp2026/`).
- Keep a compatibility layer (`plog-includes/compat.php`) with wrappers for any theme-facing function that is renamed or changed. Mark with `@deprecated`, log a notice in debug mode only.
- Build `tools/theme-check.php`: scans a theme directory for calls to functions that do not exist and for PHP 8.x problems; the upgrader runs it and prints a readable report.
- The bundled themes `default`, `lucid`, `air`, `xip-bs` must pass the theme check in CI. The new `reloaded` theme must pass its own rendering/accessibility tests. Legacy themes retain their own CSS/JS; they are not silently rewritten to use Plogger UI.

## 7. Testing

- **PHPUnit:** DB layer, password migration, CSRF, native PHP EXIF extraction and legacy `$exif` compatibility, EXIF/IPTC/XMP parsing/prefill, CDN URL validation, Oypo eligibility, migration runner.
- **Upgrade fixtures** per source (section 4.10), run in CI.
- **Static analysis:** PHPStan starting at level 3 in phase 1, level 6 by end of phase 2.
- **Manual security checklist** in `docs/security-checklist.md`, run before every release.

## 8. Deployment notes (document these in `docs/`)

- Ship **nginx** snippets alongside Apache `.htaccess`: deny script execution in `plog-content/`, block direct access to config and `plog-admin` includes, optional pretty-URL rules. nginx ignores `.htaccess`, and production uses nginx.
- Mention PHP-FPM tuning for thumbnail generation (`max_execution_time`, `memory_limit`) and that import/regeneration jobs are chunked.

## 9. Open inputs needed from the maintainer

Do not guess these; leave a clearly marked TODO and continue with other work:

1. Current **Oypo code** (link format and parameters) from `degroteprijs` / the `dgp2026` theme.
2. **Minimum pixel size** for showing the Oypo order button.
3. A copy of **`plog-content/themes/dgp2026/`** for compatibility testing.
4. The maintainer's **existing custom EXIF-on-FTP-import code**, to match its behaviour (field mapping, truncation rules).
5. Whether the site uses **pretty URLs** (and the nginx rules for them), and roughly how many photos/albums it has.
6. Whether a **PHP 7.4-compatible bridge release** is wanted (default: no).

## 10. Codex execution protocol (mandatory)

This file describes the **target state**, not a claim that any feature is implemented. Treat the repository as the source of truth. Never claim a command, test, scan or deployment succeeded without actually running it and recording the outcome.

### Start of every task

1. Read `AGENTS.md`, `README.md`, `composer.json` (if present), `LICENSE`, relevant source files and tests. Run `git status --short` and identify the active branch. Do not reset unrelated changes.
2. Summarise the requested change, relevant code paths, risks, expected database impact and an incremental implementation plan. If a repo fact in section 3 is stale, update the documentation with evidence.
3. Keep changes within the requested phase or user-approved scope. Avoid unrelated formatting/refactors and never silently add frameworks or frontend build tools.
4. If blocked by missing project-specific input (section 9), create an isolated interface, a safe disabled-by-default placeholder or a TODO. Never invent production secrets, Oypo URLs, image thresholds or URL rewrite rules.
5. Before code edits, establish a reproducible failing test for a bug when feasible. Preserve tests for prior supported behaviour.

### Completion of every task

1. Run relevant syntax checks, tests and static analysis. Note explicitly what was **not** run and why.
2. Inspect `git diff --check` and the diff for accidental credentials, generated caches, binary/photo changes or unrelated edits.
3. Report changed files, behaviour, tests, risks, remaining TODOs and any manual staging steps.
4. **Do not** deploy to production, replace production configuration, migrate live data, delete photos, push commits, tag releases or merge PRs without explicit authorization. Creating a local commit is only allowed if the user requested it; otherwise leave the patch reviewable.
5. Keep a small, phase-oriented `ROADMAP.md` / issue checklist updated when scope changes. Do not mark checkboxes complete on intention alone.

### Suggested commands (verify actual repo scripts first)

```bash
git status --short
find . -name '*.php' -not -path './vendor/*' -print0 | xargs -0 -n1 php -l
composer validate --no-check-publish   # only when composer.json exists
vendor/bin/phpunit               # only when configured
vendor/bin/phpstan analyse       # only when configured
git diff --check
```

Never install or run unreviewed scripts downloaded from the internet as a substitute for a missing project test runner.

## 11. Database architecture, integrity and upgrades

- Build a small driver-independent database abstraction around **PDO**; do not introduce an ORM. Keep existing configurable table prefixes. Quote **identifiers** by validated whitelist and platform conventions (never via parameter placeholders); bind **values** with prepared statements.
- Support the existing MySQL/MariaDB and PostgreSQL differences for placeholders, auto-increment/identity, quoting, upsert, metadata inspection and DDL. A migration is incomplete unless both variants work or a limitation is explicitly documented and blocks release.
- Maintain one canonical schema per supported driver in the installer and matching incremental migrations. A fresh install and an upgraded install must have equivalent logical schema, indexes, nullable/default semantics and permissions.
- Build an inspect-only diagnostics command with checks for tables, columns, indexes, schema version, prefix and orphaned references. Do not blindly create indexes to suppress errors; identify conflicting names/definitions first.
- Migration identity must be versioned and recorded only after success. Record prior schema/version and completed steps, and treat concurrent invocations safely (lock / exclusive guard). If a migration is interrupted, it must resume safely or stop with recovery instructions.
- Do not assume transactional DDL on MySQL/MariaDB. Plan roll-forward/backup-based recovery for nontransactional operations; rollback of code alone does **not** reverse schema/data migrations.
- Before migration, perform read-only checks: database connection/driver, expected source schema, sufficient permissions and disk space where measurable, image-path existence samples, row counts for collections/albums/photos and active theme.
- After migration, compare record counts, relationships and representative photo paths against the baseline; verify login, albums, image rendering, thumbs and custom theme. No automated removal of orphan rows or files.
- Preserve IDs, album hierarchy, sort order, descriptions, captions, timestamps, metadata and image-relative paths unless an explicit reviewed migration changes them. Preserve legacy URL behavior or provide redirects with tests.
- Backups: document and test a restore on staging. External database dumps must be generated via appropriately authorized tools, not guessed SQL that might silently omit objects or use insecure shell arguments.
- Make upgrade operations available from CLI and web, but protect the web upgrader with strong authorization, CSRF protection, a one-run guard and post-upgrade disablement or removal.

**Database acceptance checks:** fresh install and upgrade for RC1/php7/php8 fixtures on each driver; second upgrade is a no-op; an interrupted migration recovers predictably; pre/post item counts agree; old images remain byte-identical in fixture tests.

## 12. Security baseline and error handling

- Authentication: use `password_hash()` and `password_verify()`, detect legacy MD5 hashes by strict expected format and upgrade only on a successful valid login. Use constant-time comparisons as appropriate and do not log password/hash values. Ensure all login routes, including `plog-remote.php`, follow the same policy or disable insecure legacy routes.
- Authorization: enforce access control **server-side for every state-changing and sensitive action**, including AJAX, uploads, imports, downloads and remote endpoints. Do not rely on hidden form controls or menu visibility.
- CSRF: protect every state-changing browser action (including AJAX, multipart uploads, settings, password changes and deletions). Use cryptographically secure session-bound tokens (`random_bytes()`), verify with `hash_equals()` before performing writes, reject missing/invalid tokens, and never allow state changes via GET. Cover authentication boundaries and login CSRF where relevant. Use HTTPS-aware `HttpOnly` and `SameSite=Lax` or `Strict` session cookies as defense in depth, not as a substitute for tokens. Preserve custom session storage and legitimate workflows. Rate-limit login and expensive public endpoints where practical.
- File handling: reject null bytes, traversal, symlinks escaping allowed roots, unexpected extension/content combinations, decompression bombs and unsafe archive entry paths. Limit file size, dimensions and pixel allocation before decoding. Use randomly generated temporary names, atomic renames where safe and conservative permissions.
- Treat all EXIF/IPTC/XMP data, captions and filenames as **untrusted**. Escape according to context (HTML text, attribute, URL, JSON, HTTP headers), not a single blanket sanitization. Do not interpolate user input into markup/SQL/filesystem paths.
- **Legacy XSS release blockers (source-review findings, not yet dynamically exploited):**
  1. **Reflected XSS in the admin login redirect:** `plog-admin/index.php` reads `$_GET['r']` into `$form_action` and outputs it unescaped in `<form action="...">`. Restrict the destination to an explicit allowlist of local admin paths, reject external/scheme-relative URLs and control characters, and HTML-attribute-escape the final value. Keep legitimate post-login destinations working.
  2. **Reflected XSS through public query parameters:** `plogger.php` collects unknown query parameters (including `jump-menu`) into `$config['query_args']`; `plog-includes/plog-functions.php` subsequently injects those values into hidden inputs and generated URLs without adequate context-specific encoding. Treat keys and values as untrusted, handle arrays and malformed inputs safely, construct query strings using correct URL encoding, and escape final HTML attributes. Preserve safe third-party query parameters and embedded-gallery compatibility; do not fix only the literal `jump-menu` name.
  3. **Stored XSS in album descriptions (audit the related fields):** `plog-admin/plog-manage.php` passes submitted `description` to `update_album()` in `plog-admin/plog-admin-functions.php`; `plogger_get_album_description()` in `plog-includes/plog-functions.php` may return raw HTML, and management views render it directly. Fix all relevant render paths, including admin, public themes, AJAX/inline editing and export where applicable; audit collection descriptions, photograph descriptions, titles/captions and imported metadata for analogous behavior. Preserve stored source text, legitimate line breaks and documented rich-text behavior. Plain text must be escaped at output; if HTML is intentionally supported, establish an explicit safe-HTML policy and test it. Never rely on SQL quoting to prevent XSS.
- **Legacy CSRF release blocker — album update (source-review finding; no live exploit performed):** In `plog-admin/plog-manage.php`, the `update-album` action passes `$_POST['pid']`, `name`, `description` and `thumbnail_id` to `update_album()` without a visible CSRF-token verification. A historical Plogger 1.0 RC1 proof of concept sends a forged POST to `plog-admin/plog-manage.php?level=albums&id=1` with `action=update-album`. Add a CSRF check before this write, and comprehensively audit `update-picture`, `update-collection`, `update-comment`, create/delete actions, settings, uploads/imports, remote endpoints and any other state-changing admin route. Do not treat a hidden `action` field, authentication or SameSite alone as CSRF protection.
- **CSRF regression tests:** Verify an authenticated cross-site-style POST with no token cannot change album data; also test malformed, missing, expired/invalid and correct tokens, authenticated/unauthenticated sessions, and permitted admin workflows. Assert database state before and after requests. Include HTTP-level tests rather than only token-helper unit tests. Do not automatically edit existing album data in a live environment.
- **Regression tests for the above:** exercise the historical entry points with harmless script tags, quoted HTML attributes, event-handler strings, encoded payloads, malformed or array query parameters, stored malicious descriptions and benign text/line breaks. Assert the resulting HTML/DOM is inert and normal navigation/editing still works. Include HTTP/browser-level checks in addition to helper tests, and cover both admin and frontend rendering; do not claim a fix solely because input is stored unchanged.
- Apply fixes as a focused security hotfix before exposing a Plesk staging instance publicly; do not defer these known reachable sinks simply because the broader security phase is still underway. Do not rewrite/destroy existing database content as a shortcut. If currently working in phase 0/1, integrate a focused patch and tests without restarting completed phases; document the remaining broader audit for phase 2.
- Configure HTTPS-aware cookies and session fixation defenses; honor production's custom session save path. Ensure secrets and diagnostics cannot be served from public directories.
- Log exceptions with a request correlation ID and useful file/operation context, without credentials, tokens, full filesystem secrets or personal metadata dumps. Public error pages show a generic message; detailed stack traces require explicit local/admin debug mode.
- Keep nonessential `plog-remote.php`, legacy demos, test fixtures and old library endpoints inaccessible by default until audited. Inventory direct-entry PHP files; verify each initializes authentication and configuration correctly.
- Review bundled library licenses and known vulnerabilities before release; retain necessary attribution. Do not assume replacing a library is safe without behavior tests.
- Supply production-ready Apache and nginx snippets with an explicit test matrix (including PHP-FPM routing) proving no PHP/script execution in `plog-content/` and on the dedicated static CDN host. These are deployment examples, not changes to live Plesk configuration.

**Public release blockers:** the three known legacy XSS paths and historical album-update CSRF path above until regression tests pass, other reachable reflected/stored XSS, unauthenticated admin/write access, upload script execution, reachable insecure `eval()`, unparameterized user-controlled SQL, missing CSRF on writes, exposed configuration or known destructive upgrade paths.

## 13. Image pipeline, EXIF/IPTC/XMP and FTP import

**Mandatory dependency and removal policy:** `ext-exif` is a required PHP extension for Plogger Reloaded. Installer, documentation and CI must check it (and Composer must declare it if runtime requirements are defined there). Removing `plog-includes/lib/exifer1_7/` is required, not optional. Do not retain an Exifer fallback. IPTC and XMP parsing remain distinct native-PHP pathways; native EXIF does not parse arbitrary XMP.

### Metadata behavior

- Create one metadata-reading adapter used by upload, FTP import (both preview and final commit) and backfill. EXIF extraction **must use the native PHP EXIF extension** (`exif_read_data()`), never Exifer or another third-party EXIF library. Preserve raw originals without modifying embedded metadata. For a running installation with the required EXIF extension available, metadata extraction failures for individual images must not prevent an otherwise valid import; surface warnings and allow manual edit.
- **Caption/title and description are distinct.** Use the provisional priority lists in phase 5a, with explicit empty-value fallback and user-overrides-first semantics. Never overwrite a nonempty user-edited field during import or later backfill.
- Implement bounded EXIF parsing with PHP `exif_read_data()`, IPTC parsing with `getimagesize()` + `iptcparse()`, and separately bounded XMP parsing using built-in PHP XML facilities. XML parsers must disable external entity/network resolution. XMP may contain language alternatives, arrays and namespaces; select language deterministically and normalize to UTF-8. Document mappings in `docs/metadata.md` including GPS treatment and date/time timezone ambiguities.
- For legacy compatibility, evaluate whether existing Plogger title truncation (24 words) is used or desired. Do not truncate the stored description; only apply title limits approved by the maintainer. Preserve line breaks as **data**; render safely in themes and avoid double `nl2br()` conversion.
- In the FTP import screen, present editable proposed values with provenance labels and indicate parsing failures. Save exactly the user's submitted values, including deliberate clearing, rather than silently reapplying metadata during `add_picture()`.
- Read a bounded number of files per request; pagination/batch continuation for large directories. Do not reparse every image on each redraw if source file and mtime are unchanged. Limit metadata size and provide cancellation/resume.

### Image processing

- **phpThumb remains the preferred existing image-processing layer.** Preserve its working behavior and caching; do not replace it with an original first-party implementation without a separately justified compatibility and security decision. Review/patch the shipped phpThumb code and remove its obsolete demo directory and exposed sample endpoints.
- Preserve image originals byte-for-byte and their orientation/metadata. Apply EXIF orientation to **derived thumbnails/previews**, avoiding double rotation. Never upscale unless expressly configured.
- Detect phpThumb backend requirements and installed GD/Imagick abilities at runtime. Format support (JPEG, PNG, GIF including animated behavior, WebP, AVIF) depends on libraries/versions; provide graceful fallback, never promise universal AVIF capability. Preserve transparency and use resource/pixel limits.
- Ensure predictable thumbnail cache keys based on the source identity plus all rendering options; regenerate invalid/missing derivatives without altering originals. Use safe concurrent writes and configurable cache lifetime/cleanup.
- Extract width/height once where possible, persist through the existing or migrated schema, and use resumable backfill for older photos. Differentiate pixel dimensions of the stored original from rotated display dimensions.
- Test filenames with spaces, accents, Unicode and reserved characters; corrupted images, EXIF without IPTC, IPTC without EXIF, XMP side cases, invalid encodings, large directories and inaccessible files.

## 14. CDN and static asset URL rules

- A CDN is optional. An empty `cdn_url` must preserve legacy local URLs exactly. Support a dedicated nginx-only static hostname as the main deployment example; allow HTTPS object-store/CDN URL bases without assuming synchronization is automatic.
- Centralize public asset URL construction. Map files by validated relative paths under `plog-content/images/`, `plog-content/thumbs/` and `plog-content/themes/`; preserve any existing compatibility path shapes. URL-encode **path segments**, not an entire URL; prohibit path traversal and protocol-relative URLs.
- Do **not** rewrite local disk paths, admin actions, authenticated downloads, dynamic thumbnail endpoints, signed/private assets or nonpublic resources. If an asset cannot be served statically, it remains origin-hosted until a reviewed publish flow exists.
- Support and document origin fallback only if its semantics are unambiguous. Do not silently degrade private resources to public CDN links.
- Document syncing for nginx static mirror and, separately, bucket storage (`rsync` or supported provider workflow), cache control, immutable derivative filenames/invalidation, cross-origin considerations, HTTPS and safe headers. Credentials never belong in the repository or URL fields.
- Test subdirectory deployments, trailing slashes, base URL with a path prefix, percent-encoding and CDN disabled/enabled without breaking theme assets or photo pages.

## 15. Oypo integration contract (optional feature)

- Implement a minimal documented picture-action/hook interface in core. Oypo lives in a separately enabled module; disabling it must restore core behavior without broken links or errors.
- Support one optional Oypo album ID per Plogger album, with server-side validation and schema migration for MySQL/MariaDB **and** PostgreSQL. Confirm whether existing custom code already has an Oypo field before adding another.
- Use dimensions of the **original** photo and a configurable eligibility rule; no fixed minimum until the maintainer specifies the exact width/height or long-edge policy. Missing dimensions means no order button and an optional admin warning, not a guessed eligibility result.
- Render the order action under a photo only when the module is enabled, album ID exists, dimensions pass, and the required URL can be generated. Use an accessible translated label through `plog_tr()` and `rel="noopener noreferrer"` for new-window links.
- The provider URL format and required identifiers are **blocked on the supplied original integration code**. Never derive or invent a working Oypo URL from assumptions. Unit tests use illustrative non-live stub URLs.
- Ensure no customer/order/payment data is stored in core merely to display the link. Do not claim an external order was placed or verified.

## 16. Theme/public API and backwards compatibility

- Inventory documented and de facto theme-facing functions, globals, template variables and hooks in `plog-includes/plog-functions.php` and `plogger.php`. Preserve function names, parameter order/defaults, return shapes, output escaping expectations and URL semantics unless a documented compatibility wrapper exists.
- Include embedded gallery use (`the_plogger_head()`, `the_plogger_gallery()`), standard gallery route, RSS, XML, downloads and admin preview in smoke testing. Never hardcode Joomla-specific integration in core; Plogger must remain framework-independent.
- A theme checker can only approximate compatibility by static analysis. It must report **possible** missing functions/PHP issues, not guarantee a theme works. Use runtime smoke tests with all bundled themes; test `dgp2026` when its files are provided.
- Keep custom themes and user content untouched by upgrades. If a default theme is replaced, preserve old theme folders and settings; release notes identify deprecations. Plogger UI must be opt-in for public themes; a theme may bundle Bootstrap 5 or any other self-contained stylesheet without loading it globally.
- Maintain simple translation hooks `plog_tr()` for user-facing strings; document encoding and fallback locales.

## 17. Test matrix, CI and performance budgets

- Test PHP 8.1/8.2/8.3/8.4 on supported DB drivers with the most practical CI matrix; at minimum all PHP versions get syntax/unit tests and each database driver gets installation/upgrade/integration coverage. Explain any matrix reductions explicitly.
- Use separate synthetic fixture data for fresh install, RC1, php7, php8 and corrupted/incomplete schema. Ensure fixtures contain sample albums, different encodings, metadata variants and deterministic test images; **never** include production photos or credentials.
- Security tests cover malformed paths, CSRF, SQL injection, the three historical reflected/stored XSS cases and the historical album-update CSRF case from section 12, XSS in metadata, forged image extensions, archive traversal, oversized decode, unauthorized download and unauthenticated action routes.
- Add browser or HTTP-level smoke tests for admin login, album management, import preview, metadata edits, image rendering, CDN URLs, new Plogger UI theme layouts and Oypo module disabled/enabled. Tests should assert public-facing output, not just internal helpers.
- Performance baseline: production PHP request budget approximately **60 seconds / 256 MB** (from initial setup notes; recheck on staging). Batch imports/backfills so each web request stays below both; CLI may have separate configured limits. Record representative batch sizes and query counts.
- Paginate album/photo listing and import directory scanning. Avoid N+1 database queries, per-request full album rescans, metadata parsing on every view and rebuilding thumbs on every request.
- Provide an optional read-only `doctor` CLI mode for configuration/driver checks, directory permissions, schema/indexes, missing originals/derivatives and detected thumbnail/metadata libraries. No repair/deletion without an explicit separate command and dry-run.
- Before public releases, run tests on an actual staging copy that mimics nginx + PHP-FPM + MariaDB 10.6 and verify byte-identical originals and production-theme behavior.

## 18. Documentation, release and definition of done

Required repository documents (create when the relevant phase begins):

| File | Purpose |
|---|---|
| `README.md` | Status, requirements, supported PHP/database versions, quickstart and feature availability |
| `ROADMAP.md` | Phase checklist, decisions and known blockers |
| `CONTRIBUTING.md` | Local setup, checks, test commands, PR/commit conventions |
| `CREDITS.md` / `LICENSE` | Attribution and reviewed licensing |
| `UPGRADING.md` | RC1/php7/php8 upgrade paths, backup and tested restore/rollback |
| `docs/security.md` | Authentication, upload execution prevention, permissions and deployment security |
| `docs/security-checklist.md` | Release security gate and reproducible manual checks |
| `docs/metadata.md` | EXIF/IPTC/XMP field mapping, encoding, precedence, user edits |
| `docs/cdn.md` | Dedicated static nginx CDN and provider/bucket examples |
| `docs/theme-api.md` | Public theme API, compatibility promises, deprecations |
| `docs/plogger-ui.md` | First-party CSS library architecture, tokens, components, layering, size budgets and opt-in theme integration |
| `docs/deployment.md` | nginx/Apache/PHP-FPM/Plesk-independent installation, permissions, configuration |
| `CHANGELOG.md` | User-visible releases, migrations, security/compatibility notes |

**Definition of done for an implementation task:** requested functionality works; backwards compatibility evaluated; tests added/passing; both SQL backends considered; unsafe inputs reviewed; translations where applicable; documentation updated; no unrelated data changes; tests and unverified assumptions reported. A phase is complete only once its named acceptance criteria pass, not when its code was merely written.

**Release gates:** no security blockers (section 12), reproducible install, fixture upgrades, staging restore exercise, supported PHP/db tests, bundled-theme smoke tests, license review, clear upgrade documentation and release artifact checksums. Beta release notes must plainly label not-yet-implemented phase 5/6 capabilities.

## 19. Explicit unresolved decisions and implementation boundaries

The missing inputs from section 9 remain open. Additionally confirm before those features are declared finished:

1. Whether an existing Oypo DB field or plugin is already present in the private code, and what exact photo identifier/URL parameters the current link expects.
2. Exact pixel eligibility logic (minimum long edge, both dimensions, and any orientation/aspect rules).
3. Exact IPTC/XMP/EXIF caption and description mapping based on the maintainer's historical import change, including deliberate blank fields and title truncation.
4. Desired CDN deployment directory mapping, static URL shapes and whether files are mirrored by an existing sync job.
5. Whether existing downloads are public or permission-protected, and whether dynamically generated images are allowed behind the CDN.
6. Whether the first release needs a 7.4 bridge (currently **no**) and whether any legacy external integrations depend on remote endpoints.
7. The exact license provenance for original Plogger and bundled libraries (GPLv3 currently assumed, not verified from the repository in this document).

**Do not stall unrelated tasks because these answers are pending.** Implement other phases safely, document the blocker, and request the minimum missing information when reaching the affected functionality.


## 20. Plogger UI: first-party CSS architecture (approved direction)

**Decision:** Build and maintain a compact, first-party **Plogger UI** CSS library instead of adopting Bootstrap, Pure.css, Bulma, Tailwind or another general-purpose CSS framework for the new interface. This is a design target for phase 6, **not** a claim that these files already exist. Plogger must remain a lightweight, server-rendered PHP gallery with no mandatory CSS/JS build pipeline.

### Scope and separation

- **Plogger UI Core:** CSS custom properties/design tokens, minimal base/reset styles, typography, responsive containers/layout, and reusable primitives (buttons, forms, tables, alerts, navigation and pagination).
- **Plogger Gallery:** collection/album/photo grids, thumbnail cards, captions, photo navigation, pagination and accessible lightbox presentation. Use CSS Grid/Flexbox and semantic HTML, with progressively enhanced vanilla JavaScript **only** for interactions that require it.
- **Plogger Admin:** administration-only components, import forms and previews, status messages, panels, tables, settings and responsive navigation. Do not ship admin-only styles or scripts to public gallery pages.
- **Theme layer:** `reloaded` opts into the shared core/gallery styles and supplies only its own theme overrides. Existing `default`, `air`, `lucid`, `xip-bs`, and private `dgp2026` themes remain independent and functional; do not force their markup or CSS to change.

### Proposed file layout (confirm against real repository before creating)

```text
plog-content/
  ui/
    css/
      tokens.css
      base.css
      layout.css
      components.css
      gallery.css
      admin.css
    js/
      gallery.js            # only if necessary
      lightbox.js           # only if necessary
  themes/
    reloaded/
      theme.css
      ...existing theme entry files per Plogger API...
    bootstrap5/             # OPTIONAL future separate theme; do not create by default
      bootstrap5.css        # theme-local, never loaded by the Plogger core
      theme.css
```

The paths are an initial proposal; choose locations that fit the existing theme loading and CDN path behavior. Do not introduce redundant `plogger.css` bundles or duplicate the same rules across core, admin and themes. Plain separate CSS files may be served as-is; if an optimized distribution bundle is helpful, generate it through a small optional documented tool or release packaging, **not** a required Node/npm/Sass/PostCSS step.

### Design and implementation rules

1. Prefer **native modern CSS**: Grid, Flexbox, custom properties, logical properties, `clamp()`, `min()`, `max()`, media queries and container queries when useful. Supply reasonable fallbacks for any feature that would otherwise make key controls unusable. Never require JavaScript for basic gallery navigation or forms.
2. Use predictable `plog-` prefixes for reusable component classes and `--plog-*` design tokens to minimize collisions with existing themes, embedded gallery hosts and third-party styles. Do not reset global host-page styling for embedded gallery use; scope potentially disruptive rules to an explicit UI root.
3. Where `@layer` is used, document precedence (`reset`, `base`, `layout`, `components`, `theme`, etc.) and note that unlayered author rules take precedence over normal layered declarations. Avoid surprising theme override behavior.
4. Keep styling configurable via tokens rather than copying full component styles for color, spacing, fonts and border-radius. Respect `prefers-reduced-motion`, keyboard navigation, visible focus, sufficient contrast and meaningful semantic elements.
5. Avoid icon-font dependencies and Font Awesome as a new core requirement. Prefer existing accessible text labels or small locally maintained SVG icons where necessary; no external runtime font/CDN required.
6. A lightbox is a separately testable progressive enhancement, not a reason to load an entire UI framework. Support keyboard closing/focus management, image alternatives and non-JS links. Choose a minimal vanilla JS implementation only after auditing current behavior.
7. Keep a **size budget, not a hard byte limit**: target approximately **7–11 KB gzip for public CSS** and minimize extra admin CSS; measure actual compressed bytes in CI/release checks once assets exist. Prioritize correctness, accessibility and maintainability over arbitrary micro-optimizations.
8. No mandatory **Bootstrap, Pure.css, Bulma, Tailwind, jQuery, Node.js, npm or frontend compilation** in the Plogger core, new `reloaded` theme or admin. Avoid external CDN dependencies for the default installation.
9. **Bootstrap 5 remains allowed as a theme-local optional choice.** A future `bootstrap5` theme may bundle a pinned `bootstrap5.css` and, only if necessary, its own JS. Load these assets **only** when that theme is active; do not change core markup/APIs just to suit Bootstrap, and do not load the Bootstrap stylesheet globally or in admin.
10. Preserve the ability to deploy by `git pull` / Plesk Git deployment onto an ordinary PHP-FPM/nginx or Apache host. Deployable CSS and JS must already be in the repository/release archive, with no server-side build step.
11. CDN URL generation for Plogger UI assets must follow section 14. Validate correct URLs with CDN disabled/enabled and with application installs in subdirectories. Legacy themes must continue to resolve their own assets unchanged.

### Acceptance criteria

- New `reloaded` public theme and refreshed admin are responsive and usable without Bootstrap or jQuery; no mandatory build tooling or external network assets.
- Core/gallery/admin assets are loaded only on relevant pages; themes can override token values without editing library source. No CSS leakage into an embedding host page.
- Existing themes, including `xip-bs` (its legacy Bootstrap version), still render without being converted. A future optional theme-local `bootstrap5.css` is supported by the asset-loading contract but need not be implemented now.
- Automated/smoke tests cover responsive collection/album/photo layouts, admin forms, focus and keyboard access, no-JS navigation, and CDN URL mapping; record actual CSS gzip sizes when files exist.
- Document module boundaries, class/token conventions, supported override points, load order and any browser support assumptions in `docs/plogger-ui.md`.

**Implementation timing:** Do not start a large frontend rewrite during PHP compatibility, security, library or upgrade phases. Implement Plogger UI primarily in **phase 6**, with minimal earlier changes only where needed to maintain safe admin behavior or test coverage.
