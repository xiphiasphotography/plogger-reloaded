<?php
/** Opt-in UI assets; admin assets always remain on the application origin. */
function plogger_ui_styles(string $area): string {
    global $config;
    if (!in_array($area, ['gallery', 'admin'], true)) throw new InvalidArgumentException('Unknown Plogger UI area.');
    $html = '';
    foreach (['tokens', 'base', 'layout', 'components', $area] as $name) {
        $path = 'plog-content/ui/css/'.$name.'.css';
        $url = $area === 'admin' ? rtrim($config['gallery_url'], '/').'/'.$path : plogger_asset_url($path);
        $html .= '<link rel="stylesheet" href="'.plogger_escape($url).'" />'."\n";
    }
    return $html;
}
