# Phase 2 validation — 2026-10-15

**Security implementation is reviewable; full Phase 2 acceptance remains open.**
Work continues on `phase-1-php-compatibility`, based on existing commit `1d24622`.
`main` was not merged or changed. No new commit, push, deployment, live migration
or production configuration/content modification was performed in this continuation.

## Additional correction

Decoded image content is insufficient when a server associates PHP handlers with
earlier filename extensions. The continuation rejects names such as `photo.php.jpg`,
case/number variants and names that become executable extension combinations after
sanitization. The final truncated/sanitized destination is checked again before
moving an original. Thumbnail generation rejects these names too. Existing files
are not renamed or deleted. Reviewed Apache/nginx protections are still mandatory.

Regression coverage includes a genuine synthetic JPEG containing harmless PHP
comment text, submitted through the multipart upload endpoint with a `.php.jpg`
name: HTTP 400, with no corresponding image published. Ordinary upload and FTP
import still pass in the same test; the preexisting fixture original's SHA-256
remains unchanged.

## Executed checks

Local Windows PHP CLI and isolated localhost HTTP servers were used. All image,
configuration and database fixtures are generated synthetic data. Native databases
use generated `plogger_phase1_ci_*` names on dedicated localhost ports 33336/33337.
No production credentials or user images are copied into fixtures.
The continuation reused temporary cluster processes from the preceding session;
redundant start attempts were refused by their data-directory locks. Both owned
processes were shut down after validation, and TCP probes confirmed ports 33336
and 33337 were closed.

| PHP | Strict syntax | PHPUnit, default fixture mode | MariaDB 10.6.24 HTTP | PostgreSQL 16.15 HTTP |
| --- | --- | --- | --- | --- |
| 8.1.34 | 146 files, pass | 64 tests / 1,247 assertions | 22 tests / 588 assertions | 22 tests / 588 assertions |
| 8.2.34 | 146 files, pass | 64 tests / 1,247 assertions | 22 tests / 588 assertions | 22 tests / 588 assertions |
| 8.3.35 | 146 files, pass | 64 tests / 1,247 assertions | 22 tests / 588 assertions | 22 tests / 588 assertions |
| 8.4.26 | 146 files, pass | 64 tests / 1,247 assertions | 22 tests / 588 assertions | 22 tests / 588 assertions |

There are no failures/errors in those runs. Default mode deliberately skips three
native persistence tests; the native matrix runs them. Native mode deliberately
skips four PDO-double payload/missing-file injections; default mode runs them.
The numbers above are discovered test counts, including these intentional skips.

Coverage includes fresh installer fixtures, exact settings/comment storage, legacy
and modern browser/remote login, MD5 upgrade only after success, password changes,
single-use/CSRF-protected reset, cross-session throttling, session cookies, historical
album-update CSRF, reflected/stored XSS, all bundled themes, uploads, FTP import,
thumbnails, downloads, print, RSS/XML and unchanged fixture-original bytes. HTTP
fixture logs are checked for unexpected PHP warnings, deprecations and exceptions.
Theme tests retain the 106 inherited theme-facing function signatures. Library
tests cover gettext plural parsing, archive paths/limits, image decoding and absence
of PHP evaluation tokens in bundled libraries.

- `composer validate --no-check-publish`: valid; the intentional missing-license
  metadata warning remains pending provenance review.
- PHPStan tooling level 6: pass.
- PHPStan shared security level 6 (`phpstan.security.neon`): pass, no baseline.
- Full application PHPStan level 6: **fails with 694 findings**. These include 278
  missing parameter types, 244 missing return types, 94 possibly undefined variables,
  30 unresolved constants and remaining legacy/static-discovery issues. They are
  retained without suppression; this is an acceptance gap, not 694 confirmed exploits.
- `git -c core.whitespace=cr-at-eol diff --check`: pass. Plain `git diff --check`
  flags carriage returns on added lines in inherited CRLF PHP files.
- Reviewed the continuation diff: no credentials, generated caches, binaries,
  production config, originals, thumbnails or upload-content changes.

Commands use the supported PHP executable explicitly, for example:

```text
<php-8.3> tools/lint.php
<php-8.3> vendor/phpunit/phpunit/phpunit
<php-8.3> vendor/phpstan/phpstan/phpstan analyse --no-progress
<php-8.3> vendor/phpstan/phpstan/phpstan analyse --configuration=phpstan.security.neon --memory-limit=512M --no-progress
<php-8.3> vendor/phpstan/phpstan/phpstan analyse --configuration=phpstan.application.neon --memory-limit=512M --no-progress
```

Native HTTP commands additionally set `PLOGGER_TEST_DRIVER`, `PLOGGER_TEST_PORT`,
`PLOGGER_TEST_USER` and `PLOGGER_TEST_PASSWORD` for the isolated test cluster, then
run PHPUnit with `--filter HttpCompatibilityTest`. Existing native fixture guards
reject nonlocal/nonfixture database targets. These variables must never contain
production connection details.

## Not performed / remaining gates

- Actual Apache/nginx/PHP-FPM execution of the supplied configuration examples;
  those servers are unavailable locally. No claim is made that a Plesk configuration
  is safe until the [server matrix](security-checklist.md) runs on restricted staging.
- Actual browser interaction with inline editing/slideshows and real reset-email
  delivery. HTTP tests verify the corresponding server behavior; mail transport is
  not certified. Private `dgp2026` and independently installed plugins are unavailable.
- RC1/php7/php8 fixture upgrades, production restores or schema migrations. They
  remain separate upgrade work; these tests exercise fresh synthetic installs.
- GitHub Actions execution for this unpushed patch, full licensing/provenance review,
  and representative production-size import/performance measurements.
- Full application PHPStan cleanup/review. The optional unfinished tag code has an
  invalid legacy whitespace regex among the reported findings and remains uncertified.

## Files changed by this continuation

- `plog-includes/plog-security.php`: reusable executable-extension filename policy.
- `plog-admin/plog-admin-functions.php`: check final stored image filename.
- `plog-includes/plog-functions.php`: apply the policy to thumbnail sources.
- `tests/SecurityInputTest.php`: extension/sanitization regressions.
- `tests/HttpCompatibilityTest.php`: multipart JPEG/double-extension regression.
- `docs/security.md`: document filename restrictions and existing-file behavior.
- `docs/development.md`: correct fileinfo requirements and level-6 check guidance.
- `ROADMAP.md`: distinguish implemented security work from open acceptance gates.
- `docs/phase-2-validation.md`: this recorded result and limitations.

The wider authentication/CSRF/PDO/escaping/library changes already reside in
`1d24622`; the maintainer's separate phpThumb update is `8963e69`. Both are preserved.
See [security.md](security.md) for those behavior changes and deployment requirements.
