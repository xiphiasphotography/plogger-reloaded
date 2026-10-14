# Roadmap

Status: Phase 1 compatibility repairs prepared; final staging/static-analysis acceptance is **pending**. [AUDIT.md](AUDIT.md) is the verified source baseline. The supported PHP/database fixture matrix passes; full staging, security and upgrade readiness are not established.

## Phase 0 — repository setup

- [x] Development branch with inherited history retained (no reset or reinitialization).
- [x] README, CONTRIBUTING, CREDITS and local development/Plesk deployment guides.
- [x] Development-only Composer manifest, PHPUnit harness and PHPStan tooling scope.
- [x] PHP 8.1–8.4 GitHub Actions syntax/unit/static-analysis matrix without containers.
- [x] Ignore rules for local configuration, secrets, caches and site content; bundled guards/assets preserved.
- [x] Install temporary development tools, generate composer.lock on supported PHP; run PHPUnit and tooling PHPStan. Lock-file review remains part of patch review.
- [x] Add native MariaDB/PostgreSQL installer/HTTP fixtures and execute the matrix locally.
- [ ] Execute the configured GitHub Actions workflow after an authorized push.
- [ ] Demonstrate a fresh browser installation/config download on nginx/PHP-FPM or Apache staging.

The audited syntax errors are repaired, obsolete demos removed, and CI now uses strict lint. See docs/phase-1-validation.md for actual local results and remaining limits. No Phase 0 acceptance box is checked solely because CI was configured.

## Phase 1 — PHP compatibility

- [x] Repair fatal offsets, constructors, argument/reference errors and thumbnail/remote bootstrapping; retain bundled libraries.
- [x] Repair tested deprecations, missing request defaults, nullable metadata/timestamps and missing thumbnail dimensions.
- [x] Preserve 106 inherited theme-facing function signatures; render all bundled themes in fixtures.
- [x] Add PHP/library/HTTP regression tests and run supported PHP/database fixtures locally.
- [x] Recheck the matrix after guarding missing translation-file resources and administration diagnostics without GD/server headers (24 tests, 401 assertions per PHP version).
- [ ] Resolve/review remaining application PHPStan findings; no blanket baseline is added.
- [ ] Complete nginx/PHP-FPM/manual browser acceptance and private dgp2026 compatibility checks when provided.

No production configuration, databases or originals were changed. The only protective behavior addition requires a successful remote login session before remote writes, so repaired write routes do not become unauthenticated entry points. Phase 2 security gates remain unresolved.

## Later phases — not started by this task

An explicitly requested [reflected XSS hotfix](docs/reflected-xss-hotfix.md) addresses legacy login redirects and propagated gallery query arguments. This limited security correction does not complete Phase 2 or restart Phase 1.

The subsequent [stored-description XSS hotfix](docs/stored-description-xss-hotfix.md) sanitizes album/collection/photo descriptions at rendering, including administrative inline edits, while retaining basic formatting and leaving database content unchanged.

The [CSRF hotfix](docs/csrf-hotfix.md) adds session-bound tokens, protected POST workflows and secure session-cookie defaults across core administration, installer and remote commands. The unsafe legacy upgrader is disabled. Broader Phase 2 security and staging acceptance remain pending.

- [ ] Phase 2: authorization, prepared SQL, password migration, CSRF, escaping, safe uploads, sessions, throttling and unsafe eval removal.
- [ ] Phase 3: fully remove PclZip and Exifer, replacing them with native ZipArchive and EXIF plus separate native IPTC/XMP adapters; retain, audit and modernize gettext and phpThumb while preserving translation APIs, thumbnail behavior and caching.
- [ ] Phase 4: protected CLI/web idempotent migrations, dry-run, backup/restore gates, config/theme checks and three-source fixtures.
- [ ] Phase 5: editable metadata import preview, optional Oypo hook/module and public asset CDN URLs.
- [ ] Phase 6: dependency-free Plogger UI and reloaded theme, retain legacy themes, then release artifacts after all acceptance gates.

Public release blockers include the remaining remote-route audit, destructive upgrader, upload execution risk, missing admin CSRF, SQL/XSS/authentication weaknesses, reachable unsafe eval and unresolved licensing. Existing manual upgrade instructions are not a safe migration path.

Maintainer inputs remain pending: private dgp2026 theme, historical import/Oypo code, ordering pixel rule, metadata mapping/truncation, pretty URL rules, data volume, CDN mirror/access policy and original license grants. Default migration is staging on PHP 8.3; no PHP 7.4 bridge is assumed.
