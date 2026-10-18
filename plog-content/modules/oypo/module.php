<?php
if (!class_exists('PloggerExtensions', false)) { http_response_code(404); exit; }
/** Optional integration. There is deliberately no default provider URL or pixel rule. */
function plogger_oypo_album_id(mixed $value): ?string {
    if ($value === '' || $value === null) return null;
    if (!is_string($value) || !preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $value)) {
        throw new InvalidArgumentException(plog_tr('Enter an Oypo album ID using letters, digits, underscores or hyphens, or leave it empty.'));
    }
    return $value;
}

/** @return array{mode:string,width:int,height:int,long_edge:int}|null */
function plogger_oypo_rule(mixed $rule): ?array {
    if (!is_array($rule) || !in_array($rule['mode'] ?? null, ['long_edge', 'dimensions'], true)) return null;
    $keys = $rule['mode'] === 'long_edge' ? ['long_edge'] : ['width', 'height'];
    $result = ['mode'=>$rule['mode'], 'width'=>0, 'height'=>0, 'long_edge'=>0];
    foreach ($keys as $key) {
        if (!is_int($rule[$key] ?? null) || $rule[$key] < 1 || $rule[$key] > 2147483647) return null;
        $result[$key] = $rule[$key];
    }
    return $result;
}

/** Stored original axes, before any EXIF display rotation.
 * @param array<string, mixed> $picture */
function plogger_oypo_eligible(array $picture, mixed $albumId, mixed $rule): bool {
    try { if (plogger_oypo_album_id($albumId) === null) return false; }
    catch (InvalidArgumentException) { return false; }
    $rule = plogger_oypo_rule($rule);
    $width = filter_var($picture['original_width'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    $height = filter_var($picture['original_height'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    if (!$rule || !$width || !$height) return false;
    return $rule['mode'] === 'long_edge' ? max($width, $height) >= $rule['long_edge']
        : $width >= $rule['width'] && $height >= $rule['height'];
}

/** The supplied trusted adapter receives individually URL-encoded identifier/path values.
 * @param array<string, mixed> $picture
 * @param array<string, mixed> $album
 * @return array{url:string,label:string}|null */
function plogger_oypo_action(array $picture, array $album, mixed $rule, ?callable $adapter): ?array {
    if (!$adapter || !plogger_oypo_eligible($picture, $album['oypo_album_id'] ?? null, $rule)) return null;
    $path = $picture['path'] ?? null;
    $id = filter_var($picture['id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    if (!is_string($path) || !plogger_safe_relative_path($path) || !$id) return null;
    $url = $adapter(rawurlencode($album['oypo_album_id']), rawurlencode((string) $id),
        implode('/', array_map('rawurlencode', explode('/', $path))));
    if (!is_string($url) || plogger_public_link($url) === '') return null;
    return ['url'=>$url, 'label'=>plog_tr('Order photograph')];
}

PloggerExtensions::$albumFields['oypo_album_id'] = ['label'=>plog_tr('Oypo album ID (optional)'), 'validate'=>'plogger_oypo_album_id'];
PloggerExtensions::$actions['oypo'] = static function (array $picture, array $album): ?array {
    $adapter = defined('PLOGGER_OYPO_URL_ADAPTER') ? PLOGGER_OYPO_URL_ADAPTER : null;
    $rule = ['mode'=>defined('PLOGGER_OYPO_MINIMUM_MODE') ? PLOGGER_OYPO_MINIMUM_MODE : null,
        'long_edge'=>defined('PLOGGER_OYPO_MINIMUM_LONG_EDGE') ? PLOGGER_OYPO_MINIMUM_LONG_EDGE : null,
        'width'=>defined('PLOGGER_OYPO_MINIMUM_WIDTH') ? PLOGGER_OYPO_MINIMUM_WIDTH : null,
        'height'=>defined('PLOGGER_OYPO_MINIMUM_HEIGHT') ? PLOGGER_OYPO_MINIMUM_HEIGHT : null];
    return plogger_oypo_action($picture, $album, $rule,
        is_callable($adapter) ? $adapter : null);
};
