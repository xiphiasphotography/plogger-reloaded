<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class FileDiscoveryTest extends TestCase
{
    public function testImportScanSkipsExtensionlessFilesWithoutWarnings(): void
    {
        require_once dirname(__DIR__) . '/plog-admin/plog-admin-functions.php';
        $directory = sys_get_temp_dir() . '/plog-import-' . bin2hex(random_bytes(8));
        mkdir($directory);
        file_put_contents($directory . '/README', 'synthetic text');
        file_put_contents($directory . '/café photo.jpg', 'synthetic name fixture');
        set_error_handler(static function (int $severity, string $message): bool {
            if (error_reporting() & $severity) throw new ErrorException($message, 0, $severity);
            return false;
        });
        try {
            self::assertSame([$directory . '/café photo.jpg'], get_files($directory));
            self::assertCount(2, get_files($directory, true));
        } finally {
            restore_error_handler();
            unlink($directory . '/README');
            unlink($directory . '/café photo.jpg');
            rmdir($directory);
        }
    }
}
