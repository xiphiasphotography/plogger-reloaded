# Local Linux staging environment

Updated: 2026-10-23. **Release status: NO-GO for v1.0.0-beta.1.**

This records the maintainer's existing local staging environment and reported
results. This documentation task did not connect to, deploy to or retest that
environment. Commands below are manual procedures, not an execution record.
See [release blockers](../RELEASE-BLOCKERS.md) and the
[release audit](release-readiness-audit.md) for the remaining gates.

## Reported configuration

| Item | Value |
| --- | --- |
| Host | Windows 11 with WSL2 |
| Linux distribution | Ubuntu 22.04.5 LTS |
| WSL distribution name | `Ubuntu-22.04` |
| Request chain | nginx 1.30.5 → Apache 2.4.52 → PHP-FPM 8.4.27 |
| Database engine | MariaDB 10.6.23 |
| Staging URL | `http://localhost:8080` |
| Apache backend | `127.0.0.1:7080` |
| PHP-FPM socket | `/run/php/php8.4-fpm.sock` |
| PHP-FPM worker user | `www-data` |
| Database name | `plogger_staging` |
| Database user | `plogger_staging` |
| Database host | `localhost` |
| Repository | `/home/xiphias/projects/plogger-reloaded` |
| Deployment directory | `/var/www/vhosts/plogger-staging.local/httpdocs` |
| Logs | `/var/www/vhosts/plogger-staging.local/logs` |

The repository and deployed application are separate directories. Windows IDE
paths and this Linux checkout must not be assumed to share uncommitted changes;
record the deployed Git revision. Preserve the public scheme and original Host
header, including port `8080`, through the trusted nginx/Apache proxy chain so
same-origin checks compare the browser-facing origin. Do not expose the Apache
backend or PHP-FPM directly to other networks.

This topology permits real PHP-FPM testing but does not prove equivalence to
production Plesk routing, permissions or resource limits. The HTTP-only staging
URL does not verify HTTPS or Secure cookie behavior.

## Windows access to the WSL filesystem

The Windows UNC root is `\\wsl.localhost\Ubuntu-22.04\`. These UNC paths
provide Windows access to the **same filesystem and files** as the Linux paths
inside that WSL distribution; they are not a synchronized copy.

| Purpose | Linux path inside WSL | Windows UNC equivalent |
| --- | --- | --- |
| Distribution root | `/` | `\\wsl.localhost\Ubuntu-22.04\` |
| Git repository | `/home/xiphias/projects/plogger-reloaded` | `\\wsl.localhost\Ubuntu-22.04\home\xiphias\projects\plogger-reloaded` |
| Staging webroot | `/var/www/vhosts/plogger-staging.local/httpdocs` | `\\wsl.localhost\Ubuntu-22.04\var\www\vhosts\plogger-staging.local\httpdocs` |
| Staging logs | `/var/www/vhosts/plogger-staging.local/logs` | `\\wsl.localhost\Ubuntu-22.04\var\www\vhosts\plogger-staging.local\logs` |
| nginx configuration directory | `/etc/nginx/conf.d` | `\\wsl.localhost\Ubuntu-22.04\etc\nginx\conf.d` |
| Apache configuration directory | `/etc/apache2/sites-available` | `\\wsl.localhost\Ubuntu-22.04\etc\apache2\sites-available` |

Prefer Linux paths for commands executed inside WSL. Use UNC paths for
Windows-based inspection and authorized editing, for example in File Explorer
or an editor. Editing through UNC changes the underlying WSL file immediately
and remains subject to Linux permissions; configuration edits still require
separate authorization and server validation.

The repository and deployed webroot remain separate directories under both path
notations. A Windows checkout such as `S:\GitHub\plogger-reloaded` is a separate
checkout, unlike the UNC alias of the Linux repository. Editing the repository
does not deploy it; editing the webroot changes the deployed copy directly.
Never copy credentials, staging `plog-config.php`, server configuration, private
logs, session cookies or CSRF tokens into Git. These mappings document access
only; no staging files, configuration or services were inspected or changed as
part of this documentation update.

## Verified results reported by the maintainer

| Check | Reported result |
| --- | --- |
| Installation | Completed successfully |
| MariaDB connection | Succeeded |
| Administrative login | Succeeded after CSRF/referrer-policy fix `d67856c` |
| JPEG upload | Succeeded |

Commit `d67856c` changes the referrer policy to `same-origin` while retaining
CSRF token and origin checks. See [the CSRF hotfix record](csrf-hotfix.md).
Successful login verifies that workflow on this setup, not every CSRF boundary.
Successful JPEG upload does not establish EXIF persistence, thumbnail correctness
or support for every image format.

## Outstanding staging failures

| ID | Observed failure | Required investigation and regression coverage |
| --- | --- | --- |
| STAGING-001 | EXIF metadata is not being extracted and persisted to the database. | Trace a known EXIF-bearing synthetic JPEG through native extraction, shared metadata mapping and prepared persistence. Compare expected fields with the stored row and capture sanitized diagnostics. Add a failing regression before repairing the path. Do not reintroduce Exifer or alter original photographs. |
| STAGING-002 | Frontend displays `Invalid CDN URL configuration. Contact the gallery administrator.` despite a configured CDN URL. | Reproduce with the actual configuration privately; inspect normalization, validation, stored value and generated URLs. Cover the supported URL shape and relevant host/port/path cases with regression tests. Do not assume the cause, disclose private URL parameters or disable validation as a workaround. |

Neither failure is fixed by this documentation change. Both remain open release
acceptance issues. Extension availability, exact server configuration files,
deny rules and runtime limits require recorded checks; they are not inferred
from the four successful workflows above.

## Manual update procedure

Run these commands inside Linux only when a staging update has been explicitly
authorized. First verify a restorable external backup of the staging database,
configuration, originals and custom themes/plugins. Keep backups outside both
the repository and served tree. Follow [UPGRADING.md](../UPGRADING.md) for separate
migration/rollback authorization; copying code does not apply or reverse a migration.

```bash
set -euo pipefail
cd /home/xiphias/projects/plogger-reloaded
git status --short
git branch --show-current
git diff --quiet
git diff --cached --quiet
test -z "$(git ls-files --others --exclude-standard)"
test "$(git branch --show-current)" = phase-1-php-compatibility
# Stop on failure; never reset or discard local changes.
git pull --ff-only
git rev-parse HEAD
```

Review the fetched revision and run the repository checks before copying files.
The following Bash block restricts deployment to runtime paths. It excludes
development material, local secrets, configuration, user images, thumbnails,
imports and translations. It preserves custom destination files by avoiding
deletion. Existing files with the same runtime path can still be replaced;
review local customizations and every proposed path before copying.

```bash
set -euo pipefail
repo=/home/xiphias/projects/plogger-reloaded
deploy=/var/www/vhosts/plogger-staging.local/httpdocs
test "$(realpath "$repo")" = /home/xiphias/projects/plogger-reloaded
test "$(realpath "$deploy")" = /var/www/vhosts/plogger-staging.local/httpdocs
test "$repo" != "$deploy"
filters=(
  --exclude='.git*' --exclude='.local/' --exclude='.env*'
  --exclude='tests/' --exclude='tools/' --exclude='docs/' --exclude='vendor/'
  --exclude='.phpunit*' --exclude='.phpstan*' --exclude='coverage/'
  --exclude='backups/' --exclude='*.log' --exclude='*.sql*'
  --exclude='*.pem' --exclude='*.key' --exclude='auth.json'
  --exclude='composer.*' --exclude='phpstan*' --exclude='phpunit*'
  --exclude='*.md' --exclude='readme.html'
  --exclude='/plog-config.php*' --exclude='/.htaccess'
  --exclude='/plog-content/images/***'
  --exclude='/plog-content/thumbs/***'
  --exclude='/plog-content/uploads/***'
  --exclude='/plog-content/translations/***'
  --include='/*.php' --include='/favicon.ico' --include='/LICENSE'
  --include='/plog-admin/***' --include='/plog-includes/***'
  --include='/plog-content/'
  --include='/plog-content/ui/***'
  --include='/plog-content/themes/' --include='/plog-content/themes/index.php'
  --include='/plog-content/themes/air/***'
  --include='/plog-content/themes/default/***'
  --include='/plog-content/themes/lucid/***'
  --include='/plog-content/themes/xip-bs/***'
  --include='/plog-content/themes/reloaded/***'
  --include='/plog-content/plugins/' --include='/plog-content/plugins/index.php'
  --include='/plog-content/plugins/latest-comments/***'
  --include='/plog-content/plugins/latest-images/***'
  --include='/plog-content/plugins/random-images/***'
  --include='/plog-content/modules/***' --include='/plog-content/index.php'
  --exclude='*'
)
sudo rsync -rltc --safe-links --itemize-changes --dry-run \
  "${filters[@]}" "$repo/" "$deploy/"
# Review the plan. Only after approving it, run manually:
# sudo rsync -rltc --safe-links --itemize-changes \
#   "${filters[@]}" "$repo/" "$deploy/"
```

Do not add `--delete`, `--delete-excluded`, `--remove-source-files` or broad
permission/ownership changes. This update example assumes an existing deployment
with its directory guards already installed. It does not provision writable
directories, rewrite local `.htaccess` rules or synchronize CDN storage.
No Composer installation, Node.js build or container is needed in the webroot.

## Permissions and HTTP protection requirements

- PHP-FPM runs as `www-data`. Code and configuration must be readable as needed,
  but not writable by the worker. Keep the checkout outside the webroot.
- Limit application-content writes to necessary `plog-content/images/`,
  `plog-content/thumbs/` and `plog-content/uploads/` directories. Private sessions,
  temporary files and logs need separately scoped storage outside public access.
  Grant installer configuration-write access temporarily only when required.
- Do not recursively make the webroot owned/writable by `www-data`, or use
  `chmod 777`. A configuration mode such as `0640`, with an appropriate deployment
  owner and `www-data` read group, is a policy example, not a verified current mode.
- Deny HTTP access to configuration (including backups/proposals), dotfiles,
  development files and internal PHP/includes. Disable listings and prevent
  script execution in image/thumbnail/upload directories, including double
  extensions. Retain required public assets. Verify restrictions through nginx
  and directly on the Apache backend; nginx does not process `.htaccess`.
- Follow [security configuration](security.md) and the
  [server deny-rule matrix](security-checklist.md). Preserve CSRF tokens and strict
  same-origin checks; do not weaken them for proxy misconfiguration.
- Never store passwords, real configuration, session cookies, CSRF/reset tokens,
  private logs, database dumps or user photographs in Git. Do not copy `.local/`
  notes into tracked documentation. Database names/users above are identifiers,
  not credentials.

## Manual verification after an authorized update

Run development tools from the checkout, not the deployed application:

```bash
cd /home/xiphias/projects/plogger-reloaded
php8.4 tools/check-runtime.php
php8.4 tools/check-extensions.php
php8.4 tools/lint.php
php8.4 vendor/phpunit/phpunit/phpunit --do-not-cache-result
php8.4 vendor/phpstan/phpstan/phpstan analyse --no-progress
php8.4 vendor/phpstan/phpstan/phpstan analyse -c phpstan.security.neon --no-progress
php8.4 vendor/phpstan/phpstan/phpstan analyse -c phpstan.application.neon --no-progress
sudo nginx -t
sudo apache2ctl configtest
curl --silent --show-error --output /dev/null --write-out '%{http_code}\n' \
  http://localhost:8080/
```

CLI extensions/settings do not prove FPM settings. Inspect effective worker
extensions, socket access, private session storage and the 60-second/256-MB
request budget through restricted diagnostics; never publish `phpinfo()`.
Review logs privately under the recorded log directory, redacting secrets before
sharing. Record the revision, engine versions, requests and actual results.

Repeat login/logout, CSRF rejection, upload/import, metadata persistence,
thumbnails, downloads and CDN rendering using synthetic content. Check private
resource denial with response bodies discarded; do not print configuration
contents. Follow the full [security checklist](security-checklist.md), including
reset mail capture, restore, accessibility and resource tests. Do not point
native database test fixtures at `plogger_staging`: those tests create/drop their
own databases on a separate disposable cluster/high port.

The PHP target remains **8.2–8.6**: patched **8.2–8.5** are the stable target,
and **8.6.0RC4** is a separate experimental prerelease target. This setup only
records PHP **8.4.27**. It does not establish PostgreSQL, all upgrade paths, other
PHP versions or production readiness. Follow [runtime-support.md](runtime-support.md)
for security floors and vendor-backport evidence. Keep the release **NO-GO**
until every blocker, including both staging failures above, is resolved and
verified. Do not generate a release archive, tag or publication from these results.

## Separate disposable acceptance run, 2026-10-23/24

[Executed release checks](rb05-08-validation.md) used a new
`/tmp/plogger-acceptance-*` copy, a separate MariaDB datadir on port 33338 and
per-test loopback nginx/Apache/FPM processes. They did not deploy to or modify
this document's existing webroot, database, services or uploaded files.
Fresh/three-source fixture workflows, both HTTP-layer deny policies, verified
TLS session cookies, custom private session storage, local password-reset mail
capture and generated EXIF upload/import persistence passed.

A fresh-installer defect initialized the CDN field to the application URL;
new installs now leave it empty. No existing CDN setting was changed. The prior
configured-CDN and real-image EXIF reports above still need a targeted recheck
against the affected installation and metadata, without printing credentials or
using originals as write targets. Passing generated fixtures does not establish
that these existing reports are resolved. Release status remains NO-GO.
