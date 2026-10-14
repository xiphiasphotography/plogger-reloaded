# Phase 0 validation record

Performed on 2026-10-14 on local Windows PowerShell, branch `phase-0-repository-setup`. Starting checkout was `main` at `f79f85f`; AGENTS.md and AUDIT.md were already untracked and were left unchanged. Git history was not reset or rewritten.

| Check | Actual result |
| --- | --- |
| PHP CLI discovery | PHP 8.5.11 available; outside supported matrix |
| Syntax of three new PHP files | Passed (`tools/lint.php`, `tests/bootstrap.php`, `tests/LegacyHelpersTest.php`) |
| Full strict lint | 133 PHP files checked; exit 1, four inherited errors exactly matching AUDIT.md |
| Explicit audited lint baseline | Exit 0; reports/accepts only the four audited failures, not clean application syntax |
| Direct helper sanity check | Quote/Unicode/newline preservation and fixed-length password shape passed; this was not a PHPUnit run |
| Configuration parsing | composer.json JSON and phpunit.xml.dist XML parsed successfully; not Composer schema validation |
| Ignore-rule probes | Config, .env, photo, import, custom theme and vendor paths ignored; sample config, bundled theme and composer.lock retained |
| git diff --check | Passed |
| Application/data diff | README is the only modified tracked file; added files are development tooling/docs. No application, schema, library, original photo or LICENSE changes |

The strict lint failures are GPS curly offsets, PclZip write-context syntax, phpThumb demo curly offsets and phpThumb GIF curly offsets. Their repair is deliberately outside this Phase 0 task.

Not performed: Composer validation/install/lock generation (Composer unavailable); PHPUnit and PHPStan (dependencies unavailable); supported PHP 8.1–8.4 execution (only 8.5 available); GitHub Actions execution (no push); MySQL/MariaDB/PostgreSQL installer/integration tests, web smoke tests and Plesk deployment (no authorized staging/database environment). Local PHP lacks GD, EXIF, mbstring and both PDO database drivers, so it is also unsuitable for application acceptance.

Before Phase 0 acceptance, install reviewed tools on supported PHP, generate/review the dependency lock, run the matrix and complete staging installation for both database drivers. Known inherited compatibility/security blockers and unresolved license provenance remain. No commits, pushes, releases, database migrations or deployments were performed.
