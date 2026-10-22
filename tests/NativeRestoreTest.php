<?php
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__).'/plog-includes/plog-upgrade.php';
require_once __DIR__.'/fixtures/upgrade-seed.php';

/** Real dump/client round trips; never reads an installation's configuration. */
final class NativeRestoreTest extends TestCase {
    public static function sources(): array { return [['rc1'], ['php7'], ['php8']]; }

    /** @dataProvider sources */
    public function testExternalBackupRestoresLegacyDataAndOriginals(string $source): void {
        $driver = getenv('PLOGGER_TEST_DRIVER');
        $dump = getenv('PLOGGER_TEST_DUMP');
        $client = getenv('PLOGGER_TEST_CLIENT');
        if (!$driver || !$dump || !$client) self::markTestSkipped('Explicit disposable native server and dump/client executables required.');
        $port = (int) getenv('PLOGGER_TEST_PORT');
        if (!in_array($driver, ['mysql','pgsql'], true) || $port < 20000 || !is_file($dump) || !is_file($client)) {
            throw new RuntimeException('Unsafe native restore fixture settings.');
        }
        // Fixtures use only a local, passwordless account on this private cluster.
        if (getenv('PLOGGER_TEST_PASSWORD')) throw new RuntimeException('Use the documented private fixture cluster, never site credentials.');
        $user = getenv('PLOGGER_TEST_USER');
        $name = 'plogger_restore_ci_'.bin2hex(random_bytes(8));
        $restoredName = $name.'_r';
        $admin = new PDO($driver.':host=127.0.0.1;port='.$port.($driver==='pgsql'?';dbname=postgres':''), $user, '',
            [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        if ($driver==='mysql') plogger_db_execute($admin,"SET SESSION sql_mode = 'ANSI,STRICT_ALL_TABLES'");
        $root = sys_get_temp_dir().'/plog-restore-'.bin2hex(random_bytes(8));
        mkdir($root,0700); mkdir($root.'/backup',0700); mkdir($root.'/restored',0700);
        $created = []; $db = $restored = null;
        try {
            foreach ([$name,$restoredName] as $database) {
                plogger_db_execute($admin,'CREATE DATABASE '.plogger_db_identifier($database)); $created[]=$database;
            }
            $db=plogger_db_connect($driver,'127.0.0.1',$name,$user,'',$port);
            plogger_seed_upgrade_fixture($db,$driver,'restore_',$source,'http://example.invalid/');
            $snapshot = static function(PDO $connection): array {
                $rows=[];
                foreach (['config','collections','albums','pictures','comments','thumbnail_config'] as $table) {
                    $rows[$table]=plogger_db_execute($connection,'SELECT * FROM '.plogger_db_identifier('restore_'.$table).' ORDER BY 1')->fetchAll();
                }
                return $rows;
            };
            $before=$snapshot($db);
            $settings=['PLOGGER_DB_TYPE'=>$driver,'PLOGGER_DB_HOST'=>'127.0.0.1','PLOGGER_DB_PORT'=>$port,
                'PLOGGER_DB_NAME'=>$name,'PLOGGER_DB_USER'=>$user,'PLOGGER_DB_PW'=>'','PLOGGER_TABLE_PREFIX'=>'restore_'];
            file_put_contents($root.'/plog-config.php',plogger_upgrade_proposal($settings));
            $original='plog-content/images/fixture/album/photo.jpg';
            foreach (['','/backup','/restored'] as $copy) mkdir($root.$copy.'/plog-content/images/fixture/album',0700,true);
            foreach (['','/backup','/restored'] as $copy) mkdir($root.$copy.'/plog-content/themes/default',0700,true);
            file_put_contents($root.'/plog-content/themes/default/gallery.php','<?php echo plogger_get_picture_caption();');
            $image=imagecreatetruecolor(8,8); imagejpeg($image,$root.'/'.$original); unset($image);
            foreach (['plog-config.php',$original,'plog-content/themes/default/gallery.php'] as $file) {
                self::assertTrue(copy($root.'/'.$file,$root.'/backup/'.$file));
                self::assertTrue(copy($root.'/backup/'.$file,$root.'/restored/'.$file));
                self::assertSame(hash_file('sha256',$root.'/'.$file),hash_file('sha256',$root.'/restored/'.$file));
            }
            $command=$driver==='mysql'
                ? [$dump,'--no-defaults','--host=127.0.0.1','--port='.$port,'--user='.$user,'--single-transaction','--skip-comments',$name]
                : [$dump,'--host=127.0.0.1','--port='.$port,'--username='.$user,'--no-password','--format=plain','--no-owner','--no-privileges',$name];
            $process=proc_open($command,[0=>['pipe','r'],1=>['file',$root.'/backup/database.sql','w'],2=>['pipe','w']],$pipes);
            self::assertIsResource($process); fclose($pipes[0]);
            $error=stream_get_contents($pipes[2]); fclose($pipes[2]);
            self::assertSame(0,proc_close($process),$error);
            self::assertGreaterThan(0,filesize($root.'/backup/database.sql'));
            $command=$driver==='mysql'
                ? [$client,'--no-defaults','--host=127.0.0.1','--port='.$port,'--user='.$user,$restoredName]
                : [$client,'--host=127.0.0.1','--port='.$port,'--username='.$user,'--no-password','--dbname='.$restoredName,'--set=ON_ERROR_STOP=1'];
            $process=proc_open($command,[0=>['file',$root.'/backup/database.sql','r'],1=>['file',$root.'/restore.log','w'],2=>['pipe','w']],$pipes);
            self::assertIsResource($process); $error=stream_get_contents($pipes[2]); fclose($pipes[2]);
            self::assertSame(0,proc_close($process),$error);
            $restored=plogger_db_connect($driver,'127.0.0.1',$restoredName,$user,'',$port);
            self::assertSame($before,$snapshot($restored));
            $upgrade=new PloggerUpgrade($restored,'restore_',$root.'/restored');
            $plan=$upgrade->inspect();
            self::assertSame(0,$plan['schema_version']);
            $result=$upgrade->apply(['database'=>'verified native dump/client restore','files'=>'verified fixture originals/config copies','restore_tested'=>true],true,null,PloggerUpgrade::VERSION);
            self::assertSame(PloggerUpgrade::VERSION,$result['schema_version']);
            self::assertSame([],$upgrade->apply(['database'=>'verified native restore','files'=>'verified fixture file restore','restore_tested'=>true],true)['pending']);
            self::assertSame(hash_file('sha256',$root.'/'.$original),hash_file('sha256',$root.'/restored/'.$original));
            self::assertSame($before,$snapshot($db)); // Source database was never migrated.
            $upgrade=null;
        } finally {
            $db=$restored=null;
            foreach (array_reverse($created) as $database) plogger_db_execute($admin,'DROP DATABASE '.plogger_db_identifier($database));
            if (realpath(dirname($root))!==realpath(sys_get_temp_dir()) || !str_starts_with(basename($root),'plog-restore-')) throw new RuntimeException('Unsafe fixture cleanup.');
            $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $file) { if ($file->isDir()) rmdir($file->getPathname()); else unlink($file->getPathname()); }
            rmdir($root);
        }
    }
}
