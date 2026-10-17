<?php
// This will create a picture thumbnail on disk
// First it will be used for import only.

// Load configuration variables from database, plog-globals, & plog-includes/plog-functions
require_once(dirname(dirname(__FILE__)).'/plog-load-config.php');
require(PLOGGER_DIR.'plog-admin/plog-admin.php');
plogger_csrf_require_post();
require_once(PLOGGER_DIR.'plog-admin/plog-admin-functions.php');

// Set up the default error message
$found = plog_tr('No such image');

if (empty($_POST['img'])) {
	exit($found);
}

require_once PLOGGER_DIR.'plog-includes/plog-import.php';
$file = plogger_import_source(plogger_input($_POST, 'img'), PLOGGER_DIR.'plog-content/uploads');
if ($file === null && empty($_SESSION['plogger_import_files'])) {
	$batch = plogger_import_scan(PLOGGER_DIR.'plog-content/uploads', [], plogger_import_batch_size());
	foreach ($batch['files'] as $path) plogger_import_preview($path, PLOGGER_DIR.'plog-content/uploads');
	$file = plogger_import_source(plogger_input($_POST, 'img'), PLOGGER_DIR.'plog-content/uploads');
}
if ($file !== null) {
	$thumbpath = generate_thumb($file, 'import-'.substr(md5($file), 0, 2), THUMB_SMALL);
	$found = '<img src="'.plogger_escape($thumbpath).'" alt="" />';
}

close_db();
close_ftp();
echo $found;

?>
