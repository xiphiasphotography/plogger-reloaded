# Phase 6 validation — 2026-10-21

Implemented Plogger UI, the opt-in Reloaded public theme and the shared
administration/login presentation. No runtime dependency, database/schema
migration, production configuration change, deployment or original photograph
change was made. Existing public themes and inherited theme API signatures remain
unchanged. Release packaging/tagging is outside this task and remains blocked on
the earlier acceptance gates.

## Changes

- Six scoped stylesheets under `plog-content/ui/css/`; separate gallery/admin
  presentation and reusable layout, form, navigation and table components.
- `plog-includes/plog-ui.php`: additive stylesheet helper, with explicit
  application-origin handling for admin assets.
- `plog-content/themes/reloaded/`: all public template entry points, translated
  controls, lazy thumbnails, accessible search/sort links, comments and photo actions.
- Admin shell/login: responsive viewport, landmarks/skip link, current-page
  navigation, text branding and password-manager autocomplete. Existing
  upload/import/management JavaScript and lightbox behavior are retained.
- Shared asset validation and both static nginx examples permit the bounded UI
  CSS paths. Administration stays on origin; legacy public assets keep their URLs.
- Static theme checker knows UI/security/translation helpers and flags selected
  removed PHP functions without executing a template. Unknown plugin functions
  remain warnings; this remains an approximation.
- Regression tests cover UI asset separation/origin safety, size/scoping
  contracts, all public views including slideshow, existing XSS fixtures,
  admin forms and native-browser component layout/focus.
- CI adds Reloaded scans, CSS size output and native Chrome checks. Gitignore
  now allows the first-party theme without allowing private/custom themes.

See [architecture and customization](plogger-ui.md) for the intentional new-theme
behavior: native details, explicit sort links and a scrollable slideshow without
automatic advance. No existing site is activated onto Reloaded automatically.
The retained xip-bs theme still owns its existing Bootstrap/external assets.

## Executed checks

Windows PowerShell, using existing temporary PHP installations rather than the
unsupported default PATH PHP. Existing development vendor tools were used
directly; Composer is unavailable on PATH.

| Check | Result |
| --- | --- |
| PHPUnit, PHP 8.1.34 | 110 tests, 2,121 assertions, nine skips, no failures |
| PHPUnit, PHP 8.2.34 | 110 tests, 2,121 assertions, nine skips, no failures |
| PHPUnit, PHP 8.3.35 | 110 tests, 2,121 assertions, nine skips, no failures |
| PHPUnit, PHP 8.4.26 | 110 tests, 2,121 assertions, nine skips, no failures |
| Strict PHP lint, all four versions | 187 files per version, zero failures |
| Tooling PHPStan level 6, PHP 8.4 | Passed |
| Shared security/UI PHPStan level 6, PHP 8.4 | Passed |
| Five bundled theme scans | Zero errors and zero warnings each |
| Native Chrome + static nginx fixtures, PHP 8.4 | Two tests, 118 assertions, passed |
| CRLF-aware Git diff whitespace check | Passed |

Commands used (with the chosen PHP executable):

```text
php -d extension=pdo_sqlite vendor/phpunit/phpunit/phpunit --do-not-cache-result
php tools/lint.php
php vendor/phpstan/phpstan/phpstan analyse --no-progress
php vendor/phpstan/phpstan/phpstan analyse --configuration=phpstan.security.neon --memory-limit=512M --no-progress
php tools/theme-check.php plog-content/themes/NAME
php tools/css-size.php
git -c core.whitespace=cr-at-eol diff --check
```

The full suite's nine skips are seven native database cases plus the optional
browser and nginx cases. The latter two were subsequently run explicitly, using
the installed native Chrome and temporary native nginx 1.30.6. The browser needed
execution outside the restricted Windows process sandbox; no browser sandbox was
disabled. Those tests use disposable profiles, synthetic files and localhost
servers only. Native browser checks cover exact 360/768/1280 CSS-pixel frames,
one/multiple grid columns, horizontal overflow containment, focus/skip behavior,
reduced-motion preference and embedding-host CSS isolation. These are component
fixtures, not full end-to-end screen-reader or visual acceptance.

## CSS size

Measured LF source, gzip level 9, summing separately compressed response files:

| Resource | Raw bytes | Gzip bytes |
| --- | ---: | ---: |
| tokens.css | 632 | 373 |
| base.css | 1,566 | 686 |
| layout.css | 744 | 361 |
| components.css | 2,055 | 680 |
| gallery.css | 1,749 | 601 |
| admin.css | 2,441 | 784 |
| Reloaded theme.css | 221 | 167 |

Public total including theme overrides: **2,868 gzip bytes**. Admin UI total:
**2,884 gzip bytes**, excluding the retained lightbox stylesheet.
The public total is below the approximate 7–11 KB budget; no filler CSS was added.
Actual transfer depends on deployed line endings, compression and cache headers.

## Limits and staging checklist

Native MySQL/MariaDB and PostgreSQL servers were not available for this run;
their installation/upgrade/persistence fixtures remain unverified locally.
This task adds no SQL or schema change. Database compatibility is preserved by
using existing engine-independent APIs, but this is not a new native-driver
acceptance claim. Hosted GitHub Actions, including the updated browser step,
have not been executed by these local checks.

The full optional application PHPStan report was not rerun; prior unresolved
legacy findings remain a release gate. No suppressions/baseline were introduced.
Private dgp2026, real nginx/PHP-FPM, browser/OS diversity, screen-reader operation,
200–400% zoom and complete keyboard administration workflows still require
staging review. Publish the new public CSS to any CDN mirror before activation;
verify mobile albums, comments, print/downloads and the owner’s custom theme.
Licensing, production restore and prior security/upgrade acceptance gates remain
open. Oypo remains disabled pending confirmed provider/pixel settings.
