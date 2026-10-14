<?php
/** Authentication helpers shared by browser and remote administration. */
function plogger_password_hash(string $password): string {
    // Bcrypt fits the inherited MySQL varchar(64), without a schema migration.
    // Prehashing also preserves distinctions beyond bcrypt's 72-byte limit.
    return 'sha:'.password_hash(base64_encode(hash('sha256', $password, true)), PASSWORD_BCRYPT);
}

function plogger_password_verify(mixed $password, mixed $stored): bool {
    if (!is_string($password) || !is_string($stored)) return false;
    if (preg_match('/\A[0-9a-f]{32}\z/i', $stored)) {
        return hash_equals(strtolower($stored), md5($password));
    }
    if (str_starts_with($stored, 'sha:')) {
        return password_verify(base64_encode(hash('sha256', $password, true)), substr($stored, 4));
    }
    // Also accept standard modern hashes written by an existing integration.
    return password_verify($password, $stored);
}

function plogger_auth_session_valid(): bool {
    global $config;
    return is_string($_SESSION['plogger_auth_version'] ?? null)
        && hash_equals(hash('sha256', $config['admin_password']), $_SESSION['plogger_auth_version']);
}

function plogger_authenticate(mixed $username, mixed $password): bool {
    global $config;
    if (!plogger_login_attempt()) return false;
    if (!is_string($username) || !is_string($password)
        || !hash_equals((string) $config['admin_username'], $username)
        || !plogger_password_verify($password, $config['admin_password'])) return false;
    $previous = $config['admin_password'];
    if (!str_starts_with($previous, 'sha:')
        || password_needs_rehash(substr($previous, 4), PASSWORD_BCRYPT)) {
        $replacement = plogger_password_hash($password);
        // Compare-and-set prevents a simultaneous password change being overwritten.
        $updated = run_query('UPDATE '.plogger_db_table('config').' SET "admin_password" = ? WHERE "admin_password" = ?',
            false, 'Database error.', [$replacement, $previous]);
        if ($updated === false || $updated->rowCount() !== 1) return false;
        $config['admin_password'] = $replacement;
    }
    plogger_login_attempt(true);
    return true;
}

/** Reserve an attempt under an exclusive lock, independently of browser sessions. */
function plogger_login_attempt(bool $successful = false, string $purpose = 'login'): bool {
    $directory = defined('PLOGGER_AUTH_STORAGE') ? PLOGGER_AUTH_STORAGE
        : sys_get_temp_dir().'/plogger-auth-'.substr(hash('sha256', __DIR__), 0, 24);
    if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) return false;
    if (is_link($directory)) return false;
    if (plogger_path_within($directory, dirname(__DIR__))) return false;
    $file = $directory.'/'.hash('sha256', $purpose.'|'.(string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown')).'.json';
    if (is_link($file)) return false;
    $handle = @fopen($file, 'c+');
    if ($handle === false) return false;
    @chmod($file, 0600);
    try {
        if (!flock($handle, LOCK_EX)) return false;
        $stored = stream_get_contents($handle, 2048);
        $state = json_decode($stored ?: '{}', true);
        $now = time();
        $state = plogger_login_window(is_array($state) ? $state : [], $now);
        if ($successful) $state['attempts'] = 0;
        if ($state['attempts'] > 5) return false;
        rewind($handle);
        if (!ftruncate($handle, 0) || fwrite($handle, json_encode($state)) === false || !fflush($handle)) return false;
        return true;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function plogger_reset_key_valid(mixed $supplied, mixed $stored, ?int $now = null): bool {
    if (!is_string($supplied) || !is_string($stored)
        || !preg_match('/\A([a-f0-9]{8})\.[a-f0-9]{48}\z/', $supplied, $match)
        || !hash_equals($stored, $supplied)) return false;
    $issued = hexdec($match[1]);
    $now ??= time();
    return $issued <= $now && $issued + 3600 > $now;
}

/** Five authentication attempts per address in a fifteen-minute window. */
/** @param array<array-key, mixed> $state
 * @return array{started: int, attempts: int} */
function plogger_login_window(array $state, int $now): array {
    if (!isset($state['started'], $state['attempts']) || !is_int($state['started'])
        || !is_int($state['attempts']) || $state['started'] > $now || $state['started'] + 900 <= $now) {
        return ['started' => $now, 'attempts' => 1];
    }
    return ['started' => $state['started'], 'attempts' => $state['attempts'] + 1];
}
