<?php
global $config, $thumbnail_config;
/* Code by Mike Johnson -- mike@solanosystems.com October 23rd, 2004.
 This is the main administrative interface code. The initial tab is UPLOAD function. */

// Load configuration variables from database, plog-globals, & plog-includes/plog-functions
require_once(dirname(dirname(__FILE__)).'/plog-load-config.php');
require(PLOGGER_DIR.'plog-admin/plog-admin.php');

function generate_albums_menu($albums, $type = 'multiple', $preselect = null) {
	$output = '';

	if ($type == 'multiple')
	$output .= '<select name="destinations[]" onclick="var k=document.getElementsByName(\'destination_radio\');k[0].checked=true;" >';
	else
	$output .= '<select name="destination" onclick="var k=document.getElementsByName(\'destination_radio\');k[0].checked=true;" >';
	foreach($albums as $album_id => $album_data) {
		if ($preselect == $album_id)
		$selected = ' selected="selected"'; else $selected = '';
		$output .= "\n\t\t\t\t\t" . '<option value="'.$album_id.'"'.$selected.'>'.plogger_escape($album_data['collection_name']).': '.plogger_escape($album_data['album_name']).'</option>';
	}
	$output .= "\n\t\t\t\t</select>";

	return $output;
}

function generate_collections_menu() {
	$collections = get_collections();
	$output = '<select name="collections_menu" id="collections_menu" onclick="var k=document.getElementsByName(\'destination_radio\');k[1].checked=true;" >';
	foreach($collections as $collection) {
		$output .= "\n\t\t\t\t\t" . '<option value="'.$collection['id'].'">'.plogger_escape($collection['name']).'</option>';
	}
	$output .= "\n\t\t\t\t</select>";

	return $output;
}


require_once PLOGGER_DIR.'plog-includes/plog-import.php';
$uploadRoot = $config['basedir'].'plog-content/uploads';
$root = is_link($uploadRoot) ? '' : rtrim(str_replace('\\', '/', realpath($uploadRoot) ?: ''), '/');
$output = '<h1>'.plog_tr('Import Images').'</h1>';
if ($root === '' || !is_readable($root) || !is_writable($root)) {
    $output .= '<p class="errors">'.plog_tr('The uploads directory must exist and be readable and writable. Symlinked upload roots are not allowed.').'</p>';
}
$limit = plogger_import_batch_size();
$posted = isset($_POST['upload']);
$retry = [];
$selected = [];
$nojs = isset($_GET['nojs']);
$base = 'plog-import.php'.($nojs ? '?nojs=1&' : '?');

if ($posted) {
    $selected = $_POST['selected'] ?? [];
    if (!is_array($selected) || !$selected || count($selected) > $limit || count(array_unique($selected)) !== count($selected)) {
        http_response_code(400);
        $output .= '<p class="errors">'.plog_tr('Select images within the current import batch.').'</p>';
        $selected = [];
    }
    // Legacy form clients can submit hashes without first opening a preview. Discovery stays bounded.
    foreach ($selected as $key) {
        if (plogger_import_entry($key, $root) === null) {
            $scan = plogger_import_scan($root, [], $limit);
            foreach ($scan['files'] as $path) plogger_import_preview($path, $root);
            break;
        }
    }
    $sources = [];
    foreach ($selected as $key) {
        $source = plogger_import_source($key, $root);
        if ($source === null) {
            $output .= '<p class="errors">'.plog_tr('An import source changed or its preview expired. Reload and review the batch.').'</p>';
            $sources = []; break;
        }
        $sources[$key] = $source;
    }
    $reviewPaths = [];
    $fieldKeys = array_unique(array_merge(array_keys($_POST['captions'] ?? []), array_keys($_POST['descriptions'] ?? [])));
    foreach (array_slice($fieldKeys, 0, $limit) as $key) {
        $path = is_string($key) ? plogger_import_source($key, $root) : null;
        if ($path === null) continue;
        $reviewPaths[] = $path;
        // Preserve edits submitted by legacy Windows clients when redisplaying canonical keys.
        $canonical = md5($path);
        if ($canonical !== $key && in_array($key, $selected, true)) $selected[] = $canonical;
        foreach (['captions', 'descriptions'] as $field) {
            if (array_key_exists($key, $_POST[$field] ?? [])) $_POST[$field][$canonical] = $_POST[$field][$key];
        }
    }
    $albumId = 0;
    if ($sources) {
        if (($_POST['destination_radio'] ?? '') === 'new') {
            $name = plogger_input($_POST, 'new_album_name');
            if (trim($name) !== '') {
                $result = add_album($name, null, (int) plogger_input($_POST, 'collections_menu'));
                if (empty($result['errors']) || ($result['output'] ?? '') === 'existing') $albumId = (int) ($result['id'] ?? 0);
            }
            if (!$albumId) $output .= '<p class="errors">'.plog_tr('Choose a valid destination or enter a new album name.').'</p>';
        } elseif (($_POST['destination_radio'] ?? '') === 'existing') $albumId = (int) plogger_input($_POST, 'destination');
    }
    $imported = 0; $started = microtime(true); $paused = false;
    foreach ($sources as $key => $path) {
        $retry[] = $path;
        if (!$albumId) continue;
        if (microtime(true) - $started > 20) { $paused = true; continue; }
        $result = add_picture($albumId, $path, basename($path), $_POST['captions'][$key] ?? null,
            $_POST['descriptions'][$key] ?? null, in_array($key, $_POST['allow_comments'] ?? [], true) ? 1 : 0);
        if ($result['picture_id'] !== false) {
            ++$imported; array_pop($retry); unset($_SESSION['plogger_import_files'][md5($path)]);
        } else $output .= '<p class="errors">'.plogger_escape(plog_tr('Image import failed. Check the file and destination permissions.')).'</p>';
    }
    if ($imported) $output .= '<p class="success">'.sprintf(plog_tr('%s images successfully imported.'), $imported).'</p>';
    if ($paused) $output .= '<p class="actions">'.plog_tr('Batch paused. Review and submit the remaining images below to continue.').'</p>';
    foreach ($reviewPaths as $path) if (plogger_import_identity($path, $root) !== null && !in_array($path, $retry, true)) $retry[] = $path;
    $retry = array_slice($retry, 0, $limit);
    // Directory offsets are invalid after files move. Restart discovery of remaining sources.
}

$pending = [];
$cursor = plogger_input($_GET, 'batch');
if (!$posted && $cursor !== '') {
    $state = $_SESSION['plogger_import_batches'][$cursor] ?? null;
    if (is_array($state) && $state['expires'] > time() && $state['root'] === $root) $pending = $state['pending'];
    else $output .= '<p class="actions">'.plog_tr('Import batch expired. Discovery restarted.').'</p>';
}
$directory = plogger_input($_GET, 'directory');
if (!$pending && $directory !== '' && isset($_SESSION['plogger_import_directories'][$directory])) {
    $path = $_SESSION['plogger_import_directories'][$directory];
    if (plogger_path_within($path, $root)) $pending = [['path'=>$path, 'offset'=>0]];
}
$scan = $retry ? ['files'=>$retry, 'pending'=>[], 'directories'=>[]] : plogger_import_scan($root, $pending, $limit);
if (!$posted && !$cursor && $directory !== '' && !isset($_SESSION['plogger_import_directories'][$directory])) {
    if (isset($scan['directories'][$directory])) {
        $path = $scan['directories'][$directory];
        $_SESSION['plogger_import_directories'][$directory] = $path;
        $scan = plogger_import_scan($root, [['path'=>$path, 'offset'=>0]], $limit);
    } elseif ($directory !== md5($root)) {
        $output .= '<p class="actions">'.plog_tr('Folder not yet discovered. Continue discovery or choose a listed folder.').'</p>';
    }
}
foreach ($scan['directories'] as $key => $path) $_SESSION['plogger_import_directories'][$key] = $path;
while (count($_SESSION['plogger_import_directories'] ?? []) > 256) array_shift($_SESSION['plogger_import_directories']);
$next = '';
if ($scan['pending']) {
    $next = bin2hex(random_bytes(16));
    $_SESSION['plogger_import_batches'][$next] = ['root'=>$root, 'pending'=>$scan['pending'], 'expires'=>time()+3600];
    while (count($_SESSION['plogger_import_batches']) > 8) array_shift($_SESSION['plogger_import_batches']);
}
$output .= '<p class="actions">'.sprintf(plog_tr('Review up to %s images per batch. Edits are submitted with this batch only.'), $limit).'</p>';
if ($scan['files']) {
    $output .= '<form id="uploadForm" method="post" action="'.plogger_escape($base).'" enctype="multipart/form-data">'
        .'<table style="width:100%"><tr><th><input name="allbox" type="checkbox" checked="checked" onclick="checkAll(document.getElementById(\'uploadForm\'));" />'.plog_tr('Select').'</th><th>'.plog_tr('Thumb').'</th><th>'.plog_tr('Filename and metadata').'</th><th>'.plog_tr('Title and description').'</th><th>'.plog_tr('Allow Comments').'</th></tr>';
    $keys = [];
    foreach ($scan['files'] as $path) {
        $key = md5($path); $keys[] = $key;
        $preview = plogger_import_preview($path, $root);
        $fields = $preview['fields'];
        foreach (['caption'=>'captions', 'description'=>'descriptions'] as $field => $postName) {
            if ($posted && array_key_exists($key, $_POST[$postName] ?? [])) {
                $fields[$field] = $_POST[$postName][$key]; $fields[$field.'_source'] = 'manual';
            }
        }
        $facts = [$preview['date'], $preview['camera']];
        if ($preview['dimensions']) $facts[] = $preview['dimensions']['width'].' × '.$preview['dimensions']['height'];
        $thumb = $nojs ? generate_thumb($path, 'import-'.substr($key, 0, 2), THUMB_SMALL) : $config['gallery_url'].'plog-admin/images/ajax-loader.gif';
        $checked = !$posted || in_array($key, $_POST['allow_comments'] ?? [], true);
        $imageChecked = !$retry || in_array($key, $selected, true);
        $output .= '<tr><td><input type="checkbox" name="selected[]" value="'.$key.'"'.($imageChecked ? ' checked="checked"' : '').' /></td>'
            .'<td><div class="img-shadow" id="pic_'.$key.'"><img src="'.plogger_escape($thumb).'" alt="'.plogger_escape(plog_tr('thumbnail')).'" /></div></td>'
            .'<td>'.plogger_escape(substr($path, strlen($root)+1)).'<br />'.plogger_escape(implode(' · ', array_filter($facts)))
            .($preview['warning'] ? '<p class="errors">'.plog_tr('Some metadata could not be read. Please review these fields.').'</p>' : '').'</td>'
            .'<td><label for="caption-'.$key.'">'.plog_tr('Title').' ('.plogger_escape($fields['caption_source'] ?: plog_tr('No metadata')).')</label>'
            .'<input id="caption-'.$key.'" type="text" name="captions['.$key.']" value="'.plogger_escape($fields['caption']).'" size="60" />'
            .'<label for="description-'.$key.'">'.plog_tr('Description').' ('.plogger_escape($fields['description_source'] ?: plog_tr('No metadata')).')</label>'
            .'<textarea id="description-'.$key.'" name="descriptions['.$key.']" rows="4" cols="60">'.plogger_escape($fields['description']).'</textarea></td>'
            .'<td><input type="checkbox" name="allow_comments[]" value="'.$key.'"'.($checked && $config['allow_comments'] ? ' checked="checked"' : '').' /></td></tr>';
    }
    $albumId = (int) plogger_input($_POST, 'destination');
    $new = plogger_input($_POST, 'destination_radio') === 'new';
    $newName = plogger_input($_POST, 'new_album_name');
    if (!$posted) {
        // Keep the legacy collection/album folder-name destination suggestion.
        $parts = explode('/', trim(substr(dirname($scan['files'][0]), strlen($root)), '/'));
        $collectionName = plogger_input($_GET, 'collection_name', $parts[0]);
        $suggestion = plogger_input($_GET, 'album_name', $parts[1] ?? $collectionName);
        $statement = run_query('SELECT id FROM '.plogger_db_table('albums').' WHERE name = ?', true, 'Database error.', [$suggestion]);
        $row = $statement->fetch();
        $albumId = (int) ($row['id'] ?? 0);
        $new = !$albumId; $newName = $new ? ucfirst($suggestion) : '';
    }
    $output .= '<tr><td colspan="5"><a href="#" onclick="checkToggle(document.getElementById(\'uploadForm\')); return false;">'.plog_tr('Toggle Checkbox Selection').'</a></td></tr></table><h2>'.plog_tr('Destination:').'</h2>'
        .'<p><label><input type="radio" name="destination_radio" value="existing"'.(!$new ? ' checked="checked"' : '').' />'.plog_tr('Existing Album').'</label>'
        .generate_albums_menu(get_albums(), 'single', $albumId).'</p>'
        .'<p><label><input type="radio" name="destination_radio" value="new"'.($new ? ' checked="checked"' : '').' />'.plog_tr('Create a New Album').'</label>'
        .'<label for="new_album_name">'.plog_tr('New Album Name:').'</label><input id="new_album_name" name="new_album_name" value="'.plogger_escape($newName).'" />'
        .generate_collections_menu().'</p><input type="submit" name="upload" value="'.plogger_escape(plog_tr('Import')).'" /></form>';
    if (!$nojs) $output .= '<script>var importThumbs='.json_encode($keys).';requestImportThumb();</script>';
} else $output .= '<p class="actions">'.plog_tr('No images in this batch. Continue discovery or transfer images to plog-content/uploads using FTP.').'</p>';
if ($next !== '') $output .= '<p><a href="'.plogger_escape($base.'batch='.$next).'">'.plog_tr('Next batch (discard unsubmitted edits)').'</a></p>';
$output .= '<p><a href="'.plogger_escape($base).'">'.plog_tr('Cancel / restart discovery').'</a></p>';
foreach ($scan['directories'] as $key => $path) $output .= '<p><a class="folder" href="'.plogger_escape($base.'directory='.$key).'">'.plogger_escape(substr($path, strlen($root)+1)).'</a></p>';
if (count_albums() > 0) display($output, 'import');
else display('<h1>'.plog_tr('Import').'</h1><p>'.plog_tr('Create a collection and an album before importing images.').'</p>', 'import');
