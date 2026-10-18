<?php
/** Public static asset URLs only. No remote requests or filesystem synchronization. */
function plogger_normalize_cdn_url(mixed $url): ?string {
    if (!is_string($url)) return null;
    if ($url === '') return '';
    if (strlen($url) > 255 || preg_match('/[\x00-\x20\x7f\\\\<>"\x27]/', $url)) return null;
    $parts = parse_url($url);
    if ($parts === false || !in_array(strtolower($parts['scheme'] ?? ''), ['http','https'], true)
        || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
        || isset($parts['query']) || isset($parts['fragment'])) return null;
    $host = strtolower($parts['host']); $address = trim($host, '[]');
    if (filter_var($address, FILTER_VALIDATE_IP)) {
        if (!filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return null;
    } elseif (strlen($host) > 253 || !str_contains($host, '.')
        || !preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z][a-z0-9-]{1,62}$/D', $host)
        || preg_match('/\.(?:localhost|local|internal)$/iD', $host)) return null;
    $path = $parts['path'] ?? '';
    if ($path !== '' && $path[0] !== '/') return null;
    $segments = [];
    $prefix = rtrim($path, '/');
    foreach ($prefix === '' ? [] : explode('/', substr($prefix, 1)) as $segment) {
        if (preg_match('/%(?![a-f0-9]{2})/i', $segment)) return null;
        $decoded = rawurldecode($segment);
        if ($decoded === '.' || $decoded === '..' || preg_match('/[\x00-\x1f\x7f\/\\\\]/', $decoded)
            || preg_match('/%[a-f0-9]{2}/i', $decoded) || !preg_match('//u', $decoded)) return null;
        $segments[] = rawurlencode($decoded);
    }
    $normalized = strtolower($parts['scheme']).'://'.$host.(isset($parts['port']) ? ':'.$parts['port'] : '')
        .'/'.($segments ? implode('/', $segments).'/' : '');
    return strlen($normalized) <= 255 ? $normalized : null;
}

function plogger_asset_origin_request(): bool {
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'] ?? '');
    return str_contains($script, '/plog-admin/')
        || in_array(basename($script), ['plog-thumb.php','plog-download.php','plog-remote.php'], true);
}

/** Validate raw filesystem-relative asset names, not already URL-encoded paths. */
function plogger_asset_path_valid(string $path): bool {
    $trimmed = rtrim($path, '/');
    if (strlen($path) > 4096 || !plogger_safe_relative_path($trimmed) || !preg_match('//u', $path)
        || !preg_match('~^plog-content/(images|thumbs|themes)/.+~D', $path)) return false;
    foreach (explode('/', $trimmed) as $segment) {
        do {
            if (str_starts_with($segment, '.') || preg_match('/[\x00-\x1f\x7f\/\\\\]/', $segment)) return false;
            $decoded = rawurldecode($segment);
            if ($decoded === $segment) break;
            $segment = $decoded;
        } while (true);
    }
    return !str_ends_with($path, '/') || str_starts_with($path, 'plog-content/themes/');
}

/** Return an unescaped URL. Legacy theme wrappers retain their existing HTML escaping.
 * $legacyOrigin preserves the raw URL shape of inherited picture-data arrays when CDN is off.
 */
function plogger_asset_url(string $path, ?string $legacyOrigin = null): string {
    global $config;
    if (!plogger_asset_path_valid($path)) return '#';
    $encoded = implode('/', array_map('rawurlencode', explode('/', $path)));
    $origin = rtrim($config['gallery_url'], '/').'/'.$encoded;
    $cdn = plogger_normalize_cdn_url($config['cdn_url'] ?? '');
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $image = in_array($extension, ['jpg','jpeg','png','gif','bmp','webp','avif'], true);
    $theme = str_starts_with($path, 'plog-content/themes/');
    $public = $theme ? (str_ends_with($path, '/') || $image || in_array($extension, ['css','js','mjs','svg','ico','woff','woff2','ttf','otf','eot'], true)) : $image;
    if (preg_match('/\.(?:php\d*|phtml|phar|cgi|pl|py|sh)(?:[.\/]|$)/i', $path)
        || str_starts_with($path, 'plog-content/thumbs/uploads/')
        || (str_starts_with($path, 'plog-content/images/') && empty($config['allow_fullpic']))) $public = false;
    if (!$public || plogger_asset_origin_request() || $cdn === '') return $legacyOrigin ?? $origin;
    if ($cdn === null) throw new InvalidArgumentException('Invalid CDN URL configuration.');
    return $cdn.$encoded;
}

/** Resolve our generated asset URLs to existing local files; never request the CDN over HTTP. */
function plogger_asset_local_path(string $url): ?string {
    global $config;
    if (strpbrk($url, '?#') !== false) return null;
    $bases = [rtrim($config['gallery_url'], '/').'/'];
    $cdn = plogger_normalize_cdn_url($config['cdn_url'] ?? '');
    if ($cdn !== null && $cdn !== '') $bases[] = $cdn;
    foreach ($bases as $base) {
        if (!str_starts_with($url, $base)) continue;
        $relative = rawurldecode(substr($url, strlen($base)));
        if (!plogger_asset_path_valid($relative)) continue;
        $path = $config['basedir'].$relative;
        $parts = explode('/', $relative);
        if (is_file($path) && plogger_path_within($path, $config['basedir'].'plog-content/'.$parts[1])) return $path;
    }
    return null;
}
