<?php
global $config, $thumbnail_config;

/* This file handles the generation of zip file for downloads. */

include(dirname(__FILE__).'/plog-load-config.php');
if (!$config['allow_dl']) {
	http_response_code(403);
	// ignorance is bliss
	exit();
}

require_once __DIR__.'/plog-includes/plog-zip.php';
if (!class_exists('ZipArchive')) {
    http_response_code(503);
    exit(plog_tr('Enable the PHP zip extension (ZipArchive) for ZIP downloads.'));
}
plogger_require_zip();

connect_db();

if (!isset($_REQUEST['checked']) || (!is_array($_REQUEST['checked']))) {
	echo 'No pictures were selected.';
} else {
	create_zip($_REQUEST['checked'], $_REQUEST['dl_type'] ?? 'album');
}

close_db();

/**
 * @param mixed $checked Untrusted selection is validated before archive creation.
 * @param mixed $level Untrusted level is checked against a whitelist.
 * @return void
 */
function create_zip($checked, $level) {
    global $zipfile, $download_bytes;
    $download_bytes = 0;
    if (!is_array($checked) || count($checked) > 1000 || !is_string($level)
        || !in_array($level, array('collections', 'collection', 'album', 'search'), true)) {
        http_response_code(400); return;
    }
    $temporary = tempnam(sys_get_temp_dir(), 'plogger-download-');
    if ($temporary === false) throw new RuntimeException('Cannot create download archive.');
    $zipfile = new ZipArchive();
    try {
        if ($zipfile->open($temporary, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Cannot open download archive.');
        }
        try {
            add_photos($checked, $level, 'Plogger.'.date('Y.m.d').'/');
            if ($zipfile->numFiles === 0) { http_response_code(404); return; }
        } catch (LengthException $error) { http_response_code(413); return; }
        finally { if (!$zipfile->close()) throw new RuntimeException('Cannot finish download archive.'); }
        header('Cache-Control: private, no-store');
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename=plog-package-'.date('Y.m.d').'.zip');
        header('Content-Length: '.filesize($temporary));
        readfile($temporary);
    } finally { if (is_file($temporary)) unlink($temporary); }
}
/**
 * @param array<array-key,mixed> $checked
 * @param string $type
 * @param string $dir
 * @return void
 */
function add_photos($checked, $type, $dir) {
	global $zipfile, $config, $download_bytes;

	if ($type == 'collections') {
		foreach ($checked as $cid) {
			if (!is_scalar($cid)) continue;
			$query = 'SELECT * FROM ' . plogger_db_table('collections') . ' WHERE "id" = ?';
			$result = run_query($query, true, 'Database error.', array((int) $cid));

			while ($row = $result->fetch()) {
				$query = 'SELECT * FROM ' . plogger_db_table('albums') . ' WHERE "parent_id" = ?';
				$newresult = run_query($query, true, 'Database error.', array((int) $row['id']));

				$newchecked = array();

				while ($newrow = $newresult->fetch()) {
					$newchecked[] = $newrow['id'];
				}

				$newdir = $row['name'];

				$i = 1;

				while (is_dir($newdir)) {
					$newdir = $row['name'].'('.$i++.')';
				}

				// $zipfile -> add_dir($dir . $newdir);

				add_photos($newchecked, 'collection', $dir.$newdir.'/');
			}
		}
	} else if ($type == 'collection') {
		foreach ($checked as $aid) {
			if (!is_scalar($aid)) continue;
			$query = 'SELECT * FROM ' . plogger_db_table('albums') . ' WHERE "id" = ?';
			$result = run_query($query, true, 'Database error.', array((int) $aid));

			while ($row = $result->fetch()) {
				$query = 'SELECT * FROM ' . plogger_db_table('pictures') . ' WHERE "parent_album" = ?';
				$newresult = run_query($query, true, 'Database error.', array((int) $row['id']));

				$newchecked = array();

				while ($newrow = $newresult->fetch()) {
					$newchecked[] = $newrow['id'];
				}

				$newdir = $row['name'];

				$i = 1;

				while (is_dir($newdir)) {
					$newdir = $row['name'].'('.$i++.')';
				}

				// $zipfile -> add_dir($dir . $newdir);

				add_photos($newchecked, 'album', $dir.$newdir.'/');
			}
		}
	} elseif ($type == 'album' || $type == 'search') {
		foreach ($checked as $pid) {
			if (!is_scalar($pid)) continue;
			$query = 'SELECT * FROM ' . plogger_db_table('pictures') . ' WHERE "id" = ?';
			$result = run_query($query, true, 'Database error.', array((int) $pid));

			while ($row = $result->fetch()) {
				$original = $config['basedir'].'plog-content/images/'.$row['path'];
				if (!plogger_safe_relative_path($row['path']) || !plogger_path_within($original, $config['basedir'].'plog-content/images')
					|| !is_file($original) || !is_readable($original)) continue;
				$download_bytes += filesize($original);
				if ($download_bytes > 67108864 || $zipfile->numFiles >= 1000) throw new LengthException('Download exceeds the request size limit.');
				if (!$zipfile->addFile($original, $row['path'])) throw new RuntimeException('Cannot add download image.');

			}
		}
	}

	return;
}

?>
