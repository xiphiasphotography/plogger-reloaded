# Plogger UI and the Reloaded theme

Plogger UI is first-party, plain CSS shipped ready to serve. No framework,
package manager, webfont, icon font, JavaScript library or build step is required.
It changes presentation only: no schema, query, image storage or migration change.

## Files and loading

| File under `plog-content/ui/css/` | Responsibility |
| --- | --- |
| `tokens.css` | Scoped colors, spacing, typography, radii and content width |
| `base.css` | Scoped element defaults, focus, skip link, reduced motion and print |
| `layout.css` | Shell, stack, flex rows, grid, panels and table scrolling |
| `components.css` | Buttons, fields, labels, tables, navigation and status messages |
| `gallery.css` | Cards, responsive photograph grid, picture details and comments |
| `admin.css` | Administration/login presentation and existing markup bridge |

The additive `plogger_ui_styles('gallery')` emits the first four files followed
by gallery CSS. `plogger_ui_styles('admin')` substitutes admin CSS. The caller
opts in; bootstrap does not emit styles. The new theme loads its own
`theme.css` last. Admin retains its existing functional Prototype/lightbox code
and lightbox stylesheet; no new dependency was added. Login needs no JavaScript.

Public files use the shared `plogger_asset_url()` and segment encoding. Only
`plog-content/ui/css/[a-z-]+.css` is added to the CDN allowlist, not arbitrary
module/PHP/private files. Admin requests keep styles and form actions on origin.
Update the public mirror/manifest and the supplied nginx policy to include these
CSS files before selecting Reloaded on a CDN installation. There is no automatic
synchronization or remote fallback. Subdirectory installations retain their
application prefix on origin; CDN prefixes remain independent.

## Scope, cascade and customization

All library selectors start with `.plog-ui` (including scoped tokens); there is
no global `html`, `body` or `:root` reset. The public theme owns
`<section class="plog-ui plog-gallery">`. The admin owns
`<body class="plog-ui plog-admin">`. Existing themes never receive these roots
or assets automatically. Embedding host typography and colors remain independent.

This version deliberately uses ordinary, unlayered CSS in the documented file
order. There are no hidden cascade layers. If a theme introduces `@layer`,
remember that normal unlayered rules outrank normal layered rules. Put ordinary
theme overrides after the library and scope them to your root, for example:

```css
.plog-ui.plog-gallery {
  --plog-accent: #165b40;
  --plog-accent-hover: #10432e;
  --plog-width: 72rem;
  --plog-radius: .25rem;
}
```

Keep text/background contrast at least 4.5:1 for normal text and a visible focus
indicator. Components include `plog-shell`, `plog-row`, `plog-between`,
`plog-stack`, `plog-grid`, `plog-panel`, `plog-button`, `plog-nav`,
`plog-pagination` and `plog-table-scroll`. Tables that need horizontal scrolling
should have a labeled, keyboard-focusable wrapper:

```html
<div class="plog-table-scroll" role="region" aria-label="Photographs" tabindex="0">
  <table>...</table>
</div>
```

Keep native labels, buttons, headings and table headers. Text goes through
`plog_tr()` and context-specific output escaping. Do not put untrusted HTML in
CSS/custom properties.

## Reloaded behavior and compatibility

Select **Reloaded** on the existing administration Themes page. Activation uses
the existing setting/CSRF workflow; no existing site is switched automatically.
Collections, albums, search, photographs, comments, downloads, print, RSS,
pagination and generic photograph actions use the existing public API. No
function was renamed or removed and the signature fixture remains unchanged.

The new layout uses native Grid/Flexbox, intrinsic sizing and `clamp()`.
Collection/album/photo grids adapt without viewport-specific column counts.
Card images reserve dimensions, load lazily and decode asynchronously. The
current large photograph loads normally. No extra metadata pass or per-card
query was introduced. No local image path is rewritten or original modified.

Navigation, search, sorting and forms work without JavaScript. Reloaded provides
ordinary sort links instead of change-on-select navigation. Photograph metadata
uses native `details/summary`. Its slideshow route presents a scrollable,
lazy-loaded sequence with links to photographs; it deliberately has no automatic
advance or lightbox. Legacy slideshow/theme implementations remain unchanged.
Comments render after the download form, avoiding nested forms. The new theme
does not copy a rich-text implementation: existing safe description/comment
renderers remain authoritative.

Skip links, labeled regions, image alternatives, visible focus, current-page
admin navigation, password-manager autocomplete and comfortable control targets
are provided. Reduced-motion and print rules are scoped. Modern browsers with
Grid, Flexbox, custom properties and `min()/clamp()` are the target; container
queries enhance the header but the flex-wrap/grid fallback remains usable.
No claim is made for Internet Explorer or a complete accessibility certification.
Manual screen-reader, keyboard, zoom and browser staging checks remain required.

The four legacy themes keep their CSS and JavaScript unchanged. Bootstrap stays
entirely theme-specific: existing `xip-bs` retains its Bootstrap 5 integration
and external font/assets. It is not loaded by Reloaded or the administration.
A future theme may ship a local `bootstrap5.css`; no core hook is necessary.
The private `dgp2026` theme still needs to be supplied for validation.

## Checks and sizes

Run these from a local PHP/Composer development environment:

```text
php tools/css-size.php
php tools/theme-check.php plog-content/themes/reloaded
php vendor/phpunit/phpunit/phpunit
php vendor/phpstan/phpstan/phpstan analyse --no-progress
php vendor/phpstan/phpstan/phpstan analyse --configuration=phpstan.security.neon --memory-limit=512M --no-progress
```

The CSS size command reads the deployed files and sums their separately gzip-9
compressed sizes, matching separate responses rather than an imaginary bundle.
Initial measured totals are **2,868 bytes public** (including theme overrides)
and **2,884 bytes admin UI**, excluding the retained lightbox stylesheet.
These are below the approximate 7–11 KB public budget; padding to a minimum
would waste bandwidth. Server compression/cache headers still need configuring.
No generated CSS bundle or source map is required.

HTTP tests render every new template, admin pages, safe descriptions/captions
and CDN output. Optional native Chromium checks run with an existing executable:

```text
PLOGGER_TEST_BROWSER=/path/to/google-chrome php vendor/phpunit/phpunit/phpunit tests/UiBrowserTest.php
```

On PowerShell set `$env:PLOGGER_TEST_BROWSER` before the PHP command.
The test uses disposable profiles and exact 360/768/1280-pixel iframe viewports
to avoid desktop minimum-window sizes. It checks component overflow, focus/skip
navigation, reduced-motion preference and host CSS isolation. It does not install
a browser or use Node/WebDriver. This component fixture complements real PHP HTTP
rendering; it is not a full end-to-end visual or screen-reader test. CI runs it
using native Chrome on the hosted runner. Inspect runner results before claiming
hosted coverage.

The compatibility checker tokenizes PHP without executing themes, includes known
core UI/translation helpers, reports syntax errors and selected removed PHP APIs,
and warns about possible missing calls. Dynamic includes/plugins, namespaced
dispatch and runtime behavior cannot be proved by this approximation.
See [Phase 6 validation](phase-6-validation.md) for executed results and open gates.
