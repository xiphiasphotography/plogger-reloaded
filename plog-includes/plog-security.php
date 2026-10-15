<?php
/** Escape untrusted text for HTML text and quoted attribute contexts. */
function plogger_escape(mixed $value): string {
    return htmlspecialchars(is_scalar($value) ? (string) $value : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Scalar request accessor: malformed arrays never reach string/URL/password functions.
 * @param array<array-key, mixed> $source */
function plogger_input(array $source, string $key, string $default = ''): string {
    return isset($source[$key]) && is_string($source[$key]) ? $source[$key] : $default;
}

function plogger_script_json(mixed $value): string {
    return json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?: 'null';
}

function plogger_public_link(mixed $url): string {
    if (!is_string($url) || preg_match('/[\x00-\x20\x7f<>"\x27]/', $url)) return '';
    $parts = parse_url($url);
    if ($parts === false || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
        || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) return '';
    return $url;
}

function plogger_image_public_url(string $path): string {
    global $config;
    if (!plogger_safe_relative_path($path)) return '#';
    return plogger_escape($config['gallery_url'].'plog-content/images/'.implode('/', array_map('rawurlencode', explode('/', $path))));
}

/** Browser redirects may remain within the current origin only. */
function plogger_local_redirect(mixed $candidate, string $base, string $fallback): string {
    if (!is_string($candidate) || preg_match('/[\x00-\x20\x7f\\\\]/', $candidate)) return $fallback;
    if (str_starts_with($candidate, '/') && !str_starts_with($candidate, '//')) return $candidate;
    if (plogger_public_link($candidate) === '') return $fallback;
    $target = parse_url($candidate);
    $origin = parse_url($base);
    foreach (['scheme', 'host'] as $key) {
        if (strcasecmp($target[$key] ?? '', $origin[$key] ?? '') !== 0) return $fallback;
    }
    $default = strtolower($origin['scheme'] ?? '') === 'https' ? 443 : 80;
    return ($target['port'] ?? $default) === ($origin['port'] ?? $default) ? $candidate : $fallback;
}

/** Log operation and location, never SQL, credentials, tokens or exception messages. */
function plogger_log_exception(Throwable $error, string $operation): string {
    static $reference;
    $reference ??= bin2hex(random_bytes(8));
    error_log('[Plogger '.$reference.'] '.preg_replace('/[^a-zA-Z0-9 ._-]/', '', $operation)
        .' '.get_class($error).' '.basename($error->getFile()).':'.$error->getLine());
    return $reference;
}

function plogger_exception_handler(Throwable $error): void {
    $reference = plogger_log_exception($error, 'Unhandled application exception');
    http_response_code(500);
    echo 'An application error occurred. Reference: '.plogger_escape($reference);
}

function plogger_debug_allowed(): bool {
    return defined('PLOGGER_DEBUG') && PLOGGER_DEBUG == '1'
        && ((!empty($_SESSION['plogger_logged_in']) && plogger_auth_session_valid())
            || in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true));
}

function plogger_error_handler(int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) return true;
    if ($severity === E_USER_ERROR || $severity === E_RECOVERABLE_ERROR) throw new ErrorException('', 0, $severity, $file, $line);
    plogger_log_exception(new ErrorException('', 0, $severity, $file, $line), 'PHP runtime warning');
    return true;
}

function plogger_safe_relative_path(mixed $path): bool {
    if (!is_string($path) || $path === '' || preg_match('/[\x00-\x1f\x7f\\\\:]/', $path)
        || $path[0] === '/') return false;
    foreach (explode('/', $path) as $part) {
        if ($part === '' || $part === '.' || $part === '..') return false;
    }
    return true;
}

/** Resolve an existing file without permitting symlinks outside the approved root. */
function plogger_path_within(mixed $path, mixed $root): bool {
    if (!is_string($path) || !is_string($root) || str_contains($path, "\0")) return false;
    $resolved = realpath($path);
    $base = realpath($root);
    if ($resolved === false || $base === false) return false;
    $base = rtrim(str_replace('\\', '/', $base), '/').'/';
    $resolved = str_replace('\\', '/', $resolved);
    if (PHP_OS_FAMILY === 'Windows') { $base = strtolower($base); $resolved = strtolower($resolved); }
    return str_starts_with($resolved, $base);
}

/** Check each existing ancestor before creating a derived-file destination. */
function plogger_safe_destination(string $root, string $relative): bool {
    if (!plogger_safe_relative_path($relative) || !is_dir($root)) return false;
    $candidate = rtrim($root, '/\\');
    foreach (explode('/', $relative) as $part) {
        $candidate .= '/'.$part;
        if ((file_exists($candidate) || is_link($candidate)) && !plogger_path_within($candidate, $root)) return false;
    }
    return true;
}

/** Apache can associate a handler with any extension component, not just the last. */
function plogger_safe_image_filename(mixed $filename): bool {
    if (!is_string($filename) || !plogger_safe_relative_path($filename) || str_contains($filename, '/')) return false;
    $stored = sanitize_filename($filename);
    return !preg_match('/(?:^|\.)(?:php[0-9]*|phtml|phar|cgi|pl|py|sh|shtml)(?:\.|$)/i', $stored);
}

function plogger_image_budget(int $width, int $height, int $bytes): bool {
    if ($width < 1 || $height < 1 || $width > 20000 || $height > 20000 || $width * $height > 20000000) return false;
    $limit = ini_get('memory_limit');
    $budget = (int) $limit;
    if (preg_match('/k$/i', $limit)) $budget *= 1024;
    elseif (preg_match('/m$/i', $limit)) $budget *= 1048576;
    elseif (preg_match('/g$/i', $limit)) $budget *= 1073741824;
    return $budget <= 0 || memory_get_usage(true) + $width * $height * 8 + $bytes * 2 < $budget;
}

/** Validate before moving originals or invoking legacy metadata/image libraries. */
function plogger_validate_image(mixed $path, mixed $filename): bool {
    if (!class_exists('finfo') || !function_exists('imagecreatefromstring')) return false;
    if (!is_string($path) || !is_string($filename) || !plogger_safe_image_filename($filename)
        || !is_file($path) || !is_readable($path)) return false;
    $types = ['jpg' => IMAGETYPE_JPEG, 'jpeg' => IMAGETYPE_JPEG, 'png' => IMAGETYPE_PNG,
        'gif' => IMAGETYPE_GIF, 'bmp' => IMAGETYPE_BMP];
    $type = $types[strtolower(pathinfo($filename, PATHINFO_EXTENSION))] ?? null;
    if ($type === null || filesize($path) > 32 * 1024 * 1024) return false;
    $info = @getimagesize($path);
    if ($info === false || $info[2] !== $type || $info[0] < 1 || $info[1] < 1
        || $info[0] > 20000 || $info[1] > 20000 || $info[0] * $info[1] > 20000000) return false;
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
    $mimes = [IMAGETYPE_JPEG => ['image/jpeg'], IMAGETYPE_PNG => ['image/png'],
        IMAGETYPE_GIF => ['image/gif'], IMAGETYPE_BMP => ['image/bmp', 'image/x-ms-bmp']];
    if (!in_array($mime, $mimes[$type], true)) return false;
    if (!plogger_image_budget($info[0], $info[1], filesize($path))) return false;
    $image = @imagecreatefromstring(file_get_contents($path));
    if ($image === false) return false;
    imagedestroy($image);
    return true;
}

/** Extract into a private temporary directory; publish only validated images. */
function plogger_extract_image_archive(string $archivePath, string $uploads): ?string {
    require_once __DIR__.'/plog-zip.php';
    plogger_require_zip();
    if (!is_file($archivePath) || filesize($archivePath) > 33554432) return null;
    if (!is_dir($uploads) || is_link($uploads)) return null;
    $stage = str_replace('\\', '/', sys_get_temp_dir()).'/plogger-zip-'.bin2hex(random_bytes(16));
    if (!mkdir($stage, 0700)) return null;
    $files = []; $destination = null; $published = false;
    try {
        $archive = new ZipArchive();
        if ($archive->open($archivePath, ZipArchive::CHECKCONS) !== true) return null;
        try {
            $entries = plogger_zip_entries($archive);
            if ($entries === null) return null;
            foreach ($entries as $entry) {
                $name = basename($entry['name']);
                $path = $stage.'/'.$name;
                if (!plogger_zip_extract_entry($archive, $entry['name'], $path, $entry['size'], $entry['crc'])
                    || !plogger_validate_image($path, $name)) return null;
                $files[] = $name;
            }
        } finally { $archive->close(); }
        if (!$files) return null;
        $destination = rtrim($uploads, '/\\').'/archive-'.bin2hex(random_bytes(16));
        // Rename is atomic on the same filesystem; copying supports a separate temp volume.
        if (!mkdir($destination, 0755)) return null;
        foreach ($files as $name) {
            if (!copy($stage.'/'.$name, $destination.'/'.$name)) {
                foreach (glob($destination.'/*') ?: [] as $created) unlink($created);
                rmdir($destination);
                return null;
            }
            chmod($destination.'/'.$name, 0644);
        }
        $published = true;
        return $destination;
    } catch (Throwable $error) {
        plogger_log_exception($error, 'Archive image extraction');
        return null;
    } finally {
        if (!$published && $destination !== null && is_dir($destination)) {
            foreach (new DirectoryIterator($destination) as $created) if ($created->isFile()) unlink($created->getPathname());
            rmdir($destination);
        }
        // Stage is newly generated and extraction removes all directory components.
        foreach (new DirectoryIterator($stage) as $created) if ($created->isFile()) unlink($created->getPathname());
        rmdir($stage);
    }
}
