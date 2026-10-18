# Phase 5c CDN validation

Scope: public static URL generation and deployment guidance. No production
configuration/database, cloud account, original photograph or existing thumbnail
was modified. No schema migration, SDK or automatic synchronization was added.
Phase 5b and Phase 6 are untouched.

## Changed files and behavior

- `plog-includes/plog-assets.php`: shared CDN-base validation/normalization,
  public asset URL generation and safe URL-to-local-file inspection. Base URLs
  are limited to the existing 255-byte field and validated without network calls.
- `plog-includes/plog-security.php`: the existing image URL API delegates to the
  shared generator while retaining contextual HTML escaping.
- `plog-load-config.php`: normalize valid stored CDN values in memory and build
  theme bases through the shared helper. Invalid legacy CDN values reject public
  rendering while leaving administration available to repair the setting.
- `plog-admin/plog-options.php`: validate CDN settings before writes and save a
  normalized trailing slash via the existing prepared settings update.
- `plog-includes/plog-functions.php`: thumbnails and picture-data URLs use the
  common mapping; dimensions are read locally for CDN URLs. The touched album
  lookup uses a prepared value. Existing function signatures/defaults remain.
- `plog-rss.php`: avoid encoding already encoded image URLs again, including CDN
  prefixes containing escaped spaces. Gallery/feed navigation remains on origin.
- `docs/server/nginx-cdn.conf`: dedicated static-only public-file allowlist,
  script/private-path denials, MIME/CORS/cache guidance and no PHP fallback.
- `tests/CdnTest.php`, `tests/CdnNginxTest.php`,
  `tests/HttpCompatibilityTest.php`, `tests/fixtures/FixtureDatabase.php`:
  URL, settings, theme, privacy, local-dimension and native nginx regressions;
  optional real-database persistence assertions use only guarded fixture targets.
- `.github/workflows/checks.yml`: add a native, container-free nginx fixture job.
- `phpstan.security.neon`, `phpstan.application.neon`: include the shared helper.
- `README.md`, `ROADMAP.md`, `docs/deployment.md`, `docs/cdn.md`, this file:
  status, mapping, publication workflow, intentional behavior and acceptance gaps.

Public images are CDN-eligible only when `allow_fullpic` permits them. Public
thumbnails/theme assets use the CDN; administration, imports, private preview
thumbnails, dynamic thumbnail requests, remote actions and protected downloads
remain on origin. Unknown/executable theme resources are not mapped to the CDN.
No-CDN public getters and inherited picture-data URL shapes remain compatible.
Local paths never become CDN filesystem paths. There is no automatic outage/404
fallback or change to thumbnail generation/cache identity.

## Checks

Tests use PHP 8.1.34, 8.2.34, 8.3.35 and 8.4.26 CLI, PHPUnit 10.5.66,
PHPStan 2.3.1, and portable native nginx 1.30.6. All HTTP/photo files are generated
fixtures in disposable local directories. No actual CDN/provider was contacted.

| Check | Result |
| --- | --- |
| Full PHPUnit, each PHP 8.1–8.4 | Pass: 98 discovered tests, 1,765 assertions, 8 skips per run |
| Syntax/deprecation lint, each PHP 8.1–8.4 | Pass: 164 PHP files, zero syntax failures |
| Native nginx fixture, each PHP 8.1–8.4 | Pass separately: 1 test, 51 assertions per run |
| CDN URL unit tests, PHP 8.3 | Pass: 4 tests, 61 assertions; included in the full suite |
| HTTP regression suite, PHP 8.3 | Pass: 29 tests, 1,016 assertions, 4 native-only skips; included in the full suite |
| Tooling and shared security/assets PHPStan level 6 | Pass, no suppression baseline |
| Full application PHPStan level 6 | 663 outstanding application findings; existing release acceptance gap |
| CRLF-aware diff check | Pass with `git -c core.whitespace=cr-at-eol diff --check`; default diff check reports inherited CRLF endings |
| Original/config diff review | No original-photo, existing-thumbnail, production-config, binary, secret or cache changes |

The eight default-suite skips are seven native-database scenarios and the
optional nginx fixture. Separate explicit runs above cover the nginx skip.

The native nginx fixture initially could not read its temporary configuration
inside the restricted Windows sandbox. It was rerun outside that sandbox with
only a generated config, synthetic files and a random localhost port. An initial
fixture setup issue (missing nginx temp directory) was corrected before passing
runs. The shipped Linux `disable_symlinks on` directive is omitted only in the
Windows fixture because Windows nginx lacks the Unix guard.

## Coverage and remaining acceptance

Regression cases cover empty/CDN bases, provider-shaped public endpoints,
subdirectory installations, independent path prefixes, trailing slashes,
UTF-8/space/quote/percent encoding, malformed arrays, credentials, fragments,
query-token bases, control characters, traversal, nested escapes, private literal
IP addresses/local names, private previews, disabled originals and admin requests.
HTTP tests cover settings rejection before writes, normalized storage, invalid
legacy-setting repair, all four bundled themes, picture-data arrays, thumbnail
dimensions and RSS encoding. Existing theme API/security/upgrade tests remain.

The nginx fixture checks static originals/thumbnails/CSS/JS/fonts, UTF-8 names,
prefix rewriting, correct bytes, CORS/nosniff headers, missing files, disabled
directory indexes, POST rejection, private previews, executable/disguised script
names, PHP theme files, configuration, admin and download routes. Its temporary
server is stopped after each test. No production server is started or changed.

Remaining work:

- Native MySQL/MariaDB and PostgreSQL servers are unavailable in this environment;
  their seven fixture-only tests are skipped. Existing CI covers both drivers and
  the new settings persistence assertions. No fresh real-engine pass is claimed.
- The default suite skips the optional nginx test unless `PLOGGER_TEST_NGINX`
  points to a native executable; separate explicit runs cover it. The new Linux
  nginx CI job has not been run remotely. Its Unix symlink guard needs Linux CI
  or staging confirmation.
- Production TLS/nginx/PHP-FPM, provider permissions, synchronization timing,
  cache invalidation and the private dgp2026 theme need staging verification.
  The actual mirror/prefix/access policy remains a maintainer TODO in `docs/cdn.md`.
- Composer CLI is unavailable, so `composer validate` was not run; the manifest
  was not changed. Full application PHPStan acceptance and earlier release/license
  gates remain open; no suppression baseline is introduced.

Local reproduction after installing development tools:

```text
php tools/lint.php
php vendor/bin/phpunit
php vendor/bin/phpstan analyse --no-progress
php vendor/bin/phpstan analyse -c phpstan.security.neon --memory-limit=512M --no-progress
```

For the optional server test, set `PLOGGER_TEST_NGINX` to the absolute native nginx
binary and run `php vendor/bin/phpunit tests/CdnNginxTest.php`. This fixture never
uses the host's nginx configuration, default pid file or privileged listen ports.
