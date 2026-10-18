<?php
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__).'/plog-includes/plog-dimensions.php';
require_once dirname(__DIR__).'/plog-includes/plog-upgrade.php';
if (!function_exists('plog_tr')) { function plog_tr($text) { return $text; } }
require_once dirname(__DIR__).'/plog-content/modules/oypo/module.php';

final class OypoTest extends TestCase {
    private array $actions;
    private array $fields;
    protected function setUp(): void {
        $this->actions = PloggerExtensions::$actions;
        $this->fields = PloggerExtensions::$albumFields;
    }
    protected function tearDown(): void {
        PloggerExtensions::$actions = $this->actions;
        PloggerExtensions::$albumFields = $this->fields;
    }
    public function testEligibilityRequiresExplicitRuleAlbumAndBothOriginalDimensions(): void {
        $photo = ['original_width'=>3000, 'original_height'=>2000];
        foreach ([null, [], ['mode'=>'long_edge'], ['mode'=>'long_edge','long_edge'=>0],
            ['mode'=>'long_edge','long_edge'=>'2000'], ['mode'=>'dimensions','width'=>1]] as $rule) {
            self::assertFalse(plogger_oypo_eligible($photo, 'album', $rule));
        }
        $rule = ['mode'=>'long_edge','long_edge'=>3000]; // Synthetic policy, not a production default.
        self::assertTrue(plogger_oypo_eligible($photo, 'album', $rule));
        self::assertTrue(plogger_oypo_eligible(['original_width'=>'2000','original_height'=>'3000'], 'album', $rule));
        self::assertFalse(plogger_oypo_eligible($photo, null, $rule));
        self::assertFalse(plogger_oypo_eligible($photo, [], $rule));
        self::assertFalse(plogger_oypo_eligible(['original_width'=>4000], 'album', $rule));
        self::assertFalse(plogger_oypo_eligible(['original_width'=>2999,'original_height'=>2000], 'album', $rule));
        self::assertTrue(plogger_oypo_eligible($photo, 'album', ['mode'=>'dimensions','width'=>3000,'height'=>2000]));
        self::assertFalse(plogger_oypo_eligible($photo, 'album', ['mode'=>'dimensions','width'=>2000,'height'=>3000]));
    }
    public function testModuleStaysDisabledWithoutConfirmedUrlAdapterAndPolicy(): void {
        $photo = ['id'=>1,'path'=>'folder/photo.jpg','original_width'=>3000,'original_height'=>2000];
        self::assertNull(plogger_oypo_action($photo, ['oypo_album_id'=>'album'], ['mode'=>'long_edge','long_edge'=>1], null));
        self::assertNull(plogger_oypo_action($photo, ['oypo_album_id'=>'album'], null, fn()=> 'https://example.invalid/order'));
        self::assertSame('', plogger_get_picture_actions($photo, ['oypo_album_id'=>'album']));
        PloggerExtensions::$actions = [];
        self::assertSame('', plogger_get_picture_actions()); // Disabled modules need no DB access.
    }
    public function testEncodedIdentifiersSafeActionOutputAndUnsafeUrls(): void {
        $photo = ['id'=>7,'path'=>'folder/photo é &?.jpg','original_width'=>80,'original_height'=>40];
        $rule = ['mode'=>'long_edge','long_edge'=>80];
        $adapter = static function ($album, $id, $path) {
            self::assertSame('album_1', $album); self::assertSame('7', $id);
            self::assertSame('folder/photo%20%C3%A9%20%26%3F.jpg', $path);
            return 'https://example.invalid/order?album='.$album.'&photo='.$path;
        };
        $action = plogger_oypo_action($photo, ['oypo_album_id'=>'album_1'], $rule, $adapter);
        PloggerExtensions::$actions = ['test'=>fn()=> $action + ['ignored'=>'']];
        $html = plogger_get_picture_actions($photo, []);
        self::assertStringContainsString('&amp;photo=', $html);
        self::assertStringContainsString('rel="noopener noreferrer"', $html);
        foreach (['javascript:alert(1)', '//evil.invalid', 'https://user:pw@example.invalid', "https://example.invalid/\" onclick=\"bad"] as $url) {
            self::assertNull(plogger_oypo_action($photo, ['oypo_album_id'=>'album'], $rule, fn()=> $url));
            PloggerExtensions::$actions = ['test'=>fn()=> ['url'=>$url,'label'=>'Order']];
            self::assertSame('', plogger_get_picture_actions($photo, []));
        }
        PloggerExtensions::$actions = ['test'=>fn()=> ['url'=>'https://example.invalid/','label'=>'<script>bad</script>']];
        self::assertStringContainsString('&lt;script&gt;', plogger_get_picture_actions($photo, []));
    }
    public function testAlbumFieldValidationClearingAndOutputEscaping(): void {
        self::assertSame(['oypo_album_id'=>null], plogger_album_extension_values(['oypo_album_id'=>'']));
        self::assertSame(['oypo_album_id'=>'abc-12'], plogger_album_extension_values(['oypo_album_id'=>'abc-12','ignored'=>'x']));
        self::assertSame([], plogger_album_extension_values([]));
        self::assertStringContainsString('&quot;&gt;&lt;script&gt;', plogger_album_extension_form(['oypo_album_id'=>'"><script>']));
        foreach ([[], 'a/b', '<script>', str_repeat('a',129), "bad\n"] as $id) {
            try { plogger_oypo_album_id($id); self::fail('Unsafe ID accepted'); }
            catch (InvalidArgumentException) { self::assertTrue(true); }
        }
    }
    public function testBackfillDryRunResumeFailuresAndOriginalIntegrity(): void {
        if (!extension_loaded('pdo_sqlite') || !extension_loaded('gd')) self::markTestSkipped('SQLite/GD required for isolated backfill fixture.');
        $root = sys_get_temp_dir().'/plog-dimensions-'.bin2hex(random_bytes(6)); mkdir($root);
        $image = imagecreatetruecolor(80,40); imagepng($image, $root.'/photo.png'); imagedestroy($image);
        file_put_contents($root.'/broken.png','corrupt');
        $hash = hash_file('sha256', $root.'/photo.png');
        try {
            $db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
            $db->exec('CREATE TABLE test_pictures (id INTEGER PRIMARY KEY, path TEXT, original_width INTEGER, original_height INTEGER)');
            foreach ([1=>'photo.png',2=>'../escape.png',3=>'broken.png',4=>'missing.png',5=>'photo.png'] as $id=>$path) {
                plogger_db_execute($db,'INSERT INTO test_pictures VALUES (?, ?, NULL, NULL)',[$id,$path]);
            }
            $preview = plogger_backfill_dimensions($db,'test_',$root,0,2);
            self::assertSame([2], $preview['failed']); self::assertSame(1, $preview['updated']);
            self::assertNull($db->query('SELECT original_width FROM test_pictures WHERE id=1')->fetchColumn());
            $first = plogger_backfill_dimensions($db,'test_',$root,0,2,false);
            self::assertSame(2, $first['last_id']); self::assertFalse($first['done']);
            $next = plogger_backfill_dimensions($db,'test_',$root,$first['last_id'],100,false);
            self::assertSame([3,4], $next['failed']); self::assertTrue($next['done']);
            self::assertSame(80, $db->query('SELECT original_width FROM test_pictures WHERE id=5')->fetchColumn());
            self::assertSame(0, plogger_backfill_dimensions($db,'test_',$root,0,100,false)['updated']);
            self::assertSame($hash, hash_file('sha256', $root.'/photo.png'));
            self::assertNull(plogger_original_dimensions($root,'../photo.png'));
        } finally { unlink($root.'/photo.png'); unlink($root.'/broken.png'); rmdir($root); }
    }

    public function testAdditiveMigrationIsIdempotentAndRefusesPrivateColumnConflicts(): void {
        if (!extension_loaded('pdo_sqlite')) self::markTestSkipped('SQLite required for isolated migration unit test.');
        foreach (['mysql','pgsql'] as $driver) {
            // Metadata test double exercises migration decisions, not native engine behavior.
            $db = new PhotographSchemaFixture($driver);
            $db->exec('CREATE TABLE test_albums (id INTEGER PRIMARY KEY)');
            $db->exec('CREATE TABLE test_pictures (id INTEGER PRIMARY KEY, path TEXT)');
            $db->exec("INSERT INTO test_albums VALUES (1)");
            $db->exec("INSERT INTO test_pictures VALUES (1, 'fixture/photo.jpg')");
            $upgrade = new PloggerUpgrade($db, 'test_', dirname(__DIR__));
            $upgrade->ensurePhotographActions();
            self::assertNull($db->query('SELECT oypo_album_id FROM test_albums')->fetchColumn());
            self::assertNull($db->query('SELECT original_width FROM test_pictures')->fetchColumn());
            $db->exec("UPDATE test_albums SET oypo_album_id='existing'");
            $db->exec('UPDATE test_pictures SET original_width=80, original_height=40');
            $upgrade->ensurePhotographActions();
            self::assertSame('existing', $db->query('SELECT oypo_album_id FROM test_albums')->fetchColumn());
            self::assertSame(['id'=>1,'path'=>'fixture/photo.jpg','original_width'=>80,'original_height'=>40], $db->query('SELECT * FROM test_pictures')->fetch());
            foreach (['mysql','pgsql'] as $schemaDriver) {
                $schema = plogger_schema_tables($schemaDriver);
                self::assertStringContainsString('oypo_album_id VARCHAR(128) DEFAULT NULL', $schema['albums'][0]);
                self::assertStringContainsString('original_width INTEGER DEFAULT NULL', $schema['pictures'][0]);
            }
            $db->exec('ALTER TABLE test_albums DROP COLUMN oypo_album_id');
            $db->exec('ALTER TABLE test_albums ADD COLUMN oypo_album_id VARCHAR(64)');
            try { $upgrade->ensurePhotographActions(); self::fail('Private column conflict accepted'); }
            catch (RuntimeException $error) { self::assertStringContainsString('Review existing integration column', $error->getMessage()); }
        }
    }
}

/** SQLite-backed metadata double; native database fixtures remain required in CI. */
final class PhotographSchemaFixture extends PDO {
    public function __construct(private string $driver) {
        parent::__construct('sqlite::memory:', null, null, [PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    }
    public function getAttribute(int $attribute): mixed {
        return $attribute === PDO::ATTR_DRIVER_NAME ? $this->driver : parent::getAttribute($attribute);
    }
    public function prepare(string $query, array $options = []): PDOStatement|false {
        if (str_contains($query, 'information_schema.columns')) {
            $query = "SELECT name AS column_name, CASE WHEN type LIKE 'VARCHAR(%' THEN 'varchar' ELSE lower(type) END AS data_type,"
                ." CASE WHEN [notnull]=0 THEN 'YES' ELSE 'NO' END AS is_nullable, dflt_value AS column_default,"
                ." CASE WHEN type LIKE 'VARCHAR(%' THEN CAST(substr(type,9) AS INTEGER) ELSE NULL END AS character_maximum_length FROM pragma_table_info(?)";
        }
        return parent::prepare($query, $options);
    }
}
