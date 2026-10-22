# Contributing

Read [AGENTS.md](AGENTS.md), [AUDIT.md](AUDIT.md) and [ROADMAP.md](ROADMAP.md) before changes. Follow the requested phase or release-blocker scope. Use the [PHP runtime policy](docs/runtime-support.md); PHP 8.1 is unsupported and PHP 8.6 RC4 is experimental only.

Use a local branch and open a small PR against `main`. Write English code, comments and documentation; translate UI strings through `plog_tr()`. Use imperative commit subjects. Do not commit, push, deploy or migrate without the maintainer's authorization.

Follow [development setup](docs/development.md). Before a commit run:

```text
composer validate --no-check-publish
composer lint
composer test
composer analyse
composer analyse:security
composer analyse:application
git diff --check
```

Strict lint rejects syntax failures and compile-time deprecations. Tooling and focused security PHPStan run at level 6 against PHP 8.2 syntax. The separate full application report also runs at level 6 and has unresolved findings; review it without a suppression baseline. Keep E_ALL enabled. PHPUnit treats notices, warnings and deprecations as failures. Historical phase reports do not establish current release clearance.

Add a failing regression test before a bug fix where feasible; all behavior changes need PHPUnit coverage or an installer/upgrade fixture run. Preserve existing theme APIs, both PDO drivers, configurable table prefixes and original photos. Never use production content or credentials in tests. New/touched queries must use prepared statements. No new runtime dependency without a documented reason.

Review the diff for secrets, generated artifacts and unrelated changes. `.gitignore` does not protect already tracked secrets. Report checks actually run, skipped checks and staging steps in the PR. Preserve upstream notices; licensing review remains a publication blocker.
