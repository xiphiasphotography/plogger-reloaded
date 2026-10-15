<?php
use PHPUnit\Framework\TestCase;

final class SecurityInputTest extends TestCase {
    public function testPathsAndHtmlContexts(): void {
        foreach (['../image.jpg', '/image.jpg', 'a/../image.jpg', 'a\\image.jpg', "a\0.jpg", 'https:evil', []] as $path) {
            self::assertFalse(plogger_safe_relative_path($path));
        }
        self::assertTrue(plogger_safe_relative_path('album/café image.jpg'));
        self::assertSame('&quot;&lt;img onerror=&#039;x&#039;&gt;&amp;', plogger_escape('"<img onerror=\'x\'>&'));
        self::assertSame('', plogger_public_link('javascript:alert(1)'));
        self::assertSame('', plogger_public_link('https://user:secret@example.invalid/'));
        self::assertSame('https://example.invalid/?a=1&b=2', plogger_public_link('https://example.invalid/?a=1&b=2'));
        self::assertFalse(plogger_image_budget(20001, 1, 1));
        self::assertFalse(plogger_image_budget(6000, 4000, 1));
        self::assertTrue(plogger_image_budget(8, 8, 100));
        self::assertSame('', plogger_input(['caption' => []], 'caption'));
        self::assertStringNotContainsString('</script>', plogger_script_json("</script><img onerror='fixture'>"));
        self::assertSame('/gallery/?level=album&id=1', plogger_local_redirect('/gallery/?level=album&id=1', 'https://example.invalid/gallery/', '/fallback'));
        foreach (['//evil.invalid/', 'https://evil.invalid/', "https://example.invalid/\r\nInjected: value", 'javascript:fixture()', []] as $target) {
            self::assertSame('/fallback', plogger_local_redirect($target, 'https://example.invalid/gallery/', '/fallback'));
        }
    }

    public function testForgedExtensionsAndInvalidImages(): void {
        $file = tempnam(sys_get_temp_dir(), 'plog-security-');
        try {
            file_put_contents($file, '<?php echo "fixture"; ?>');
            self::assertFalse(plogger_validate_image($file, 'fixture.jpg'));
            if (!class_exists('finfo') || !function_exists('imagejpeg')) {
                self::markTestSkipped('GD and fileinfo required for image validation.');
            }
            $image = imagecreatetruecolor(8, 8);
            imagejpeg($image, $file);
            imagedestroy($image);
            self::assertTrue(plogger_validate_image($file, 'café image.jpg'));
            self::assertFalse(plogger_validate_image($file, 'fixture.png'));
            self::assertFalse(plogger_validate_image($file, 'fixture.php'));
            self::assertFalse(plogger_validate_image($file, 'fixture.php.jpg'));
            self::assertFalse(plogger_validate_image($file, 'fixture.PHP8.jpg'));
            self::assertFalse(plogger_validate_image($file, 'fixture.ph"p.jpg'));
            self::assertFalse(plogger_safe_image_filename('fixture.phtml.jpg'));
            self::assertFalse(plogger_validate_image($file, '../fixture.jpg'));
            self::assertFalse(plogger_path_within($file, __DIR__));
            self::assertTrue(plogger_path_within($file, sys_get_temp_dir()));
        } finally { unlink($file); }
    }
}
