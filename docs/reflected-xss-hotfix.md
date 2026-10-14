# Legacy reflected XSS hotfix

Scope: the two legacy paths reported by the maintainer as 2014 vulnerabilities. No CVE attribution or broader security clearance is claimed.

Login form actions now accept only `plog-upload.php`, `plog-import.php`, `plog-manage.php`, `plog-feedback.php`, `plog-options.php`, `plog-themes.php` and `plog-plugins.php`. External URLs, query/fragment suffixes, encoded alternatives, traversal, arrays and other malformed targets fall back to upload. The form action uses `htmlspecialchars()` with ENT_QUOTES and UTF-8. The neighboring unauthenticated admin redirect uses the same allowlist and URL-encodes its `r` value.

Unknown non-Plogger query arguments are serialized with `http_build_query()` and PHP_QUERY_RFC3986. Scalar values, empty values and nested arrays remain supported. Gallery control parameters, including `jump-menu`, are excluded from propagation. Search hidden-field names and decoded values are escaped for HTML attributes, including literal entities, quotes and invalid UTF-8. Array field names retain PHP bracket notation.

`generate_url()` preserves its public signature and default HTML-ready return contract; plaintext callers still receive a raw URL. It encodes argument keys/values and rewritten path segments, escapes the completed HTML URL, and preserves multiple parameters at collections level. Neighboring search/slideshow/sort callers now pass raw search text to avoid double URL encoding. Null argument lists remain accepted. Existing themes do not need edits.

Regression tests first reproduced login attribute injection and gallery attribute injection/array conversion warnings. HTTP tests inspect parsed HTML and navigation URLs in all four bundled themes. Helper tests cover the destination allowlist, entity and percent handling, malformed bytes, nested arrays, collections links, rewritten paths and plaintext versus HTML output.

Validation uses synthetic temporary application copies and images. No production configuration, databases or originals are modified. Other Phase 2 security gates and existing application PHPStan findings remain unresolved; this hotfix is not public-release clearance.

Checks executed on PHP 8.1.34, 8.2.34, 8.3.35 and 8.4.26: strict syntax checks passed for 133 PHP files; PHPUnit passed with 28 tests and 619 assertions per version, including the two HTTP XSS regressions, helper cases and inherited theme API checks. Tooling PHPStan level 3 passed on PHP 8.3; the application report still has 165 findings. CRLF-aware `git diff --check` passed. Native database integration and nginx/PHP-FPM/browser execution were not repeated for this hotfix: no SQL or database behavior was changed, and HTTP checks use the isolated PDO fixture. GitHub Actions and production deployment were not executed.
