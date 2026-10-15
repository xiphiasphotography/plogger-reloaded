<?php
/** Read-only CLI diagnostic; does not load site configuration or connect to a database. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$missing = [];
foreach (['gd', 'exif', 'zip', 'fileinfo', 'mbstring', 'PDO', 'session'] as $extension) {
    $enabled = extension_loaded($extension);
    echo $extension.': '.($enabled ? 'available' : 'MISSING; enable ext-'.$extension.' in php.ini').PHP_EOL;
    if (!$enabled) $missing[] = $extension;
}
echo 'PDO drivers: '.(class_exists('PDO') ? implode(', ', PDO::getAvailableDrivers()) : 'none').PHP_EOL;
exit($missing ? 1 : 0);
