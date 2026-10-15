<?php
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__).'/plog-includes/lib/gettext/streams.php';
require_once dirname(__DIR__).'/plog-includes/lib/gettext/gettext.php';

final class GettextCatalogTest extends TestCase {
    private function catalog(string $order): string {
        $originals = ['', "photo\0photos"];
        $translations = ["Content-Type: text/plain; charset=UTF-8\nPlural-Forms: nplurals=2; plural=(n != 1);\n", "foto\0foto’s"];
        $data = ''; $tables = '';
        foreach (array_merge($originals, $translations) as $text) {
            $tables .= pack($order.'2', strlen($text), 60 + strlen($data));
            $data .= $text."\0";
        }
        return pack($order.'7', 0x950412de, 0, 2, 28, 44, 0, 0).$tables.$data;
    }

    public function testBothByteOrdersPreserveUtf8PluralsAndFallbacks(): void {
        foreach (['V','N'] as $order) {
            $reader = new gettext_reader(new StringReader($this->catalog($order)));
            self::assertSame(0, $reader->error);
            self::assertSame('foto', $reader->ngettext('photo','photos',1));
            self::assertSame('foto’s', $reader->ngettext('photo','photos',2));
            self::assertSame('missing', $reader->translate('missing'));
        }
    }

    public function testMalformedCatalogsFallBackWithoutWarnings(): void {
        $catalog = $this->catalog('V');
        $samples = ['', 'not MO', substr($catalog,0,27), substr($catalog,0,-1),
            substr_replace($catalog, pack('V',0xffffffff),8,4),
            substr_replace($catalog, pack('V',0xffffffff),12,4),
            substr_replace($catalog, pack('V',0xffffffff),32,4)];
        foreach ($samples as $sample) {
            $reader = new gettext_reader(new StringReader($sample));
            self::assertSame(1, $reader->error);
            self::assertSame('source', $reader->translate('source'));
            self::assertSame('sources', $reader->ngettext('source','sources',2));
        }
    }

    public function testBundledCatalogsAreStillReadable(): void {
        self::assertDirectoryExists(dirname(__DIR__).'/plog-content/translations');
        foreach (glob(dirname(__DIR__).'/plog-content/translations/*.mo') as $file) {
            $stream = new FileReader($file);
            try { self::assertSame(0, (new gettext_reader($stream))->error, basename($file)); }
            finally { $stream->close(); }
        }
    }
}
