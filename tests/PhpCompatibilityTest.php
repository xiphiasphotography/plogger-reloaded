<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PhpCompatibilityTest extends TestCase
{
    private int $previousErrorReporting;

    protected function setUp(): void
    {
        $this->previousErrorReporting = error_reporting(E_ALL);
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (error_reporting() & $severity) {
                throw new ErrorException($message, 0, $severity, $file, $line);
            }
            return false;
        });
    }

    protected function tearDown(): void
    {
        restore_error_handler();
        error_reporting($this->previousErrorReporting);
    }

    public function testStringReaderKeepsConstructorInput(): void
    {
        require_once dirname(__DIR__) . '/plog-includes/lib/gettext/streams.php';
        $reader = new StringReader('gallery');
        self::assertSame('gall', $reader->read(4));
        self::assertSame(7, $reader->length());
    }

    public function testStringReaderTreatsNullAsEmptyWithoutDeprecations(): void
    {
        require_once dirname(__DIR__) . '/plog-includes/lib/gettext/streams.php';
        $reader = new StringReader(null);
        self::assertSame('', $reader->read(4));
        self::assertSame(0, $reader->length());
    }

    public function testMissingTranslationFileCanBeSoughtAndClosed(): void
    {
        require_once dirname(__DIR__) . '/plog-includes/lib/gettext/streams.php';
        $reader = new FileReader(sys_get_temp_dir() . '/plog-missing-' . bin2hex(random_bytes(8)));
        self::assertSame(2, $reader->error);
        self::assertSame('', $reader->read(4));
        self::assertSame(0, $reader->seekto(0));
        self::assertSame(0, $reader->length());
        $reader->close();
        $reader->close();
    }

    public function testServerInformationHandlesMissingGdAndServerHeader(): void
    {
        $source = dirname(__DIR__) . '/plog-admin/plog-admin-functions.php';
        // -n provides an isolated CLI without GD or a web server header.
        $code = 'error_reporting(E_ALL); set_error_handler(function($severity, $message) { throw new Exception($message); });'
            . '$_SERVER["PHP_SELF"] = "fixture.php"; $_SESSION["plogger_logged_in"] = true;'
            . 'define("PLOGGER_DB_TYPE", "mysql"); if (!class_exists("PDO")) { class PDO { const ATTR_SERVER_VERSION = 4; } }'
            . '$PLOGGER_DBH = new class { function getAttribute($key) { return "fixture"; } };'
            . 'function plog_tr($text) { return $text; } function is_safe_mode() { return false; }'
            . 'require ' . var_export($source, true) . '; echo plogger_generate_server_info();';
        $process = proc_open([PHP_BINARY, '-n', '-r', $code], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $output);
        self::assertStringContainsString('Not installed', $output);
    }

    public function testGettextReadsSyntheticCatalogWithoutDynamicProperties(): void
    {
        require_once dirname(__DIR__) . '/plog-includes/lib/gettext/streams.php';
        require_once dirname(__DIR__) . '/plog-includes/lib/gettext/gettext.php';
        // One-entry little-endian MO catalog: original table, translation table, strings.
        $catalog = pack('V7', 0x950412de, 0, 1, 28, 36, 0, 0)
            . pack('V4', 5, 44, 5, 50) . "hello\0hallo\0";
        $reader = new gettext_reader(new StringReader($catalog));
        self::assertSame('hallo', $reader->translate('hello'));
        self::assertSame('missing', $reader->translate('missing'));
    }

    public function testZipUploadConstructsAndExtractsSyntheticArchive(): void
    {
        require_once dirname(__DIR__) . '/plog-includes/lib/pclzip-2-4/pclzip.lib.php';
        $path = tempnam(sys_get_temp_dir(), 'plog-zip-');
        self::assertNotFalse($path);
        $destination = $path . '-extract';
        mkdir($destination);
        try {
            $name = 'photo.txt';
            $data = 'synthetic fixture';
            $crc = crc32($data);
            $local = pack('VvvvvvVVVvv', 0x04034b50, 10, 0, 0, 0, 0, $crc,
                strlen($data), strlen($data), strlen($name), 0) . $name . $data;
            $central = pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 10, 10, 0, 0, 0, 0,
                $crc, strlen($data), strlen($data), strlen($name), 0, 0, 0, 0, 0, 0) . $name;
            $end = pack('VvvvvVVv', 0x06054b50, 0, 0, 1, 1, strlen($central), strlen($local), 0);
            file_put_contents($path, $local . $central . $end);
            $archive = new PclZip($path);
            self::assertSame($path, $archive->zipname);
            $files = $archive->extract(PCLZIP_OPT_PATH, $destination);
            self::assertIsArray($files);
            self::assertSame($data, file_get_contents($destination . '/' . $name));
            unlink($destination . '/' . $name);
            // The legacy option remains callable after removal of ereg().
            $files = $archive->extract(PCLZIP_OPT_PATH, $destination, PCLZIP_OPT_BY_EREG, '^photo[.]txt$');
            self::assertSame($data, file_get_contents($destination . '/' . $name));
        } finally {
            unlink($path);
            if (is_file($destination . '/photo.txt')) unlink($destination . '/photo.txt');
            rmdir($destination);
        }
    }

    public function testGpsReferenceHandlesShortAndPaddedStrings(): void
    {
        require_once dirname(__DIR__) . '/plog-includes/lib/exifer1_7/makers/gps.php';
        self::assertSame('N', formatGPSData('ASCII', '0001', 1, 'N'));
        self::assertSame('N', formatGPSData('ASCII', '0001', 1, "N\0\0\0"));
    }

    public function testPhpThumbRendersGdOutputWithoutSourceResource(): void
    {
        if (!extension_loaded('gd')) {
            self::markTestSkipped('GD is required for thumbnail regression coverage.');
        }
        require_once dirname(__DIR__) . '/plog-includes/lib/phpthumb/phpthumb.class.php';
        $thumb = new phpthumb();
        $thumb->gdimg_output = imagecreatetruecolor(2, 2);
        $thumb->gdimg_source = null;
        $thumb->thumbnailFormat = 'png';
        self::assertTrue($thumb->RenderOutput());
        self::assertStringStartsWith("\x89PNG", $thumb->outputImageData);
    }

    public function testObsoleteDemosAreAbsent(): void
    {
        self::assertDirectoryDoesNotExist(dirname(__DIR__) . '/plog-includes/lib/phpthumb/demo');
    }

    public function testMetadataFailureDoesNotRequireNativeGettext(): void
    {
        require_once dirname(__DIR__) . '/plog-includes/lib/exifer1_7/exif.php';
        $result = read_exif_data_raw(sys_get_temp_dir() . '/plog-missing-' . bin2hex(random_bytes(8)), false);
        self::assertSame(1, $result['Errors']);
    }

    public function testMalformedShortApp1MarkerReturnsMetadataError(): void
    {
        require_once dirname(__DIR__) . '/plog-includes/lib/exifer1_7/exif.php';
        $path = tempnam(sys_get_temp_dir(), 'plog-exif-');
        try {
            file_put_contents($path, "\xff\xd8\xff\xe1\x00\x03x\xff\xd9");
            $result = read_exif_data_raw($path, false);
            self::assertSame(1, $result['Errors']);
        } finally {
            unlink($path);
        }
    }

    public function testGifPalettePixelPlotterAcceptsPhp8StringOffsets(): void
    {
        if (!extension_loaded('gd')) self::markTestSkipped('GD required.');
        require_once dirname(__DIR__) . '/plog-includes/lib/phpthumb/phpthumb.functions.php';
        require_once dirname(__DIR__) . '/plog-includes/lib/phpthumb/phpthumb.gif.php';
        $path = tempnam(sys_get_temp_dir(), 'plog-gif-');
        try {
            $image = imagecreate(2, 2);
            imagecolorallocate($image, 255, 0, 0);
            imagegif($image, $path);
            $gif = new CGIF();
            self::assertTrue($gif->loadFile($path, 0));
            $width = $height = 0;
            self::assertTrue($gif->getSize($path, $width, $height));
            self::assertSame([2, 2], [$width, $height]);
            $rendered = $gif->getGD_PixelPlotterVersion();
            self::assertInstanceOf(GdImage::class, $rendered);
            self::assertSame(2, imagesx($rendered));
        } finally {
            unlink($path);
        }
    }
}
