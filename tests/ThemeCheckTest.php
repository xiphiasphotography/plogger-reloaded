<?php
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__).'/plog-includes/plog-theme-check.php';

final class ThemeCheckTest extends TestCase {
    public function testBundledThemesHaveNoSyntaxErrors(): void {
        foreach(['default','air','lucid','xip-bs','reloaded'] as $name) {
            $report=plogger_check_theme(dirname(__DIR__).'/plog-content/themes/'.$name);
            self::assertSame([],$report['errors'],$name);
            self::assertGreaterThan(0,$report['files']);
            foreach ($report['warnings'] as $warning) self::assertStringNotContainsString('plogger_get_picture_actions', $warning);
        }
    }
    public function testThemeCodeIsNeverExecutedAndUnknownCallsAreOnlyWarnings(): void {
        $root=sys_get_temp_dir().'/plog-theme-'.bin2hex(random_bytes(8)); mkdir($root);
        file_put_contents($root.'/gallery.php','<?php $GLOBALS["theme_was_executed"] = true; missing_fixture_function();');
        try {
            $report=plogger_check_theme($root);
            self::assertSame([],$report['errors']); self::assertCount(1,$report['warnings']);
            self::assertArrayNotHasKey('theme_was_executed',$GLOBALS);
            file_put_contents($root.'/gallery.php','<?php function broken( {');
            self::assertCount(1,plogger_check_theme($root)['errors']);
        } finally { unlink($root.'/gallery.php'); rmdir($root); }
    }
    public function testRemovedPhpFunctionsBlockThemeCompatibility(): void {
        $root=sys_get_temp_dir().'/plog-theme-'.bin2hex(random_bytes(8)); mkdir($root);
        file_put_contents($root.'/picture.php', '<?php each($items); create_function("", "return 1;");');
        try {
            $report=plogger_check_theme($root);
            self::assertCount(2,$report['errors']);
            self::assertStringContainsString('removed PHP function each', $report['errors'][0]);
        } finally { unlink($root.'/picture.php'); rmdir($root); }
    }
}
