<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/plog-includes/plog-csrf.php';

final class CsrfTest extends TestCase
{
    public function testCustomSessionFilesPersistAcrossRealHttpRequests(): void {
        $root=sys_get_temp_dir().'/plog-session-test-'.bin2hex(random_bytes(8));
        mkdir($root,0700); mkdir($root.'/public',0700); mkdir($root.'/sessions',0700);
        $server=null;
        try {
            $code='<?php define("PLOGGER_SESSION_SAVE_PATH",dirname(__DIR__)."/sessions"); require '
                .var_export(dirname(__DIR__).'/plog-includes/plog-csrf.php',true).';'
                .'plogger_start_session(); if ($_SERVER["REQUEST_METHOD"]!=="GET") plogger_csrf_require_post();'
                .'$_SESSION["visits"]=($_SESSION["visits"]??0)+1;'
                .'header("Content-Type: application/json"); echo json_encode(["visits"=>$_SESSION["visits"],"token"=>plogger_csrf_token()]);';
            file_put_contents($root.'/public/index.php',$code);
            $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);
            self::assertIsResource($socket); $address=stream_socket_get_name($socket,false); fclose($socket);
            $server=proc_open([PHP_BINARY,'-d','error_reporting=-1','-d','display_errors=1','-S',$address,'-t',$root.'/public'],
                [0=>['pipe','r'],1=>['file',$root.'/server.log','a'],2=>['file',$root.'/server.log','a']],$pipes);
            self::assertIsResource($server); fclose($pipes[0]);
            for ($attempt=0;$attempt<50;$attempt++) {
                $socket=@stream_socket_client('tcp://'.$address,$errno,$error,0.1);
                if ($socket) { fclose($socket); break; } usleep(100000);
            }
            $context=stream_context_create(['http'=>['ignore_errors'=>true,'timeout'=>10]]);
            [$body,$headers]=plogger_test_http_response('http://'.$address.'/',$context);
            $first=json_decode($body,true,512,JSON_THROW_ON_ERROR); $cookie='';
            foreach ($headers as $header) if (preg_match('/^Set-Cookie: ([^;]+)/i',$header,$matches)) {
                $cookie=$matches[1]; self::assertStringContainsString('HttpOnly',$header); self::assertStringContainsString('SameSite=Lax',$header);
            }
            self::assertNotSame('',$cookie);
            stream_context_set_option($context,'http','header','Cookie: '.$cookie);
            [$body]=plogger_test_http_response('http://'.$address.'/',$context);
            $second=json_decode($body,true,512,JSON_THROW_ON_ERROR);
            self::assertSame(2,$second['visits']); self::assertSame($first['token'],$second['token']);
            self::assertCount(1,glob($root.'/sessions/sess_*'));
            stream_context_set_option($context,'http','method','POST');
            stream_context_set_option($context,'http','header',"Cookie: $cookie\r\nContent-Type: application/x-www-form-urlencoded\r\n");
            foreach (['','_csrf=invalid'] as $content) {
                stream_context_set_option($context,'http','content',$content);
                [, $headers]=plogger_test_http_response('http://'.$address.'/',$context);
                self::assertStringContainsString('403',$headers[0]);
            }
            stream_context_set_option($context,'http','content',http_build_query(['_csrf'=>$first['token']]));
            [$body,$headers]=plogger_test_http_response('http://'.$address.'/',$context);
            self::assertStringContainsString('200',$headers[0]);
            self::assertSame(3,json_decode($body,true,512,JSON_THROW_ON_ERROR)['visits']);
        } finally {
            if (is_resource($server)) { proc_terminate($server); proc_close($server); }
            if (realpath(dirname($root))!==realpath(sys_get_temp_dir()) || !str_starts_with(basename($root),'plog-session-test-')) throw new RuntimeException('Unsafe fixture cleanup.');
            $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $file) { if ($file->isDir()) rmdir($file->getPathname()); else unlink($file->getPathname()); }
            rmdir($root);
        }
    }
    public function testHttpsCookieOptionsAndCustomSessionPath(): void
    {
        $code = '$_SERVER["HTTPS"]="on"; define("PLOGGER_SESSION_SAVE_PATH",sys_get_temp_dir()); require '
            . var_export(dirname(__DIR__) . '/plog-includes/plog-csrf.php', true)
            . '; plogger_start_session(); echo json_encode([session_get_cookie_params(),session_save_path()]); session_destroy();';
        $process = proc_open([PHP_BINARY, '-r', $code], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $errors);
        [$options, $path] = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($options['secure']);
        self::assertTrue($options['httponly']);
        self::assertSame('Lax', $options['samesite']);
        self::assertSame(sys_get_temp_dir(), $path);
    }
    public function testTokensAreRandomSessionBoundAndConstantTimeCompared(): void
    {
        $previous = $_SESSION ?? [];
        try {
            $_SESSION = [];
            $first = plogger_csrf_token();
            self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $first);
            self::assertSame($first, plogger_csrf_token());
            self::assertTrue(plogger_csrf_valid($first));
            foreach ([null, '', [], [$first], str_repeat('0', 64)] as $bad) self::assertFalse(plogger_csrf_valid($bad));
            $_SESSION = [];
            self::assertNotSame($first, plogger_csrf_token());
            self::assertFalse(plogger_csrf_valid($first));
            $form = plogger_csrf_forms('<form method="post"><input name="x" /></form><form method="get"></form>');
            self::assertSame(1, substr_count($form, 'name="_csrf"'));
        } finally {
            $_SESSION = $previous;
        }
    }
}
