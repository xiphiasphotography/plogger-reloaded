<?php
require_once __DIR__.'/plog-database.php';
require_once __DIR__.'/plog-security.php';

/** Read headers only; never decode, rotate, write or contact an image URL.
 * @return array{width:int,height:int}|null */
function plogger_original_dimensions(string $root, mixed $relative): ?array {
    if (!is_string($relative) || !plogger_safe_relative_path($relative)) return null;
    $path = $root.'/'.$relative;
    if (!is_file($path) || !is_readable($path) || !plogger_path_within($path, $root)) return null;
    $size = @getimagesize($path);
    if (!$size || $size[0] < 1 || $size[1] < 1) return null;
    return ['width'=>$size[0], 'height'=>$size[1]];
}

/** Bounded keyset scan. Resume after last_id; failed rows can be retried from zero.
 * Existing complete dimensions are never replaced. Concurrent writes use a conditional update.
 * @return array{last_id:int,scanned:int,updated:int,failed:list<int>,done:bool,dry_run:bool} */
function plogger_backfill_dimensions(PDO $db, string $prefix, string $root, int $after = 0, int $limit = 50, bool $dryRun = true): array {
    if ($after < 0 || $limit < 1 || $limit > 100) throw new InvalidArgumentException('Use a nonnegative cursor and a batch size of 1 to 100.');
    $table = plogger_db_identifier(plogger_db_prefix($prefix).'pictures');
    $rows = plogger_db_execute($db, 'SELECT "id", "path" FROM '.$table
        .' WHERE "id" > ? AND ("original_width" IS NULL OR "original_height" IS NULL OR "original_width" <= 0 OR "original_height" <= 0)'
        .' ORDER BY "id" LIMIT '.$limit, [$after])->fetchAll();
    $result = ['last_id'=>$after, 'scanned'=>0, 'updated'=>0, 'failed'=>[], 'done'=>count($rows) < $limit, 'dry_run'=>$dryRun];
    $start = microtime(true);
    foreach ($rows as $row) {
        if (microtime(true) - $start >= 10) { $result['done'] = false; break; }
        $id = (int) $row['id'];
        $result['last_id'] = $id;
        ++$result['scanned'];
        $size = plogger_original_dimensions($root, $row['path']);
        if (!$size) { $result['failed'][] = $id; continue; }
        if ($dryRun) { ++$result['updated']; continue; }
        $changed = plogger_db_execute($db, 'UPDATE '.$table.' SET "original_width" = ?, "original_height" = ?'
            .' WHERE "id" = ? AND "path" = ? AND ("original_width" IS NULL OR "original_height" IS NULL OR "original_width" <= 0 OR "original_height" <= 0)',
            [$size['width'], $size['height'], $id, $row['path']]);
        $result['updated'] += $changed->rowCount();
    }
    return $result;
}
