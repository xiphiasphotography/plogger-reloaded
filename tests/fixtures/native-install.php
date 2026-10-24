<?php

// Called only by the isolated HTTP test harness, never against a site config.
if (PHP_SAPI !== 'cli') exit(1);
$root = $argv[1] ?? '';
$driver = getenv('PLOGGER_TEST_DRIVER');
$database = getenv('PLOGGER_TEST_DATABASE');
$port = (int) getenv('PLOGGER_TEST_PORT');
if (!preg_match('/^plogger_phase1_ci_[a-f0-9]{16}$/D', $database)
    || !str_starts_with(basename($root), 'plog-fixture-')
    || realpath(dirname($root)) !== realpath(sys_get_temp_dir())
    || $port < 20000 || !in_array($driver, ['mysql', 'pgsql'], true)) {
    throw new RuntimeException('Unsafe native installation fixture target.');
}
$connection = new PDO($driver . ':host=127.0.0.1;port=' . $port . ($driver === 'pgsql' ? ';dbname=postgres' : ''),
    getenv('PLOGGER_TEST_USER'), getenv('PLOGGER_TEST_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
if ($driver === 'mysql') $connection->exec("SET SESSION sql_mode = 'ANSI,STRICT_ALL_TABLES'");
$connection->exec('CREATE DATABASE "' . $database . '"');
$connection = null;
$_SERVER['SCRIPT_FILENAME'] = $root . '/plog-admin/_install.php';
$_SERVER['PHP_SELF'] = '/plog-admin/_install.php';
$_SERVER['HTTP_HOST'] = $argv[2];
require __DIR__ . '/http-bootstrap.php';
require $root . '/plog-globals.php';
require $root . '/plog-includes/plog-functions.php';
require $root . '/plog-admin/includes/install-functions.php';
if (in_array($driver, ['mysql', 'pgsql'], true)) {
    // Exercise both production DSN builders with this non-default local port.
    if (check_pdo(PLOGGER_DB_TYPE, PLOGGER_DB_HOST, PLOGGER_DB_USER, PLOGGER_DB_PW, PLOGGER_DB_NAME, PLOGGER_DB_PORT)) {
        throw new RuntimeException('Installer port check failed.');
    }
    close_db();
    if (!connect_db(false)) throw new RuntimeException('Gallery port connection failed.');
}
$upgradeSource=getenv('PLOGGER_TEST_UPGRADE_FROM');
if($upgradeSource) {
    require_once $root.'/plog-includes/plog-upgrade.php';
    require_once __DIR__.'/upgrade-seed.php';
    plogger_seed_upgrade_fixture($PLOGGER_DBH,$driver,PLOGGER_TABLE_PREFIX,$upgradeSource,'http://'.$argv[2].'/');
    $upgrade=new PloggerUpgrade($PLOGGER_DBH,PLOGGER_TABLE_PREFIX,$root);
    $before=$upgrade->snapshot();
    $upgraded=$upgrade->apply(['database'=>'synthetic verified fixture backup','files'=>'synthetic fixture originals/config backup','restore_tested'=>true],true,null,PloggerUpgrade::VERSION);
    if($before!==$upgraded['snapshot']) throw new RuntimeException('Upgrade fixture changed existing content.');
    echo "Native upgrade fixture created: ".$upgradeSource."\n";
    exit;
}
create_tables($driver);
configure_plogger(['gallery_name' => 'Synthetic gallery', 'admin_username' => 'fixture',
    'admin_password' => 'fixture-password', 'admin_email' => 'fixture@example.invalid']);
// Re-read configuration as the actual web loader does before creating sample records.
$config = $PLOGGER_DBH->query('SELECT * FROM "fixture_config"')->fetch();
// A fresh install must work at localhost without treating its gallery URL as a CDN.
if ($config['cdn_url'] !== '') throw new RuntimeException('Fresh installer must leave the optional CDN disabled.');
$config['basedir'] = PLOGGER_DIR;
$collection = add_collection('Fixture', 'Synthetic collection');
if (empty($collection['id'])) throw new RuntimeException('Fixture collection creation failed.');
$album = add_album('Album', null, $collection['id']);
if (empty($album['id'])) throw new RuntimeException('Fixture album creation failed.');
$statement = $PLOGGER_DBH->prepare('INSERT INTO "fixture_pictures" ("id", "path", "parent_album", "parent_collection",
    "caption", "description", "date_modified", "date_submitted", "exif_date_taken", "exif_camera",
    "exif_shutterspeed", "exif_focallength", "exif_flash", "exif_aperture", "exif_iso", "allow_comments")
    VALUES (1, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, NULL, ?, ?, ?, ?, ?, ?, 1)');
$statement->execute(['fixture/album/photo.jpg', $album['id'], $collection['id'], 'Fixture caption',
    "Fixture description\nsecond line", '', '', '', '', '', '']);
if ($driver === 'pgsql') $PLOGGER_DBH->query("SELECT setval('fixture_pictures_id_seq', 1)");
// Enable downloads for their smoke test; the installer keeps its original default disabled.
$PLOGGER_DBH->exec('UPDATE "fixture_config" SET "allow_dl" = 1');
echo "Native installation fixture created\n";
