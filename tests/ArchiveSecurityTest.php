<?php
use PHPUnit\Framework\TestCase;

final class ArchiveSecurityTest extends TestCase {
    private function archive(string $name, string $data, ?int $declaredSize = null): string {
        $path = tempnam(sys_get_temp_dir(), 'plog-archive-');
        $size = $declaredSize ?? strlen($data);
        $crc = crc32($data);
        $local = pack('VvvvvvVVVvv', 0x04034b50, 10, 0, 0, 0, 0, $crc, strlen($data), $size, strlen($name), 0).$name.$data;
        $central = pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 10, 10, 0, 0, 0, 0, $crc,
            strlen($data), $size, strlen($name), 0, 0, 0, 0, 0, 0).$name;
        file_put_contents($path, $local.$central.pack('VvvvvVVv', 0x06054b50, 0, 0, 1, 1, strlen($central), strlen($local), 0));
        return $path;
    }

    public function testArchiveTraversalExecutablesAndBombsAreRejected(): void {
        require_once dirname(__DIR__).'/plog-includes/lib/pclzip-2-4/pclzip.lib.php';
        foreach (['../fixture.jpg', '/fixture.jpg', 'folder/../../fixture.jpg', 'folder\\fixture.jpg', '.htaccess', 'fixture.php', 'fixture.phtml'] as $name) {
            $path = $this->archive($name, 'harmless fixture');
            try { self::assertFalse((new PclZip($path))->ploggerValidateArchive(true), $name); }
            finally { unlink($path); }
        }
        $path = $this->archive('fixture.jpg', 'small', 33554433);
        try { self::assertFalse((new PclZip($path))->ploggerValidateArchive(true)); }
        finally { unlink($path); }
    }

    public function testOnlyDecodedImagesArePublished(): void {
        if (!extension_loaded('fileinfo') || !extension_loaded('gd')) self::markTestSkipped('GD and fileinfo required.');
        $uploads = sys_get_temp_dir().'/plog-archive-images-'.bin2hex(random_bytes(8));
        mkdir($uploads, 0700);
        $image = imagecreatetruecolor(8, 8);
        ob_start(); imagejpeg($image); $bytes = ob_get_clean(); imagedestroy($image);
        $path = $this->archive('nested/café photo.jpg', $bytes);
        $invalid = $this->archive('fake.jpg', '<?php /* harmless fixture */ ?>');
        $destination = null;
        try {
            self::assertNull(plogger_extract_image_archive($invalid, $uploads));
            self::assertSame([], glob($uploads.'/*'));
            $destination = plogger_extract_image_archive($path, $uploads);
            self::assertNotNull($destination);
            self::assertSame(hash('sha256', $bytes), hash_file('sha256', $destination.'/café photo.jpg'));
        } finally {
            unlink($path); unlink($invalid);
            if ($destination) { foreach (glob($destination.'/*') as $file) unlink($file); rmdir($destination); }
            rmdir($uploads);
        }
    }

    public function testNoPhpEvaluationTokensRemainInBundledLibraries(): void {
        $directory = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__).'/plog-includes/lib'));
        foreach ($directory as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') continue;
            $evaluations = array_filter(token_get_all(file_get_contents($file->getPathname())),
                static fn ($token) => is_array($token) && $token[0] === T_EVAL);
            self::assertSame([], $evaluations, $file->getPathname());
        }
    }
}
