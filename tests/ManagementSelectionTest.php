<?php
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__).'/plog-admin/plog-admin-functions.php';
if (!function_exists('plog_tr')) { function plog_tr($text) { return $text; } }

final class ManagementSelectionTest extends TestCase {
    public function testMalformedIdsNeverReachQueriesOrFilesystemOperations(): void {
        $previous = $GLOBALS['PLOGGER_DBH'] ?? null;
        $db = new ManagementQueryGuard();
        $GLOBALS['PLOGGER_DBH'] = $db;
        if (!defined('PLOGGER_TABLE_PREFIX')) define('PLOGGER_TABLE_PREFIX', 'fixture_');
        try {
            foreach (["0' OR 'fixture'='fixture", '1; DELETE FROM albums', '1abc', '1e2', '1.0', ' 1', '-1', '0', '', [], ['1'], null, true, 1.5, str_repeat('9', 40)] as $bad) {
                foreach (['delete_album','delete_collection','delete_picture'] as $operation) {
                    self::assertArrayHasKey('errors', $operation($bad), $operation);
                }
                foreach (['move_album','move_picture'] as $operation) {
                    self::assertArrayHasKey('errors', $operation($bad, 1), $operation);
                    self::assertArrayHasKey('errors', $operation(1, $bad), $operation);
                }
            }
            self::assertSame(0, $db->attempts);
        } finally { $GLOBALS['PLOGGER_DBH'] = $previous; }
    }

    public function testSelectionValidationIsStrictAndChecksTheEntireBatch(): void {
        self::assertTrue(function_exists('plogger_db_id_list'));
        self::assertSame([1,2], plogger_db_id_list(['1',2,'01']));
        self::assertSame([], plogger_db_id_list([]));
        foreach (['1', null, ['x'=>1], [[1]], [1,"2' OR 'a'='a"], [1,0], [1,true]] as $bad) {
            try { plogger_db_id_list($bad); self::fail('Malformed batch accepted.'); }
            catch (InvalidArgumentException) { self::assertTrue(true); }
        }
    }

    public function testEmptyAlbumDeletionBindsAllValuesAndPreservesOtherAlbums(): void {
        if (!extension_loaded('pdo_sqlite')) self::markTestSkipped('SQLite required for isolated album-deletion fixture.');
        if (!defined('PLOGGER_TABLE_PREFIX')) define('PLOGGER_TABLE_PREFIX', 'fixture_');
        $previousDb = $GLOBALS['PLOGGER_DBH'] ?? null;
        $previousConfig = $GLOBALS['config'] ?? null;
        $root = sys_get_temp_dir().'/plog-rb01-'.bin2hex(random_bytes(8));
        mkdir($root.'/plog-content/images/collection/remove',0755,true);
        mkdir($root.'/plog-content/images/collection/keep',0755,true);
        file_put_contents($root.'/plog-content/images/collection/keep/sentinel.txt','unchanged synthetic data');
        $sentinel = hash_file('sha256',$root.'/plog-content/images/collection/keep/sentinel.txt');
        $db = new ManagementSqliteDatabase();
        $db->exec('CREATE TABLE fixture_collections (id INTEGER PRIMARY KEY, name TEXT, path TEXT)');
        $db->exec('CREATE TABLE fixture_albums (id INTEGER PRIMARY KEY, parent_id INTEGER, name TEXT, path TEXT)');
        $db->exec('CREATE TABLE fixture_pictures (id INTEGER PRIMARY KEY, parent_album INTEGER)');
        $db->exec("INSERT INTO fixture_collections VALUES (1,'Collection','collection')");
        $db->exec("INSERT INTO fixture_albums VALUES (11,1,'Remove','remove'),(12,1,'Keep','keep')");
        $GLOBALS['PLOGGER_DBH'] = $db;
        $GLOBALS['config'] = ['basedir'=>$root.'/'];
        try {
            self::assertArrayHasKey('errors',delete_album("11' OR 'a'='a"));
            self::assertSame([],$db->prepared);
            self::assertSame([],delete_album('11'));
            self::assertSame(1,(int)$db->query('SELECT COUNT(*) FROM fixture_albums')->fetchColumn());
            self::assertSame(12,(int)$db->query('SELECT id FROM fixture_albums')->fetchColumn());
            self::assertDirectoryDoesNotExist($root.'/plog-content/images/collection/remove');
            self::assertSame($sentinel,hash_file('sha256',$root.'/plog-content/images/collection/keep/sentinel.txt'));
            self::assertCount(3,$db->prepared);
            foreach ($db->prepared as $sql) self::assertStringContainsString('?', $sql);
        } finally {
            $GLOBALS['PLOGGER_DBH'] = $previousDb;
            $GLOBALS['config'] = $previousConfig;
            $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $file) { if ($file->isDir() && !$file->isLink()) rmdir($file->getPathname()); else unlink($file->getPathname()); }
            rmdir($root);
        }
    }
}

/** No SQL executes: an attempted query fails before any statement or file work. */
final class ManagementQueryGuard extends PDO {
    public int $attempts = 0;
    public function __construct() {}
    public function prepare(string $query, array $options = []): PDOStatement|false {
        ++$this->attempts;
        throw new LogicException('Invalid management input reached the database.');
    }
}

final class ManagementSqliteDatabase extends PDO {
    public array $prepared = [];
    public function __construct() {
        parent::__construct('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    }
    public function prepare(string $query, array $options = []): PDOStatement|false {
        $this->prepared[] = $query;
        return parent::prepare($query,$options);
    }
}
