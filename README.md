# Plogger Reloaded

**Status: Phase 5 features and Phase 6 Plogger UI/Reloaded theme implemented; Oypo ordering stays disabled pending maintainer inputs. Release acceptance gates remain open. Not ready for public deployment.** [AUDIT.md](AUDIT.md) documents inherited PHP syntax, security and licensing blockers. Tooling support does not establish application compatibility.

A continuation of Plogger, the simple self-hosted gallery: collections > albums > photos. Based on `plogger_php8`, with procedural PHP and inherited Git history preserved.

**Release audit (2026-10-22): NO-GO for v1.0.0-beta.1.** Patched PHP **8.2.35, 8.3.36, 8.4.27 and 8.5.12** now pass local syntax/unit/synthetic HTTP checks. **8.6.0RC4** passes those checks separately as an experimental prerelease, with an outstanding Composer deprecation; it is not production-supported. Native MariaDB/PostgreSQL fixtures, real WSL nginx/Apache/PHP-FPM checks and full application PHPStan now pass; original RC1 provenance, licensing and remaining manual/private staging acceptance still block release. See [current acceptance evidence](docs/rb05-08-validation.md). See [RELEASE-BLOCKERS.md](RELEASE-BLOCKERS.md), [current compatibility evidence](docs/php-82-86-validation.md) and [draft release notes](CHANGELOG.md). No release ZIP was generated.

The database targets remain MySQL/MariaDB (production target MariaDB 10.6) and PostgreSQL via PDO. Deploy as a traditional PHP website on nginx/PHP-FPM or Apache. No Docker, containers, Node.js or frontend build is required. Composer installs development tools only. PHP 8.1 is unsupported; follow the [stable runtime security floors and prerelease policy](docs/runtime-support.md).

## Setup

Read [local development](docs/development.md), [CONTRIBUTING.md](CONTRIBUTING.md) and [Plesk Git deployment](docs/deployment.md).

On an isolated staging domain, create a dedicated database/user, copy the application files and visit `plog-admin/_install.php`. It generates the untracked `plog-config.php`. Enable PDO with the selected driver, GD, fileinfo, mbstring, sessions, zlib, EXIF and ZipArchive (ext-zip). Keep credentials and uploaded content outside Git. Native installer/gallery and isolated nginx/Apache/PHP-FPM checks pass. Complete the documented real-install, private-theme and manual acceptance gates before release. New installs leave the optional CDN disabled; existing configuration is never rewritten automatically.

```text
composer install
composer check
```

CI pins the four patched stable runtimes above and tests RC4 in a separate prerelease job. Hosted CI has not been executed for this patch. Tests include synthetic HTTP requests, native MariaDB/PostgreSQL fixtures and inherited theme API signatures; the native database cases now pass locally on MariaDB and PostgreSQL. `composer analyse` and `composer analyse:security` check focused scopes at level 6; `composer analyse:application` also passes level 6 and is enforced by `composer check` and CI. Earlier phase reports are historical coverage. No runtime Composer installation is needed.

The old destructive upgrader is removed. Follow [UPGRADING.md](UPGRADING.md) for read-only inspection, verified-backup gates, reviewed plans and the opt-in web/CLI migration tools. The intended first migration uses a backed-up staging copy on PHP 8.3; no PHP 7.4 bridge is provided.

An optional [public static CDN](docs/cdn.md) can serve photographs, generated thumbnails and theme assets from a separate nginx hostname or a public storage/CDN URL. Administration, imports and protected downloads remain on the application origin. Synchronization is handled separately by the owner; no SDK or automatic storage publishing is included.

## Embedding

Require `path/to/plogger.php` from your PHP page, call `the_plogger_head()` in its head and `the_plogger_gallery()` in its body. Inherited theme function names and parameter defaults are preserved. Broken PHP paths have targeted fixes; remote write clients must retain the authenticated session cookie.

## Roadmap and licensing

[ROADMAP.md](ROADMAP.md) tracks pending acceptance gates. Phase 3 replaces the retired metadata/ZIP libraries with native PHP EXIF/IPTC and ZipArchive, retaining gettext and phpThumb. See [library inventory](docs/libraries.md), [metadata mapping](docs/metadata.md) and [Phase 3 validation](docs/phase-3-validation.md). See [security.md](docs/security.md) for implemented hardening and open acceptance gates. Upgrade fixtures and the remaining acceptance limits are documented in [Phase 4 validation](docs/phase-4-validation.md). See [optional Oypo integration](docs/oypo.md) for configuration, dimension backfill and outstanding activation requirements. Phase 6 adds dependency-free Plogger UI and the optional Reloaded theme; existing themes remain independent.

Select **Reloaded** through the existing Themes page to opt into the new responsive public theme. [Plogger UI](docs/plogger-ui.md) supplies scoped, dependency-free CSS shared with the refreshed administration. Existing public themes keep their own assets and are not switched automatically. See [Phase 6 validation](docs/phase-6-validation.md) for coverage and remaining staging checks.

The inherited [LICENSE](LICENSE) contains GPLv3; provenance remains unresolved, including the original GPL2 notice and CAPTCHA restrictions. No relicensing is performed. See [CREDITS.md](CREDITS.md) before redistribution.
