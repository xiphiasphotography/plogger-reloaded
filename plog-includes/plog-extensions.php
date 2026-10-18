<?php
/** Trusted local modules register data; core owns action markup and escaping. */
final class PloggerExtensions {
    /** @var array<string, callable> */
    public static array $actions = [];
    /** @var array<string, array{label:string,validate:callable}> */
    public static array $albumFields = [];
}

/** @param array<string, mixed> $input
 * @return array<string, mixed> */
function plogger_album_extension_values(array $input): array {
    $values = [];
    foreach (PloggerExtensions::$albumFields as $name => $field) {
        if (array_key_exists($name, $input)) $values[$name] = ($field['validate'])($input[$name]);
    }
    return $values;
}

/** @param array<string, mixed> $album */
function plogger_album_extension_form(array $album): string {
    $html = '';
    foreach (PloggerExtensions::$albumFields as $name => $field) {
        $html .= '<p><label for="'.plogger_escape($name).'">'.plogger_escape($field['label']).'</label><br />'
            .'<input id="'.plogger_escape($name).'" name="'.plogger_escape($name).'" maxlength="128" value="'
            .plogger_escape($album[$name] ?? '').'" /></p>';
    }
    return $html;
}

/** @param array<string, mixed>|null $picture
 * @param array<string, mixed>|null $album */
function plogger_get_picture_actions(?array $picture = null, ?array $album = null): string {
    if (!PloggerExtensions::$actions) return '';
    $picture ??= $GLOBALS['current_picture'] ?? [];
    $album ??= get_album_by_id((int) ($picture['parent_album'] ?? 0)) ?: [];
    $html = '';
    foreach (PloggerExtensions::$actions as $provider) {
        $action = $provider($picture, $album);
        if (!is_array($action) || !is_string($action['label'] ?? null)) continue;
        $url = plogger_public_link($action['url'] ?? null);
        if ($url === '') continue;
        $html .= '<a class="plog-picture-action" href="'.plogger_escape($url).'" target="_blank" rel="noopener noreferrer">'
            .plogger_escape($action['label']).'</a>';
    }
    return $html === '' ? '' : '<div class="plog-picture-actions">'.$html.'</div>';
}

/** Explicit local allowlist; never accept module names from a request or database. */
function plogger_load_extensions(): void {
    static $loaded = false;
    if ($loaded) return;
    $loaded = true;
    $modules = defined('PLOGGER_MODULES') ? PLOGGER_MODULES : [];
    if (is_string($modules)) $modules = array_filter(array_map('trim', explode(',', $modules)));
    if (!is_array($modules)) return;
    foreach ($modules as $module) {
        if (!is_string($module) || !preg_match('/^[a-z][a-z0-9-]*$/D', $module)) continue;
        $root = dirname(__DIR__).'/plog-content/modules';
        $file = $root.'/'.$module.'/module.php';
        if (is_file($file) && plogger_path_within($file, $root)) require_once $file;
    }
}
