<?php
global $PLOGGER_DBH, $config;
require_once dirname(__DIR__).'/plog-load-config.php';
require __DIR__.'/plog-admin.php';
require_once dirname(__DIR__).'/plog-includes/plog-dimensions.php';
$output = '<h2>'.plog_tr('Original photograph dimensions').'</h2><p>'
    .plog_tr('Preview a batch first. Applying updates only missing database dimensions; original files remain unchanged. Keep a verified database backup.').'</p>';
$cursor = 0;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $cursor = filter_var($_POST['after'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>0]]);
    if ($cursor === false) { http_response_code(400); exit(plog_tr('Invalid batch cursor.')); }
    $apply = ($_POST['operation'] ?? '') === 'apply';
    if ($apply && ($_POST['confirm'] ?? '') !== 'yes') { http_response_code(400); exit(plog_tr('Confirm the database update.')); }
    $report = plogger_backfill_dimensions($PLOGGER_DBH, PLOGGER_TABLE_PREFIX, $config['basedir'].'plog-content/images', $cursor, 50, !$apply);
    $output .= '<pre>'.plogger_escape(json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)).'</pre>';
    // Preview must not advance the apply cursor past the rows just reviewed.
    if ($apply) $cursor = $report['last_id'];
}
$output .= '<form method="post" action="plog-dimensions.php">'.plogger_csrf_field()
    .'<label>'.plog_tr('Resume after photograph ID').' <input name="after" type="number" min="0" value="'.$cursor.'" /></label>'
    .'<p><label><input type="checkbox" name="confirm" value="yes" /> '.plog_tr('I have a verified database backup and confirm this batch update.').'</label></p>'
    .'<button name="operation" value="preview">'.plog_tr('Preview batch').'</button> '
    .'<button name="operation" value="apply">'.plog_tr('Apply batch').'</button></form>';
display($output, 'manage');
