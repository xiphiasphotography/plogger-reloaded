# Security acceptance checklist

Run on disposable staging data, never production. Automated checks complement
these checks; they do not establish that a Plesk/nginx/Apache configuration is safe.

- [ ] Requested PHP 8.2–8.6 syntax, unit and HTTP tests pass (distinguish 8.6 prerelease testing from stable support).
- [ ] Native MariaDB and PostgreSQL installer, authentication migration, settings,
  album CSRF, upload, FTP import, thumbnails and downloads pass.
- [ ] PHPStan application level 6 passes without a suppression baseline.
- [ ] SQL-looking names, captions and descriptions remain literal data.
- [ ] Legacy login `r`, public `jump-menu`/unknown query parameters and stored
  descriptions remain inert in every bundled theme and administration.
- [ ] Inspect caption, collection/album names, comment fields and EXIF display with
  harmless `<script>fixture()</script>` and `<img onerror="fixture()">` fixtures.
- [ ] Missing, incorrect, array and cross-origin CSRF tokens cannot update albums,
  collections, photos, comments, settings, uploads, imports or theme activation.
  GET mutations return 405; unauthenticated protected routes cannot write.
- [ ] Wrong legacy passwords do not change the database. Correct login upgrades
  the hash; subsequent modern login and password change still work. Reset keys
  expire, work once, and mail is delivered only to the configured administrator.
- [ ] Five wrong login attempts across fresh sessions block further attempts for
  fifteen minutes. Test writable private throttle storage and shared-proxy behavior.
- [ ] HTTPS cookies are Secure, HttpOnly, SameSite=Lax; login changes session ID.
  Existing custom session.save_path remains effective.
- [ ] Upload `.php`, disguised PHP-as-JPEG, malformed images, oversized dimensions,
  traversal names, archive traversal/symlinks, mixed executable archives and archive
  bombs. No executable or unvalidated file becomes publicly accessible.
- [ ] FTP symlinks escaping uploads are excluded. Existing image originals are
  byte-identical before and after ordinary gallery/admin requests.
- [ ] With downloads disabled, the download endpoint returns 403. Downloads never
  read outside the originals directory, including via symlinks.
- [ ] Private files and directory listings return 403/404 with each server example.

## Webserver matrix (requires a real staging server)

Test **Apache 2.4 + PHP-FPM**, **nginx + PHP-FPM**, and the **static nginx host**.
Use a temporary staging-only PHP fixture that prints a harmless marker, and a
known JPEG. Do not place these probes into production user content.

| Request | Gallery server | Static host |
| --- | --- | --- |
| `/plog-content/images/probe.php`, `.phtml`, `.phar`, `.PHP` | 403/404, no marker or source | 404 |
| `/plog-content/images/probe.php/extra`, `probe.php.jpg` | 403/404 | 404 |
| equivalent paths under `thumbs`, `uploads`, `themes` | 403/404 | 404 |
| known JPEG under images/thumbs | image, correct MIME | image, correct MIME |
| known theme CSS/JS | asset | asset |
| `/plog-config.php`, `/plog-config.php.bak`, `/.env`, `/.git/config` | 403/404 | 404 |
| `/tests/`, `/vendor/`, `/docs/`, `/plog-includes/lib/` | 403/404 | 404 |
| `/plog-admin/includes/install-functions.php` | 403/404 | 404 |
| `/plog-content/uploads/import.jpg` | 403/404 | 404 |
| normal gallery, login, CAPTCHA and authenticated thumbnail/import | functional | 404 |

Check encoded/double-extension variants and verify PHP-FPM never receives denied
paths. Run `apachectl configtest` / `nginx -t` before reloading staging. Existing
Plesk generated locations may take precedence; inspect the merged configuration.
Record actual results before marking this checklist complete.

## Final audit status — 2026-10-22

**NO-GO.** See [RELEASE-BLOCKERS.md](../RELEASE-BLOCKERS.md) and the
[executed audit](release-readiness-audit.md). The release gates above remain
unchecked: passing a focused automated fixture is not the full staging check.

- [x] PHP 8.2/8.3/8.4 synthetic regression suites: 110 tests, 2,121 assertions
  per version; seven native database cases remain unverified after browser/nginx
  tests were run separately.
- [x] Historical reflected/stored XSS and CSRF unit/HTTP fixture regressions ran
  as part of those suites; no production requests or data were used.
- [x] Native Chrome component and static nginx fixture checks: two tests,
  118 assertions, passed on PHP 8.4.
- [x] Focused tooling/security PHPStan level 6 passed.
- [x] Limited tracked-file key-pattern scan: 397 files, zero matches. This does
  not clear Git history, arbitrary secrets, personal data or asset provenance.
- [ ] Correct the newly captured unbound album-deletion SQL and audit neighboring
  management queries. Add regression coverage before claiming SQL safety.
- [x] Correct observed PHP 8.5 application/fixture failures and test 8.6.0RC4
  separately: patched 8.2.35/8.3.36/8.4.27/8.5.12 and RC4 each pass 118 tests /
  2,792 assertions and strict lint of 191 files. Chrome/static nginx pass
  separately on all five (two tests / 118 assertions each). Eight native database
  cases remain unverified. See [follow-up evidence](php-82-86-validation.md).
- [x] Use patched local test runtimes; reconcile Composer/lock, CI, PHPStan and
  current setup docs. Document [security floors/vendor exceptions](runtime-support.md).
- [ ] Clear the upstream Composer RC4 deprecation, execute hosted/native-driver
  CI and finish PHP-FPM acceptance. Passing RC4 fixtures is not stable support.
- [ ] Resolve 663 full application PHPStan findings and run native database,
  upgrade/restore, real webserver, mail and private-theme acceptance.
- [ ] Clear licensing and review release contents before packaging/distribution.
