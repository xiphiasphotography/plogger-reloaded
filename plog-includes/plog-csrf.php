<?php
// Shared session and browser-request protection; no database operations.
function plogger_https_request(): bool {
    return (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

function plogger_start_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE || headers_sent()) return;
    if (defined('PLOGGER_SESSION_SAVE_PATH') && PLOGGER_SESSION_SAVE_PATH !== '') {
        session_save_path(PLOGGER_SESSION_SAVE_PATH);
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    $options = session_get_cookie_params();
    $options['httponly'] = true;
    $options['secure'] = plogger_https_request();
    $options['samesite'] = 'Lax';
    session_set_cookie_params($options);
    session_start();
}

function plogger_end_session(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) return;
    $options = session_get_cookie_params();
    unset($options['lifetime']);
    $options['expires'] = time() - 42000;
    setcookie(session_name(), '', $options);
    $_SESSION = array();
    session_destroy();
}

function plogger_csrf_token(): string {
    if (!isset($_SESSION['plogger_csrf']) || !is_string($_SESSION['plogger_csrf'])) {
        $_SESSION['plogger_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['plogger_csrf'];
}

function plogger_csrf_valid(mixed $token): bool {
    return is_string($token) && isset($_SESSION['plogger_csrf'])
        && is_string($_SESSION['plogger_csrf']) && hash_equals($_SESSION['plogger_csrf'], $token);
}

function plogger_csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="'.htmlspecialchars(plogger_csrf_token(), ENT_QUOTES, 'UTF-8').'" />';
}

function plogger_csrf_forms(string $html): string {
    $result = preg_replace_callback('/<form\b[^>]*\bmethod=["\x27]post["\x27][^>]*>/i', function ($match) {
        return $match[0].plogger_csrf_field();
    }, $html);
    if ($result === null) throw new RuntimeException("Unable to protect administrative forms");
    return $result;
}

function plogger_csrf_row_button(string $name, int|string $id, string $label): string {
    $confirm = 'return confirm('.json_encode(plog_tr('Are you sure?'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP).');';
    return '<button type="submit" name="'.htmlspecialchars($name, ENT_QUOTES, 'UTF-8').'" value="'.intval($id).'" onclick="'.htmlspecialchars($confirm, ENT_QUOTES, 'UTF-8').'">'.htmlspecialchars($label, ENT_QUOTES, 'UTF-8').'</button>';
}

function plogger_csrf_require_post(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit(plog_tr('This action requires POST.'));
    }
    $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
    $expected = (plogger_https_request() ? 'https://' : 'http://').($_SERVER['HTTP_HOST'] ?? '');
    if (($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site'
        || ($origin !== null && (!is_string($origin) || strcasecmp($origin, $expected) !== 0))
        || !plogger_csrf_valid($_POST['_csrf'] ?? null)) {
        http_response_code(403);
        exit(plog_tr('Invalid or missing request token. Reload the form and try again.'));
    }
}

function plogger_csrf_admin_guard(): void {
    header('Cache-Control: no-store');
    header('Referrer-Policy: no-referrer');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') plogger_csrf_require_post();
    $array_fields = array('selected', 'captions', 'descriptions', 'allow_comments', 'destinations');
    foreach ($_POST as $key => $value) {
        if (!is_array($value)) continue;
        if (!in_array($key, $array_fields, true) || count($value) > 1000) {
            http_response_code(400);
            exit(plog_tr('Malformed administrative request.'));
        }
        foreach ($value as $item) {
            if (!is_string($item)) {
                http_response_code(400);
                exit(plog_tr('Malformed administrative request.'));
            }
        }
    }
    $readActions = array('edit-picture', 'edit-album', 'edit-collection', 'edit-comment');
    if (isset($_REQUEST['action']) && !in_array($_REQUEST['action'], $readActions, true)) plogger_csrf_require_post();
    foreach (array('activate', 'delete_checked', 'move_checked', 'approve_checked', 'upload') as $key) {
        if (isset($_GET[$key])) plogger_csrf_require_post();
    }
}
