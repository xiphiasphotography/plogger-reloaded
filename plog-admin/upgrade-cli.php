<?php
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once dirname(__DIR__).'/plog-includes/plog-upgrade.php';
$options=getopt('', ['config:','dry-run','apply','doctor','plan:','backup-database:','backup-files:','restore-tested','confirm','propose-config','help']);
if(isset($options['help'])) {
    echo "Usage: php plog-admin/upgrade-cli.php [--config=PATH] [--dry-run|--doctor|--apply]\n"
        ."Apply requires --plan=REVIEWED_DRY_RUN_HASH --backup-database=EXTERNAL_REFERENCE --backup-files=EXTERNAL_REFERENCE --restore-tested --confirm.\n"
        ."Use --propose-config with confirmed apply to write a reviewed proposal beside the original.\n";
    exit;
}
ini_set('display_errors','0');
try {
    $path=$options['config'] ?? dirname(__DIR__).'/plog-config.php';
    if(!is_string($path)) throw new RuntimeException('Invalid configuration path.');
    $settings=plogger_upgrade_config($path);
    $db=plogger_upgrade_connection($settings);
    $upgrade=new PloggerUpgrade($db,$settings['PLOGGER_TABLE_PREFIX'] ?? 'plogger_',dirname($path));
    $report=$upgrade->inspect();
    if(isset($options['apply'])) {
        if(isset($options['dry-run']) || isset($options['doctor'])) throw new RuntimeException('Choose either read-only inspection or apply.');
        if(!is_string($options['plan'] ?? null) || !preg_match('/^[a-f0-9]{64}$/D',$options['plan'])) throw new RuntimeException('Run --dry-run first and supply its reviewed plan_hash using --plan.');
        $report=$upgrade->apply(['database'=>$options['backup-database'] ?? '', 'files'=>$options['backup-files'] ?? '',
            'restore_tested'=>isset($options['restore-tested'])],isset($options['confirm']),$options['plan'],PloggerUpgrade::VERSION);
        if(isset($options['propose-config'])) plogger_upgrade_write_proposal($path,$settings);
    } elseif(isset($options['propose-config'])) throw new RuntimeException('Configuration proposals require confirmed apply; dry-run writes nothing.');
    $report['mode']=isset($options['apply'])?'apply':'dry-run';
    $report['plan_hash']=PloggerUpgrade::planHash($report);
    $report['config_proposal_needed']=!isset($settings['PLOGGER_DB_TYPE'],$settings['PLOGGER_DB_PORT']);
    echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
} catch(Throwable $error) {
    if($error instanceof PDOException || $error instanceof ParseError) {
        plogger_log_exception($error,'CLI schema upgrade');
        $message='Upgrade failed while reading configuration or accessing the database. Check configuration and database permissions; no credentials are printed.';
    } else $message=$error->getMessage();
    fwrite(STDERR,$message.PHP_EOL);
    exit(1);
}
