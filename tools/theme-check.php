<?php
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once dirname(__DIR__).'/plog-includes/plog-theme-check.php';
$path=$argv[1] ?? '';
if(!is_dir($path) || is_link($path)) { fwrite(STDERR,"Usage: php tools/theme-check.php THEME_DIRECTORY\n"); exit(1); }
try {
    $report=plogger_check_theme($path);
    echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
    exit($report['errors']?1:0);
} catch(Throwable $error) { fwrite(STDERR,"Theme check failed. Check path, syntax and scan limits.\n"); exit(1); }
