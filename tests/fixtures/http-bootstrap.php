<?php

// Only used as auto_prepend_file in the temporary, localhost-only test website.
require_once __DIR__ . '/FixtureDatabase.php';
$fixture_driver = getenv('PLOGGER_TEST_DRIVER') ?: 'mysql';
define('PLOGGER_DB_TYPE', $fixture_driver);
if (($_GET['fixture'] ?? '') !== 'fresh') define('PLOGGER_DB_HOST', '127.0.0.1');
define('PLOGGER_DB_PORT', getenv('PLOGGER_TEST_PORT') ?: '');
define('PLOGGER_DB_NAME', getenv('PLOGGER_TEST_DATABASE') ?: 'fixture');
define('PLOGGER_DB_USER', getenv('PLOGGER_TEST_USER') ?: 'fixture');
define('PLOGGER_DB_PW', getenv('PLOGGER_TEST_PASSWORD') ?: '');
define('PLOGGER_TABLE_PREFIX', 'fixture_');
define('PLOGGER_DEBUG', 0);
if (!isset($_GET['fixture_remote_disabled'])) define('PLOGGER_ENABLE_REMOTE', true);
define('PLOGGER_AUTH_STORAGE', $_SERVER['DOCUMENT_ROOT'].'/../plog-auth-fixture-'.hash('sha256', str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']))));
if (isset($_GET['fixture_throttle'])) $_SERVER['REMOTE_ADDR'] = '127.0.0.99';
if (getenv('PLOGGER_TEST_DRIVER')) {
    // Native test mode is limited to generated databases on a local high port.
    if (!in_array($fixture_driver, ['mysql', 'pgsql'], true)
        || !preg_match('/^plogger_phase1_ci_[a-f0-9]{16}$/D', PLOGGER_DB_NAME)
        || (int) PLOGGER_DB_PORT < 20000) throw new RuntimeException('Unsafe native fixture target.');
    $dsn = $fixture_driver . ':host=127.0.0.1;port=' . PLOGGER_DB_PORT . ';dbname=' . PLOGGER_DB_NAME;
    $PLOGGER_DBH = new PDO($dsn, PLOGGER_DB_USER, PLOGGER_DB_PW, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
    if ($fixture_driver === 'mysql') $PLOGGER_DBH->exec("SET SESSION sql_mode = 'ANSI,STRICT_ALL_TABLES'");
} else {
    $PLOGGER_DBH = new FixtureDatabase();
}
ini_set('display_errors', '1');
error_reporting(E_ALL);
