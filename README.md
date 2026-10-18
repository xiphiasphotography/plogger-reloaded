# Plogger Reloaded

**Status: Phase 5a metadata import, Phase 5b optional ordering infrastructure and Phase 5c CDN routing implemented; Oypo ordering stays disabled pending maintainer inputs. Release acceptance gates remain open. Not ready for public deployment.** [AUDIT.md](AUDIT.md) documents inherited PHP syntax, security and licensing blockers. Tooling support does not establish application compatibility.

A continuation of Plogger, the simple self-hosted gallery: collections > albums > photos. Based on `plogger_php8`, with procedural PHP and inherited Git history preserved.

The target is **PHP 8.1–8.4**, MySQL/MariaDB (production target MariaDB 10.6) and PostgreSQL via PDO. Deploy as a traditional PHP website on nginx/PHP-FPM or Apache. No Docker, containers, Node.js or frontend build is required. Composer installs development tools only; application bootstrap remains unchanged.

## Setup

Read [local development](docs/development.md), [CONTRIBUTING.md](CONTRIBUTING.md) and [Plesk Git deployment](docs/deployment.md).

On an isolated staging domain, create a dedicated database/user, copy the application files and visit `plog-admin/_install.php`. It generates the untracked `plog-config.php`. Enable PDO with the selected driver, GD, fileinfo, mbstring, sessions, zlib, EXIF and ZipArchive (ext-zip). Keep credentials and uploaded content outside Git. Native synthetic installer/gallery checks pass on supported PHP versions; nginx/PHP-FPM staging acceptance and security hardening remain pending.

```text
composer install
composer check
```

Strict lint and PHPUnit cover PHP 8.1–8.4. Tests include synthetic HTTP requests, native MariaDB/PostgreSQL fixtures and inherited theme API signatures. `composer analyse` checks tooling at level 6; `composer analyse:application` reports unresolved legacy findings. See [Phase 1 validation](docs/phase-1-validation.md) for coverage and limits. No runtime Composer installation is needed.

The old destructive upgrader is removed. Follow [UPGRADING.md](UPGRADING.md) for read-only inspection, verified-backup gates, reviewed plans and the opt-in web/CLI migration tools. The intended first migration uses a backed-up staging copy on PHP 8.3; no PHP 7.4 bridge is provided.

An optional [public static CDN](docs/cdn.md) can serve photographs, generated thumbnails and theme assets from a separate nginx hostname or a public storage/CDN URL. Administration, imports and protected downloads remain on the application origin. Synchronization is handled separately by the owner; no SDK or automatic storage publishing is included.

## Embedding

Require `path/to/plogger.php` from your PHP page, call `the_plogger_head()` in its head and `the_plogger_gallery()` in its body. Inherited theme function names and parameter defaults are preserved. Broken PHP paths have targeted fixes; remote write clients must retain the authenticated session cookie.

## Roadmap and licensing

[ROADMAP.md](ROADMAP.md) tracks pending acceptance gates. Phase 3 replaces the retired metadata/ZIP libraries with native PHP EXIF/IPTC and ZipArchive, retaining gettext and phpThumb. See [library inventory](docs/libraries.md), [metadata mapping](docs/metadata.md) and [Phase 3 validation](docs/phase-3-validation.md). See [security.md](docs/security.md) for implemented hardening and open acceptance gates. Upgrade fixtures and the remaining acceptance limits are documented in [Phase 4 validation](docs/phase-4-validation.md). See [optional Oypo integration](docs/oypo.md) for configuration, dimension backfill and outstanding activation requirements. Dependency-free Plogger UI is scheduled for Phase 6; existing themes remain independent.

The inherited [LICENSE](LICENSE) contains GPLv3; provenance remains unresolved, including the original GPL2 notice and CAPTCHA restrictions. No relicensing is performed. See [CREDITS.md](CREDITS.md) before redistribution.
