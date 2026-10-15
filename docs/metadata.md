# Native image metadata

Phase 3 uses `plogger_read_metadata()` in `plog-includes/plog-metadata.php`.
Upload and FTP commit call it through `add_picture()`; FTP thumbnail previews
use it through `generate_thumb()` on a cache miss. Future import-prefill and
backfill code must use the same adapter. No backfill or schema change runs here.
Original files are opened read-only and are never rotated or rewritten.

PHP **exif, mbstring, GD and fileinfo** are required. The installer checks these;
`php tools/check-extensions.php` checks CLI without loading configuration or
connecting to a database. Check the FPM configuration separately. Missing EXIF
blocks ingestion before moving a file; malformed metadata on an otherwise valid
image produces an administrative warning and does not prevent ingestion.

| Adapter data | Source and compatibility |
| --- | --- |
| `IFD0` | Native IFD0 text, Make, Model, ImageDescription, Copyright and numeric orientation represented as strings |
| `SubIFD` | Native EXIF section; legacy section name retained |
| `SubIFD.ExposureTime` | Fraction or decimal followed by ` sec`, matching stored shutter-speed display |
| `SubIFD.FNumber`, `FocalLength` | `f/2.8`, `50 mm` display strings, matching existing database/theme consumers |
| `SubIFD.Flash`, `ISOSpeedRatings` | Existing flash labels and scalar ISO text |
| `SubIFD.DateTimeOriginal` | Strict valid `YYYY:MM:DD HH:MM:SS`; invalid dates become empty rather than invalid SQL timestamps |
| `SubIFD.LensModel` | Native lens model, including the native parser's numeric-tag alias |
| `GPS` | Separate native GPS keys; coordinate rational arrays and hemisphere references preserved; not added to public output or new database columns |
| `IPTC` | Separate native `getimagesize()` APP13 / `iptcparse()` dataset arrays, e.g. `2#120` |
| `Errors`, `warnings` | Source/image errors and generic metadata diagnostics; no personal metadata dumps |

Files are limited to 32 MiB before metadata parsing, APP13 to 1 MiB, sections
to 256 tags, arrays to 64 values (IPTC repeats to 100), and text to 64 KiB.
NUL padding is removed; valid UTF-8 remains UTF-8, otherwise Windows-1252 is the
explicit legacy fallback. Ambiguous encodings cannot be inferred reliably.
Existing MySQL EXIF display fields are bounded to their existing 64-character
capacity on ingestion on both engines; existing database records are unchanged.
Maker-specific proprietary fields are no longer decoded by a bundled parser.

Capture times remain local camera times. No timezone or GPS-derived timezone is
guessed. GPS is sensitive location data: availability in the adapter is not
authorization to publish it. There is no new GPS UI or persistence.

Phase 3 preserves the existing ImageDescription fallback for blank upload/import
caption and description, including its legacy title truncation and `nl2br()`
storage behavior. Nonempty user fields take precedence. Existing rendering
sanitization continues to protect stored markup. Deliberately cleared values,
editable preview/provenance, separate configurable IPTC/EXIF title/description
priorities and batch backfills are Phase 5 work awaiting the historical import
code. They are not silently changed by replacing the parser.

No current consumer requires XMP fields. XMP parsing and its proposed title/
description mapping are deferred to Phase 5; the adapter does not claim to parse
XMP through EXIF. Any future XML implementation must bound packet sizes and
disable network/external entity resolution. No XML entity processing is introduced
in Phase 3.

Derived thumbnails now honor EXIF orientations 1–8, including mirrored cases,
through retained phpThumb. Originals and their embedded metadata remain intact.
Cache hits do not reparse metadata. Original dimensions remain distinct from
oriented derivative dimensions; no new dimension columns are introduced.
