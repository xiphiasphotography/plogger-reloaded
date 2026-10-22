<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__.'/MetadataTest.php';
require_once dirname(__DIR__).'/plog-includes/lib/phpthumb/phpthumb.class.php';

final class ThumbnailLibraryTest extends TestCase {
    public function testEveryExifOrientationPreservesSourceAndProducesExpectedDimensions(): void {
        $path = tempnam(sys_get_temp_dir(), 'plog-orient-');
        try {
            foreach (range(1,8) as $orientation) {
                $bytes = MetadataTest::jpeg($orientation); file_put_contents($path,$bytes);
                $thumb = new phpthumb();
                $thumb->config_prefer_imagemagick = false;
                $thumb->config_imagemagick_path = '';
                $thumb->setSourceFileName($path);
                $thumb->ar = 'x'; $thumb->w = 20; $thumb->aoe = false;
                self::assertTrue($thumb->GenerateThumbnail(), implode("\n",$thumb->debugmessages));
                self::assertTrue($thumb->RenderOutput());
                $info = getimagesizefromstring($thumb->outputImageData);
                self::assertSame([20, $orientation >= 5 ? 40 : 10], array_slice($info,0,2));
                $decoded=imagecreatefromstring($thumb->outputImageData);
                $pixel=imagecolorsforindex($decoded,imagecolorat($decoded,2,2));
                $expected=[1=>[255,0,0],2=>[0,255,0],3=>[255,255,0],4=>[0,0,255],
                    5=>[255,0,0],6=>[0,0,255],7=>[255,255,0],8=>[0,255,0]][$orientation];
                foreach (['red','green','blue'] as $channel=>$key) self::assertLessThan(30,abs($pixel[$key]-$expected[$channel]));
                unset($decoded);
                self::assertSame(hash('sha256',$bytes),hash_file('sha256',$path));
            }
        } finally { unlink($path); }
    }

    public function testCropTransparencyAndNoEnlargement(): void {
        $path = tempnam(sys_get_temp_dir(),'plog-alpha-');
        $image = imagecreatetruecolor(40,20);
        imagealphablending($image,false); imagesavealpha($image,true);
        imagefill($image,0,0,imagecolorallocatealpha($image,255,0,0,127));
        imagepng($image,$path); unset($image);
        try {
            $thumb = new phpthumb(); $thumb->config_prefer_imagemagick=false;
            $thumb->setSourceFileName($path); $thumb->f='png'; $thumb->w=10; $thumb->h=10; $thumb->zc=1;
            self::assertTrue($thumb->GenerateThumbnail()); self::assertTrue($thumb->RenderOutput());
            $output=imagecreatefromstring($thumb->outputImageData);
            self::assertSame([10,10],[imagesx($output),imagesy($output)]);
            self::assertSame(127,(imagecolorat($output,0,0)>>24)&127);
            unset($output);
            $thumb = new phpthumb(); $thumb->config_prefer_imagemagick=false;
            $thumb->setSourceFileName($path); $thumb->w=100; $thumb->aoe=false;
            self::assertTrue($thumb->GenerateThumbnail()); self::assertTrue($thumb->RenderOutput());
            self::assertSame([40,20],array_slice(getimagesizefromstring($thumb->outputImageData),0,2));
        } finally { unlink($path); }
    }
}
