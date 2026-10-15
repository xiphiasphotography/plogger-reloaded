# Phase 2 security hardening

**Not approved for public deployment.** Application PHPStan level 6 and the real
nginx/Apache/PHP-FPM staging acceptance checklist remain release gates. See
[security-checklist.md](security-checklist.md) and [Phase 2 validation](phase-2-validation.md).
No production database, configuration, image or thumbnail is changed by the tests.

## Authentication and sessions

Browser and explicitly enabled remote login share verification and prepared
compare-and-set hash upgrades. Strict 32-character hexadecimal MD5 hashes are
accepted only when the submitted password matches; failed authentication never
rewrites a hash. Standard `password_hash()` hashes from existing integrations also
remain verifiable. Successful authentication upgrades older formats as needed.

New stored values are `sha:` followed by PHP's bcrypt `password_hash()` output,
exactly 64 characters, fitting the inherited MySQL `varchar(64)` without DDL.
The bcrypt input is `base64_encode(hash('sha256', $password, true))`; this avoids
bcrypt's 72-byte truncation and null-byte restrictions while retaining distinct
long/Unicode passwords. The explicit prefix distinguishes this encoding from
ordinary bcrypt. Verification uses `password_verify()` for the corresponding
encoding. Integration code must use the shared helpers, not hash passwords itself.
No schema migration or forced reset is performed.

Successful browser/remote login destroys the old session ID and rotates CSRF
tokens. Sessions record a hash-version fingerprint so a changed/reset password
invalidates existing authenticated sessions on their next administrative request.
Existing sessions without the fingerprint must log in again. Cookies are HttpOnly,
SameSite=Lax and Secure when HTTPS is detected through HTTPS/SERVER_PORT; forwarded
headers are not trusted. Existing session.save_path remains effective. Embedding
applications that start sessions first must configure their own cookie flags.

Five failed login attempts per REMOTE_ADDR are allowed in fifteen minutes across
browser sessions; a successful login clears the attempt count. Reset requests have
a separate counter. Exclusively locked JSON ledgers contain only counters/times,
under a private installation-specific directory in the system temp directory.
Failure to access storage fails authentication closed. Optionally define
`PLOGGER_AUTH_STORAGE` as a private writable directory **outside the webroot**;
restrict ownership/ACLs to the PHP worker. Shared/multiple web workers must share
this location. Do not trust arbitrary X-Forwarded-For headers. Configure appropriate
host rate limits for a shared proxy and distributed attacks; stale ledger cleanup
and filesystem capacity monitoring remain operational responsibilities.

Password reset links contain 192 random bits and an issuance timestamp, expire
after one hour, require a protected POST, and clear the activation key atomically
with the new hash. The user chooses/confirm their replacement password; no password
is emailed. Old pre-hardening reset links must be requested again. Installer
notification email likewise omits the chosen password. Actual mail delivery still
requires staging verification.

## Request and output boundaries

Core administrative POST forms, AJAX requests, uploads/imports, settings, comment
management, deletions and theme activation use session-bound random_bytes tokens
checked with hash_equals. Missing/invalid tokens and cross-origin browser headers
are rejected before handlers run; GET mutations return 405. Unexpected nested or
scalar-field arrays return 400. Administrative responses disable caching/framing
and referrer propagation. The legacy destructive upgrader stays disabled.

The prior `r`, `jump-menu`/unknown query and stored-description XSS regressions
remain covered. Names, captions, comment form values, metadata, menus, settings and
inline responses use contextual UTF-8 ENT_QUOTES escaping; slideshow strings use
JSON hexadecimal escaping. HTTP(S) links reject credentials, control characters and
unsafe markup/schemes; comment redirects stay on the current origin. Gallery/CDN
URL settings are checked on load/save without rewriting database contents.

Description formatting retains the existing strict attribute-free HTML policy;
line breaks remain data. Bundled themes render captions and names as escaped text.
Raw/strip theme getter modes remain available for compatibility: custom themes
must escape them in their own output contexts. No public function is removed or
renamed. The private dgp2026 theme and independently installed plugins remain
unverified until provided. Browser testing of editable fields/slideshows remains
part of staging acceptance.

Bundled gettext keeps its translation API and replaces PHP evaluation with a
bounded integer plural-expression parser, including nested ternaries and standard
logical/comparison/arithmetic operators. Native ZipArchive replaces the retired ZIP library.
Prototype's administrative response-script evaluation is disabled and X-JSON uses
JSON.parse: AJAX HTML fragments cannot become scripts. The standalone phpThumb
HTTP endpoint is disabled; internal phpthumb.class.php processing remains in use.
Phase 3 removes the retired metadata/ZIP libraries and bounds MO catalogs; see [library review](libraries.md) and [metadata adapter](metadata.md).

## Image and archive limits

Before moving originals, upload/import validates a readable regular image, filename,
extension/type agreement, finfo MIME, dimensions and a successful GD decode.
Current limits are **32 MiB per image, 20 million pixels, 20,000 pixels per side**
plus a conservative remaining-memory check. These are intentional security limits;
larger legitimate originals require a reviewed limit/memory-budget adjustment.
JPEG, PNG, GIF and BMP remain supported. Validation does not rewrite originals.
Executable extension components such as `.php.jpg` are rejected, including after
filename sanitization/truncation, to prevent Apache multi-extension handler
confusion. Thumbnail generation also rejects these names; existing files are not
renamed or deleted automatically. Host script-execution denials remain necessary.

FTP sources must resolve inside uploads; directory scans skip symlinks. Destination
paths reject traversal/control characters and escaping symlink ancestors. Thumbnail
sources and cache destinations are bounded to the corresponding content roots.

ZIP uploads are inspected before extraction: no traversal/absolute paths, encrypted
entries, symlinks, executable/nonimage files or duplicate flattened names. Limits
are 1,000 entries, 32 MiB per entry/compressed archive, 64 MiB total uncompressed
data and compression ratio at most 1,000. Inflation has an independent output cap.
Extraction occurs in a new private random temporary directory. Only decoded images
are copied to a new random uploads subdirectory, preserving their bytes. Nested
ZIP paths are flattened as before; originals and preexisting uploads are untouched.
Request time limits and large-folder pagination still need staging measurement.

Downloads honor allow_dl server-side and cannot read outside originals, including
via symlinks. Dynamic ZIP downloads are capped at 64 MiB of source bytes. Public
downloads retain their existing public access model when enabled; no private-gallery
authorization model is invented. Direct static originals need host access rules if
the maintainer requires confidentiality: hiding a full-image link is not access control.

## Deployment, private files and logging

Apply reviewed host configuration **before public exposure**:

- [Apache 2.4 example](server/apache-security.conf)
- [nginx/PHP-FPM example](server/nginx-security.conf)
- [Dedicated static nginx host](server/nginx-static-host.conf)

The examples deny script execution under plog-content, all HTTP access to uploads,
library/admin includes, config/backups, dotfiles and development/private directories.
Server-side template/includes still work. Remove the installer denial only during
a restricted fresh installation, then restore it. nginx ignores .htaccess. Inspect
Plesk's merged locations; an earlier generic PHP handler or a ^~ content location
can invalidate a later regex denial. No live host configuration is modified here.
Use an unserved deployment checkout and copy only distribution files, as described
in [deployment.md](deployment.md). Runtime does not need vendor or Composer.

Exceptions/warnings log a correlation ID, operation, exception class and basename/
line, without SQL, bindings, passwords, tokens, metadata or full exception messages.
Public failures show generic text and the reference ID; database debug messages no
longer echo SQL or PDO details. Existing host handlers are preserved for embedded
applications, which must provide equivalent safeguards. Explicit debug diagnostics
are restricted to local/authenticated use and exclude sensitive configuration.
Keep PHP/server logs outside the webroot and restrict their ACLs; configure reset
endpoint access logs to omit query strings containing the reset key.

Remote administration is disabled by default. Enable `PLOGGER_ENABLE_REMOTE` only
after auditing the client: it must retain cookies and fetch/send the current CSRF
token, including after login rotation. Legacy clients without these capabilities
are deliberately unsupported. No remote POST credential dump is retained.
