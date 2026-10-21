<?php
/** Inspect deployed CSS; no build step or generated output. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
$totals = ['gallery'=>0, 'admin'=>0];
foreach (['tokens','base','layout','components','gallery','admin'] as $name) {
    $path = 'plog-content/ui/css/'.$name.'.css';
    $css = file_get_contents($root.'/'.$path);
    if ($css === false) throw new RuntimeException('Cannot read stylesheet.');
    $gzip = gzencode($css, 9);
    if ($gzip === false) throw new RuntimeException('Cannot measure gzip size.');
    printf("%s: %d bytes; %d gzip bytes\n", $path, strlen($css), strlen($gzip));
    foreach ($totals as $area=>$total) if ($name !== ($area === 'gallery' ? 'admin' : 'gallery')) $totals[$area] += strlen($gzip);
}
$theme = file_get_contents($root.'/plog-content/themes/reloaded/theme.css');
if ($theme === false) throw new RuntimeException('Cannot read theme stylesheet.');
$gzip = gzencode($theme, 9);
if ($gzip === false) throw new RuntimeException('Cannot measure theme gzip size.');
$totals['gallery'] += strlen($gzip);
printf("reloaded/theme.css: %d bytes; %d gzip bytes\n", strlen($theme), strlen($gzip));
foreach ($totals as $area=>$size) printf("%s total (sum of separately gzipped files): %d bytes\n", $area, $size);
