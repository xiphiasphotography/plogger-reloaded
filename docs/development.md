# Local development

Use VSCode/terminal with native PHP CLI **8.1–8.4**, Composer 2 and Git. Use an existing nginx/PHP-FPM or Apache environment and native MariaDB/MySQL or PostgreSQL, or a separate restricted Plesk staging domain. No Docker, containers, Node.js or frontend build is required.

Enable PDO, the selected `pdo_mysql` or `pdo_pgsql` driver, GD (JPEG/PNG and TTF support), **fileinfo, exif, zip and mbstring**, sessions and zlib. The installer checks the required image/metadata/archive extensions. Run `php tools/check-extensions.php` for read-only CLI diagnostics. PHPUnit additionally needs dom, xml and xmlwriter. Check CLI and FPM separately: their php.ini and extensions may differ. See [libraries.md](libraries.md) and [metadata.md](metadata.md) for limits and behavior.

```text
php -v
php --ini
php -m
composer --version
composer install
composer validate --no-check-publish
composer check
```

Composer dependencies are development-only. There is no application autoload/bootstrap change and no runtime Composer requirement. PHPUnit 10.5 supports the PHP 8.1 minimum ([official versions](https://phpunit.de/supported-versions.html)); PHPStan analyzes tooling at level 6 with PHP 8.1 syntax ([configuration reference](https://phpstan.org/config-reference)). The development manifest deliberately rejects unsupported PHP versions. Its license field is intentionally omitted pending provenance review; Composer may report a recommendation warning, so validation does not use `--strict`. composer.lock was generated with Composer on PHP 8.3 and is part of the reviewable patch. CI installs those locked development dependencies.

`composer lint` checks all tracked/unignored existing PHP files, skips deleted files and rejects syntax deprecations as well as errors. `composer test` runs helper, library, API and isolated HTTP regressions. `composer analyse` checks tooling at level 6; `composer analyse:application` is the explicit legacy application report, with remaining findings documented. Run `git diff --check`; for inherited CRLF sources use `git -c core.whitespace=cr-at-eol diff --check` so carriage returns are not mistaken for added whitespace.

## Manual staging setup

1. Create an isolated, access-restricted staging domain and dedicated empty database/user. Never reuse production credentials or originals for routine tests.
2. Copy application files. Set PHP-FPM to a supported version (8.3 is the first real-world target). Configure uploads (`file_uploads`, `upload_max_filesize`, `post_max_size`, writable temporary directory) for your synthetic fixtures.
3. Give the PHP user write access only where necessary: images, thumbs, uploads and temporary installer config generation. Keep site configuration untracked with conservative permissions; remove installer write access when finished. Honor the existing custom `PLOGGER_SESSION_SAVE_PATH`/session.save_path and verify sessions work. Do not use world-writable permissions.
4. Create the selected PDO-backed database and visit `plog-admin/_install.php`. Verify login, collection/album creation, upload/import, thumbnails, all bundled themes, RSS/download and embedded gallery. Record results for each driver; known audited failures must be fixed in their own phases.
5. Keep staging errors at E_ALL with server-side logging. Target 60 seconds/256 MB for production-like requests; inherited globals attempt runtime overrides, so these limits are not yet an implemented guarantee. Verify effective FPM settings without changing the application in Phase 0.

Never expose tests, vendor, tooling, Git metadata or diagnostic documents through a public staging docroot. nginx ignores .htaccess; use reviewed host configuration to restrict access. Production-ready hardening snippets and their routing tests belong to the security phase.

## CI and native database fixtures

GitHub Actions configures native PHP 8.1–8.4 syntax/unit/API/HTTP checks and a PHP × MariaDB/PostgreSQL matrix. It starts disposable native database processes in runner temporary directories; no containers or service images are used. The legacy application PHPStan report is explicitly nonblocking until its findings are addressed; the tooling analysis is blocking. GitHub Actions itself has not been executed from this local patch.

By default PHPUnit uses a deterministic PDO test double for isolated HTTP tests. For native integration, start an isolated disposable server on localhost at a port >= 20000, then set `PLOGGER_TEST_DRIVER` (`mysql` or `pgsql`), `PLOGGER_TEST_PORT`, `PLOGGER_TEST_USER`, and `PLOGGER_TEST_PASSWORD` before running `vendor/bin/phpunit --filter HttpCompatibilityTest`. The account needs CREATE DATABASE privileges only on that test server. The harness creates randomly named `plogger_phase1_ci_*` databases and temporary application copies, generates its own images, and never reads site configuration or user originals. Current fixtures drop their own generated databases on teardown; stop the disposable cluster after testing. Do not point this harness at a production/shared database server.

The native fixture invokes existing installer functions and creates the sample collection/album, then HTTP-tests login/admin, upload, FTP import, image/thumbnail rendering, all bundled themes, feeds and ZIP download. Missing-image error injection uses the PDO double and is intentionally skipped in native runs. This does not replace a full browser installation/config-download exercise on nginx/PHP-FPM or Apache. Run `vendor/bin/phpunit --filter UpgradeDatabaseTest` for source upgrades, restart/lock/backup checks and CLI regression tests. Set `PLOGGER_TEST_UPGRADE_FROM=rc1`, `php7` or `php8` before the HTTP suite to run against an upgraded synthetic installation. RC1 is a modeled fixture; its PostgreSQL variant is a synthetic projection, not historical support. See [UPGRADING.md](../UPGRADING.md) and [Phase 4 results](phase-4-validation.md).
