<?php
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__).'/plog-includes/plog-metadata.php';
require_once dirname(__DIR__).'/plog-includes/plog-import.php';

final class ImportMetadataTest extends TestCase {
    public function testSeparatePrioritiesAndExplicitBlankEdits(): void {
        $metadata = ['IPTC'=>['2#005'=>['Title é'], '2#120'=>["Description\nsecond line"]],
            'IFD0'=>['ImageDescription'=>'EXIF fallback']];
        $fields = plogger_metadata_fields($metadata);
        self::assertSame('Title é', $fields['caption']);
        self::assertSame("Description\nsecond line", $fields['description']);
        self::assertSame('IPTC.2#005', $fields['caption_source']);
        $edited = plogger_metadata_fields($metadata, '', "  Manual é\n\n");
        self::assertSame('', $edited['caption']);
        self::assertSame("  Manual é\n\n", $edited['description']);
        self::assertSame('manual', $edited['caption_source']);
        self::assertSame('EXIF fallback', plogger_metadata_fields($metadata, null, null,
            ['caption'=>['IFD0.ImageDescription'], 'description'=>[]])['caption']);
        self::assertSame('', plogger_metadata_fields([])['description']);
        self::assertSame('Second', plogger_metadata_fields(['IPTC'=>['2#005'=>['', 'Second']]])['caption']);
        $this->expectException(InvalidArgumentException::class);
        plogger_metadata_fields([], null, null, ['caption'=>['unknown']]);
    }

    public function testXmpNamespacesLanguageAlternativesAndUnsafeXml(): void {
        $xml = '<x:xmpmeta xmlns:x="adobe:ns:meta/"><r:RDF xmlns:r="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
            .'<r:Description xmlns:d="http://purl.org/dc/elements/1.1/"><d:title><r:Alt>'
            .'<r:li xml:lang="nl">Nederlands</r:li><r:li xml:lang="x-default">Title &amp; é</r:li>'
            .'</r:Alt></d:title><d:description><r:Seq><r:li>First&#10;Second</r:li></r:Seq>'
            .'</d:description></r:Description></r:RDF></x:xmpmeta>';
        self::assertSame(['dc:title'=>'Title & é', 'dc:description'=>"First\nSecond"], plogger_metadata_xmp($xml));
        foreach (['<!DOCTYPE x [<!ENTITY a SYSTEM "file:///not-readable">]><x>&a;</x>',
            "\xff\xfe<\0!\0D\0O\0C\0T\0Y\0P\0E\0", '<broken>', str_repeat('x', 65537)] as $bad) {
            self::assertSame([], plogger_metadata_xmp($bad));
        }
    }

    public function testBoundedDiscoveryCachingAndStaleSourceRejection(): void {
        $root = sys_get_temp_dir().'/plog-import-'.bin2hex(random_bytes(8));
        mkdir($root); mkdir($root.'/nested');
        $_SESSION = [];
        try {
            for ($i = 0; $i < 25; ++$i) file_put_contents($root.'/nested/'.$i.'.jpg', 'not an image');
            file_put_contents($root.'/.hidden.jpg', 'hidden');
            file_put_contents($root.'/private.txt', 'not a photo');
            $first = plogger_import_scan($root, [], 7);
            self::assertCount(7, $first['files']);
            self::assertNotEmpty($first['pending']);
            $files = $first['files']; $state = $first['pending'];
            for ($page = 0; $state && $page < 10; ++$page) {
                $batch = plogger_import_scan($root, $state, 7);
                self::assertLessThanOrEqual(7, count($batch['files']));
                self::assertLessThanOrEqual(1000, $batch['examined']);
                $files = array_merge($files, $batch['files']); $state = $batch['pending'];
            }
            self::assertCount(25, array_unique($files));
            self::assertCount(25, $files);
            $path = $files[0]; $key = md5($path);
            $preview = plogger_import_preview($path, $root);
            self::assertTrue($preview['warning']);
            self::assertSame($preview, plogger_import_preview($path, $root));
            self::assertSame($path, plogger_import_source($key, $root));
            file_put_contents($path, 'changed bytes');
            self::assertNull(plogger_import_source($key, $root));
            self::assertNull(plogger_import_source('unknown', $root));
            self::assertNull(plogger_import_source($key, dirname($root).'/different-root'));
            // An expired session entry cannot authorize a file move.
            plogger_import_preview($path, $root);
            $_SESSION['plogger_import_files'][$key]['expires'] = 0;
            self::assertNull(plogger_import_source($key, $root));
        } finally {
            foreach (glob($root.'/nested/*') as $path) unlink($path);
            rmdir($root.'/nested'); unlink($root.'/.hidden.jpg'); unlink($root.'/private.txt'); rmdir($root);
            $_SESSION = [];
        }
    }

    public function testEmbeddedXmpAndIptcUseTheSamePreviewAndImportPolicy(): void {
        require_once __DIR__.'/MetadataTest.php';
        $root = sys_get_temp_dir().'/plog-import-meta-'.bin2hex(random_bytes(8)); mkdir($root);
        $path = $root.'/photo é.jpg';
        $xml = '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns:dc="http://purl.org/dc/elements/1.1/">'
            .'<rdf:Description><dc:title>XMP title</dc:title><dc:description>Line 1&#10;Line é 2</dc:description></rdf:Description></rdf:RDF>';
        $packet = "http://ns.adobe.com/xap/1.0/\0".$xml;
        $jpeg = MetadataTest::jpeg();
        file_put_contents($path, substr($jpeg, 0, 2)."\xff\xe1".pack('n', strlen($packet)+2).$packet.substr($jpeg, 2));
        $_SESSION = [];
        try {
            $original = hash_file('sha256', $path);
            $raw = plogger_read_metadata($path);
            $preview = plogger_import_preview(str_replace('\\', '/', $path), $root);
            self::assertSame(plogger_metadata_fields($raw), $preview['fields']);
            self::assertSame('XMP title', $preview['fields']['caption']);
            self::assertSame("Line 1\nLine é 2", $preview['fields']['description']);
            self::assertSame(['width'=>80, 'height'=>40], $preview['dimensions']);
            self::assertSame('Fixture Camera', $preview['camera']);
            self::assertSame('2026:10:15 12:34:56', $preview['date']);
            $text = "Caf\xe9\nIPTC description";
            $title = '" onfocus="fixture </textarea><script>fixture</script>';
            $iptc = "\x1c\x02\x05".pack('n', strlen($title)).$title."\x1c\x02\x78".pack('n', strlen($text)).$text;
            $bytes = iptcembed($iptc, $path); file_put_contents($path, $bytes);
            $preview = plogger_import_preview(str_replace('\\', '/', $path), $root);
            self::assertSame($title, $preview['fields']['caption']);
            self::assertSame("Café\nIPTC description", $preview['fields']['description']);
            self::assertSame('IPTC.2#005', $preview['fields']['caption_source']);
            self::assertStringNotContainsString('<script>', plogger_escape($title));
            // Imported source text remains intact, while existing theme rendering stays inert.
            $GLOBALS['current_picture']['description'] = $title."\n<img src=x onerror=fixture>";
            $html = plogger_get_picture_description();
            $dom = new DOMDocument(); @$dom->loadHTML($html); $xpath = new DOMXPath($dom);
            self::assertSame(0, $xpath->query('//script | //img | //*[@onerror or @onfocus]')->length);
            self::assertStringContainsString('<br', $html);
            unset($GLOBALS['current_picture']);
            self::assertSame(hash('sha256', $bytes), hash_file('sha256', $path));
            self::assertNotSame($original, hash_file('sha256', $path)); // Fixture construction only.
        } finally { unlink($path); rmdir($root); $_SESSION = []; }
    }

    public function testLargeDirectoryDiscoveryYieldsBeforeReadingMetadata(): void {
        self::assertSame([], plogger_import_scan('')['files']);
        $root = sys_get_temp_dir().'/plog-import-large-'.bin2hex(random_bytes(8)); mkdir($root);
        try {
            for ($i=0; $i<1005; ++$i) file_put_contents($root.'/'.sprintf('%04d', $i).'.txt', '');
            $page = plogger_import_scan($root);
            self::assertSame([], $page['files']);
            self::assertLessThanOrEqual(1000, $page['examined']);
            self::assertNotEmpty($page['pending']);
            $next = plogger_import_scan($root, $page['pending']);
            self::assertSame([], $next['files']);
            self::assertSame([], $next['pending']);
            self::assertSame(1005, $page['examined']+$next['examined']);
        } finally { foreach (glob($root.'/*') as $path) unlink($path); rmdir($root); }
    }

    public function testMalformedXmpKeepsExifAndAllowsManualFields(): void {
        require_once __DIR__.'/MetadataTest.php';
        $path = tempnam(sys_get_temp_dir(), 'plog-bad-xmp-');
        $packet = "http://ns.adobe.com/xap/1.0/\0".'<!DOCTYPE x [<!ENTITY a SYSTEM "file:///not-readable">]><x>&a;</x>';
        $jpeg = MetadataTest::jpeg();
        $bytes = substr($jpeg,0,2)."\xff\xe1".pack('n',strlen($packet)+2).$packet.substr($jpeg,2);
        file_put_contents($path,$bytes);
        try {
            $metadata = plogger_read_metadata($path);
            self::assertSame([], $metadata['XMP']);
            self::assertNotEmpty($metadata['warnings']);
            self::assertSame(0, $metadata['Errors']);
            self::assertSame('Fixture Camera',$metadata['IFD0']['Model']);
            $fields = plogger_metadata_fields($metadata, 'Manual é', "Line 1\nLine 2");
            self::assertSame('Manual é',$fields['caption']);
            self::assertSame("Line 1\nLine 2",$fields['description']);
            self::assertSame(hash('sha256',$bytes),hash_file('sha256',$path));
        } finally { unlink($path); }
    }
}
