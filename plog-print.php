<?php

/* This file handles the generation of print image page. */

include(dirname(__FILE__).'/plog-load-config.php');
if (empty($config['allow_print'])) { http_response_code(403); exit; }

$picture = get_picture_by_id(intval($_GET['id'] ?? 0));
if (!$picture) { http_response_code(404); exit; }
$picture['url'] = html_entity_decode(generate_thumb($picture['path'], $picture['id'], THUMB_LARGE) ?: '', ENT_QUOTES, 'UTF-8');
$GLOBALS['plogger_level'] = 'picture';
$GLOBALS['plogger_id'] = intval($_GET['id'] ?? 0);
$GLOBALS['plogger_mode'] = 'print';

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xml:lang="<?php echo $language ?>" lang="<?php echo $language ?>" xmlns="http://www.w3.org/1999/xhtml"> 
<head>
<title><?php echo plogger_escape(html_entity_decode(get_head_title(), ENT_QUOTES, 'UTF-8')) ?></title>
</head>

<body onload="window.print();">

<div><img src="<?php echo plogger_escape($picture['url']); ?>" alt="<?php echo plogger_escape($picture['caption']); ?>" /></div>

<?php close_db(); ?>
</body>
</html>
