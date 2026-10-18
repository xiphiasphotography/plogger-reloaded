# Plesk Git deployment workflow

For an optional separate static hostname or storage/CDN origin, follow
[CDN publication and nginx setup](cdn.md). Keep that host independent of PHP-FPM
and deploy only a reviewed public-asset mirror; Plogger performs no storage sync.

This describes a future controlled staging deployment; Phase 0 is not ready for public production. No live Plesk settings are changed by repository setup. The application remains a traditional PHP website, with no Composer runtime dependency or frontend build step.

Plesk Git can pull a remote repository into a local repository and deploy to a selected directory. Automatic deployment can publish pulled changes immediately; select **Manual deployment** for review before deployment ([Plesk Git documentation](https://docs.plesk.com/en-US/obsidian/administrator-guide/website-management/deploying-website-content-using-git%E2%84%A2.75824/)).

1. Create an isolated staging domain, restrict access and select PHP-FPM 8.3 initially. Verify extensions, session path, uploads, permissions and database driver using [development.md](development.md). Keep nginx/Apache host routing under maintainer control.
2. In the domain's Git page, connect the Reloaded repository using read-only access where possible. Store private repository credentials/deploy keys in Plesk, never in tracked files or URLs. Select the reviewed branch and manual deployment. Confirm the destination belongs to staging; do not accept an existing production httpdocs path by default.
3. Prefer a nonpublic deployment directory as the incoming source. From that directory, publish only root application PHP files, favicon, LICENSE, `plog-admin/`, `plog-includes/` and bundled `plog-content/` distribution files into the staging webroot through a reviewed copy process. Preserve all preexisting config, originals, thumbs, uploads and custom themes/plugins. Avoid deletion/mirroring flags. Verify Plesk's actual handling of untracked destination files on a disposable staging copy before relying on it.
4. Exclude `.git`, `.github`, `.env*`, tests, tools, vendor, Composer/tool configs, caches, audit/development docs and backups from the served tree. `.gitignore` controls Git tracking, not web access or deployment exclusions. A raw repository deployment to httpdocs needs explicit host access restrictions before any requests; the nonpublic incoming directory avoids serving development files.
5. Create site configuration through the existing installer on a fresh staging database, or restore a separately backed-up staging config. Do not overwrite an existing config during pulls/copies. Runtime does not need `composer install`; never run dev dependency installation as a Plesk deployment action. Use host permissions instead of chmod 777.
6. Pull the reviewed revision, inspect incoming files and deploy manually. Record its commit ID. Run the manual smoke checklist on staging, check server logs and verify originals remain byte-identical. Git pull/deploy is not a schema migration. Do not invoke `_upgrade.php` as a deployment hook.

Before any eventual production update, externally back up and verify restore of the prefixed database tables, configuration, originals and custom site files. Keep the previous code revision and document which database/files backup matches it. Reverting Git code does not undo database changes. The reviewed runner and rollback procedure are documented in [UPGRADING.md](../UPGRADING.md); no automated production switch is configured here.

For the php7-based first target, the default order remains: backup, create a test-domain copy, move that copy to PHP 8.3, run the backup-gated staging upgrade, verify the private theme and gallery, then separately authorize the production switch. Phase 0 provides no PHP 7.4 bridge and performs no deployment.
