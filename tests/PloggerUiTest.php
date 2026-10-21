<?php
use PHPUnit\Framework\TestCase;

final class PloggerUiTest extends TestCase {
    public function testStylesAreOptInSeparatedAndSafeForSubdirectoryCdn(): void {
        self::assertTrue(function_exists('plogger_ui_styles'));
        $previous = $GLOBALS['config'] ?? null;
        $script = $_SERVER['SCRIPT_FILENAME'] ?? null;
        try {
            $GLOBALS['config'] = ['gallery_url'=>'https://gallery.example.test/photos/', 'cdn_url'=>'https://cdn.example.test/mirror/'];
            $_SERVER['SCRIPT_FILENAME'] = '/site/index.php';
            $public = plogger_ui_styles('gallery');
            self::assertStringContainsString('https://cdn.example.test/mirror/plog-content/ui/css/gallery.css', $public);
            self::assertStringNotContainsString('admin.css', $public);
            self::assertStringNotContainsString('cdn.example.test', plogger_ui_styles('admin'));
            $_SERVER['SCRIPT_FILENAME'] = '/site/plog-admin/plog-options.php';
            $admin = plogger_ui_styles('admin');
            self::assertStringContainsString('https://gallery.example.test/photos/plog-content/ui/css/admin.css', $admin);
            self::assertStringNotContainsString('cdn.example.test', $admin);
            self::assertStringNotContainsString('gallery.css', $admin);
            $GLOBALS['config']['cdn_url'] = '';
            $_SERVER['SCRIPT_FILENAME'] = '/site/index.php';
            self::assertStringContainsString('/photos/plog-content/ui/css/tokens.css', plogger_ui_styles('gallery'));
            self::assertSame('#', plogger_asset_url('plog-content/ui/private.php'));
        } finally {
            $GLOBALS['config'] = $previous;
            if ($script === null) unset($_SERVER['SCRIPT_FILENAME']); else $_SERVER['SCRIPT_FILENAME'] = $script;
        }
    }

    public function testUiFilesStayScopedAndWithinDocumentedBudget(): void {
        $root = dirname(__DIR__).'/plog-content/ui/css/';
        $total = 0;
        foreach (['tokens','base','layout','components','gallery','admin'] as $name) {
            self::assertFileExists($root.$name.'.css');
            $css = file_get_contents($root.$name.'.css');
            self::assertStringNotContainsString('@import', $css);
            self::assertStringNotContainsString('https://', $css);
            self::assertStringContainsString('.plog-ui', $css);
            self::assertDoesNotMatchRegularExpression('/(?:^|})\s*(?:body|html|:root)\s*\{/m', $css);
            if ($name !== 'admin') $total += strlen(gzencode($css, 9));
        }
        self::assertLessThan(11264, $total);
        $theme = file_get_contents(dirname(__DIR__).'/plog-content/themes/reloaded/theme.css');
        self::assertLessThan(11264, $total + strlen(gzencode($theme, 9)));
        $css = file_get_contents($root.'base.css');
        self::assertStringContainsString(':focus-visible', $css);
        self::assertStringContainsString('prefers-reduced-motion', $css);
        self::assertStringContainsString('auto-fit', file_get_contents($root.'gallery.css'));
    }
}
