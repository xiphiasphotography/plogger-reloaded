<?php
global $config, $thumbnail_config;
// Load configuration variables from database, plog-globals, & plog-includes/plog-functions
require_once(dirname(dirname(__FILE__)).'/plog-load-config.php');
plogger_csrf_admin_guard();
if (!plogger_auth_session_valid()) unset($_SESSION['plogger_logged_in'], $_SESSION['plogger_remote_logged_in']);

// If we're already logged in, redirect to the upload page
if (isset($_SESSION['plogger_logged_in']) && $_SESSION['plogger_logged_in'] === true) {
	header('Location: plog-upload.php');
	exit;
}

// Handle redirects for the form
$form_action = plogger_admin_login_destination($_GET['r'] ?? null);

$output = '';

/* Action messages */
// Logout message
if (isset($_REQUEST['loggedout'])) {
	$output .= "\t" . '<div id="login-action">'.plog_tr('You have been successfully logged out').'</div>' . "\n\n";
}

// Reset password messages
if (isset($_REQUEST['checkemail'])) {
	switch($_REQUEST['checkemail']) {

		case 1: // Reset password request completed, check email for confirmation link
		$output .= "\t" . '<div id="login-action">'.plog_tr('Please check your email for the confirmation link').'.</div>' . "\n\n";
		break;

		case 2: // Verification successful, password has been reset, check email
		$output .= "\t" . '<div id="login-action">'.plog_tr('Verification successful. Please check your email for the new password').'.</div>' . "\n\n";
		break;

		case 3: // Password has been reset, login and reset password to permanent one
		$output .= "\t" . '<div id="login-action">'.plog_tr('Please change your password after logging in').'.</div>' . "\n\n";
		break;

	}
}

/* Error messages */
// Login error message
if (isset($_REQUEST['loginerror'])) {
	// Invalid login info, either username or password didn't match
	$output .= "\t" . '<div id="login-error">'.plog_tr('Invalid username or password').'</div>' . "\n\n";
}

// Reset password errors
if (isset($_REQUEST['reseterror'])) {
	switch($_REQUEST['reseterror']) {

		case 1: // Password reset - invalid username or email address
		$output .= "\t" . '<div id="login-error">'.plog_tr('Invalid admin username or email address').'</div>' . "\n\n";
		break;

		case 2: // Password reset - Verification link is invalid or expired
		$output .= "\t" . '<div id="login-error">'.plog_tr('Sorry, that verification key does not appear to be valid').'.</div>' . "\n\n";
		break;

		case 3: // Password reset - email could not be sent
		$output .= "\t" . '<div id="login-error">'.plog_tr('The email could not be sent. Possible reason: your host may have disabled the mail() function.').'</div>' . "\n\n";
		break;
	}
}

// Create the form information
if(isset($_REQUEST['resetpassword'])) {
	$output .= "\t" . '<div id="login-action">'.plog_tr('Please enter your username or email address and you will be notified via email the password reset process.').'</div>' . "\n\n";
	$form_content = '<p><label for="admin_email">'.plog_tr('Username or Email').'</label></p>
		<p><input type="text" name="admin_email" id="admin_email" autocomplete="username" tabindex="10" /></p>
		<p><input type="hidden" name="action" value="password_reset" /><input class="submit" type="submit" value="'.plog_tr('Reset Password').'" tabindex="30" /></p>' . "\n";
	$link = '<a title="'.plog_tr('Log in').'" href="'.$config['gallery_url'].'plog-admin/index.php">'.plog_tr('Log in').'</a>' . "\n";
} else {
	$form_content = '<p><label for="plog_username">'.plog_tr('Username').':</label> <input type="text" name="plog_username" id="plog_username" autocomplete="username" tabindex="10" /></p>
		<p><label for="plog_password">'.plog_tr('Password').':</label> <input type="password" name="plog_password" id="plog_password" autocomplete="current-password" tabindex="20" /></p>
		<p><input type="hidden" name="action" value="log_in" /><input class="submit" type="submit" value="'.plog_tr('Log In').'" tabindex="50" /></p>' . "\n";
	$link = '<a title="'.plog_tr('Reset password').'" href="'.$config['gallery_url'].'plog-admin/index.php?resetpassword">'.plog_tr('Reset password').'</a>' . "\n";
}

close_db();
?>
<!DOCTYPE html>
<html lang="<?php echo plogger_escape($GLOBALS['language'] ?? 'en'); ?>">
<head>
	<title>Plogger <?php echo plog_tr('Gallery Admin | Login') ?></title>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<?php echo plogger_ui_styles('admin'); ?>
</head>

<body id="login-page" class="plog-ui plog-admin">

	<div id="login-logo">
		<h1>Plogger Reloaded</h1>
	</div><!-- /login-logo -->

<?php echo $output ?>
	<div id="login-box">
		<form action="<?php echo htmlspecialchars($form_action, ENT_QUOTES, 'UTF-8') ?>" method="post">
		<?php echo plogger_csrf_field(); ?>
		<?php echo $form_content ?>
		</form>
	</div><!-- /login-box -->

	<div id="login-nav">
		<a title="<?php echo plog_tr('View gallery') ?>" href="<?php echo $config['gallery_url'] ?>"><?php echo plog_tr('View gallery') ?></a>&nbsp;&nbsp;|&nbsp;&nbsp;<?php echo $link ?>
	</div><!-- /login-nav -->

</body>
</html>
