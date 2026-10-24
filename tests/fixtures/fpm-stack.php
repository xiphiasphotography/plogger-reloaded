<?php
// Optional Linux-only HTTP acceptance driver. No system service is reconfigured.
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || PHP_OS_FAMILY !== 'Linux' || !extension_loaded('pcntl')) exit(1);
$root=realpath($argv[1] ?? '');
$address=$argv[2] ?? '';
if (!$root || dirname($root)!==realpath(sys_get_temp_dir())
    || !preg_match('/^plog-fixture-[a-f0-9]{16}$/D',basename($root))
    || !preg_match('/^127\.0\.0\.1:[0-9]{4,5}$/D',$address)) exit(1);
$source=dirname(__DIR__,2);
$reserve=static function(): int {
    $socket=stream_socket_server('tcp://127.0.0.1:0');
    if (!$socket) throw new RuntimeException('Cannot reserve acceptance port.');
    $port=(int)substr(strrchr(stream_socket_get_name($socket,false),':'),1);
    fclose($socket); return $port;
};
$apachePort=$reserve(); $apacheTlsPort=$reserve(); $tlsPort=$reserve(); $fpmPort=$reserve();
$log=$root.'/server.log';
$sessionRoot=sys_get_temp_dir().'/plog-session-fixture-'.basename($root);
mkdir($sessionRoot,0700);
file_put_contents($sessionRoot.'/capture-mail',"#!/bin/sh\ncat > ".escapeshellarg($sessionRoot.'/mail.eml')."\n");
chmod($sessionRoot.'/capture-mail',0700);
$certificate=$sessionRoot.'/certificate.pem'; $key=$sessionRoot.'/key.pem';
$openssl=proc_open(['/usr/bin/openssl','req','-x509','-newkey','rsa:2048','-nodes','-days','1',
    '-subj','/CN=localhost','-addext','subjectAltName=IP:127.0.0.1','-keyout',$key,'-out',$certificate],
    [0=>['file','/dev/null','r'],1=>['file',$log,'a'],2=>['file',$log,'a']],$pipes);
if (!is_resource($openssl) || proc_close($openssl)!==0) throw new RuntimeException('Cannot generate disposable TLS certificate.');
chmod($key,0600);
// PHP workers inherit only the fixture environment, never a site's configuration.
$fpm="[global]\npid=$root/fpm.pid\nerror_log=$log\ndaemonize=no\n[fixture]\nlisten=127.0.0.1:$fpmPort\npm=static\npm.max_children=2\nclear_env=no\ncatch_workers_output=yes\n";
if (posix_geteuid()===0) $fpm.="user=www-data\ngroup=www-data\n";
$fpm.="php_admin_value[auto_prepend_file]=$source/tests/fixtures/http-bootstrap.php\nphp_admin_value[error_reporting]=-1\nphp_admin_flag[display_errors]=on\nphp_admin_value[error_log]=$log\nphp_admin_value[session.save_path]=$sessionRoot\nphp_admin_value[max_execution_time]=60\nphp_admin_value[memory_limit]=256M\n";
$fpm.="php_admin_value[sendmail_path]=$sessionRoot/capture-mail\n";
file_put_contents($root.'/fixture-runtime.php','<?php require __DIR__."/plog-load-config.php"; echo json_encode([PHP_SAPI,ini_get("session.save_path"),ini_get("max_execution_time"),ini_get("memory_limit"),posix_geteuid()]);');
file_put_contents($root.'/fpm.conf',$fpm);
$apache="ServerRoot /etc/apache2\nServerName localhost\nPidFile $root/apache.pid\nListen 127.0.0.1:$apachePort\nErrorLog $log\n";
foreach (['mpm_event','authz_core','authz_host','mime','dir','env','proxy','proxy_fcgi'] as $module) {
    $apache.="LoadModule {$module}_module /usr/lib/apache2/modules/mod_$module.so\n";
}
$apache.="TypesConfig /etc/mime.types\nDocumentRoot $root\nDirectoryIndex index.php\n<Directory $root>\nRequire all granted\n</Directory>\n<FilesMatch \\.php$>\nSetHandler \"proxy:fcgi://127.0.0.1:$fpmPort\"\n</FilesMatch>\n";
$apache.=str_replace('/var/www/plogger',$root,file_get_contents($source.'/docs/server/apache-security.conf'));
$apache.="\nListen 127.0.0.1:$apacheTlsPort\n<VirtualHost 127.0.0.1:$apachePort>\nServerName localhost\n</VirtualHost>\n<VirtualHost 127.0.0.1:$apacheTlsPort>\nServerName localhost\nSetEnv HTTPS on\n</VirtualHost>\n";
file_put_contents($root.'/apache.conf',$apache);
$nginx="daemon off;\npid $root/nginx.pid;\nerror_log $log;\nevents {}\nhttp {\naccess_log off;\nclient_body_temp_path $root/body;\nproxy_temp_path $root/proxy;\nserver {\nlisten $address;\nroot $root;\n";
$nginx.=file_get_contents($source.'/docs/server/nginx-security.conf');
$nginx.="\nlocation / { proxy_set_header Host ".'$http_host'."; proxy_pass http://127.0.0.1:$apachePort; }\n}\n";
$nginx.="server { listen 127.0.0.1:$tlsPort ssl; ssl_certificate $certificate; ssl_certificate_key $key; root $root;\n";
$nginx.=file_get_contents($source.'/docs/server/nginx-security.conf');
$nginx.="\nlocation / { proxy_set_header Host ".'$http_host'."; proxy_pass http://127.0.0.1:$apacheTlsPort; }\n}\n}\n";
file_put_contents($root.'/nginx.conf',$nginx);
file_put_contents($root.'/stack-addresses.json',json_encode(['nginx'=>$address,'apache'=>'127.0.0.1:'.$apachePort]));
file_put_contents($root.'/stack-tls.json',json_encode(['url'=>'https://127.0.0.1:'.$tlsPort,'certificate'=>$certificate]));
$processes=[];
$stop=static function() use (&$processes,$sessionRoot): void {
    foreach (array_reverse($processes) as $process) if (is_resource($process)) { proc_terminate($process); proc_close($process); }
    $processes=[];
    if (is_dir($sessionRoot)) {
        foreach (glob($sessionRoot.'/sess_*') ?: [] as $file) unlink($file);
        foreach (['capture-mail','mail.eml','certificate.pem','key.pem'] as $name) if (is_file($sessionRoot.'/'.$name)) unlink($sessionRoot.'/'.$name);
        rmdir($sessionRoot);
    }
};
register_shutdown_function($stop);
pcntl_async_signals(true);
pcntl_signal(SIGTERM,static function() use ($stop): void { $stop(); exit; });
try {
    foreach ([[$fpmPort,['/usr/sbin/php-fpm8.4','-F','-y',$root.'/fpm.conf']],
        [$apachePort,['/usr/sbin/apache2','-f',$root.'/apache.conf','-DFOREGROUND']],
        [(int)substr(strrchr($address,':'),1),['/usr/sbin/nginx','-p',$root.'/','-c',$root.'/nginx.conf']]] as [$port,$command]) {
        $process=proc_open($command,[0=>['file','/dev/null','r'],1=>['file',$log,'a'],2=>['file',$log,'a']],$pipes);
        if (!is_resource($process)) throw new RuntimeException('Cannot start fixture server.');
        $processes[]=$process;
        $ready=false;
        for ($attempt=0;$attempt<50;++$attempt) {
            $connection=@stream_socket_client('tcp://127.0.0.1:'.$port,$error,$message,0.1);
            if ($connection) { fclose($connection); $ready=true; break; }
            if (!proc_get_status($process)['running']) break;
            usleep(100000);
        }
        if (!$ready) throw new RuntimeException('Fixture server did not become ready: '.$command[0]);
    }
    while (true) {
        foreach ($processes as $process) if (!proc_get_status($process)['running']) throw new RuntimeException('Fixture server exited.');
        usleep(100000);
    }
} finally { $stop(); }
