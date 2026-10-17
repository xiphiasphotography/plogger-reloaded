<?php
/** Bounded FTP discovery and session-local previews. Never writes to source directories. */
require_once __DIR__.'/plog-metadata.php';
require_once __DIR__.'/plog-security.php';

function plogger_import_batch_size(): int {
    return defined('PLOGGER_IMPORT_BATCH_SIZE') ? max(1, min(20, (int) PLOGGER_IMPORT_BATCH_SIZE)) : 10;
}

/** @param list<array{path:string,offset:int}> $pending
 * @return array{files:list<string>,pending:list<array{path:string,offset:int}>,directories:array<string,string>,examined:int}
 */
function plogger_import_scan(string $root, array $pending = [], int $limit = 10): array {
    $files = []; $directories = []; $examined = 0;
    if ($root === '' || is_link($root)) return ['files'=>[], 'pending'=>[], 'directories'=>[], 'examined'=>0];
    $root = rtrim(str_replace('\\', '/', realpath($root) ?: ''), '/');
    if ($root === '' || !is_dir($root)) return compact('files', 'pending', 'directories', 'examined');
    if ($pending === []) $pending = [['path'=>$root, 'offset'=>0]];
    $started = microtime(true); $activePath = ''; $iterator = null;
    while ($pending && count($files) < max(1, min(20, $limit)) && $examined < 1000 && microtime(true) - $started < 2) {
        $index = count($pending) - 1;
        $entry = &$pending[$index];
        if (($entry['path'] !== $root && !plogger_path_within($entry['path'], $root)) || is_link($entry['path']) || !is_readable($entry['path'])) {
            array_pop($pending); unset($entry); continue;
        }
        if ($activePath !== $entry['path'] || $iterator === null) {
            try { $iterator = new FilesystemIterator($entry['path'], FilesystemIterator::SKIP_DOTS); }
            catch (UnexpectedValueException $error) { array_pop($pending); unset($entry); continue; }
            $activePath = $entry['path'];
            // Reopening a resumed directory skips names, but never reparses earlier image metadata.
            for ($skip = 0; $skip < $entry['offset'] && $iterator->valid(); ++$skip) $iterator->next();
        }
        if (!$iterator->valid()) { array_pop($pending); unset($entry); continue; }
        $file = $iterator->current(); $iterator->next(); ++$entry['offset']; ++$examined;
        $path = str_replace('\\', '/', $file->getPathname());
        unset($entry);
        if ($file->isLink() || str_starts_with($file->getFilename(), '.') || !plogger_path_within($path, $root)) continue;
        if ($file->isDir()) {
            $directories[md5($path)] = $path;
            if (count($pending) < 32) $pending[] = ['path'=>$path, 'offset'=>0];
        } elseif ($file->isFile() && $file->isReadable() && preg_match('/\.(?:jpe?g|png|gif|bmp)$/iD', $path)) {
            $files[] = $path;
        }
    }
    return compact('files', 'pending', 'directories', 'examined');
}

/** @return array{size:int,mtime:int,ctime:int}|null */
function plogger_import_identity(string $path, string $root): ?array {
    clearstatcache(true, $path);
    if ($root === '' || is_link($root) || is_link($path) || !is_file($path) || !plogger_path_within($path, $root)) return null;
    $stat = @stat($path);
    if (!$stat) return null;
    return ['size'=>$stat['size'], 'mtime'=>$stat['mtime'], 'ctime'=>$stat['ctime']];
}

/** @return array<string, mixed> */
function plogger_import_preview(string $path, string $root): array {
    $identity = plogger_import_identity($path, $root);
    if ($identity === null) throw new RuntimeException('Import source is unavailable.');
    $key = md5($path);
    $policy = hash('sha256', serialize(defined('PLOGGER_METADATA_PRIORITIES') ? PLOGGER_METADATA_PRIORITIES : []));
    $old = $_SESSION['plogger_import_files'][$key] ?? null;
    if (is_array($old) && ($old['policy'] ?? '') === $policy && $old['identity'] === $identity && $old['expires'] > time()) return $old;
    $data = plogger_read_metadata($path);
    $fields = plogger_metadata_fields($data);
    $make = plogger_metadata_text($data['IFD0']['Make'] ?? '');
    $model = plogger_metadata_text($data['IFD0']['Model'] ?? '');
    $camera = trim((str_starts_with($model, $make) ? '' : $make.' ').$model);
    $preview = ['path'=>$path, 'identity'=>$identity, 'policy'=>$policy, 'expires'=>time()+3600, 'fields'=>$fields,
        'camera'=>$camera, 'date'=>plogger_metadata_text($data['SubIFD']['DateTimeOriginal'] ?? ''),
        'dimensions'=>$data['dimensions'], 'warning'=>(bool) ($data['Errors'] || $data['warnings'])];
    $_SESSION['plogger_import_files'][$key] = $preview;
    while (count($_SESSION['plogger_import_files']) > 40) array_shift($_SESSION['plogger_import_files']);
    return $preview;
}

/** @return array<string, mixed>|null */
function plogger_import_entry(string $key, string $root): ?array {
    if (!preg_match('/^[a-f0-9]{32}$/D', $key)) return null;
    $entry = $_SESSION['plogger_import_files'][$key] ?? null;
    // Earlier Windows forms hashed the native gallery root followed by slash-separated paths.
    if ($entry === null && PHP_OS_FAMILY === 'Windows') {
        $normalized = rtrim(str_replace('\\', '/', realpath($root) ?: ''), '/');
        foreach ($_SESSION['plogger_import_files'] ?? [] as $candidate) {
            $legacy = realpath(dirname(dirname($root))).'/plog-content/uploads/'.substr($candidate['path'], strlen($normalized)+1);
            if (hash_equals(md5($legacy), $key)) { $entry = $candidate; break; }
        }
    }
    return is_array($entry) ? $entry : null;
}

/** Resolve only previously previewed files; callers may do one bounded discovery for old form clients. */
function plogger_import_source(string $key, string $root): ?string {
    $entry = plogger_import_entry($key, $root);
    if (!is_array($entry) || $entry['expires'] < time() || $entry['identity'] !== plogger_import_identity($entry['path'], $root)) return null;
    return $entry['path'];
}
