<?php
/*
This file will load all the configuration elements from the database
and place them into a global associative array called $config

SVN keyword tags:
$LastChangedDate: 2009-10-26 10:36:57 -0700 (Mon, 26 Oct 2009) $ (Date)
$LastChangedRevision: 602 $ (Revision)
$LastChangedBy: sidtheduck $ (Author)
$HeadURL: http://svn.plogger.org/trunk/plog-load-config.php $ (URL)
$Id: plog-load-config.php 602 2009-10-26 17:36:57Z sidtheduck $

Note that SVN keywords are only propset enabled for plog-load-config.php!
Variables enabled: LastChangedDate LastChangedRevision LastChangedBy HeadURL Id
*/

$config = array();
$thumbnail_config = array();

if (is_file(dirname(__FILE__).'/plog-config.php')) {
	require_once(dirname(__FILE__).'/plog-config.php');
} else if (is_file(dirname(__FILE__).'/plog-config-sample.php')) {
	require_once(dirname(__FILE__).'/plog-config-sample.php');
} else {
	die(plog_tr('Could not find a config file!'));
}

require_once(dirname(__FILE__).'/plog-globals.php');
require_once(PLOGGER_DIR.'plog-includes/plog-functions.php');

if (defined('PLOGGER_DEBUG') && PLOGGER_DEBUG == '1') {
	$GLOBALS['query_count'] = 0;
	$GLOBALS['queries'] = array();
	$plog_start_time = plog_timer();
}

// Check if Plogger is installed first
if (!is_plogger_installed()) {
	if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'plog-admin')) {
		$install_url = '_install.php';
		$upgrade_url = '_upgrade.php';
		$img = '<img src="images/plogger.gif" alt="Plogger" />';
	} else {
		$install_url = 'plog-admin/_install.php';
		$upgrade_url = 'plog-admin/_upgrade.php';
		$img = '<img src="plog-admin/images/plogger.gif" alt="Plogger" />';
	}
	die($img."\n" . '<p style="font-family: tahoma, verdana, arial, sans-serif; font-size: 16px; letter-spacing: .25px; margin: 30px;">'.plog_tr('Please run <a href="'.$install_url.'">_install.php</a> to set up Plogger. If you are upgrading from a previous version, please run <a href="'.$upgrade_url.'">_upgrade.php</a>').'.</p>');
}

connect_db();

$query = 'SELECT * FROM '.plogger_quote_identifier(PLOGGER_TABLE_PREFIX.'config');
$result = run_query($query, true, 'A database error occurred. Enable PLOGGER_DEBUG for details.', array());

$config = $result->fetch();
if (!$config) {
	die(plog_tr('No config information in the database.'));
}
if ((int) ($config['schema_version'] ?? 0) !== 4) {
    http_response_code(503);
    exit(plog_tr('This installation requires the reviewed upgrade workflow. Back up and verify a staging copy, then follow UPGRADING.md.'));
}
if (($config['gallery_url'] ?? '') !== '' && plogger_public_link($config['gallery_url']) === '') {
    http_response_code(500);
    exit(plog_tr('Invalid gallery URL configuration. Contact the gallery administrator.'));
}
$normalized_cdn = plogger_normalize_cdn_url($config['cdn_url'] ?? '');
if ($normalized_cdn === null) {
    // Keep the administrator's repair form reachable; never use an unsafe CDN value publicly.
    if (!plogger_asset_origin_request()) {
        http_response_code(500);
        exit(plog_tr('Invalid CDN URL configuration. Contact the gallery administrator.'));
    }
} else $config['cdn_url'] = $normalized_cdn;

$config['gallery_name'] = SmartStripSlashes($config['gallery_name']);

$config['basedir'] = PLOGGER_DIR;
plogger_load_extensions();
if (!is_string($config['theme_dir']) || !plogger_safe_relative_path($config['theme_dir'])
    || str_contains($config['theme_dir'], '/')
    || (is_dir(PLOGGER_DIR.'plog-content/themes/'.$config['theme_dir'])
        && !plogger_path_within(PLOGGER_DIR.'plog-content/themes/'.$config['theme_dir'], PLOGGER_DIR.'plog-content/themes'))) {
    http_response_code(500);
    exit(plog_tr('Invalid theme configuration. Contact the gallery administrator.'));
}

// Try to figure out whether we are embedded (for example, running from within WordPress)
if (!defined('PLOGGER_EMBEDDED') || PLOGGER_EMBEDDED == '') {
	// $_SERVER['PATH_TRANSLATED'] is not set in all server environments, so backup with $_SERVER['SCRIPT_FILENAME']
	// $_SERVER['SCRIPT_FILENAME'] should produce the same result as __FILE__ if inclusion script in same location as Plogger
	// In some environments (virtual hosts) $_SERVER['SCRIPT_FILENAME'] is not the correct absolute path, but realpath() seems to clear it up
	$compare_path = $_SERVER['PATH_TRANSLATED'] ?? (realpath($_SERVER['SCRIPT_FILENAME'] ?? __FILE__) ?: __FILE__);
	if (dirname(__FILE__) != dirname($compare_path) && strpos($compare_path, 'plog-admin') === false) {
		$config['embedded'] = 1;
		// Disable our own cruft-free urls, because the URL has already been processed by WordPress
		$config['use_mod_rewrite'] = 0;
		trace('Plogger is embedded');
		trace('dirname: '.dirname(__FILE__));
		trace('$_SERVER[\'SCRIPT_FILENAME\']' .': '.($_SERVER['SCRIPT_FILENAME'] ?? ''));
		trace('realpath($_SERVER[\'SCRIPT_FILENAME\'])' .': '.realpath($_SERVER['SCRIPT_FILENAME'] ?? __FILE__));
	} else {
		$config['embedded'] = 0;
	}
} else {
	// Set PLOGGER_EMBEDDED to 1 in config file to overrule automatic test
	if (PLOGGER_EMBEDDED == '1') {
		$config['embedded'] = 1;
		$config['use_mod_rewrite'] = 0;
		trace('Plogger is embedded');
		// Set PLOGGER_EMBEDDED to 0 in case Plogger inclusion in different folder location, but does not already use mod_rewrite paths
	} else {
		$config['embedded'] = 0;
	}
}

// if mod_rewrite is on and we're not embedded, remove the file basename
if (($_SERVER['HTTPS'] ?? '') == "on") {
	$srvproto = 'https://';
} else {
	$srvproto = 'http://';
}
if ($config['use_mod_rewrite'] == 1 && $config['embedded'] == 0) {
	$config['baseurl'] = $srvproto.$_SERVER['HTTP_HOST'].dirname($_SERVER['PHP_SELF']).'/';
// otherwise just use our cleaned up version of $_SERVER['PHP_SELF'] from plog-globals.php
} else {
	$config['baseurl'] = $srvproto.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'];
}

// Remove plog-admin/ from the end, if present .. is there a better way to determine the full url?
// Do we need this anymore?
$is_admin = strpos($config['baseurl'], 'plog-admin/');
if ($is_admin !== false) {
	$config['baseurl'] = substr($config['baseurl'], 0, $is_admin);
}

$config['theme_url'] = plogger_asset_url('plog-content/themes/'.basename($config['theme_dir']).'/');

$config['charset'] = 'utf-8';

$config['version'] = 'VERSION: 1.0-PDO';

// Charset set with HTTP headers has higher priority than that set in HTML head section
// Since some servers set their own charset for PHP files, this should take care of it
// and hopefully doesn't break anything

if (!headers_sent()) {
	header('Content-Type: text/html; charset='.$config['charset']);
}

$query = 'SELECT * FROM '.plogger_quote_identifier(PLOGGER_TABLE_PREFIX.'thumbnail_config');
$result = run_query($query, true, 'A database error occurred. Enable PLOGGER_DEBUG for details.', array());

$rows = $result->fetchAll();
if (!$rows) {
	die(plog_tr('No thumbnail config information in the database.'));
}

$prefix_arr = array(1 => 'small', 2 => 'large', 3 => 'rss', 4 => 'thumbnav');

foreach ($rows as $row) {
	if (!isset($prefix_arr[$row['id']])) continue;
	$thumbnail_config[$row['id']] = array(
	'type' => $prefix_arr[$row['id']],
	'size' => $row['max_size'],
	'timestamp' => $row['update_timestamp'],
	'disabled' => $row['disabled'],
	'resize_option' => $row['resize_option']);
}

// Add the new theme preview thumbnail array
$thumbnail_config[5] = array(
	'type' => 'theme',
	'size' => 150,
	'timestamp' => 0,
	'disabled' => 0,
	'resize_option' => 3);

// Debugging functions
/**
 * @return void
 */
function display_uservariables() {
	global $config;
	if (!plogger_debug_allowed()) return;
	foreach (array('theme_dir', 'thumb_num', 'default_sortby', 'default_sortdir', 'show_exif', 'allow_dl') as $key) {
		echo plogger_escape($key).' = '.plogger_escape($config[$key] ?? '').'<br />';
	}
}

/**
 * @param mixed $output Debug output is normalized by plogger_escape.
 * @param bool $echo
 * @return string|null
 */
function trace($output, $echo = true) {
	if (!plogger_debug_allowed()) return null;
	$output = plogger_escape($output);
	if (defined('PLOGGER_DEBUG')) {
		if (PLOGGER_DEBUG == '1') {
			//echo('*'.vname($output).':'.$output.'*');
			if ($echo === false) {
				return '*'.$output.'*<br />';
			} else {
				echo '*'.$output.'*<br />';
			}
		}
	}
	return null;
}

/**
 * @param mixed $var Any referenced variable; its original value is restored.
 * @param array<array-key,mixed>|false $scope
 * @param string $prefix
 * @param string $suffix
 * @return int|string|false
 */
function vname(&$var, $scope = false, $prefix = 'unique', $suffix = 'value') {
	if ($scope) $vals = $scope;
	else $vals = $GLOBALS;
	$old = $var;
	$var = $new = $prefix.rand().$suffix;
	$vname = FALSE;
	foreach($vals as $key => $val) {
		if($val === $new) $vname = $key;
	}
	$var = $old;
	return $vname;
}

if (!isset($_SESSION['plogger_sortby'])) {
	$_SESSION['plogger_sortby'] = $config['default_sortby'];
}

if (!isset($_SESSION['plogger_sortdir'])) {
	$_SESSION['plogger_sortdir'] = $config['default_sortdir'];
}

if (!isset($_SESSION['plogger_details'])) {
	$_SESSION['plogger_details'] = 0;
}

?>
