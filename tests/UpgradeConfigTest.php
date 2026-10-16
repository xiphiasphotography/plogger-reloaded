<?php
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__).'/plog-includes/plog-upgrade.php';

final class UpgradeConfigTest extends TestCase {
    private function config(string $extra=''): string {
        return "<?php\n/* synthetic */\ndefine('PLOGGER_DB_HOST', '127.0.0.1');\ndefine('PLOGGER_DB_USER', 'fixture');\n"
            ."define('PLOGGER_DB_PW', ".var_export("quo'te\\slash",true).");\ndefine('PLOGGER_DB_NAME', 'fixture');\n".$extra;
    }
    public function testLiteralLegacyConfigIsReadWithoutExecutionAndProposalIsIdempotent(): void {
        $path=tempnam(sys_get_temp_dir(),'plog-config-test-');
        $source=$this->config("define('PLOGGER_CHMOD_DIR', 0755);\n"); file_put_contents($path,$source);
        try {
            $values=plogger_upgrade_config($path);
            self::assertSame("quo'te\\slash",$values['PLOGGER_DB_PW']);
            self::assertSame(0755,$values['PLOGGER_CHMOD_DIR']);
            plogger_upgrade_write_proposal($path,$values); plogger_upgrade_write_proposal($path,$values);
            self::assertSame('mysql',plogger_upgrade_config($path.'.proposed.php')['PLOGGER_DB_TYPE']);
            self::assertSame($source,file_get_contents($path));
        } finally { unlink($path); if(is_file($path.'.proposed.php')) unlink($path.'.proposed.php'); }
    }
    public function testDynamicPhpIsRejectedWithoutSideEffects(): void {
        $path=tempnam(sys_get_temp_dir(),'plog-config-test-');
        file_put_contents($path,$this->config('$GLOBALS["upgrade_injected"] = true;'));
        try {
            try { plogger_upgrade_config($path); self::fail('Dynamic PHP was accepted'); }
            catch (RuntimeException $error) { self::assertArrayNotHasKey('upgrade_injected',$GLOBALS); }
        } finally { unlink($path); }
    }
    public function testMissingTokenizerHasAnActionableDiagnostic(): void {
        $code='require '.var_export(dirname(__DIR__).'/plog-includes/plog-upgrade-config.php',true).';'
            .'try { plogger_upgrade_config("unused"); exit(2); } catch (RuntimeException $error) { echo $error->getMessage(); }';
        $process=proc_open([PHP_BINARY,'-d','disable_functions=token_get_all','-r',$code],
            [1=>['pipe','w'],2=>['pipe','w']],$pipes);
        $output=stream_get_contents($pipes[1]).stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
        self::assertSame(0,proc_close($process),$output);
        self::assertStringContainsString('Enable the PHP tokenizer extension',$output);
    }
    public function testMigrationChecksumsSurviveGitLineEndingConversion(): void {
        $path=tempnam(sys_get_temp_dir(),'plog-migration-checksum-');
        try {
            $source="<?php\nreturn [];\n"; file_put_contents($path,$source);
            $expected=PloggerUpgrade::migrationChecksum($path);
            file_put_contents($path,str_replace("\n","\r\n",$source));
            self::assertSame($expected,PloggerUpgrade::migrationChecksum($path));
        } finally { unlink($path); }
    }
}
