<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__).'/plog-includes/plog-upgrade.php';
require_once dirname(__DIR__).'/plog-includes/plog-dimensions.php';
$options = getopt('', ['config:', 'after:', 'limit:', 'dry-run', 'apply', 'confirm', 'help']);
if (isset($options['help'])) {
    echo "Usage: php plog-admin/dimensions-cli.php [--config=PATH] [--after=ID] [--limit=1..100] [--dry-run|--apply --confirm]\n";
    exit;
}
ini_set('display_errors', '0');
try {
    if (isset($options['apply']) && (isset($options['dry-run']) || !isset($options['confirm']))) throw new RuntimeException('Apply requires --confirm and cannot be combined with --dry-run.');
    $path = $options['config'] ?? dirname(__DIR__).'/plog-config.php';
    if (!is_string($path)) throw new RuntimeException('Invalid configuration path.');
    $after = filter_var($options['after'] ?? 0, FILTER_VALIDATE_INT, ['options'=>['min_range'=>0]]);
    $limit = filter_var($options['limit'] ?? 50, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1, 'max_range'=>100]]);
    if ($after === false || $limit === false) throw new RuntimeException('Invalid cursor or batch size.');
    $settings = plogger_upgrade_config($path);
    $db = plogger_upgrade_connection($settings);
    $prefix = $settings['PLOGGER_TABLE_PREFIX'] ?? 'plogger_';
    $upgrade = new PloggerUpgrade($db, $prefix, dirname($path));
    if ($upgrade->version() !== PloggerUpgrade::VERSION) throw new RuntimeException('Complete the reviewed schema upgrade first.');
    $report = plogger_backfill_dimensions($db, $prefix, dirname($path).'/plog-content/images', $after, $limit, !isset($options['apply']));
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $error) {
    if ($error instanceof PDOException) { plogger_log_exception($error, 'Dimension backfill'); $message = 'Database operation failed; inspect the private error log.'; }
    else $message = $error->getMessage();
    fwrite(STDERR, $message.PHP_EOL);
    exit(1);
}
