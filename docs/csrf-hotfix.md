# Administrative CSRF protection

Administrative writes require POST, an active PHP session and the session's `_csrf` form field. Tokens contain 32 cryptographically random bytes from `random_bytes()`, encoded as hex, and are checked with `hash_equals()`. Missing, malformed, invalid or other-session tokens return HTTP 403 before write dispatch. GET mutation requests return HTTP 405. Tokens rotate after browser login and disappear when the session is destroyed; previously opened forms may need reloading after login/logout or session expiry.

Requests with a foreign `Origin`, `Origin: null` or `Sec-Fetch-Site: cross-site` are rejected even when a token is supplied. Requests without these optional headers still require a token. HTTPS detection uses the server's HTTPS flag or port 443; it does not trust arbitrary forwarded headers. Reverse proxies must configure the web/PHP server's HTTPS indication correctly.

## Endpoint audit

| Route | Protection and workflow |
| --- | --- |
| `plog-admin/plog-admin.php` | Shared pre-dispatch guard for all POSTs, including login, logout, password reset and every page that includes the admin bootstrap |
| `plog-admin/index.php` | Login/reset form token and POST guard; redirect allowlist retained |
| `plog-manage.php` | Album/collection/picture/comment updates, creation, moves and deletion protected; individual delete links converted to submit buttons |
| `plog-feedback.php` | Comment editing, deletion and approval protected; individual row operations submit through the existing form |
| `plog-options.php` | Settings and password changes protected by the shared guard |
| `plog-upload.php`, `plog-import.php` | Uploads, archive submissions and FTP-folder imports protected, including multipart form tokens |
| `plog-themes.php` | Theme activation converted from GET links to token-bearing POST forms |
| `plog-rpc.php` | Inline edits and other AJAX POSTs pass the shared guard; AJAX sends the token from admin-page metadata |
| `plog-thumb.php` | Import thumbnail generation now requires admin authentication and protected POST; import JavaScript updated |
| `plog-thumbpopup.php` | Read-only preview already uses the authenticated admin bootstrap |
| `plog-plugins.php` | Bundled plugin listing is read-only and uses the admin bootstrap; independent custom plugin routes need their own integration |
| `_install.php` | Every POST, including config download and setup, is checked before installer operations; installer forms contain tokens |
| `_upgrade.php` | Destructive legacy upgrader disabled before connection or schema changes, including CLI execution; safe replacement remains Phase 4 |
| Root `plog-remote.php` | Command requests require protected POST in addition to existing remote authorization; clients must retain the session cookie and obtain a token from the login form |
| Definition/template includes | No independently dispatched writes; form templates use the same token helper |

Read-only edit screens, navigation and rendering remain GET operations. Lazy image-cache generation during gallery/import rendering remains derived rendering work, not a gallery-management action. No gallery database schema, production configuration or originals are migrated by this patch.

Admin form rendering adds token fields centrally to POST forms. Login, installer and reset-confirmation forms also include fields explicitly because they bypass the admin page renderer. Logout uses a POST form. Email reset links display a confirmation form; they no longer change passwords on GET. Individual row buttons preserve the existing bulk-management handlers; single-comment deletion uses a bound identifier value in a prepared query. Pagination preferences submit through protected forms.

Admin responses use `Referrer-Policy: same-origin`, allowing same-origin form POSTs to retain their same-origin request context. This does not weaken Origin validation: explicit `Origin: null` and foreign origins remain rejected.

## Session cookies

Application-started sessions use HttpOnly, SameSite=Lax and Secure when HTTPS is active, with strict session IDs and cookie-only transport. Existing cookie path/domain/lifetime settings and `PLOGGER_SESSION_SAVE_PATH` are preserved. CAPTCHA initialization uses the same session helper. If an embedding host starts the session before Plogger, that host must configure equivalent cookie flags before starting it; Plogger cannot retroactively change an already-issued session cookie.

## Validation and remaining limits

The historical album-update request shape is tested through HTTP while authenticated. Synthetic SQL tracing verifies rejected requests do not issue mutation queries. Dedicated MariaDB/PostgreSQL regressions compare the complete album row before/after missing, malformed and foreign-origin requests, verify a valid update, restore only the generated fixture row and check the original fixture-image hash. No production database or user photo is used.

Helper/HTTP tests cover session binding, token rotation semantics, missing/invalid/array tokens, cross-site headers, POST forms, individual comment deletion, import thumbnails, uploads/imports, theme activation, HTTPS cookie flags and custom session paths. Reflected/stored XSS regressions remain enabled.

Not a full Phase 2 completion: legacy MD5 authentication/password-change behavior, remaining concatenated SQL and XSS surfaces, archive/upload defenses, throttling, unsafe library evaluation and license/upgrade release gates still need work. CSRF cannot protect a session already compromised by XSS. Private themes/plugins and external remote clients require staging verification. Browser-driven AJAX, real mail reset delivery, nginx/PHP-FPM/Plesk and GitHub Actions were not executed locally. No deployment is authorized or performed.

### Checks executed

- PHP 8.1.34, 8.2.34, 8.3.35 and 8.4.26: strict lint passed for 136 PHP files. Default PHPUnit runs passed with 38 discovered tests, 935 assertions and one intentional native-database-only skip per version.
- MariaDB 10.6.24 and PostgreSQL 16.15: native installer/HTTP fixtures passed across the PHP matrix. Three PDO-double-specific tests are skipped in native mode. The added database-backed album CSRF regression passed separately on every PHP/database combination (29 assertions each); the final PostgreSQL 8.4 combined run passed with 15 discovered tests, 430 assertions and three intentional skips.
- An initial native test-harness error passed an absent password environment value as `false` to strict PDO construction. The fixture now supplies an empty string; the regression and affected full-suite rerun passed afterward.
- Tooling PHPStan level 3 passed. Application PHPStan, now including the new CSRF helper in its analysis scope, ran with 162 remaining findings and no suppressions; the helper has no reported findings.
- CRLF-aware `git diff --check` passed. No tracked config sample, license, original-photo or thumbnail files changed. Temporary test databases used localhost-only disposable clusters, which were stopped after validation.

### Files changed by this hotfix

- `plog-includes/plog-csrf.php`, `plog-globals.php`, `plog-includes/plog-captcha.php`, `plog-remote.php`
- `plog-admin/index.php`, `plog-admin/plog-admin.php`, `plog-admin/plog-admin-functions.php`, `plog-admin/plog-manage.php`, `plog-admin/plog-feedback.php`, `plog-admin/plog-themes.php`, `plog-admin/plog-thumb.php`
- `plog-admin/_install.php`, `plog-admin/_upgrade.php`, `plog-admin/includes/install-form-setup.php`
- `plog-admin/js/ajax_editing.js`, `plog-admin/js/plogger.js`
- `tests/CsrfTest.php`, `tests/HttpCompatibilityTest.php`, `tests/fixtures/FixtureDatabase.php`
- `phpstan.application.neon`, `ROADMAP.md`, `docs/csrf-hotfix.md`

At the time this original hotfix report was written, unrelated Phase 0/1, XSS
and theme changes remained in the working tree; no commit, push or deployment
was performed.
