# Native image metadata

The shared ingestion and Phase 5a preview pipeline uses `plogger_read_metadata()`
in `plog-includes/plog-metadata.php`. Upload and FTP commit call it through
`add_picture()`; editable FTP previews use the same adapter and field resolver.
Future backfill code must reuse them. No backfill or schema change runs here.
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

Derived thumbnails now honor EXIF orientations 1–8, including mirrored cases,
through retained phpThumb. Originals and their embedded metadata remain intact.
Cache hits do not reparse metadata. Original dimensions remain distinct from
oriented derivative dimensions; no new dimension columns are introduced.

## FTP preview and field priorities (Phase 5a)

The import screen proposes editable titles and descriptions and labels their
metadata source. Camera, capture date and original pixel dimensions are shown
separately; orientation is applied only to derived thumbnails. Empty/malformed
metadata leaves fields editable and displays a generic warning where parsing failed.

| Field | First nonempty source, in order |
| --- | --- |
| Title/caption | IPTC ObjectName `2#005`, IPTC Headline `2#105`, XMP `dc:title`, EXIF `IFD0.ImageDescription` |
| Description | IPTC Caption/Abstract `2#120`, XMP `dc:description`, EXIF `IFD0.ImageDescription` |

**TODO (maintainer):** compare these provisional defaults with the historical
custom FTP import code when supplied. Both lists explicitly include EXIF
ImageDescription as a final fallback; that is the only default shared source.
No additional title truncation is introduced: the inherited `tokenTruncate()`
actually returned the first line, rather than 24 words. It remains available to
legacy callers, but ingestion no longer truncates titles or inserts stored `<br>`
tags. Existing records are never rewritten. Newlines remain data; existing safe
description rendering supplies line breaks and retains its documented formatting policy.

To override priorities, add a definition to your private `plog-config.php`
(examples also appear commented in `plog-config-sample.php`):

```php
define('PLOGGER_METADATA_PRIORITIES', [
    'caption' => ['IPTC.2#105', 'XMP.dc:title', 'IFD0.ImageDescription'],
    'description' => ['IPTC.2#120', 'XMP.dc:description'],
]);
define('PLOGGER_IMPORT_BATCH_SIZE', 10);
```

Only the six distinct source identifiers in the default lists are accepted. Omitted
field lists use defaults; an empty list disables fallback for that field.
Unknown fields/sources reject the policy before any image moves. User strings
always take precedence, including empty strings and surrounding whitespace.
For PHP callers, `plogger_metadata_fields()` and `add_picture()` interpret `null`
as absent, `''` as deliberately empty. Existing argument order is retained.
Upload/remote callers use the same policy for absent fields. Blank submitted
upload forms now remain blank; callers wanting fallback must omit fields or pass
`null`. This intentional behavior prevents cleared FTP fields from being refilled.

Values remain untrusted source text in the database. Preview input/textarea and
metadata facts use UTF-8 `htmlspecialchars()` with `ENT_QUOTES`; generated query
identifiers are session-generated or validated filesystem hashes. Public and
admin description renderers continue their strict formatting-only policy and
captions retain contextual escaping. HTML entities are not decoded at ingestion.

## Bounded XMP

Standard JPEG APP1 XMP is read separately from EXIF/IPTC, up to 128 markers and
2 MiB of JPEG headers, stopping before compressed image data. Individual packets
are at most 64 KiB. Only Dublin Core title/description are mapped. Namespace URIs
are used, independent of prefixes; simple properties/attributes and RDF
Alt/Seq/Bag are accepted. `x-default` wins, otherwise the first item in document
order wins. An empty selected value falls through to the next configured source.
DOM/libxml handles XML-declared character encodings within the accepted UTF-8
packet format. Invalid UTF-8, NUL/UTF-16 packets, malformed XML and all DTD/entity
declarations are rejected. External resolution/entity substitution are disabled
and `LIBXML_NONET` is used; no entity-expansion or huge-parser flags are enabled.
See the PHP [XML parser](https://www.php.net/manual/en/domdocument.loadxml.php)
and [libxml flag](https://www.php.net/manual/en/libxml.constants.php) documentation.
Extended XMP packets, sidecars, arbitrary TIFF/PNG XMP and additional XMP fields
are not implemented. Missing DOM support leaves XMP unavailable with a warning.

## Batches, caching and resuming

Discovery retains directory offsets rather than a full recursive file list.
Each request discovers at most 1,000 new directory entries, targets two seconds
of discovery work, and previews/imports 10 images by default (configurable 1–20).
The commit loop yields between files after 20 seconds and redisplays unprocessed
edits. A single image decode remains subject to existing image/memory limits;
the limits are not a hard wall-clock guarantee on slow filesystems.

Next-batch links use random, session-bound cursors (eight retained, one hour).
They explicitly discard **unsubmitted** edits. Submit the current batch before
continuing; failed imports redisplay submitted values. Cancel/restart discovery
does not move files. After a successful move, discovery restarts against remaining
files because directory offsets may have changed. Concurrent FTP directory
changes can also invalidate offsets: restart discovery if files are missed.

Up to 40 previews are cached in the authenticated PHP session for one hour,
keyed by path, size, mtime, ctime and mapping policy. Redraws reuse them; final
imports revalidate sources and use the same reader/resolver before moving files.
No complete EXIF/GPS dump is cached. AJAX thumbnails resolve the preview registry
instead of recursively rescanning uploads per image. Old form clients have one
bounded discovery fallback; old large-directory clients must open current previews.
Changed, expired, forged and out-of-root sources cannot authorize imports.
Symlinks are skipped; traversal is checked again against the uploads root.

Traversal is capped at 32 pending directory levels; deeper discovered folders
can be opened explicitly using their folder links. Folder lookup memory is capped
at 256 entries. Filesystem enumeration order replaces the previous globally sorted
full-tree list. Resuming a directory requires skipping its earlier entry names
(PHP has no portable persistent directory cursor), but earlier image metadata is
not reparsed. Very large or remote directory performance needs staging measurement.

No backfill, database schema migration, original rewrite or existing-photo update
runs in Phase 5a. Both PDO engines use the unchanged picture schema and prepared
values. See [validation results](phase-5a-validation.md) for checks and remaining gaps.
