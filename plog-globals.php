<?php
@ini_set('include_path', ini_get('include_path'));
@ini_set('arg_separator.output', '&amp;');

// Respect host/FPM resource budgets; bounded jobs must not raise them silently.

// clean up $_SERVER['PHP_SELF'] so it's safe to use against potential XSS attacks
$script_filename = $_SERVER['SCRIPT_FILENAME'] ?? __FILE__;
$phpself = basename(realpath($script_filename) ?: $script_filename);
$php_self = $_SERVER['PHP_SELF'] ?? '/'.$phpself;
$script_offset = strpos($php_self, $phpself);
$_SERVER['PHP_SELF'] = ($script_offset === false ? '' : substr($php_self, 0, $script_offset)) . $phpself;

error_reporting(E_ALL);
require_once __DIR__.'/plog-includes/plog-security.php';
// Embedding applications retain their existing exception handler.
$plogger_previous_handler = set_exception_handler('plogger_exception_handler');
if ($plogger_previous_handler !== null) set_exception_handler($plogger_previous_handler);
unset($plogger_previous_handler);
$plogger_previous_error_handler = set_error_handler('plogger_error_handler');
if ($plogger_previous_error_handler !== null) set_error_handler($plogger_previous_error_handler);
unset($plogger_previous_error_handler);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('zend.exception_ignore_args', '1');

define('PLOGGER_DIR', dirname(__FILE__).'/');

// If we're using an old plog-config file, define the new constants
if (!defined('PLOGGER_CHMOD_DIR')) {
	define('PLOGGER_CHMOD_DIR', 0755);
}
if (!defined('PLOGGER_CHMOD_FILE')) {
	define('PLOGGER_CHMOD_FILE', 0644);
}

if (!defined('PLOGGER_TABLE_PREFIX')) {
	define('PLOGGER_TABLE_PREFIX', 'plogger_');
}

$config_table = PLOGGER_TABLE_PREFIX.'config';
// Original Plogger configuration predates PDO driver/port constants.
if (!defined('PLOGGER_DB_TYPE')) define('PLOGGER_DB_TYPE', 'mysql');
if (!defined('PLOGGER_DB_PORT')) define('PLOGGER_DB_PORT', '');

define('THUMB_SMALL', 1);
define('THUMB_LARGE', 2);
define('THUMB_RSS', 3);
define('THUMB_NAV', 4);
define('THUMB_THEME', 5);

require_once(PLOGGER_DIR.'plog-includes/plog-csrf.php');
plogger_start_session();

if (!class_exists('streamreader')) {
	require_once(PLOGGER_DIR.'plog-includes/lib/gettext/streams.php');
	require_once(PLOGGER_DIR.'plog-includes/lib/gettext/gettext.php');
}

if (defined('PLOGGER_LOCALE') && PLOGGER_LOCALE !== '' && strlen(PLOGGER_LOCALE) >= 2) {
	$locale = PLOGGER_LOCALE;
} else {
	$locale = 'en_US';
}

$language = strtolower(substr($locale, 0, 2));

$mofile = PLOGGER_DIR.'plog-content/translations/'.$language.'.mo';

// If the mo file does not exist or is not readable, or if the locale is en_US, do not load the mo
if (is_readable($mofile) && ($locale != 'en_US')) {
	$input = new FileReader($mofile);
} else {
	$input = false;
}

$plog_l10n = new gettext_reader($input);

// Return a translated string
function plog_tr($text) {
	global $plog_l10n;

	if (isset($plog_l10n)) {
		return $plog_l10n->translate($text);
	} else {
		return $text;
	}
}

?>
