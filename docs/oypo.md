# Optional Oypo integration (Phase 5b)

Ordering remains **disabled**. The repository contains no existing Oypo URL code
or album field to extract; a repository-wide search found no Oypo implementation
outside AGENTS.md. The new provider code lives in
`plog-content/modules/oypo/module.php`. Core owns only generic extension loading,
album-field handling and photograph-action rendering, plus the additive schema.
No customer, payment or order data is collected.

## Required maintainer inputs

- Supply the current integration code, URL format and required photo identifiers.
- Confirm the minimum resolution and whether it means long edge or both stored
  width and height; clarify orientation/aspect rules. No threshold is supplied.
- Confirm any existing Oypo column/plugin in private installations before applying
  migration 0004. No private schema is inferred or automatically renamed/copied.
- Supply dgp2026 for integration and rendering checks. Bundled themes have the
  generic hook; custom themes continue working and can opt in separately.

Providing an album ID alone never enables ordering. Even an enabled module
returns no action until the resolution rule is valid **and** a trusted provider
adapter exists. The code has no default or inferred Oypo URL.

## Configuration and album administration

After the backup-gated schema upgrade described in [UPGRADING.md](../UPGRADING.md),
add the literal setting `define('PLOGGER_MODULES', 'oypo');` to the local config
to expose the optional album ID field on Edit Album. An empty value stores SQL
NULL; currently accepted IDs contain 1–128 ASCII letters, digits, underscores or
hyphens. Confirm this validation against the supplied integration code. Existing
IDs remain unchanged when the module is disabled or a field is omitted.

Remove `oypo` from this comma-separated module list to disable the module. No
database values or image files are removed. No module is enabled by default.

Only after the owner confirms the policy, configure these **literal constants**:

| Constant | Meaning |
| --- | --- |
| `PLOGGER_OYPO_MINIMUM_MODE` | `long_edge` or `dimensions` |
| `PLOGGER_OYPO_MINIMUM_LONG_EDGE` | Positive integer pixels, required for `long_edge` |
| `PLOGGER_OYPO_MINIMUM_WIDTH` | Positive integer pixels, required for `dimensions` |
| `PLOGGER_OYPO_MINIMUM_HEIGHT` | Positive integer pixels, required for `dimensions` |
| `PLOGGER_OYPO_URL_ADAPTER` | Name of the reviewed, locally defined provider URL function |

The URL adapter must be implemented from the supplied integration code before
activation. It receives `(encodedAlbumId, encodedPictureId, encodedRelativePath)`;
each path segment is encoded separately. It returns an absolute HTTP(S) URL or
null. It must explicitly map those inputs to the confirmed provider identifiers;
the database picture ID is not assumed to be an Oypo identifier. The named
function can be supplied by reviewed local module/theme code loaded before the
photo is rendered. Do not add executable adapter code to the literal config:
the upgrader intentionally refuses dynamic configuration. Unit/HTTP tests use
`example.invalid` stub URLs only.

Eligibility uses stored original pixels, without applying EXIF display rotation.
Long-edge mode compares the larger axis. Dimensions mode compares width with
width and height with height, without automatically swapping axes. Missing,
invalid or zero dimensions fail closed. Unset, zero, negative, noninteger or
incomplete resolution settings fail closed. No image is read while rendering
the action; dimensions must have been saved during upload/import or backfilled.

## Dimension backfill

New uploads and FTP imports share `add_picture()` and persist the original
dimensions already read by native `getimagesize()` in the metadata adapter.
Migration 0004 adds nullable `original_width` / `original_height` to pictures and
nullable `oypo_album_id` to albums on both SQL backends. There were no adequate
existing dimension fields in the canonical schema. This schema migration does
not read or write image files. Existing values are preserved. Incompatible
same-name private columns stop the migration for manual review; the migration
does not merge or convert them. Test on a restored staging copy first.

Keep a verified external database backup before applying a dimension backfill.
Open authenticated `plog-admin/plog-dimensions.php` for a protected preview/apply
form. Each request processes at most 50 candidate rows. Preview keeps the same
cursor for Apply; applying advances the editable resume cursor. POST requires a
valid CSRF token and explicit confirmation. No writes occur on GET.

CLI equivalent (run from the installation root):

```text
php plog-admin/dimensions-cli.php --dry-run --after=0 --limit=50
php plog-admin/dimensions-cli.php --apply --confirm --after=0 --limit=50
```

Review the preview, then apply using the **same** starting cursor. Continue with
the returned `last_id` until `done` is true. `updated` is the number of proposed
updates in dry-run and actual affected rows in apply. `failed` lists missing,
unreadable, corrupt or unsafe-path photo IDs; they remain unchanged. Resume
advances past failures so one bad file cannot block the rest. After repairing
the source problem, restart from zero to retry missing dimensions. Complete rows
are skipped; repeated runs are harmless. Conditional writes recheck path and
missing dimensions to avoid overwriting another worker's completed row.

The configurable CLI batch size is 1–100, default 50. Processing stops between
files after ten seconds and returns a resume cursor. No image decoding, EXIF
parsing, rotation, thumbnail regeneration or image writes occur. Traversal and
symlinks outside the originals root are rejected. Measure real filesystem and
database latency on staging against the 60 s / 256 MB request budget; a stalled
filesystem operation is not interrupted by the between-file time check. There
is no persistent cache or automatic background process. Closing the form or
stopping CLI execution cancels further batches.

## Generic extension API and themes

Trusted local modules live at `plog-content/modules/NAME/module.php`. Module names
are validated and resolved within that directory; request/database input never
chooses executable module paths. Modules register:

- `PloggerExtensions::$actions['provider'] = callable`: receives the photograph
  and album arrays; returns `['url'=>..., 'label'=>...]` or null. Core renders
  validated HTTP(S) URLs and escaped labels/attributes, with accessible text,
  `target="_blank"` and `rel="noopener noreferrer"`. No raw provider HTML is used.
- `PloggerExtensions::$albumFields['column']`: translated `label` and a `validate`
  callable that returns the stored value or throws `InvalidArgumentException`.
  Core validates fields before album filesystem/database work, binds values with
  prepared statements and escapes form output. Column names and callbacks are
  trusted module code, never request-supplied. A separate reviewed migration must
  provide any additional columns; this is not a plugin installation framework.

`echo plogger_get_picture_actions();` renders actions beneath the current photo.
Explicit photograph/album array arguments are also supported. An empty registry
returns an empty string without querying the database. The bundled default, air,
lucid and xip-bs picture templates call this function beneath the description.
No existing theme functions, CSS dependencies or stored descriptions change.
An enabled provider needs at most one album lookup per call, never an album scan.

## Validation and remaining acceptance

Regression tests cover disabled/unconfigured ordering, eligibility boundaries and
stored axes, album validation/clearing/escaping, encoded identifiers, malicious
URLs/labels, HTTP rendering, admin field validation, backfill CSRF, bounded
dry-run/resume/failure behavior and byte-identical originals. Existing native
upgrade fixtures cover RC1/php7/php8, v3-to-v4 progression, interrupted new DDL,
idempotence, unchanged data and migration history on both database drivers in CI.

Local verification results and unavailable checks are recorded in
[Phase 5b validation](phase-5b-validation.md). Live provider ordering, the private
theme, real database/staging performance and production deployment remain
unverified. Phase 5b activation acceptance stays open until the inputs above are
provided; Phase 6 is outside this change.
