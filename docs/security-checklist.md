# Security acceptance checklist

Run on disposable staging data, never production. Automated checks complement
these checks; they do not establish that a Plesk/nginx/Apache configuration is safe.

- [ ] Supported PHP 8.1–8.4 syntax, unit and HTTP tests pass.
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
