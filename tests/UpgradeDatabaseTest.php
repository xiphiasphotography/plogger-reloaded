<?php
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__).'/plog-includes/plog-upgrade.php';
require_once __DIR__.'/fixtures/upgrade-seed.php';

final class UpgradeDatabaseTest extends TestCase {
    public static function sources(): array { return [['rc1'],['php7'],['php8']]; }
    /** @dataProvider sources */
    public function testLegacyUpgradePreservesDataAndRestartsSafely(string $source): void {
        $driver=getenv('PLOGGER_TEST_DRIVER');
        if(!$driver) self::markTestSkipped('Native isolated database required.');
        $port=(int)getenv('PLOGGER_TEST_PORT');
        if(!in_array($driver,['mysql','pgsql'],true) || $port<20000) throw new RuntimeException('Unsafe fixture database target.');
        $name='plogger_phase4_ci_'.bin2hex(random_bytes(8));
        $admin=new PDO($driver.':host=127.0.0.1;port='.$port.($driver==='pgsql'?';dbname=postgres':''),getenv('PLOGGER_TEST_USER'),getenv('PLOGGER_TEST_PASSWORD')?:'',
            [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        if($driver==='mysql') plogger_db_execute($admin,"SET SESSION sql_mode = 'ANSI,STRICT_ALL_TABLES'");
        plogger_db_execute($admin,'CREATE DATABASE '.plogger_db_identifier($name));
        $root=sys_get_temp_dir().'/plog-upgrade-'.bin2hex(random_bytes(8)); mkdir($root,0700);
        mkdir($root.'/plog-content/themes/default',0755,true); mkdir($root.'/plog-content/images/fixture/album',0755,true);
        file_put_contents($root.'/plog-content/themes/default/gallery.php','<?php echo plogger_get_picture_caption();');
        $image=imagecreatetruecolor(8,8); imagejpeg($image,$root.'/plog-content/images/fixture/album/photo.jpg'); imagedestroy($image);
        $original=hash_file('sha256',$root.'/plog-content/images/fixture/album/photo.jpg');
        $db=null;
        try {
            $db=plogger_db_connect($driver,'127.0.0.1',$name,getenv('PLOGGER_TEST_USER'),getenv('PLOGGER_TEST_PASSWORD')?:'',$port);
            plogger_seed_upgrade_fixture($db,$driver,'case_',$source,'http://example.invalid/');
            plogger_db_execute($db,'INSERT INTO "case_comments" ("id","parent_id","author","email","url","date","comment","ip","approved") VALUES (1, 1, ?, ?, ?, ?, ?, ?, 1)',
                ['Fixture commenter','fixture@example.invalid','','2020-02-03 04:05:06',"Preserved comment\nSecond line",'127.0.0.1']);
            $settings=plogger_upgrade_config(__DIR__.'/fixtures/upgrades/'.$source.'-config.php');
            $settings['PLOGGER_DB_NAME']=$name; $settings['PLOGGER_DB_PORT']=$port;
            $settings['PLOGGER_DB_USER']=getenv('PLOGGER_TEST_USER');
            $settings['PLOGGER_DB_PW']=getenv('PLOGGER_TEST_PASSWORD')?:'';
            if($driver==='pgsql' || $source!=='rc1') $settings['PLOGGER_DB_TYPE']=$driver;
            $configSource=plogger_upgrade_proposal($settings);
            if($source==='rc1' && $driver==='mysql') $configSource=preg_replace('/^define\(\x27PLOGGER_DB_TYPE\x27[^\n]+\n/m','',$configSource);
            file_put_contents($root.'/plog-config.php',$configSource);
            if($source==='rc1') {
                $db->prepare('ALTER TABLE "case_config" '.($driver==='mysql'
                    ?'MODIFY COLUMN "admin_password" VARCHAR(32) NOT NULL':'ALTER COLUMN "admin_password" TYPE VARCHAR(32)'))->execute();
                $db->prepare('ALTER TABLE "case_config" DROP COLUMN "show_exif"')->execute();
            }
            $runCli=function(array $arguments) use($root): array {
                $process=proc_open(array_merge([PHP_BINARY,dirname(__DIR__).'/plog-admin/upgrade-cli.php','--config='.$root.'/plog-config.php'],$arguments),
                    [1=>['pipe','w'],2=>['pipe','w']],$pipes);
                $output=stream_get_contents($pipes[1]).stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
                return [proc_close($process),$output];
            };
            [$code,$output]=$runCli([]); self::assertSame(0,$code,$output);
            self::assertSame('dry-run',json_decode($output,true,512,JSON_THROW_ON_ERROR)['mode']);
            [$code,$output]=$runCli(['--apply','--confirm','--restore-tested','--backup-database=synthetic','--backup-files=synthetic']);
            self::assertSame(1,$code); self::assertStringContainsString('--dry-run first',$output);
            $before=[];
            foreach(['collections','albums','pictures','comments','thumbnail_config'] as $table) $before[$table]=plogger_db_execute($db,'SELECT * FROM "case_'.$table.'" ORDER BY "id"')->fetchAll();
            $configuration=plogger_db_execute($db,'SELECT * FROM "case_config"')->fetch();
            $upgrade=new PloggerUpgrade($db,'case_',$root); $plan=$upgrade->inspect();
            self::assertSame(0,$plan['schema_version']);
            self::assertSame($source==='rc1'?'original/RC1-compatible':'php7/php8-compatible',$plan['source']);
            self::assertArrayNotHasKey('schema_version',$upgrade->columns('config'));
            try { $upgrade->apply([],false); self::fail('Backup gate bypassed'); }
            catch(RuntimeException $error) { self::assertStringContainsString('backup',$error->getMessage()); }
            self::assertArrayNotHasKey('schema_version',$upgrade->columns('config'));
            $backup=['database'=>'synthetic external dump verified','files'=>'synthetic originals/config backup verified','restore_tested'=>true];
            foreach([array_replace($backup,['database'=>[]]),array_replace($backup,['files'=>'']),array_replace($backup,['restore_tested'=>false])] as $invalidBackup) {
                try { $upgrade->apply($invalidBackup,true); self::fail('Malformed backup gate accepted'); }
                catch(RuntimeException $error) { self::assertStringContainsString('backup',$error->getMessage()); }
            }
            $database=$upgrade->query($driver==='mysql'?'SELECT DATABASE()':'SELECT current_database() || current_schema()')->fetchColumn();
            $lock='plogger-upgrade-'.substr(hash('sha256',$database.'|case_'),0,32);
            $blocker=plogger_db_connect($driver,'127.0.0.1',$name,getenv('PLOGGER_TEST_USER'),getenv('PLOGGER_TEST_PASSWORD')?:'',$port);
            plogger_db_execute($blocker,$driver==='mysql'?'SELECT GET_LOCK(?,0)':'SELECT pg_try_advisory_lock(hashtext(?))',[$lock]);
            try { $upgrade->apply($backup,true); self::fail('Concurrent upgrade lock bypassed'); }
            catch(RuntimeException $error) { self::assertStringContainsString('Another upgrade',$error->getMessage()); }
            plogger_db_execute($blocker,$driver==='mysql'?'SELECT RELEASE_LOCK(?)':'SELECT pg_advisory_unlock(hashtext(?))',[$lock]); $blocker=null;
            // Model interruption after DDL but before version bookkeeping.
            $upgrade->ensureTracking();
            self::assertSame(0,$upgrade->version());
            $report=$upgrade->apply($backup,true,null,1);
            self::assertSame(1,$report['schema_version']);
            $report=$upgrade->apply($backup,true,null,2);
            self::assertSame(3,$report['schema_version']);
            // Existing Reloaded v3 and interruption after the new additive DDL both resume.
            $upgrade->ensurePhotographActions();
            $upgrade->ensurePhotographActions();
            $report=$upgrade->inspect();
            self::assertSame(3,$report['schema_version']);
            [$code,$output]=$runCli(['--apply','--plan='.PloggerUpgrade::planHash($report),'--backup-database=synthetic-external-dump','--backup-files=synthetic-originals-config',
                '--restore-tested','--confirm','--propose-config']); self::assertSame(0,$code,$output);
            $report=json_decode($output,true,512,JSON_THROW_ON_ERROR);
            self::assertSame(4,$report['schema_version']);
            if($source==='rc1') self::assertSame(1,(int)$upgrade->config()['show_exif']);
            self::assertSame($configSource,file_get_contents($root.'/plog-config.php'));
            self::assertFileExists($root.'/plog-config.php.proposed.php');
            self::assertSame([],$report['missing_indexes']);
            self::assertSame([],$upgrade->apply($backup,true)['pending']);
            self::assertSame(4,(int)$upgrade->query('SELECT COUNT(*) FROM "case_schema_migrations"')->fetchColumn());
            foreach($before as $table=>$rows) {
                $after = $upgrade->query('SELECT * FROM "case_'.$table.'" ORDER BY "id"')->fetchAll();
                foreach ($after as &$row) {
                    foreach (['oypo_album_id','original_width','original_height'] as $column) {
                        if (array_key_exists($column, $row)) { self::assertNull($row[$column]); unset($row[$column]); }
                    }
                }
                unset($row);
                self::assertSame($rows, $after);
            }
            foreach($configuration as $key=>$value) self::assertSame($value,$upgrade->config()[$key],$key);
            self::assertTrue(plogger_password_verify('fixture-password',$upgrade->config()['admin_password']));
            self::assertSame($original,hash_file('sha256',$root.'/plog-content/images/fixture/album/photo.jpg'));
            // A stale reviewed plan cannot authorize any further writes.
            try { $upgrade->apply($backup,true,PloggerUpgrade::planHash($plan)); self::fail('Stale plan accepted'); }
            catch(RuntimeException $error) { self::assertStringContainsString('changed',$error->getMessage()); }
            $upgrade->query('ALTER TABLE "case_albums" DROP COLUMN "name"');
            try { $upgrade->inspect(); self::fail('Incomplete schema accepted'); }
            catch(RuntimeException $error) { self::assertStringContainsString('missing albums.name',$error->getMessage()); }
        } finally {
            $blocker=null; $upgrade=null; $db=null;
            plogger_db_execute($admin,'DROP DATABASE '.plogger_db_identifier($name));
            if(realpath(dirname($root))!==realpath(sys_get_temp_dir()) || !str_starts_with(basename($root),'plog-upgrade-')) throw new RuntimeException('Unsafe fixture cleanup path.');
            $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
            foreach($iterator as $file) { if($file->isDir()) rmdir($file->getPathname()); else unlink($file->getPathname()); }
            rmdir($root);
        }
    }
}
