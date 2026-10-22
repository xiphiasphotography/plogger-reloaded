# PHP runtime policy

PHP 8.1 is no longer supported. The stable target is PHP 8.2–8.5.
PHP **8.6.0RC4** is an explicitly separate experimental test target, unsuitable
for production. Testing RC4 does not establish compatibility with PHP 8.6 GA,
other release candidates or future patch releases.

## Security patch requirements

As of 2026-10-22, use at least these upstream versions, and continue applying
subsequent security updates:

| Branch | Minimum upstream security patch | Locally tested |
| --- | --- | --- |
| 8.2 | 8.2.35 | 8.2.35 |
| 8.3 | 8.3.36 | 8.3.36 |
| 8.4 | 8.4.27 | 8.4.27 |
| 8.5 | 8.5.12 | 8.5.12 |

These floors include the fixes announced in the
[PHP security advisory of 2026-10-21](https://www.php.net/archive/2026.php).
They are minimum patch requirements, not a promise that every future runtime
has been tested or remains secure indefinitely. See the
[executed compatibility checks](php-82-86-validation.md) for coverage limits.
The application remains blocked for release by [other gates](../RELEASE-BLOCKERS.md).

A distribution may backport fixes without changing the PHP upstream version.
An exception requires a maintainer-reviewed record of the exact distribution,
package version/build, vendor advisory covering both CVE-2026-109618 and
CVE-2026-109619, support lifetime and equivalent compatibility/security results.
No vendor exception has been verified here. A marketing version or the presence
of a vendor suffix is insufficient evidence. Do not bypass checks blindly.

`php tools/check-runtime.php` is a read-only CLI diagnostic. It checks numeric
upstream patch floors and labels RC4 as prerelease; it does not approve vendor
exceptions, inspect the FPM runtime, contact a database or certify deployment.
Check CLI and FPM separately, including loaded extensions and effective ini
settings. Keep `error_reporting=E_ALL`; log production errors privately rather
than displaying them to visitors.

## Development and CI

Composer is development-only. The root PHP constraint is
`>=8.2 <8.6 || 8.6.0-RC4`: the exact prerelease exception permits RC4 tests
without admitting PHP 8.6 GA or PHP 8.1. It does not encode deployment security
patch floors; the runtime diagnostic and reviewed host policy cover those.
PHPStan analyzes against the minimum syntax target, PHP 8.2, at level 6.
PHPUnit fails on warnings, notices, deprecations and risky tests.

CI pins the four patched stable runtimes, with a separate RC4 job. Stable
native MariaDB/PostgreSQL jobs remain required; no containers are used.
Local synthetic PDO/SQLite checks cannot replace those native database gates.
Hosted CI and PHP-FPM staging results must be reviewed before release.
