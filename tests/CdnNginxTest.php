<?php
use PHPUnit\Framework\TestCase;

/** Optional real nginx fixture. No PHP handler, production config or external network. */
final class CdnNginxTest extends TestCase {
    public function testStaticMirrorServesAssetsAndRejectsPrivateAndExecutablePaths(): void {
        $binary = getenv('PLOGGER_TEST_NGINX');
        if (!$binary || !is_file($binary)) self::markTestSkipped('Set PLOGGER_TEST_NGINX to a native nginx binary.');
        $root = str_replace('\\','/',sys_get_temp_dir()).'/plog-cdn-nginx-'.bin2hex(random_bytes(8));
        mkdir($root); mkdir($root.'/logs'); mkdir($root.'/temp');
        $files = [
            'plog-content/images/café photo.jpg'=>'synthetic image',
            'plog-content/thumbs/album/small/hash-photo.jpg'=>'synthetic thumbnail',
            'plog-content/themes/default/gallery.css'=>'body { color: black; }',
            'plog-content/themes/default/dynamics.js'=>'/* synthetic script */',
            'plog-content/themes/default/font.woff2'=>'synthetic font',
            'plog-content/ui/css/gallery.css'=>'.plog-ui { color: black; }',
            'plog-content/themes/default/head.php'=>'<?php /* must not be served */',
            'plog-content/images/payload.php.jpg'=>'<?php /* must not be served */',
            'plog-content/thumbs/uploads/private.jpg'=>'private import preview',
            'plog-content/uploads/private.jpg'=>'private import',
            'plog-content/themes/default/.private.jpg'=>'private',
            'plog-config.php'=>'private fixture config',
        ];
        foreach ($files as $path=>$bytes) {
            if (!is_dir(dirname($root.'/'.$path))) mkdir(dirname($root.'/'.$path),0755,true);
            file_put_contents($root.'/'.$path,$bytes);
        }
        $socket=stream_socket_server('tcp://127.0.0.1:0',$error,$message); self::assertIsResource($socket);
        $address=stream_socket_get_name($socket,false); fclose($socket);
        $policy=file_get_contents(dirname(__DIR__).'/docs/server/nginx-cdn.conf');
        $policy=str_replace('root /srv/plogger-public;', 'root "'.$root.'";', $policy);
        // Windows nginx lacks the Unix openat symlink guard; it stays enabled in the shipped Linux policy.
        if (PHP_OS_FAMILY==='Windows') $policy=str_replace('disable_symlinks on;','',$policy);
        $configuration='worker_processes 1; error_log "'.$root.'/error.log"; pid "'.$root.'/nginx.pid";'
            .'events { worker_connections 64; } http { access_log off; '
            .'client_body_temp_path "'.$root.'/temp/body"; proxy_temp_path "'.$root.'/temp/proxy"; '
            .'fastcgi_temp_path "'.$root.'/temp/fastcgi"; uwsgi_temp_path "'.$root.'/temp/uwsgi"; scgi_temp_path "'.$root.'/temp/scgi"; '
            .'types { text/css css; application/javascript js; image/jpeg jpg; font/woff2 woff2; }'
            .'server { listen '.$address.'; server_name static.example.test; '
            .'rewrite ^/archive/(plog-content/.*)$ /$1 last; '.$policy.' } }';
        file_put_contents($root.'/nginx.conf',$configuration);
        $arguments=[$binary,'-p',$root.'/','-c','nginx.conf'];
        $check=proc_open(array_merge($arguments,['-t']),[1=>['pipe','w'],2=>['pipe','w']],$pipes,$root);
        self::assertIsResource($check); $diagnostic=stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]); $status=proc_close($check);
        $server=null;
        try {
            self::assertSame(0,$status,$diagnostic);
            $server=proc_open(array_merge($arguments,['-g','daemon off;']),[0=>['pipe','r'],1=>['file',$root.'/stdout.log','a'],2=>['file',$root.'/stderr.log','a']],$pipes,$root);
            self::assertIsResource($server); fclose($pipes[0]);
            $ready=false;
            for ($attempt=0;$attempt<50;++$attempt) {
                $connection=@stream_socket_client('tcp://'.$address,$error,$message,0.1);
                if ($connection) { fclose($connection); $ready=true; break; } usleep(100000);
            }
            self::assertTrue($ready,'Disposable nginx did not start.');
            $fetch=static function(string $path,string $method='GET') use ($address): array {
                $context=stream_context_create(['http'=>['method'=>$method,'ignore_errors'=>true,'timeout'=>5]]);
                return plogger_test_http_response('http://'.$address.$path, $context);
            };
            foreach (array_slice($files,0,6,true) as $path=>$bytes) {
                [$body,$headers]=$fetch('/'.implode('/',array_map('rawurlencode',explode('/',$path))));
                self::assertStringContainsString('200',$headers[0]); self::assertSame($bytes,$body);
                self::assertStringContainsString('X-Content-Type-Options: nosniff',implode("\n",$headers));
                self::assertStringContainsString('Access-Control-Allow-Origin: *',implode("\n",$headers));
            }
            foreach (array_keys(array_slice($files,6,null,true)) as $path) {
                [, $headers]=$fetch('/'.$path); self::assertStringContainsString('404',$headers[0],$path);
            }
            [$body,$headers]=$fetch('/archive/plog-content/images/caf%C3%A9%20photo.jpg');
            self::assertStringContainsString('200',$headers[0]); self::assertSame('synthetic image',$body);
            [, $headers]=$fetch('/archive/plog-content/thumbs/uploads/private.jpg'); self::assertStringContainsString('404',$headers[0]);
            foreach (['/plog-admin/index.php','/plog-download.php','/plog-content/images/missing.jpg','/plog-content/images/','/plog-content/themes/default/'] as $path) {
                [, $headers]=$fetch($path); self::assertStringContainsString('404',$headers[0],$path);
            }
            [, $headers]=$fetch('/plog-content/themes/default/gallery.css','POST'); self::assertStringContainsString('405',$headers[0]);
            foreach ($files as $path=>$bytes) self::assertSame($bytes,file_get_contents($root.'/'.$path));
        } finally {
            if (is_resource($server)) {
                $stop=proc_open(array_merge($arguments,['-s','quit']),[1=>['file',$root.'/stop.log','a'],2=>['file',$root.'/stop.log','a']],$pipes,$root);
                if (is_resource($stop)) proc_close($stop);
                proc_close($server);
            }
            $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $file) { if ($file->isDir() && !$file->isLink()) rmdir($file->getPathname()); else unlink($file->getPathname()); }
            rmdir($root);
        }
    }
}
