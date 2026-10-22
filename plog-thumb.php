<?php

/* This is mostly an interface for the XML service to get thumbnail URLs
 without having to generate the thumb at the time that the URL is needed. */

require_once(dirname(__FILE__).'/plog-load-config.php');

/**
* plog-thumb.php interface for XML service
*
* Purpose: Provide a link to a thumbnail for a picture even when the thumbnail
* may not have been generated yet.
*
* Request parameter: integer id (required).
* Request parameter: integer type (default 1, any of 1,2,3,4).
*/

if (!isset($_REQUEST['id']) || (intval($_REQUEST['id']) == 0)) exit;
if (!isset($_REQUEST['type'])) $_REQUEST['type'] = 1;

$type = intval($_REQUEST['type']);
if (!in_array($type, array(THUMB_SMALL, THUMB_LARGE, THUMB_RSS, THUMB_NAV), true)) {
	http_response_code(400);
	exit;
}
$query = 'SELECT "path", "id" FROM '.plogger_quote_identifier(PLOGGER_TABLE_PREFIX.'pictures').' WHERE "id" = ?';
$result = run_query($query, true, 'A database error occurred. Enable PLOGGER_DEBUG for details.', array(intval($_REQUEST['id'])));
$thumb = $result->fetch();
if (!$thumb) {
	http_response_code(404);
	exit;
}

$thumb['type'] = $type;

$path = generate_thumb($thumb['path'], $thumb['id'], $thumb['type']);
if ($path === false) {
	http_response_code(404);
	exit;
}

// Make sure the generated url and the browser url have the same domain syntax
$browser_www = strpos($_SERVER['HTTP_HOST'], 'www');
$thumb_www = strpos($path, 'www');
if ($browser_www !== $thumb_www) {
	if ($browser_www === false) {
		$path = str_replace('www', '', $path);
	} else {
		$path = str_replace('//', '//www.', $path);
	}
}

close_db();

header('Location: '.html_entity_decode($path, ENT_QUOTES, 'UTF-8'));
exit;

?>
