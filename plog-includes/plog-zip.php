<?php
/** Validate every entry before bounded streaming; never trust archive destination paths. */
function plogger_require_zip(): void {
    if (!class_exists('ZipArchive')) throw new RuntimeException('Enable PHP ext-zip (ZipArchive) for ZIP imports and downloads.');
}

/** @return list<array{name:string,size:int,crc:int}>|null */
function plogger_zip_entries(ZipArchive $archive): ?array {
    if ($archive->numFiles < 1 || $archive->numFiles > 1000) return null;
    $entries = []; $names = []; $total = 0;
    for ($index = 0; $index < $archive->numFiles; ++$index) {
        $stat = $archive->statIndex($index);
        if (!$stat) return null;
        $name = $stat['name'];
        $directory = str_ends_with($name, '/');
        if (!plogger_safe_relative_path($directory ? substr($name, 0, -1) : $name)) return null;
        $opsys = $attributes = 0;
        if (!$archive->getExternalAttributesIndex($index, $opsys, $attributes)) return null;
        $kind = ($attributes >> 16) & 0170000;
        if ($kind !== 0 && $kind !== 0100000 && !($directory && $kind === 0040000)) return null;
        if ($stat['encryption_method'] !== 0) return null;
        if ($directory) continue;
        $base = basename($name);
        $key = mb_strtolower($base, 'UTF-8');
        if (strlen($base) > 240 || str_starts_with($base, '.') || !mb_check_encoding($base, 'UTF-8')
            || preg_match('/^(?:CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])(?:\.|$)/i', $base)
            || !plogger_safe_image_filename($base) || isset($names[$key])
            || !in_array(strtolower(pathinfo($base, PATHINFO_EXTENSION)), ['jpg','jpeg','png','gif','bmp'], true)
            || $stat['size'] < 1 || $stat['size'] > 33554432
            || $stat['size'] > max(1, $stat['comp_size']) * 1000) return null;
        $total += $stat['size'];
        if ($total > 67108864) return null;
        $names[$key] = true;
        $entries[] = ['name' => $name, 'size' => $stat['size'], 'crc' => $stat['crc']];
    }
    return $entries ?: null;
}

/** Read at most the validated size, verifying actual size and CRC before publication. */
function plogger_zip_extract_entry(ZipArchive $archive, string $name, string $target, int $size, int $crc): bool {
    $input = $archive->getStream($name);
    if ($input === false) return false;
    $output = fopen($target, 'xb');
    if ($output === false) { fclose($input); return false; }
    $hash = hash_init('crc32b'); $bytes = 0;
    try {
        while (!feof($input)) {
            $chunk = fread($input, min(65536, $size - $bytes + 1));
            if ($chunk === false || ($chunk === '' && !feof($input))) return false;
            $bytes += strlen($chunk);
            if ($bytes > $size || fwrite($output, $chunk) !== strlen($chunk)) return false;
            hash_update($hash, $chunk);
        }
        return $bytes === $size && hash_final($hash) === sprintf('%08x', $crc);
    } finally { fclose($input); fclose($output); }
}
