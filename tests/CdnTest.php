<?php
use PHPUnit\Framework\TestCase;

final class CdnTest extends TestCase {
    private mixed $configuration;
    private array $server;
    protected function setUp(): void {
        $this->configuration = $GLOBALS['config'] ?? null; $this->server = $_SERVER;
        $_SERVER['SCRIPT_FILENAME'] = '/fixture/index.php';
        $GLOBALS['config'] = ['gallery_url'=>'https://gallery.example.test/sub/gallery/',
            'cdn_url'=>'', 'allow_fullpic'=>1];
    }
    protected function tearDown(): void { $GLOBALS['config'] = $this->configuration; $_SERVER = $this->server; }

    public function testCdnNormalizationAndRejectedBases(): void {
        self::assertSame('', plogger_normalize_cdn_url(''));
        self::assertSame('https://cdn.example.test/archive/', plogger_normalize_cdn_url('HTTPS://CDN.EXAMPLE.TEST/archive'));
        self::assertSame('https://cdn.example.test/a%20b/', plogger_normalize_cdn_url('https://cdn.example.test/a%20b///'));
        self::assertSame('https://cdn.example.test/one//two/', plogger_normalize_cdn_url('https://cdn.example.test/one//two'));
        foreach (['https://bucket.s3.eu-west-1.amazonaws.com/public/', 'https://assets.example.test/',
            'https://zone.b-cdn.net/photos/', 'https://public.example.r2.dev/', 'http://cdn.example.test:8080/'] as $url) {
            self::assertSame($url, plogger_normalize_cdn_url($url));
        }
        foreach (['//cdn.example.test/', 'javascript:fixture', 'ftp://cdn.example.test/',
            'https://user:secret@cdn.example.test/', 'https://cdn.example.test/#fragment',
            'https://cdn.example.test/?token=secret', "https://cdn.example.test/\r\nHeader:x",
            'https://cdn.example.test/a/../b', 'https://cdn.example.test/%2e%2e/',
            'https://cdn.example.test/%252e%252e/', 'https://cdn.example.test/%2fetc/',
            'https://cdn.example.test/%00/', 'https://cdn.example.test/invalid%',
            'https://localhost/', 'https://127.0.0.1/', 'https://10.0.0.1/', 'https://[::1]/',
            'https://cdn.example.test\\@evil.example.test/', [], null, str_repeat('a',256)] as $url) {
            self::assertNull(plogger_normalize_cdn_url($url), is_string($url) ? $url : get_debug_type($url));
        }
    }

    public function testPublicMappingEscapingAndSubdirectories(): void {
        $path = 'plog-content/images/collection/café & "photo"%20.jpg';
        $suffix = 'plog-content/images/collection/caf%C3%A9%20%26%20%22photo%22%2520.jpg';
        self::assertSame('https://gallery.example.test/sub/gallery/'.$suffix, plogger_asset_url($path));
        $GLOBALS['config']['cdn_url'] = 'https://cdn.example.test/mirror';
        self::assertSame('https://cdn.example.test/mirror/'.$suffix, plogger_asset_url($path));
        self::assertSame(plogger_asset_url($path), plogger_image_public_url('collection/café & "photo"%20.jpg'));
        foreach (['plog-content/thumbs/collection/small/hash-photo.jpg', 'plog-content/themes/default/gallery.css'] as $relative) {
            self::assertSame('https://cdn.example.test/mirror/'.$relative, plogger_asset_url($relative));
        }
        foreach (['../secret', '/absolute', '//evil.example.test/a', 'plog-admin/index.php',
            'plog-content/images/a/../x.jpg', 'plog-content/images/a\\x.jpg', 'plog-content/images/%2e%2e/x.jpg',
            'plog-content/images/%252e%252e/x.jpg', 'plog-content/images/%25252fsecret.jpg', 'plog-content/themes/default/.git/logo.jpg',
            "plog-content/images/x\0.jpg"] as $invalid) self::assertSame('#', plogger_asset_url($invalid));
    }

    public function testPrivateDynamicAndAdminResourcesStayOnOrigin(): void {
        $GLOBALS['config']['cdn_url'] = 'https://cdn.example.test/';
        foreach (['plog-content/thumbs/uploads/private.jpg', 'plog-content/themes/default/helper.php',
            'plog-content/themes/default/config.json', 'plog-content/images/private.php.jpg'] as $path) {
            self::assertSame('https://gallery.example.test/sub/gallery/'.$path, plogger_asset_url($path));
        }
        $GLOBALS['config']['allow_fullpic'] = 0;
        self::assertSame('https://gallery.example.test/sub/gallery/plog-content/images/private.jpg', plogger_asset_url('plog-content/images/private.jpg'));
        self::assertSame('https://cdn.example.test/plog-content/thumbs/public.jpg', plogger_asset_url('plog-content/thumbs/public.jpg'));
        $_SERVER['SCRIPT_FILENAME'] = '/fixture/plog-admin/plog-manage.php';
        self::assertSame('https://gallery.example.test/sub/gallery/plog-content/thumbs/public.jpg', plogger_asset_url('plog-content/thumbs/public.jpg'));
    }

    public function testLocalDimensionsResolveBothOriginsWithoutNetworkRequests(): void {
        $root = sys_get_temp_dir().'/plog-cdn-'.bin2hex(random_bytes(8));
        mkdir($root.'/plog-content/thumbs',0755,true);
        $path=$root.'/plog-content/thumbs/café photo.jpg'; file_put_contents($path,'synthetic file');
        $GLOBALS['config']['basedir']=$root.'/';
        $GLOBALS['config']['cdn_url']='https://cdn.example.test/prefix/';
        try {
            self::assertSame($path, plogger_asset_local_path(plogger_asset_url('plog-content/thumbs/café photo.jpg')));
            self::assertSame($path, plogger_asset_local_path('https://gallery.example.test/sub/gallery/plog-content/thumbs/caf%C3%A9%20photo.jpg'));
            foreach (['https://evil.example.test/plog-content/thumbs/caf%C3%A9%20photo.jpg',
                'https://cdn.example.test/prefix-evil/plog-content/thumbs/caf%C3%A9%20photo.jpg',
                'https://cdn.example.test/prefix/plog-content/thumbs/%2e%2e/config.php',
                'https://cdn.example.test/prefix/plog-content/thumbs/missing.jpg'] as $url) self::assertNull(plogger_asset_local_path($url));
            self::assertNull(plogger_asset_local_path(plogger_asset_url('plog-content/thumbs/café photo.jpg').'?cache=1'));
            $GLOBALS['config']['cdn_url']='';
            $legacy='https://gallery.example.test/sub/gallery/plog-content/images/café photo.jpg';
            self::assertSame($legacy,plogger_asset_url('plog-content/images/café photo.jpg',$legacy));
        } finally { unlink($path); rmdir(dirname($path)); rmdir($root.'/plog-content'); rmdir($root); }
    }
}
