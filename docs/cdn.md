# Optional public static CDN

Plogger keeps the existing `cdn_url` setting. Set **CDN URL** in administration
settings to a public HTTP(S) base URL, for example `https://static.example.test/`.
HTTPS is recommended and required to avoid mixed content on an HTTPS gallery.
The application normalizes the scheme/hostname, encodes prefix path segments and
stores one trailing slash. An empty value preserves existing local URLs.
No database migration, storage SDK, remote file probe or automatic synchronization
is introduced. Local image paths and thumbnail processing remain on the PHP host.

## URL and directory mapping

The CDN base is independent of `gallery_url`, including for embedded galleries and
subdirectory installations. Do not append the application subdirectory twice.

| Application | CDN setting | Result for an original |
| --- | --- | --- |
| `https://gallery.example.test/photos/` | empty | `https://gallery.example.test/photos/plog-content/images/collection/album/photo.jpg` |
| same | `https://static.example.test/` | `https://static.example.test/plog-content/images/collection/album/photo.jpg` |
| same | `https://static.example.test/archive/` | `https://static.example.test/archive/plog-content/images/collection/album/photo.jpg` |

Mirror the same **relative** paths beneath the selected public base:

```text
plog-content/images/<collection>/<album>/<original>
plog-content/thumbs/<existing generated cache directories and filenames>
plog-content/themes/<theme>/<public CSS, JavaScript, images and fonts>
plog-content/ui/css/<Plogger UI stylesheets, when reloaded is used>
```

Each asset path segment is URL-encoded separately, including spaces, accents,
quotes and literal percent signs. Filesystem names remain unchanged. Prefixes
already containing ordinary percent escapes are normalized without double encoding;
ambiguous nested escapes in a configured base URL are rejected. The existing
raw picture-data URL shape remains unchanged when CDN is disabled. Existing
`THEME_URL`, `CDN_URL`, public image/thumbnail getters and theme signatures remain
available. `THEME_URL` retains its trailing slash, so theme-relative CSS assets work.

The shared `plogger_asset_url($relativePath)` returns an **unescaped** URL for a
validated path under these three roots or the bounded `plog-content/ui/css/*.css`
allowlist. Escape it for the actual output context
(HTML attributes: `htmlspecialchars($url, ENT_QUOTES, 'UTF-8')`). Existing image and
thumbnail getters retain their prior HTML escaping. Thumbnail dimensions are
read from local files even when the rendered URL points to the CDN; no HTTP fetch
is made to determine dimensions.

## Public files only

Administrative requests, uploads, FTP previews (`thumbs/uploads`), dynamic
thumbnail endpoints, remote administration and ZIP/download endpoints stay on
the application origin. Original images are eligible for CDN routing only when
the existing `allow_fullpic` setting permits full-picture access. Static public
thumbnails remain eligible when originals are disabled.

Do not publish private/protected originals, import sources, PHP theme templates,
configuration, JSON settings, backups, logs, `.htaccess`, hidden files, executable
files or authenticated exports. Public theme file types are CSS/JS, supported
image formats and fonts; PHP/unknown theme resources remain on the origin when
addressed through the shared helper. `THEME_URL` is necessarily a directory base:
a custom theme that appends a PHP handler must instead construct that handler's
URL on `GALLERY_URL`. A raw legacy `CDN_URL` concatenation is not an authorization
check. Review private/custom themes and custom album access rules before enabling
publication; automatic inference of third-party access policies is not provided.

Changing `allow_fullpic` or clearing `cdn_url` cannot revoke copies already
published elsewhere. The owner must separately remove protected public copies
and purge their caches when access policy changes. No removal happens automatically.

## Dedicated nginx hostname without PHP

Use a separate public-only mirror directory, rather than the gallery document
root or symlinks to the complete application. Give nginx read access only. Disable
PHP for this hostname in Plesk and do not include shared PHP/FastCGI/proxy handlers.
The supplied [static policy](server/nginx-cdn.conf) serves only allowlisted static
paths and denies scripts, hidden files, imports and every other route, even if
such a file is accidentally present. Missing files return 404; no PHP fallback runs.

Example server wrapper (replace all example paths/hostnames with reviewed values):

```nginx
server {
    listen 443 ssl;
    server_name static.example.test;
    ssl_certificate /path/to/static-fullchain.pem;
    ssl_certificate_key /path/to/static-private-key.pem;
    # mime.types normally belongs at http {} level in nginx.conf.
    include /etc/nginx/mime.types;
    include /path/to/nginx-cdn.conf;
}
```

Edit the policy's `/srv/plogger-public` root to the actual public mirror. Configure
HTTP-to-HTTPS redirection separately. `disable_symlinks on` is intended for Linux
nginx; the Windows test fixture omits that Unix-specific guard.

If the CDN base contains an explicit `/archive/` prefix while the mirror contains
`plog-content/` at its root, add this **server-level** rewrite before the include:

```nginx
rewrite ^/archive/(plog-content/.*)$ /$1 last;
```

Alternatively publish beneath `archive/` and adjust the policy's URI patterns to
that prefix. This is a static-host mapping example, not a guessed production
pretty-URL rule. The application's own subdirectory needs no nginx rewrite here.
Check [nginx location precedence](https://nginx.org/en/docs/http/ngx_http_core_module.html#location)
and run `nginx -t` before loading a reviewed configuration.

## Publishing and synchronization

Generate required derivatives on the application host using normal gallery
requests. The static host cannot generate a missing thumbnail. Phase 5c does not
regenerate existing thumbnails or implement a warm-up/backfill job.

Prepare a reviewed manifest of **public files only**, using gallery-relative
paths. Copy selected bytes to a separate public staging/mirror tree. A manual
rsync dry-run can use such a manifest:

```sh
rsync -rt --no-links --dry-run --files-from=/private/public-assets.txt /path/to/gallery/ /srv/plogger-public/
```

Review the dry-run before performing an independently authorized copy without
`--dry-run`. Do not use `--delete`, `--remove-source-files` or a complete webroot
sync. Copy only public theme assets, not whole theme directories containing PHP.
No synchronization command is executed by Plogger.

For object storage, publish this same public-only tree using the owner's existing
provider console or separately authorized bucket-copy workflow. Preserve relative
object keys and MIME types, exclude import/private files, and keep upload/API
credentials in the external tool's secure configuration. Synchronization and
cache invalidation are operator responsibilities, not application features.

**TODO (maintainer):** confirm the actual CDN document-root/prefix, public-access
policy, existing synchronization process and private theme behavior before enabling
the production setting. No production mirror path or provider account is assumed.

## Provider-compatible public bases

| Origin | Illustrative base and setup |
| --- | --- |
| S3 / CloudFront | `https://plogger-public.s3.eu-west-1.amazonaws.com/gallery/` or a public HTTPS distribution/custom hostname; publish the same keys under `gallery/` |
| Cloudflare R2 | A configured public custom domain, e.g. `https://assets.example.test/`; use its public read hostname rather than the authenticated account API endpoint |
| Bunny CDN | A configured public Pull Zone/custom hostname, e.g. `https://gallery-zone.b-cdn.net/`, backed by the public mirror or public-only Storage Zone |

These work through ordinary public HTTP URLs; no cloud-specific API is called.
Private/signed URLs and query-token bases are unsupported. Configure storage or
edge access according to the provider's policy, without making a private bucket
public merely to satisfy this setting. See [S3 object hostnames](https://docs.aws.amazon.com/AmazonS3/latest/userguide/VirtualHosting.html),
[R2 public domains](https://developers.cloudflare.com/r2/buckets/public-buckets/)
and [Bunny Storage/Pull Zone configuration](https://docs.bunny.net/docs/terraform-quickstart)
(the Bunny reference is informational; no Terraform/build tooling is required).

## Validation, cache behavior and rollout

Only empty or absolute HTTP(S) public-base URLs are accepted. Credentials, query
strings, fragments, controls, backslashes, traversal, malformed escapes and
private/reserved literal IP addresses/local hostnames are rejected. ASCII DNS
names (punycode for IDNs) and public IP literals are supported. The normalized
value is limited to the existing 255-byte MySQL field. DNS reachability, provider
permissions and whether a hostname resolves privately are not probed. Invalid
legacy stored CDN values stop public rendering but leave administration reachable
to repair the setting. Valid old bases missing a slash normalize in memory and
are stored normalized on an explicit settings save; no background DB update runs.

The example nginx policy sends correct MIME types (from `mime.types`), `nosniff`,
a restrictive document CSP, public CORS without credentials and a one-hour cache
lifetime. Public cross-origin fonts require CORS; adjust the gallery's existing
CSP to allow the chosen asset hostname. Use a cookieless hostname where practical.
No CORS relaxation is added to administration or authenticated downloads.

Existing thumbnail cache names include source/rendering identity. Longer immutable
cache lifetimes are appropriate only for keys guaranteed never to change bytes.
Theme CSS/JS and original filenames are not newly fingerprinted: purge/invalidate
them on changes or retain a short TTL. Publish files before switching the CDN
setting and verify representative originals, derivatives, fonts and every active
theme. Missing/unpublished assets and CDN outages have **no automatic origin
fallback**. Clearing `cdn_url` explicitly restores local URL generation without
moving/deleting files or bypassing download protection.

See [Phase 5c validation](phase-5c-validation.md) for executed checks and remaining
nginx/PHP-FPM, TLS, provider and private-theme staging acceptance.
