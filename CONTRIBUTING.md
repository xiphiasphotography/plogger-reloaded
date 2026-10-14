# Contributing

Read [AGENTS.md](AGENTS.md), [AUDIT.md](AUDIT.md) and [ROADMAP.md](ROADMAP.md) before changes. Follow the current phase; Phase 1 repairs compatibility without adding gallery features.

Use a local branch and open a small PR against `main`. Write English code, comments and documentation; translate UI strings through `plog_tr()`. Use imperative commit subjects. Do not commit, push, deploy or migrate without the maintainer's authorization.

Follow [development setup](docs/development.md). Before a commit run:

```text
composer validate --no-check-publish
composer lint
composer test
composer analyse
composer analyse:application
git diff --check
```

Strict lint now rejects syntax failures and compile-time deprecations, including the previously audited files. Tooling PHPStan runs at level 3; the separate application report also runs at level 3 and currently has unresolved legacy findings. Review that report in every PR without blanket suppression. Phase 2 must reach level 6+. See docs/phase-1-validation.md for the measured scope.

Add a failing regression test before a bug fix where feasible; all behavior changes need PHPUnit coverage or an installer/upgrade fixture run. Preserve existing theme APIs, both PDO drivers, configurable table prefixes and original photos. Never use production content or credentials in tests. New/touched queries must use prepared statements. No new runtime dependency without a documented reason.

Review the diff for secrets, generated artifacts and unrelated changes. `.gitignore` does not protect already tracked secrets. Report checks actually run, skipped checks and staging steps in the PR. Preserve upstream notices; licensing review remains a publication blocker.
