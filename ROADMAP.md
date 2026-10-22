# Roadmap

## Final release audit — 2026-10-22

**v1.0.0-beta.1 is blocked.** See [RELEASE-BLOCKERS.md](RELEASE-BLOCKERS.md)
and [executed checks](docs/release-readiness-audit.md). The requested PHP
8.2–8.6 target now has passing local checks on patched stable 8.2–8.5 and
experimental 8.6.0RC4; this is not complete native database/staging acceptance.
The audit found an unbound album-deletion value; native database/upgrade,
full analysis, licensing and staging gates remain open. Draft notes are in
[CHANGELOG.md](CHANGELOG.md); no release ZIP or publication was authorized by
these results. Earlier phase reports below are historical evidence, not current
release clearance.

RB-01 follow-up: deletion/move IDs and whole selections are now validated and
direct management query values are bound. Local regressions pass; native
MySQL/MariaDB and PostgreSQL results are still required before closure.
See [RB-01 evidence](docs/rb-01-management-sql.md). Other blockers remain open.

RB-02/03/04 follow-up: version policy, Composer/lock, CI and PHPStan now agree;
patched-runtime local checks pass with E_ALL. RC4 remains experimental with an
upstream Composer deprecation. Native driver and hosted CI results are pending.
See [compatibility evidence](docs/php-82-86-validation.md) and
[runtime requirements](docs/runtime-support.md). Release remains blocked.

Status: Phase 1 compatibility repairs prepared; final staging/static-analysis acceptance is **pending**. [AUDIT.md](AUDIT.md) is the verified source baseline. The supported PHP/database fixture matrix passes; full staging, security and upgrade readiness are not established.

## Phase 0 — repository setup

- [x] Development branch with inherited history retained (no reset or reinitialization).
- [x] README, CONTRIBUTING, CREDITS and local development/Plesk deployment guides.
- [x] Development-only Composer manifest, PHPUnit harness and PHPStan tooling scope.
- [x] PHP 8.2–8.6 GitHub Actions syntax/unit/static-analysis matrix without containers.
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

## Phase 2 — security hardening

- [x] Shared PDO helpers, validated identifiers/prefixes and prepared value handling without schema migrations.
- [x] Shared modern password verification, successful-login MD5 upgrades, session invalidation, throttling and expiring single-use reset links.
- [x] Core administrative CSRF/POST enforcement, session-cookie hardening and contextual escaping regressions.
- [x] Image content/decode validation, bounded private archive extraction, import/cache containment and download limits.
- [x] Disable the standalone phpThumb endpoint and remote administration by default; remove PHP eval and administrative response-script evaluation.
- [x] Add redacted exception logging, Apache/nginx examples and blocking level-6 analysis for the shared security code.
- [ ] Resolve/review the full application's remaining PHPStan level-6 findings; no suppression baseline is used.
- [ ] Run the real Apache/nginx/PHP-FPM, mail delivery and browser security checklist on staging; verify the private theme when available.

See [security.md](docs/security.md) for behavior changes and [phase-2-validation.md](docs/phase-2-validation.md) for executed checks. Phase 2 acceptance remains open; no production deployment is authorized by these results.

## Security hotfix history and later phases

An explicitly requested [reflected XSS hotfix](docs/reflected-xss-hotfix.md) addresses legacy login redirects and propagated gallery query arguments. This limited security correction does not complete Phase 2 or restart Phase 1.

The subsequent [stored-description XSS hotfix](docs/stored-description-xss-hotfix.md) sanitizes album/collection/photo descriptions at rendering, including administrative inline edits, while retaining basic formatting and leaving database content unchanged.

The [CSRF hotfix](docs/csrf-hotfix.md) adds session-bound tokens, protected POST workflows and secure session-cookie defaults across core administration, installer and remote commands. The unsafe legacy upgrader is disabled. Broader Phase 2 security and staging acceptance remain pending.

The [database layer work](docs/database-layer.md) adds shared native PDO execution/DSNs, typed bindings, identifier/prefix validation and prepared write/search paths. Quoting bugs are corrected without a schema migration; broader Phase 2 gates remain pending.

- [ ] Phase 2: authorization, prepared SQL, password migration, CSRF, escaping, safe uploads, sessions, throttling and unsafe eval removal.
- [x] Phase 3 implementation: native EXIF/IPTC adapter and ZipArchive replace the retired libraries; gettext/phpThumb retained and hardened. See [library inventory](docs/libraries.md) and [validation](docs/phase-3-validation.md). XMP mappings/import-prefill remain Phase 5 work.
- [x] Phase 4 implementation: standalone CLI/opt-in web migration runner, schema tracking/checksums/locks, reviewed dry-run and backup gates, protected config proposal, shared fresh-install schema, theme checks and three-source synthetic fixtures. See [UPGRADING.md](UPGRADING.md) and [validation](docs/phase-4-validation.md).
- [ ] Phase 4 acceptance: authoritative original RC1 fixture provenance, actual CI run, staging restore/nginx/FPM/browser checks and private dgp2026 validation. Unknown older/custom schemas are refused without destructive guesses.
- [x] Phase 5a implementation: shared EXIF/IPTC/XMP priorities, editable metadata/source labels, bounded FTP preview/commit, session caching and security regressions. See [metadata policy](docs/metadata.md) and [validation](docs/phase-5a-validation.md).
- [ ] Phase 5a staging acceptance: compare provisional mappings/title policy with the historical custom import code; measure large-directory performance on nginx/PHP-FPM and verify private dgp2026 when provided.
- [x] Phase 5b implementation: optional provider module, generic photograph actions/album fields, nullable album ID, original dimensions and resumable protected backfill; ordering remains disabled. See [Oypo contract](docs/oypo.md) and [validation](docs/phase-5b-validation.md).
- [ ] Phase 5b activation/staging acceptance: obtain current URL/identifier code, exact pixel policy, private schema and dgp2026; verify native database upgrades and staging behavior before activation.
- [x] Phase 5c implementation: normalized/validated CDN setting, shared public asset URLs, local thumbnail inspection, origin-only private/admin workflows and a tested static nginx policy. See [CDN deployment](docs/cdn.md) and [validation](docs/phase-5c-validation.md).
- [ ] Phase 5c staging acceptance: confirm real mirror/prefix and publication policy, exercise TLS/nginx/PHP-FPM/provider synchronization and private-theme behavior; no production CDN was configured.
- [x] Phase 6 UI implementation: scoped first-party CSS, separate gallery/admin styling, opt-in Reloaded theme, compatibility checks, HTTP/native-browser regressions and CSS size reporting. See [architecture](docs/plogger-ui.md) and [validation](docs/phase-6-validation.md).
- [ ] Phase 6 staging/release acceptance: private dgp2026, manual keyboard/screen-reader/zoom checks and real nginx/PHP-FPM/database staging; release artifacts remain gated on all earlier security, upgrade and licensing acceptance checks.

Public release blockers include full application analysis/review, actual webserver upload-execution/config protection tests, staging security acceptance, the private-theme check, real-install upgrade/restore acceptance and unresolved licensing. The destructive upgrader is removed and the standalone thumbnail endpoint stays disabled; remote administration and web upgrading are opt-in. Use the reviewed, backup-gated workflow in UPGRADING.md; no production upgrade has been performed.

Maintainer inputs remain pending: private dgp2026 theme, historical import/Oypo code, ordering pixel rule, metadata mapping/truncation, pretty URL rules, data volume, CDN mirror/access policy and original license grants. Default migration is staging on PHP 8.3; no PHP 7.4 bridge is assumed.
