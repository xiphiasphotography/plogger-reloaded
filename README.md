# Plogger Reloaded

**Status: Work in progress. Phase 2 security hardening is on `phase-1-php-compatibility` and is not yet included in `main`. Not ready for public deployment.**

A maintained continuation of Plogger, the self-hosted photo gallery organized as collections, albums, and photos. It is based on `plogger_php8` and retains the traditional PHP application structure.

## Compatibility target

- PHP 8.1–8.4
- MySQL/MariaDB and PostgreSQL via PDO
- nginx with PHP-FPM or Apache

These are project targets; `main` has not yet passed the full compatibility, security, or staging acceptance checks. Do not use it for a public or production deployment.

## Installation

For a fresh test installation, create a dedicated database and user, deploy the files to an isolated staging site, and open `plog-admin/_install.php`. Keep the generated `plog-config.php` and uploaded content out of version control.

**There is no supported safe in-place upgrade path yet. Do not run the inherited `plog-admin/_upgrade.php` against an existing gallery.**

## Embedding

Require `plogger.php` from a PHP page, call `the_plogger_head()` inside its `<head>`, and call `the_plogger_gallery()` where the gallery should appear:

```php
<?php require 'path/to/plogger.php'; ?>
<!doctype html>
<html>
<head>
    <?php the_plogger_head(); ?>
</head>
<body>
    <?php the_plogger_gallery(); ?>
</body>
</html>
```

## Development status

Compatibility repairs and security hardening are being developed on `phase-1-php-compatibility`. That branch contains tests and development tooling not yet present in `main`; merge status and test results should be checked on the branch before relying on them.

The project builds on the original Plogger authors and the PDO and PHP 8 forks. See [LICENSE](LICENSE) for the license text. License provenance and third-party notices still require review before redistribution.
