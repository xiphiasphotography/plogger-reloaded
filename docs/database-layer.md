# PDO database security and compatibility

The database work introduces `plog-includes/plog-database.php`, a small function-based PDO adapter. The application remains procedural PHP, without an ORM or runtime Composer dependency. Existing `run_query()` calls and theme-facing function signatures remain supported, returning PDO statements as before.

## Query construction

- Every `run_query()` execution now uses PDO prepare/execute, including statements without parameters. Connections disable emulated prepares. Positional/named values are bound separately with integer, boolean, null or string types; arrays and objects cannot become SQL values.
- New insert/update helpers generate placeholders from validated column identifiers. Updates require row conditions except for the inherited singleton config table. Table names come from the core table allowlist and configured prefix; column identifiers use a strict ASCII identifier grammar. Identifiers are never supplied through value placeholders.
- Gallery table names are consistently quoted through the adapter where normalized. MySQL retains the existing ANSI session mode, allowing standard double-quoted identifiers; PostgreSQL uses its native quoting. Prefixes are validated before SQL execution. Identifiers longer than PostgreSQL's 63-byte limit are rejected rather than silently truncated by the helper.
- Administrative table selectors and sort fields are allowlisted. Numeric pagination and IDs are normalized or bound. Existing integer-only SQL fragments and fixed SQL keywords remain permitted; SQL assembly for identifiers and syntax is distinct from bound user text.

Bound paths now include album/collection/photo/comment edits and inline edits, creation of photos/collections, album moves and path updates, settings/password values, FTP credentials, theme activation, comment creation, name/path lookups, search terms, image-ID lists, tag values and tag-ID deletion lists. Bundled plugin limits are bound; random-images selects PostgreSQL `RANDOM()` or MySQL `RAND()` appropriately. Other statements pass through the same prepared execution path, including inherited literal-only queries.

The disabled legacy upgrader remains disabled and is not migrated or run. Optional legacy tag tables are not created: their SQL construction is repaired, but the installer still has no canonical tag schema, and unfinished legacy tag features are not certified as complete.

## Behavior changes

- Apostrophes, Unicode, newlines and SQL-looking text are stored as data. Values are not pre-quoted or wrapped in another set of SQL quotes. In particular, inline comment edits and album-move paths no longer store SQL quoting artifacts.
- Album/collection names remain raw while slug creation and duplicate lookups run. A successful picture move now reports success rather than interpreting a successful database update as failure.
- The password-change bug is fixed: the existing MD5 verifier receives a hash of the submitted password rather than a hash of PDO's quoted representation. This initial quoting correction is followed by the [Phase 2 shared authentication migration](security.md): new passwords use versioned bcrypt hashes, and valid legacy hashes upgrade only after successful authentication.
- Search values use placeholders. Space-separated alternatives and `+`-joined required terms are preserved, with unambiguous qualified columns and grouping valid on both engines.
- Invalid management table selectors return HTTP 400. Unsupported sort expressions fall back to `id` and a fixed direction. Malformed pagination is normalized to integers; an empty image-ID list returns the existing no-result shape rather than generating an invalid `IN` expression.
- Installer and application connection checks share the DSN builder. Explicit ports now work for MySQL as well as PostgreSQL; empty ports retain driver defaults. DSN delimiters/control characters and unsupported drivers are rejected. The existing MySQL `utf8` connection setting is retained; this patch does not migrate character sets.

## Data and schema boundaries

No table, column, index, charset, constraint or migration definition is added or changed. No production connection, migration or content cleanup is performed. Existing installer schema declarations remain intact. The inherited explicit installer FTP setup still contains its existing optional FTP-column initialization; it is not newly introduced or invoked against an existing site by this task.

Integration tests create randomly named databases in disposable localhost-only MariaDB/PostgreSQL clusters, use the native installer and generate their own images. Additional CRUD checks use a temporary fixture table, not a production/gallery migration. Fixture configuration/rows are restored where tests change them, and image hashes are checked. Test fixtures never read the site's real config or images.

## Validation and remaining work

Tests cover prepared value separation, invalid identifiers/prefixes, non-default DSNs, sort allowlists, typed CRUD/nulls and case-sensitive quoted identifiers. HTTP/native regressions verify exact setting/comment storage, the password quoting correction, valid and malicious searches, management table rejection and the existing gallery/admin/upload/import/feed/download workflows. Existing XSS, CSRF and theme API tests remain enabled.

This database work does not complete Phase 2. MD5 authentication, other output-escaping surfaces, upload/archive validation, throttling, retained-library security and safe upgrades still require their scheduled work. Static analysis and real nginx/PHP-FPM/private-theme acceptance remain separate gates. Optional tag features and private plugins have no production-schema fixtures; independent third-party SQL callers must adopt the adapter and bound values themselves.

### Executed checks

- PHP 8.1.34, 8.2.34, 8.3.35 and 8.4.26: strict lint passed for 138 PHP files. Default PHPUnit runs passed with 45 discovered tests, 955 assertions and two intentional native-only skips per version.
- MariaDB 10.6.24 and PostgreSQL 16.15, each on every supported PHP version: native installer/HTTP runs passed with 16 discovered tests, 459 assertions and three intentional PDO-double-only skips per combination. Native PDO prepares were enabled. Both connection builders were exercised on explicit high ports.
- Tooling PHPStan level 3 passed. Expanded application analysis ran with 145 remaining findings and no blanket baseline; this is not a clean application analysis or Phase 2 release clearance.
- CRLF-aware diff checks passed. Plain `git diff --check` reports inherited CRLF characters as trailing whitespace. No tracked photo, thumbnail, license or sample-config files changed.
- Real-browser, nginx/PHP-FPM/Plesk, private-theme/plugin, real mail delivery and GitHub Actions checks were not run. Filesystem rename/move edge cases and optional tag schema variants still need staging coverage.

Temporary database processes were stopped after testing. No production configuration/data, migrations, commits, pushes or deployments were performed by this task.

### Changed files

- Adapter/API: `plog-includes/plog-database.php`, `plog-includes/plog-functions.php`, `plog-includes/plog-tag-functions.php`
- Administration: `plog-admin/plog-admin-functions.php`, `plog-admin/plog-admin.php`, `plog-admin/plog-feedback.php`, `plog-admin/plog-import.php`, `plog-admin/plog-manage.php`, `plog-admin/plog-options.php`, `plog-admin/plog-themes.php`, `plog-admin/plog-upload.php`, `plog-admin/includes/install-functions.php`
- Public consumers: `plog-download.php`, `plog-remote.php`, `plog-xml.php`, `plog-content/plugins/latest-comments/latest-comments.php`, `plog-content/plugins/latest-images/latest-images.php`, `plog-content/plugins/random-images/random-images.php`
- Tests: `tests/DatabaseTest.php`, `tests/HttpCompatibilityTest.php`, `tests/fixtures/FixtureDatabase.php`, `tests/fixtures/http-bootstrap.php`, `tests/fixtures/native-install.php`
- Analysis/docs: `phpstan.application.neon`, `ROADMAP.md`, `docs/database-layer.md`
