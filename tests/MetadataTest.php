<?php
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__).'/plog-includes/plog-metadata.php';

final class MetadataTest extends TestCase {
    /** Build synthetic TIFF directories inside a generated JPEG, never a real photograph. */
    public static function jpeg(int $orientation = 6): string {
        $tiff = "II".pack('vV', 42, 8);
        $directory = function (array $tags) use (&$directory, &$tiff): int {
            $offset = strlen($tiff);
            $tiff .= str_repeat("\0", 6 + count($tags) * 12);
            $entries = pack('v', count($tags));
            foreach ($tags as $tag => [$type, $count, $data]) {
                if ($type === 4 && is_array($data)) $data = pack('V', $directory($data));
                if (strlen($data) > 4) { $value = pack('V', strlen($tiff)); $tiff .= $data; }
                else $value = str_pad($data, 4, "\0");
                $entries .= pack('vvV', $tag, $type, $count).$value;
            }
            $entries .= pack('V', 0);
            $tiff = substr_replace($tiff, $entries, $offset, strlen($entries));
            return $offset;
        };
        $text = static fn (string $value): array => [2, strlen($value) + 1, $value."\0"];
        $directory([
            0x010e => $text("Harmless <script>fixture</script>\nSecond line"),
            0x010f => $text('Fixture'), 0x0110 => $text('Fixture Camera'),
            0x0112 => [3, 1, pack('v', $orientation)],
            0x8769 => [4, 1, [0x9003 => $text('2026:10:15 12:34:56'),
                0x829a => [5,1,pack('VV',1,125)], 0x829d => [5,1,pack('VV',28,10)],
                0x920a => [5,1,pack('VV',50,1)], 0x9209 => [3,1,pack('v',1)],
                0x8827 => [3,1,pack('v',200)], 0xa434 => $text('Fixture Lens')]],
            0x8825 => [4, 1, [1 => $text('N'), 2 => [5,3,pack('V6',52,1,30,1,0,1)],
                3 => $text('E'), 4 => [5,3,pack('V6',4,1,54,1,0,1)]]],
        ]);
        $image = imagecreatetruecolor(80, 40);
        imagefilledrectangle($image,0,0,39,19,imagecolorallocate($image,255,0,0));
        imagefilledrectangle($image,40,0,79,19,imagecolorallocate($image,0,255,0));
        imagefilledrectangle($image,0,20,39,39,imagecolorallocate($image,0,0,255));
        imagefilledrectangle($image,40,20,79,39,imagecolorallocate($image,255,255,0));
        ob_start(); imagejpeg($image); $jpeg = ob_get_clean(); unset($image);
        $app = "Exif\0\0".$tiff;
        return substr($jpeg, 0, 2)."\xff\xe1".pack('n', strlen($app)+2).$app.substr($jpeg, 2);
    }

    public function testNativeExifPreservesConsumedSectionsAndOriginalBytes(): void {
        $path = tempnam(sys_get_temp_dir(), 'plog-metadata-');
        $bytes = self::jpeg(); file_put_contents($path, $bytes);
        try {
            $result = plogger_read_metadata($path);
            self::assertSame('Fixture Camera', $result['IFD0']['Model']);
            self::assertSame('6', $result['IFD0']['Orientation']);
            self::assertSame('2026:10:15 12:34:56', $result['SubIFD']['DateTimeOriginal']);
            self::assertSame('1/125 sec', $result['SubIFD']['ExposureTime']);
            self::assertSame('f/2.8', $result['SubIFD']['FNumber']);
            self::assertSame('50 mm', $result['SubIFD']['FocalLength']);
            self::assertSame('Flash', $result['SubIFD']['Flash']);
            self::assertSame('200', $result['SubIFD']['ISOSpeedRatings']);
            self::assertSame('Fixture Lens', $result['SubIFD']['UndefinedTag:0xA434'] ?? $result['SubIFD']['LensModel']);
            self::assertSame('N', $result['GPS']['GPSLatitudeRef']);
            self::assertSame(['52/1','30/1','0/1'], $result['GPS']['GPSLatitude']);
            self::assertStringContainsString("<script>fixture</script>\nSecond line", $result['IFD0']['ImageDescription']);
            self::assertSame(hash('sha256', $bytes), hash_file('sha256', $path));
        } finally { unlink($path); }
    }

    public function testIptcIsSeparateAndTextIsNormalized(): void {
        $path = tempnam(sys_get_temp_dir(), 'plog-iptc-');
        file_put_contents($path, self::jpeg());
        $caption = "Caf\xe9\nIPTC description";
        $bytes = iptcembed("\x1c\x02\x78".pack('n', strlen($caption)).$caption, $path);
        file_put_contents($path, $bytes);
        try {
            $data = plogger_read_metadata($path);
            self::assertSame(["Café\nIPTC description"], $data['IPTC']['2#120']);
            self::assertStringContainsString('Harmless', $data['IFD0']['ImageDescription']);
        } finally { unlink($path); }
        self::assertNull(plogger_metadata_number('1/0'));
        self::assertSame('', plogger_metadata_text(['malformed']));
    }

    public function testMissingCorruptAndMetadataFreeImagesAreSafe(): void {
        self::assertSame(1, plogger_read_metadata(sys_get_temp_dir().'/missing-'.bin2hex(random_bytes(8)))['Errors']);
        $path = tempnam(sys_get_temp_dir(), 'plog-corrupt-');
        try {
            foreach (["\xff\xd8\xff\xe1\x00\x03x\xff\xd9", 'not an image'] as $bytes) {
                file_put_contents($path, $bytes);
                self::assertSame(1, plogger_read_metadata($path)['Errors']);
            }
            $image = imagecreatetruecolor(2,2); imagepng($image, $path); unset($image);
            self::assertSame([], plogger_read_metadata($path)['SubIFD']);
        } finally { unlink($path); }
    }
}
