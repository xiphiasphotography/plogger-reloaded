<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$root = dirname(__DIR__);
$process = proc_open(['git', 'ls-files', '-z', '--cached', '--others', '--exclude-standard'],
    [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);
if (!is_resource($process)) {
    fwrite(STDERR, "Cannot read Git inventory.\n");
    exit(1);
}
$inventory = stream_get_contents($pipes[1]);
$error = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
if (proc_close($process) !== 0 || $inventory === false) {
    fwrite(STDERR, "Git inventory failed: " . $error . "\n");
    exit(1);
}
$files = [];
foreach (explode("\0", $inventory) as $file) {
    if (str_ends_with($file, '.php') && !str_starts_with($file, 'vendor/') && is_file($root . '/' . $file)) {
        $files[] = $file;
    }
}
$failures = [];
foreach (array_unique($files) as $file) {
    $output = [];
    $status = 0;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($root . '/' . $file) . ' 2>&1', $output, $status);
    if ($status !== 0) {
        $failures[] = $file;
        fwrite(STDERR, $file . "\n" . implode("\n", $output) . "\n");
    }
    // Syntax deprecations can exit successfully; report and fail on those too.
    foreach ($output as $line) {
        if (stripos($line, 'Deprecated:') !== false || stripos($line, 'Warning:') !== false) {
            $failures[] = $file;
            fwrite(STDERR, $file . "\n" . $line . "\n");
        }
    }
}
printf("Linted %d PHP files; %d syntax failures.\n", count(array_unique($files)), count($failures));
exit($failures === [] ? 0 : 1);
