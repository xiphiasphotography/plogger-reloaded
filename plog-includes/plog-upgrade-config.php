<?php
/** Read literal define() statements without executing an installation's PHP code. */
/** @return array<array-key, mixed> */
function plogger_upgrade_config(string $path): array {
    if(!function_exists('token_get_all')) throw new RuntimeException('Enable the PHP tokenizer extension for safe upgrade configuration inspection.');
    if (!is_file($path) || is_link($path) || !is_readable($path) || filesize($path) > 65536) {
        throw new RuntimeException('Configuration must be a readable regular file, at most 64 KiB.');
    }
    $tokens = array_values(array_filter(token_get_all(file_get_contents($path), TOKEN_PARSE),
        static fn ($token) => !is_array($token) || !in_array($token[0], [T_OPEN_TAG,T_CLOSE_TAG,T_WHITESPACE,T_COMMENT,T_DOC_COMMENT], true)));
    $constants = [];
    $literal = static function ($token): string|int|bool|null {
        if (!is_array($token)) throw new RuntimeException('Only literal configuration values are supported.');
        if ($token[0] === T_CONSTANT_ENCAPSED_STRING) {
            $text = substr($token[1], 1, -1);
            return $token[1][0] === "'" ? strtr($text, ["\\\\"=>"\\", "\\'"=>"'"]) : stripcslashes($text);
        }
        if ($token[0] === T_LNUMBER) return intval($token[1], 0);
        if ($token[0] === T_STRING && in_array(strtolower($token[1]), ['true','false','null'], true)) {
            return match (strtolower($token[1])) { 'true'=>true, 'false'=>false, default=>null };
        }
        throw new RuntimeException('Dynamic configuration is not executed. Prepare a reviewed literal configuration on staging.');
    };
    for ($i=0; $i<count($tokens); $i+=7) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_STRING || strtolower($tokens[$i][1]) !== 'define'
            || ($tokens[$i+1] ?? null) !== '(' || ($tokens[$i+3] ?? null) !== ','
            || ($tokens[$i+5] ?? null) !== ')' || ($tokens[$i+6] ?? null) !== ';') {
            throw new RuntimeException('Only literal define() statements are supported; configuration was not executed.');
        }
        $name = $literal($tokens[$i+2]);
        if (!is_string($name) || !preg_match('/^PLOGGER_[A-Z_]+$/D', $name) || array_key_exists($name, $constants)) {
            throw new RuntimeException('Invalid or duplicate configuration constant.');
        }
        $constants[$name] = $literal($tokens[$i+4]);
    }
    foreach (['HOST','USER','PW','NAME'] as $key) {
        if (!isset($constants['PLOGGER_DB_'.$key]) || !is_string($constants['PLOGGER_DB_'.$key])) throw new RuntimeException('Missing database configuration.');
    }
    return $constants;
}

/** @param array<array-key, mixed> $settings */

function plogger_upgrade_connection(array $settings): PDO {
    return plogger_db_connect($settings['PLOGGER_DB_TYPE'] ?? 'mysql', $settings['PLOGGER_DB_HOST'],
        $settings['PLOGGER_DB_NAME'], $settings['PLOGGER_DB_USER'], $settings['PLOGGER_DB_PW'], $settings['PLOGGER_DB_PORT'] ?? '');
}

/** @param array<array-key, mixed> $settings */

function plogger_upgrade_proposal(array $settings): string {
    $settings += ['PLOGGER_DB_TYPE'=>'mysql', 'PLOGGER_DB_PORT'=>'', 'PLOGGER_TABLE_PREFIX'=>'plogger_'];
    $output = "<?php\n// Reviewed upgrade proposal. The original configuration is never overwritten.\n";
    foreach ($settings as $name=>$value) $output .= 'define('.var_export($name,true).', '.var_export($value,true).");\n";
    return $output;
}

/** @param array<array-key, mixed> $settings */

function plogger_upgrade_write_proposal(string $path, array $settings): void {
    $target = $path.'.proposed.php';
    $proposal = plogger_upgrade_proposal($settings);
    if (is_link($target)) throw new RuntimeException('Unsafe configuration proposal destination.');
    if (file_exists($target)) {
        if (file_get_contents($target) !== $proposal) throw new RuntimeException('A different configuration proposal already exists; review it manually.');
        return;
    }
    $handle = fopen($target, 'xb');
    if ($handle === false) throw new RuntimeException('Cannot create configuration proposal.');
    try {
        chmod($target, 0600);
        if (fwrite($handle, $proposal) !== strlen($proposal) || !fflush($handle)) throw new RuntimeException('Cannot finish configuration proposal.');
    } finally { fclose($handle); }
}
