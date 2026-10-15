# Phase 3 library inventory and security boundaries

The retired metadata and archive library directories have been removed entirely.
No compatibility shim loads either retired library. Historical audit/validation
reports retain their original findings; they are not runtime dependencies.

## Native ZIP

Both ZIP image import and downloads require PHP ZipArchive (`ext-zip`). Missing
support produces installation guidance in the installer, CLI diagnostic and ZIP
endpoints. Runtime Composer is not required.

Import checks central-directory consistency before extracting. Limits: 32 MiB
archive, 1,000 entries, 32 MiB per image, 64 MiB total expansion and 1,000:1
expansion ratio. Paths must be relative without traversal, backslashes, drive
letters or control bytes. Symlinks/special files, encryption, duplicate flattened
names (case insensitive), hidden files, Windows device names and executable
extensions are rejected. Directory entries are checked but never materialized.
Files are streamed to a new private directory using exclusive creation, bounded
by validated byte counts and checked CRC. Nothing is published until every file
passes MIME/type, filename, pixel/memory budget and GD decode checks. Failed
staging/publication is cleaned up; original photographs are not opened for writes.

Downloads retain original relative image entry paths and unchanged bytes. They
use a private temporary ZipArchive and stream the completed archive, rather than
building compressed copies in PHP memory. Limits are 64 MiB source bytes and
1,000 files. Invalid selection types return 400, no downloadable files 404, and
size limits 413. ZIP bytes/compression and MIME (`application/zip`) differ from
the old writer. Temporary files are removed on completion/errors. Required
temporary-directory permissions and disk capacity must be checked on staging.

## Retained gettext

`plog_tr()`, `gettext_reader`, stream classes, language-prefix locale selection,
en_US/source-text fallback and NUL-separated plural translations remain. Native
gettext is not required. Both MO byte orders are tested. The parser accepts
revision 0 MO catalogs, bounds catalog size (16 MiB), count (100,000), tables,
individual strings (1 MiB) and NUL terminators before lazy lookups. Invalid or
unsupported catalogs fall back to source text. No bundled `.mo` files currently
ship; synthetic translated UTF-8/plural catalogs provide regression coverage.
The Phase 2 bounded integer plural parser remains in place, with no PHP eval.
Translations are trusted administrator-maintained content, and callers still
need contextual escaping when using translated strings in HTML/attributes.

Danilo Segan's PHP-gettext headers explicitly grant GPL-2.0-or-later. Headers
are preserved; this is compatible with the project's provisional GPLv3 option.

## Retained phpThumb

Bundled version: **1.7.24-202602061556**, already upgraded by the maintainer before
this task. Checked against [upstream master](https://github.com/JamesHeinrich/phpThumb/blob/master/phpthumb.class.php)
and [upstream releases](https://github.com/JamesHeinrich/phpThumb/releases).
No newer version was identified at review time (2026-10-15).
The upstream release notes identify the v1.7.24 fix for CVE-2025-52994;
the bundled GIF shell arguments had that escaping fix. Plogger now removes the
obsolete cjpeg shell branch entirely and uses GD. The public standalone endpoint
is a small 403 stub; its obsolete request/filter/remote-source handling is removed.
Demos remain absent. Upstream sample/config generation scripts are not executed.
This review does not certify the absence of unknown vulnerabilities.

| Used integration | Preserved behavior / hardening |
| --- | --- |
| `generate_thumb()` object API | `setSourceFileName`, `GenerateThumbnail`, `RenderToFile`; function signatures unchanged |
| Source paths | Local contained images/uploads/themes only; no request-supplied remote source or filters |
| Backend | Existing GD preference retained, embedded EXIF thumbnail optimization disabled; optional upstream ImageMagick APIs retained, not exposed through an HTTP library endpoint |
| Resize | Width, height, longest side, square zoom-crop; navigation square; theme preview width; no enlargement |
| Orientation | Native EXIF orientation applied to derived pixels once, including mirrored cases 2/4/5/7 |
| Formats | Core ingestion stays JPEG/PNG/GIF/BMP; local thumbnail raster checks also recognize WebP/AVIF if supported by the backend; JPEG remains the normal thumbnail output; PNG transparency tested at library level |
| Animated GIF | Existing frame-zero/static derivative behavior; no new animation promise |
| Caching | Source canonical path/mtime/size, rendering configuration, quality and processing-version key; valid cache reused; malformed derivatives regenerated |
| Writes | Same-directory temporary file and atomic rename, including small-image copies; original passthrough retained for small unrotated public images |
| Theme/API | Original URL API and escaped return values retained; derived filenames now include a rendering fingerprint |

Old cache files are left alone. Disk usage/cleanup policy and concurrent behavior
on the deployment filesystem need staging review; no automatic destructive cache
purge or new lifetime policy is introduced. Changes with deliberately preserved
mtime/size require cache invalidation. GD support is tested; ImageMagick, AVIF,
WebP and animated-source diversity are not universally guaranteed.

phpThumb's `license.txt` explicitly permits GPLv3 among its multiple licensing
options; retain that notice and the supplied GPL texts. GIF Util additionally
requires the Yamasoft address/header to remain; it remains intact. Unsharp Mask
retains Torstein Hønsi's notice. BMP code carries the getID3/James Heinrich header;
ICO/filter/function headers are retained. This component review does not settle
the separate original Plogger/CAPTCHA/assets provenance blockers in CREDITS.md.
