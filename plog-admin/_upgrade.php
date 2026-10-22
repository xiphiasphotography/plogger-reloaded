<?php
// Independent bootstrap: an old schema must not enter the gallery loader first.
require_once dirname(__DIR__).'/plog-includes/plog-upgrade.php';
require_once dirname(__DIR__).'/plog-includes/plog-csrf.php';
header('Cache-Control: no-store'); header('Referrer-Policy: same-origin');
header('X-Content-Type-Options: nosniff'); header('X-Frame-Options: DENY');
ini_set('display_errors','0');
$path=dirname(__DIR__).'/plog-config.php';
try { $settings=plogger_upgrade_config($path); }
catch(Throwable $error) { http_response_code(403); exit('Web upgrade is disabled. Use the documented CLI/staging workflow.'); }
if(($settings['PLOGGER_ENABLE_UPGRADE_WEB'] ?? false)!==true) {
    http_response_code(403); exit('Web upgrade is disabled. Enable it explicitly on restricted staging only.');
}
if(isset($settings['PLOGGER_SESSION_SAVE_PATH']) && !defined('PLOGGER_SESSION_SAVE_PATH')) define('PLOGGER_SESSION_SAVE_PATH',$settings['PLOGGER_SESSION_SAVE_PATH']);
require_once dirname(__DIR__).'/plog-includes/lib/gettext/streams.php';
require_once dirname(__DIR__).'/plog-includes/lib/gettext/gettext.php';
$language=strtolower(substr((string)($settings['PLOGGER_LOCALE'] ?? 'en_US'),0,2));
$catalog=dirname(__DIR__).'/plog-content/translations/'.$language.'.mo';
$plog_l10n=new gettext_reader(preg_match('/^[a-z]{2}$/D',$language) && $language!=='en' && is_readable($catalog)?new FileReader($catalog):false);
if(!function_exists('plog_tr')) { function plog_tr(string $text): string { global $plog_l10n; return $plog_l10n->translate($text); } }
plogger_start_session();
if(($_SERVER['REQUEST_METHOD'] ?? 'GET')!=='GET') plogger_csrf_require_post();
$message=''; $report=null;
try {
    $db=plogger_upgrade_connection($settings);
    $upgrade=new PloggerUpgrade($db,$settings['PLOGGER_TABLE_PREFIX'] ?? 'plogger_',dirname(__DIR__));
    $config=$upgrade->config();
    $fingerprint=hash('sha256',$config['admin_username'].'|'.$config['admin_password']);
    if(($_POST['action'] ?? null)==='login') {
        if(!plogger_login_attempt(false,'upgrade') || !is_string($_POST['username'] ?? null)
            || !hash_equals($config['admin_username'],$_POST['username'])
            || !plogger_password_verify($_POST['password'] ?? null,$config['admin_password'])) throw new RuntimeException('Invalid login or too many attempts.');
        session_regenerate_id(true);
        $_SESSION['plogger_upgrade_auth']=['fingerprint'=>$fingerprint,'expires'=>time()+900];
        unset($_SESSION['plogger_upgrade_plan']);
        plogger_login_attempt(true,'upgrade');
    }
    $auth=$_SESSION['plogger_upgrade_auth'] ?? null;
    $fingerprintValid=is_array($auth) && ($auth['expires'] ?? 0)>=time()
        && is_string($auth['fingerprint'] ?? null) && hash_equals($fingerprint,$auth['fingerprint']);
    if($fingerprintValid) {
        $report=$upgrade->inspect();
        if(($_POST['action'] ?? null)==='apply') {
            $plan=$_SESSION['plogger_upgrade_plan'] ?? null;
            if(!is_string($plan)) throw new RuntimeException('Run the dry-run preview first.');
            $report=$upgrade->apply(['database'=>$_POST['backup_database'] ?? '', 'files'=>$_POST['backup_files'] ?? '',
                'restore_tested'=>($_POST['restore_tested'] ?? '')==='yes'],($_POST['confirm'] ?? '')==='yes',$plan,1);
            if(($_POST['propose_config'] ?? '')==='yes') plogger_upgrade_write_proposal($path,$settings);
            $message='Migration step completed. Review progress before continuing.';
        }
        $_SESSION['plogger_upgrade_plan']=PloggerUpgrade::planHash($report);
        if($report['schema_version']===PloggerUpgrade::VERSION) {
            $message='Upgrade complete. Disable PLOGGER_ENABLE_UPGRADE_WEB and follow the staging verification checklist.';
            unset($_SESSION['plogger_upgrade_auth'],$_SESSION['plogger_upgrade_plan']);
        }
    } elseif(isset($_POST['action']) && $_POST['action']!=='login') { http_response_code(403); $message='Log in before inspecting or applying an upgrade.'; }
} catch(Throwable $error) {
    http_response_code(400);
    $message=$error instanceof PDOException?'Database operation failed. Check database permissions and staging logs.':$error->getMessage();
    if($error instanceof PDOException) plogger_log_exception($error,'Schema upgrade');
}
?><!doctype html><html lang="en"><meta charset="utf-8"><title><?php echo plogger_escape(plog_tr('Plogger upgrade')); ?></title>
<body><h1><?php echo plogger_escape(plog_tr('Plogger upgrade')); ?></h1>
<p><?php echo plogger_escape(plog_tr('Use a restricted staging copy on PHP 8.1–8.4. Dry-run is the default. Originals and configuration are never replaced.')); ?></p>
<p><?php echo plogger_escape(plog_tr($message)); ?></p>
<?php if($report===null): ?>
<form method="post"><?php echo plogger_csrf_field(); ?><input type="hidden" name="action" value="login">
<label><?php echo plogger_escape(plog_tr('Administrator')); ?><input name="username" autocomplete="username"></label>
<label><?php echo plogger_escape(plog_tr('Password')); ?><input name="password" type="password" autocomplete="current-password"></label>
<button><?php echo plogger_escape(plog_tr('Log in and preview')); ?></button></form>
<?php else: ?><pre><?php echo plogger_escape(json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)); ?></pre>
<?php if($report['schema_version']<PloggerUpgrade::VERSION): ?>
<form method="post"><?php echo plogger_csrf_field(); ?><input type="hidden" name="action" value="apply">
<label><?php echo plogger_escape(plog_tr('Verified external database backup reference')); ?><input name="backup_database" required></label>
<label><?php echo plogger_escape(plog_tr('Verified originals/configuration backup reference')); ?><input name="backup_files" required></label>
<label><input name="restore_tested" type="checkbox" value="yes" required><?php echo plogger_escape(plog_tr('I tested restoration of both backups.')); ?></label>
<label><input name="confirm" type="checkbox" value="yes" required><?php echo plogger_escape(plog_tr('I reviewed the dry-run and authorize the next migration step.')); ?></label>
<label><input name="propose_config" type="checkbox" value="yes"><?php echo plogger_escape(plog_tr('Write a protected configuration proposal without replacing the original.')); ?></label>
<button><?php echo plogger_escape(plog_tr('Apply one migration step')); ?></button></form>
<?php endif; endif; ?></body></html>
