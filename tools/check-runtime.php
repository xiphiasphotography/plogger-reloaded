<?php
/** Inspect-only CLI check: no configuration, sessions or database access. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/runtime-policy.php';
$policy = plogger_runtime_policy(PHP_VERSION);
echo PHP_VERSION.': '.$policy['state'].'; '.$policy['message'].PHP_EOL;
exit($policy['state'] === 'unsupported' ? 1 : 0);
