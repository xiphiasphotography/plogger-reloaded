<?php
/** Positive IDs only; never coerce malformed strings, arrays, booleans or floats. */
function plogger_db_id(mixed $value): ?int {
    if (is_int($value)) return $value > 0 ? $value : null;
    if (!is_string($value) || !preg_match('/^[0-9]+$/D', $value)) return null;
    $digits = ltrim($value, '0');
    $maximum = (string) PHP_INT_MAX;
    if ($digits === '' || strlen($digits) > strlen($maximum)
        || (strlen($digits) === strlen($maximum) && strcmp($digits, $maximum) > 0)) return null;
    return (int) $digits;
}

/** Validate the complete selection before its caller performs any action.
 * @return list<int> */
function plogger_db_id_list(mixed $values): array {
    if (!is_array($values) || !array_is_list($values)) throw new InvalidArgumentException('Invalid selection.');
    $ids = [];
    foreach ($values as $value) {
        $id = plogger_db_id($value);
        if ($id === null) throw new InvalidArgumentException('Invalid selection.');
        $ids[$id] = $id;
    }
    return array_values($ids);
}

// Driver-independent PDO operations. Values and identifiers have separate APIs.
function plogger_db_dsn(mixed $driver, mixed $host, mixed $database, mixed $port = null): string {
    if (!in_array($driver, array('mysql', 'pgsql'), true)) throw new InvalidArgumentException('Unsupported database driver');
    foreach (array($host, $database) as $part) {
        if (!is_string($part) || preg_match('/[;\x00-\x1f\x7f]/', $part)) throw new InvalidArgumentException('Invalid database connection setting');
    }
    $dsn = $driver.':host='.$host.';dbname='.$database;
    if ($port !== null && $port !== '') {
        if (!is_int($port) && !is_string($port)) throw new InvalidArgumentException('Invalid database port');
        if (!ctype_digit((string) $port) || intval($port) < 1 || intval($port) > 65535) throw new InvalidArgumentException('Invalid database port');
        $dsn .= ';port='.intval($port);
    }
    return $dsn.($driver === 'mysql' ? ';charset=utf8' : '');
}

function plogger_db_connect(mixed $driver, mixed $host, mixed $database, string $user, string $password, mixed $port = null): PDO {
    $connection = new PDO(plogger_db_dsn($driver, $host, $database, $port), $user, $password, array(
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ));
    if ($driver === 'mysql') plogger_db_execute($connection, "SET SESSION sql_mode = 'ANSI'");
    return $connection;
}

function plogger_db_identifier(mixed $name): string {
    if (!is_string($name) || !preg_match('/^[A-Za-z0-9_]+$/D', $name) || strlen($name) > 63) {
        throw new InvalidArgumentException('Invalid database identifier');
    }
    return '"'.$name.'"'; // Connections use ANSI_QUOTES on MySQL.
}

function plogger_db_prefix(mixed $prefix): string {
    if (!is_string($prefix) || !preg_match('/^[A-Za-z0-9_]*$/D', $prefix)) {
        throw new InvalidArgumentException('Invalid database table prefix');
    }
    return $prefix;
}

function plogger_db_table(mixed $table): string {
    $prefix = plogger_db_prefix(PLOGGER_TABLE_PREFIX);
    $tables = array('config', 'collections', 'albums', 'pictures', 'comments', 'thumbnail_config', 'tags', 'tag2picture');
    if (!in_array($table, $tables, true)) throw new InvalidArgumentException('Unknown gallery table');
    return plogger_db_identifier($prefix.$table);
}

/** @param list<string> $allowed */
function plogger_db_order(mixed $field, mixed $direction, array $allowed = array('id', 'name')): string {
    $field = is_string($field) && in_array($field, $allowed, true) ? $field : 'id';
    $direction = is_string($direction) && strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';
    return ' ORDER BY '.plogger_db_identifier($field).' '.$direction;
}

/** @param array<int|string, mixed> $parameters */
function plogger_db_execute(PDO $connection, string $sql, array $parameters = array()): PDOStatement {
    $statement = $connection->prepare($sql);
    if ($statement === false) throw new PDOException('Statement preparation failed');
    foreach ($parameters as $key => $value) {
        if (!is_scalar($value) && $value !== null) throw new InvalidArgumentException('Invalid database value');
        $type = $value === null ? PDO::PARAM_NULL : (is_int($value) ? PDO::PARAM_INT : (is_bool($value) ? PDO::PARAM_BOOL : PDO::PARAM_STR));
        $statement->bindValue(is_int($key) ? $key + 1 : $key, $value, $type);
    }
    $statement->execute();
    return $statement;
}

/** @param array<int|string, mixed> $values
 * @param array<int|string, mixed> $where */
function plogger_db_update(mixed $table, array $values, array $where = array()): PDOStatement|false {
    if (!$values) throw new InvalidArgumentException('Empty database update');
    if (!$where && $table !== 'config') throw new InvalidArgumentException('Database update requires a row condition');
    $set = $conditions = $parameters = array();
    foreach ($values as $column => $value) {
        $set[] = plogger_db_identifier($column).' = ?';
        $parameters[] = $value;
    }
    foreach ($where as $column => $value) {
        $conditions[] = plogger_db_identifier($column).' = ?';
        $parameters[] = $value;
    }
    return run_query('UPDATE '.plogger_db_table($table).' SET '.implode(', ', $set)
        .($conditions ? ' WHERE '.implode(' AND ', $conditions) : ''), false, 'Database error.', $parameters);
}

/** @param array<int|string, mixed> $values */
function plogger_db_insert(mixed $table, array $values): PDOStatement {
    if (!$values) throw new InvalidArgumentException('Empty database insert');
    return run_query('INSERT INTO '.plogger_db_table($table).' ('.implode(', ', array_map('plogger_db_identifier', array_keys($values))).') VALUES ('
        .implode(', ', array_fill(0, count($values), '?')).')', true, 'Database error.', array_values($values));
}
