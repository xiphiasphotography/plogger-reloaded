<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PhpCompatibilityTest extends TestCase
{
    public function testRecursiveImportListingPreservesBothLegacyReturnShapes(): void {
        require_once dirname(__DIR__).'/plog-admin/plog-admin-functions.php';
        $root=sys_get_temp_dir().'/plog-list-fixture-'.bin2hex(random_bytes(8));
        mkdir($root); mkdir($root.'/nested');
        file_put_contents($root.'/one.jpg','synthetic listing only');
        file_put_contents($root.'/nested/two.jpg','synthetic listing only');
        try {
            $files=get_files($root,true,false);
            self::assertSame([$root.'/nested/two.jpg',$root.'/one.jpg'],$files);
            $both=get_files($root,true,true);
            self::assertSame($files,$both['files']);
            self::assertContains($root.'/nested',$both['folders']);
            self::assertNull(get_files($root.'/missing',true,false));
        } finally {
            unlink($root.'/one.jpg'); unlink($root.'/nested/two.jpg');
            rmdir($root.'/nested'); rmdir($root);
        }
    }
    public function testInstallerConfigTreatsAllConnectionValuesAsData(): void {
        if (!defined('PLOGGER_DIR')) define('PLOGGER_DIR',dirname(__DIR__).'/');
        require_once dirname(__DIR__).'/plog-admin/includes/install-functions.php';
        $values=["host'\\name", "user'\\name", "password'\\value", "database'\\name", "mysql'\\value", "3306'\\value"];
        $config=create_config_file(...$values);
        $path=tempnam(sys_get_temp_dir(),'plog-config-fixture-');
        try {
            file_put_contents($path,$config);
            $code='require '.var_export($path,true).'; echo json_encode([PLOGGER_DB_HOST,PLOGGER_DB_USER,PLOGGER_DB_PW,PLOGGER_DB_NAME,PLOGGER_DB_TYPE,PLOGGER_DB_PORT]);';
            $process=proc_open([PHP_BINARY,'-r',$code],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
            $output=stream_get_contents($pipes[1]); $error=stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            self::assertSame(0,proc_close($process),$error);
            self::assertSame($values,json_decode($output,true,512,JSON_THROW_ON_ERROR));
        } finally { unlink($path); }
    }
    public function testPhpThumbUnsetCacheDirectoryOnUnixDoesNotPassNullToFilesystemApis(): void {
        require_once dirname(__DIR__).'/plog-includes/lib/phpthumb/phpthumb.class.php';
        $thumb=new phpthumb();
        $thumb->iswindows=false;
        $thumb->config_cache_directory=null;
        $thumb->config_cache_disable_warning=true;
        $thumb->config_temp_directory=sys_get_temp_dir();
        $thumb->setCacheDirectory();
        self::assertNull($thumb->config_cache_directory);
        self::assertSame(sys_get_temp_dir(),$thumb->config_temp_directory);
    }
    public function testMalformedPrettyPathsRejectMissingParentsBeforeQuerying(): void {
        self::assertSame(['level'=>'404'],resolve_path('/album'));
        self::assertSame(['level'=>'404'],resolve_path('//picture'));
        self::assertSame(['level'=>'collections','id'=>0,'plog_page'=>2],resolve_path('page/2'));
        self::assertSame(['level'=>'search','searchterms'=>'café'],resolve_path('search/caf%C3%A9'));
    }
    public function testBootstrapRespectsHostResourceBudgets(): void {
        $code='require '.var_export(dirname(__DIR__).'/plog-globals.php',true).';'
            .'echo json_encode([ini_get("max_execution_time"),ini_get("memory_limit")]); session_destroy();';
        $process=proc_open([PHP_BINARY,'-d','max_execution_time=60','-d','memory_limit=64M','-r',$code],
            [1=>['pipe','w'],2=>['pipe','w']],$pipes);
        $output=stream_get_contents($pipes[1]); $error=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
        self::assertSame(0,proc_close($process),$error);
        self::assertSame(['60','64M'],json_decode($output,true,512,JSON_THROW_ON_ERROR));
    }
    public function testTagParserPreservesQuotedUtf8PhrasesAndNormalizesWhitespace(): void {
        if (!defined('PLOGGER_TABLE_PREFIX')) define('PLOGGER_TABLE_PREFIX', 'fixture_');
        require_once dirname(__DIR__).'/plog-includes/plog-tag-functions.php';
        self::assertSame(['café photo','alpha','beta'], parse_tags("alpha  \"café   photo\"\t beta"));
        self::assertSame(['two words','one','three'], parse_tags("one 'two words'\n three"));
    }
    public function testPhpThumbRejectsNullBeforeArrayCacheAccess(): void {
        require_once dirname(__DIR__).'/plog-includes/lib/phpthumb/phpthumb.class.php';
        $thumb = new phpThumb();
        self::assertFalse($thumb->file_exists_ignoreopenbasedir(null));
        self::assertTrue($thumb->file_exists_ignoreopenbasedir(__FILE__));
    }
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

    public function testMissingNativeExtensionsHaveActionableCliDiagnostics(): void
    {
        $process = proc_open([PHP_BINARY, '-n', dirname(__DIR__).'/tools/check-extensions.php'],
            [1 => ['pipe','w'], 2 => ['pipe','w']], $pipes);
        self::assertIsResource($process);
        $output=stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        self::assertSame(1,proc_close($process),$output);
        self::assertStringContainsString('exif: MISSING; enable ext-exif in php.ini',$output);
        self::assertStringContainsString('zip:',$output);
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



    public function testPhpThumbObjectApiRemainsCompatible(): void
    {
        require_once dirname(__DIR__) . '/plog-includes/lib/phpthumb/phpthumb.class.php';

        self::assertTrue(method_exists('phpThumb', 'setSourceFileName'));
        self::assertTrue(method_exists('phpThumb', 'GenerateThumbnail'));
        self::assertTrue(method_exists('phpThumb', 'RenderToFile'));
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
            self::assertTrue(gif_outputAsJpeg($gif, $path.'-output; fixture.jpg'));
            self::assertSame(IMAGETYPE_JPEG, getimagesize($path.'-output; fixture.jpg')[2]);
        } finally {
            unlink($path);
            if (is_file($path.'-output; fixture.jpg')) unlink($path.'-output; fixture.jpg');
        }
    }
}
