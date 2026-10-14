<?php
header('Content-Type: text/html; charset=utf-8');
global $inHead;

// Load configuration variables from database, plog-globals, & plog-includes/plog-functions
require_once(dirname(dirname(__FILE__)).'/plog-load-config.php');

// Email links only display confirmation; password reset occurs on protected POST.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
	&& ($_GET['action'] ?? '') === 'password_reset' && is_string($_GET['key'] ?? null)) {
	echo '<form method="post" action="plog-admin.php">'.plogger_csrf_field()
		.'<input type="hidden" name="action" value="password_reset" />'
		.'<input type="hidden" name="key" value="'.htmlspecialchars($_GET['key'], ENT_QUOTES, 'UTF-8').'" />'
		.'<label>'.plog_tr('New Password').'<input type="password" name="new_password" autocomplete="new-password" required="required" /></label>'
		.'<label>'.plog_tr('Confirm New Password').'<input type="password" name="confirm_password" autocomplete="new-password" required="required" /></label>'
		.'<button type="submit">'.plog_tr('Confirm password reset').'</button></form>';
	exit;
}
plogger_csrf_admin_guard();
if (isset($_POST['key'])) $_GET['key'] = $_POST['key'];

// Login/logout/reset password functions
if (isset($_REQUEST['action'])) {
	switch ($_REQUEST['action']) {
		case 'log_in':
			// Check the username and password
			if (plogger_authenticate($_POST['plog_username'] ?? null, $_POST['plog_password'] ?? null)) {
				session_regenerate_id(true);
				$_SESSION['plogger_logged_in'] = true;
				$_SESSION['plogger_auth_version'] = hash('sha256', $config['admin_password']);
				$_SESSION['plogger_csrf'] = bin2hex(random_bytes(32));
				// Clear out the activation key on login if set
				if (isset($config['activation_key']) && !empty($config['activation_key'])) {
					$query = "UPDATE " . plogger_db_table('config') . " SET \"activation_key\" = ''";
					$result = run_query($query);
				}
			} else {
				// Handle error for wrong username / password
				$redirect = basename($_SERVER['PHP_SELF']);
				header('Location: index.php?loginerror&r='.$redirect);
				exit;
			}
			break;
		case 'log_out':
			// Handle logging out of the session
			plogger_end_session();
			header('Location: index.php?loggedout');
			exit;
		case 'password_reset':
			// Handle default password reset error: invalid usename or email address
			$reset_output = 'resetpassword&reseterror=1';
			if (plogger_login_attempt(false, 'password-reset') && is_string($_POST['admin_email'] ?? null) && ($_POST['admin_email'] == $config['admin_email'] || $_POST['admin_email'] == $config['admin_username'])) {
				// Change output to handle email sent success message
				$reset_output = 'checkemail=1';
				$from = str_replace('www.', '', $_SERVER['HTTP_HOST']);
				$key = dechex(time()).'.'.bin2hex(random_bytes(24));
				ini_set('sendmail_from', 'noreply@'.$from); // set for windows machines
				if (!@mail( $config['admin_email'],
										'[Plogger] '.plog_tr('Reset Password'),
										plog_tr('Someone has requested Plogger to reset the password for the following website and username.'). "\n\n".
										plog_tr('Website').': '.$config['gallery_url']. "\n".
										plog_tr('Username').': '.$config['admin_username']. "\n\n".
										plog_tr('Follow the link below to reset your password; otherwise, just ignore this email and nothing will happen.'). "\n\n".
										$config['gallery_url'].'plog-admin/plog-admin.php?action=password_reset&key='.$key,
										'From: Plogger <noreply@'.$from.'>'
									)) {
					// Change output to handle error with mail() function
					$reset_output = 'reseterror=3';
				} else {
					// Only update the activation key if an email is sent
					$query = 'UPDATE '.plogger_quote_identifier(PLOGGER_TABLE_PREFIX.'config').' SET "activation_key" = ?';
					$result = run_query($query, true, 'Database error.', array($key));
				}
			}
			if (isset($_GET['key'])) {
				if (plogger_reset_key_valid($_GET['key'], $config['activation_key'])) {
                    $password = plogger_input($_POST, 'new_password');
                    if ($password === '' || $password !== plogger_input($_POST, 'confirm_password')) {
                        http_response_code(400);
                        exit(plog_tr('Enter matching nonempty passwords. Reopen the reset link to try again.'));
                    }
                    $query = 'UPDATE '.plogger_db_table('config').' SET "admin_password" = ?, "activation_key" = ? WHERE "activation_key" = ?';
                    $result = run_query($query, false, 'Database error.', array(plogger_password_hash($password), '', $_GET['key']));
                    $reset_output = $result && $result->rowCount() === 1 ? 'checkemail=3' : 'reseterror=2';
                    if ($result && $result->rowCount() === 1) plogger_end_session();
				} else {
					// Handle bad verification key error
					$reset_output = 'reseterror=2';
				}
			}
			header('Location: index.php?'.$reset_output);
			exit;
	}
}

// Load the admin functions only after the login has been determined
require_once(PLOGGER_DIR.'plog-admin/plog-admin-functions.php');

if (!plogger_auth_session_valid()) {
    unset($_SESSION['plogger_logged_in'], $_SESSION['plogger_remote_logged_in']);
}

if (!isset($_SESSION['plogger_logged_in']) || $_SESSION['plogger_logged_in'] !== true) {
	$redirect = plogger_admin_login_destination(basename($_SERVER['PHP_SELF']));
	header('Location: index.php?r='.rawurlencode($redirect));
	exit;
}

// Display admin tabs
function display($string, $current) {
	global $inHead;
	global $config;

	$tabs = array();
	$tabs['upload']	= array('url' => 'plog-upload.php', 'caption' => plog_tr('<em>U</em>pload'));
	$tabs['import']		= array('url' => 'plog-import.php?nojs=1', 'caption' => plog_tr('<em>I</em>mport'), 'onclick' => "window.location='plog-import.php'; return false;");
	$tabs['manage']	= array('url' => 'plog-manage.php', 'caption' => plog_tr('<em>M</em>anage'));
	$tabs['feedback']	= array('url' => 'plog-feedback.php', 'caption' => plog_tr('<em>F</em>eedback'));
	$tabs['options']	= array('url' => 'plog-options.php', 'caption' => plog_tr('<em>O</em>ptions'));
	$tabs['themes']	= array('url' => 'plog-themes.php', 'caption' => plog_tr('<em>T</em>hemes'));
	$tabs['plugins']	= array('url' => 'plog-plugins.php', 'caption' => plog_tr('<em>P</em>lugins'));
	$tabs['view']		= array('url' => $config['gallery_url'], 'caption' => plog_tr('<em>V</em>iew'), 'onclick' => 'window.open('.plogger_script_json($config['gallery_url']).'); return false;');
	$tabs['support']	= array('url' => 'http://www.plogger.org/forum/', 'caption' => plog_tr('<em>S</em>upport'), 'onclick' => "window.open('http://www.plogger.org/forum/'); return false;");
	$tabs['logout']		= array('url' => 'plog-admin.php', 'caption' => plog_tr('<em>L</em>og out'));
	// Get the accesskey from the localization - it should be surrounded by <em> tags
	foreach($tabs as $key => $data) {
		if (preg_match('|<em>(.*)</em>|', $data['caption'], $matches)) {
			$tabs[$key]['accesskey'] = $matches[1];
		}
	}

$output = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<title>Plogger '.plog_tr('Gallery Admin').'</title>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<link rel="stylesheet" href="'.$config['gallery_url'].'plog-admin/css/admin.css" type="text/css" media="all" />
	<link rel="stylesheet" href="'.$config['gallery_url'].'plog-admin/css/lightbox.css" type="text/css" media="all" />
	<script type="text/javascript" src="'.$config['gallery_url'].'plog-admin/js/prototype.js"></script>
	<script type="text/javascript" src="'.$config['gallery_url'].'plog-admin/js/plogger.js"></script>
	<script type="text/javascript" src="'.$config['gallery_url'].'plog-admin/js/lightbox.js"></script>
	<meta name="plogger-csrf" content="'.htmlspecialchars(plogger_csrf_token(), ENT_QUOTES, 'UTF-8').'" />
	'.$inHead.'
</head>

<body onload="initLightbox();">

<div id="header">

	<div id="logo">
		<img src="'.$config['gallery_url'].'plog-admin/images/plogger.gif" width="393" height="90" alt="Plogger" />
	</div><!-- /logo -->

	<div id="plogger-version">
		<div class="align-right">
			'.$config['version'].'&nbsp;&nbsp;&nbsp;['.plogger_show_server_info_link().']
		</div><!-- /align-right -->
		'.plogger_generate_server_info().'
	</div><!-- /plogger-version -->

	<div style="clear: both; height: 15px;">&nbsp;</div>

	<div id="tab-nav">
		<ul>';
		foreach($tabs as $tab => $data) {
			if ($tab === 'logout') {
				$output .= '<li><form method="post" action="plog-admin.php"><input type="hidden" name="action" value="log_out" /><button type="submit">'.$data['caption'].'</button></form></li>';
				continue;
			}
		$output .= '
			<li';
			if ($current == $tab) $output .= ' id="current"';
			$output .= '><a';
			if (!empty($data['onclick'])) $output .= ' onclick="'.plogger_escape($data['onclick']).'"';
			if (!empty($data['accesskey'])) $output .= ' accesskey="'.plogger_escape($data['accesskey']).'"';
			$output .= ' href="'.plogger_escape($data['url']).'">'.$data['caption'].'</a></li>';
		}
		$output .= '
		</ul>
	</div><!-- /tab-nav -->

</div><!-- /header -->

<div id="content">
'.$string.'
</div><!-- /content -->';

if (defined('PLOGGER_DEBUG') && PLOGGER_DEBUG == '1') {
	$output .= trace('Queries: '.$GLOBALS['query_count'], false);
	foreach ($GLOBALS['queries'] as $q) {
		$output .= trace($q, false);
	}
	$output .= trace(plog_timer('end'), false);
}

$output .= "\n\n" . '</body>
</html>';

echo plogger_csrf_forms($output);

close_db();
close_ftp();
exit;
}

?>
