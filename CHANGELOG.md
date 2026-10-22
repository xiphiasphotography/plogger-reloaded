# Changelog

## v1.0.0-beta.1 — DRAFT, NOT RELEASED

Release readiness audit: **NO-GO on 2026-10-22**.
See [RELEASE-BLOCKERS.md](RELEASE-BLOCKERS.md). No downloadable release,
checksum, tag, publication or deployment is associated with these draft notes.

### Implemented on the development branch

- Targeted PHP compatibility repairs and development-only Composer tooling.
- Shared PDO value binding/identifier helpers; database conversion remains
  incomplete, including the album-deletion issue identified in the final audit.
- Modern password verification, successful-login legacy MD5 upgrades, hardened
  sessions, login throttling, CSRF guards and contextual output protection.
- Regression coverage for historical login redirect/query-parameter XSS,
  stored-description XSS and authenticated album-update CSRF.
- Native PHP EXIF/IPTC and bounded XMP metadata, native ZipArchive imports and
  downloads; retained gettext and phpThumb with targeted hardening.
- Numbered, backup-gated, dry-run web/CLI upgrade infrastructure; modeled
  RC1/php7/php8 fixtures, schema tracking and resumable recovery.
- Editable batched FTP metadata previews, source labels and shared import policy.
- Optional Oypo module infrastructure and protected dimension backfill.
- Validated public CDN asset routing and static-only nginx examples.
- Scoped first-party Plogger UI, the opt-in Reloaded theme and refreshed
  administration/login. Existing theme API and legacy theme assets are retained.

### Compatibility and intentional behavior

PHP 8.1 support is removed. The stable target is patched PHP 8.2–8.5 with
MySQL/MariaDB and PostgreSQL; exactly 8.6.0RC4 is tested separately as a prerelease.
Local syntax/unit/synthetic HTTP checks pass on 8.2.35/8.3.36/8.4.27/8.5.12 and
RC4. Composer/lock, CI and PHPStan now match that policy. GD object cleanup,
phpThumb null-cache handling, CAPTCHA casts, remote switch syntax and HTTP
fixtures are modernized without reducing error reporting. Native database and
staging verification remain pending; Composer has an upstream RC4 deprecation.
See [runtime policy](docs/runtime-support.md) and [results](docs/php-82-86-validation.md).

Standalone phpThumb HTTP access is disabled. Remote administration/web upgrades
are opt-in. State-changing browser workflows require POST/CSRF. Metadata/ZIP
operations require ext-exif/ext-zip. Contextual escaping retains stored source
text and safe documented description formatting; it does not rewrite old data.
Reloaded is selected explicitly, uses native sorting/details and a scrollable
slideshow without automatic advance; legacy themes are not switched or rewritten.

### Unavailable or unverified

Oypo ordering remains disabled until the maintainer supplies the real link
contract and minimum-resolution policy. Private dgp2026, native database
install/upgrade acceptance, real-world restore, server protection, accessibility,
mail, large-directory limits and actual CDN publishing require staging review.
No PHP 7.4 bridge is provided. No cloud SDK or automatic synchronization is added.
Original photographs are preserved; no production migration was run.

Licensing/provenance, remaining SQL concatenation, full application analysis and
native PHP/database acceptance block redistribution/release. Follow
[UPGRADING.md](UPGRADING.md) only on a backed-up isolated staging copy; code
rollback alone does not reverse schema changes.
