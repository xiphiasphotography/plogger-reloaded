<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class HttpCompatibilityTest extends TestCase
{
    private static string $root;
    private static string $url;
    private static string $log;
    private static $server;
    private static string $cookie = '';
    private static string $originalHash;

    public static function setUpBeforeClass(): void
    {
        if (!extension_loaded('gd')) self::markTestSkipped('GD required for isolated website fixtures.');
        self::$root = sys_get_temp_dir() . '/plog-fixture-' . bin2hex(random_bytes(8));
        mkdir(self::$root);
        $source = dirname(__DIR__);
        // Copy application code/assets only. Never copy existing configuration or user images.
        $paths = glob($source . '/*.php');
        foreach ($paths as $path) {
            if (basename($path) !== 'plog-config.php') copy($path, self::$root . '/' . basename($path));
        }
        foreach (['plog-admin', 'plog-includes', 'plog-content/themes', 'plog-content/ui', 'plog-content/plugins', 'plog-content/modules'] as $path) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source . '/' . $path,
                FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if (!$file->isFile() || $file->isLink()) continue;
                $target = self::$root . '/' . $path . '/' . $iterator->getSubPathname();
                if (!is_dir(dirname($target))) mkdir(dirname($target), 0755, true);
                copy($file->getPathname(), $target);
            }
        }
        foreach (['images/fixture/album', 'thumbs', 'uploads', 'translations'] as $path) {
            mkdir(self::$root . '/plog-content/' . $path, 0755, true);
        }
        file_put_contents(self::$root . '/plog-config.php', '<?php // Test constants are injected by auto_prepend_file.');
        file_put_contents(self::$root.'/fixture-oypo.php', '<?php require __DIR__."/plogger.php";'
            .'echo plogger_get_picture_actions(["id"=>7,"path"=>"fixture/photo &.jpg","original_width"=>800,"original_height"=>600], ["oypo_album_id"=>"fixture-album"]);');
        file_put_contents(self::$root.'/fixture-thumbnail.php', '<?php require __DIR__."/plog-load-config.php";'
            .'$config["compression"] = (int) ($_GET["quality"] ?? 75);'
            .'echo generate_thumb("fixture/album/photo.jpg", "cache-fixture", THUMB_SMALL);');
        file_put_contents(self::$root.'/fixture-cdn.php', '<?php require __DIR__."/plogger.php";'
            .'$GLOBALS["plogger_level"]="picture"; $GLOBALS["current_picture"]=get_picture_by_id(1);'
            .'echo json_encode(["image"=>plogger_get_source_picture_url(), "thumb"=>plogger_get_picture_thumb(THUMB_LARGE),'
            .'"info"=>plogger_get_thumbnail_info(), "theme"=>THEME_URL, "pictures"=>get_pictures(1),'
            .'"single"=>get_picture_by_id(1), "multiple"=>get_picture_by_id([1])]);');
        file_put_contents(self::$root . '/fixture-descriptions.php', '<?php require __DIR__."/plogger.php";'
            . '$GLOBALS["plogger_level"] = $_GET["fixture_level"] ?? "collections";'
            . '$GLOBALS["plogger_id"] = 1; plogger_init(); the_plogger_gallery();');
        $image = imagecreatetruecolor(800, 600);
        imagejpeg($image, self::$root . '/plog-content/images/fixture/album/photo.jpg');
        imagejpeg($image, self::$root . '/plog-content/uploads/import.jpg');
        file_put_contents(self::$root . '/plog-content/uploads/README', 'extensionless synthetic file');
        self::$originalHash = hash_file('sha256', self::$root . '/plog-content/images/fixture/album/photo.jpg');
        $socket = stream_socket_server('tcp://127.0.0.1:0', $error, $message);
        if (!$socket) throw new RuntimeException($message);
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        self::$url = 'http://' . $address;
        self::$log = self::$root . '/server.log';
        if (getenv('PLOGGER_TEST_DRIVER')) {
            putenv('PLOGGER_TEST_DATABASE=plogger_phase1_ci_' . bin2hex(random_bytes(8)));
            $install = proc_open([PHP_BINARY, __DIR__ . '/fixtures/native-install.php', self::$root, $address],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $installPipes);
            $output = stream_get_contents($installPipes[1]) . stream_get_contents($installPipes[2]);
            fclose($installPipes[1]);
            fclose($installPipes[2]);
            if (proc_close($install) !== 0 || preg_match('/Warning|Deprecated|Fatal error/', $output)) {
                throw new RuntimeException('Native installer fixture failed: ' . $output);
            }
            // The real installer creates collection/album directories first.
            // Seed images afterward, exactly as a fresh install would receive them.
            if (!is_dir(self::$root . '/plog-content/images/fixture/album')) {
                mkdir(self::$root . '/plog-content/images/fixture/album', 0755, true);
            }
            imagejpeg($image, self::$root . '/plog-content/images/fixture/album/photo.jpg');
            self::$originalHash = hash_file('sha256', self::$root . '/plog-content/images/fixture/album/photo.jpg');
        }
        $command = [PHP_BINARY, '-d', 'auto_prepend_file=' . __DIR__ . '/fixtures/http-bootstrap.php',
            '-d', 'display_errors=1', '-d', 'error_reporting=-1', '-S', $address, '-t', self::$root];
        if (getenv('PLOGGER_TEST_FPM_STACK') === '1') {
            $command = [PHP_BINARY, __DIR__.'/fixtures/fpm-stack.php', self::$root, $address];
        }
        self::$server = proc_open($command,
            [0 => ['pipe', 'r'], 1 => ['file', self::$log, 'a'], 2 => ['file', self::$log, 'a']], $pipes, self::$root);
        if (!is_resource(self::$server)) throw new RuntimeException('Cannot start isolated PHP server.');
        fclose($pipes[0]);
        for ($attempt = 0; $attempt < 50; $attempt++) {
            $connection = @stream_socket_client('tcp://' . $address, $error, $message, 0.1);
            if ($connection) { fclose($connection); return; }
            usleep(100000);
        }
        throw new RuntimeException('Isolated PHP server did not start.');
    }

    public static function tearDownAfterClass(): void
    {
        if (isset(self::$server) && is_resource(self::$server)) {
            proc_terminate(self::$server);
            proc_close(self::$server);
        }
        $runtimeFailures = [];
        if (isset(self::$log) && is_file(self::$log)) {
            preg_match_all('/^.*(?:PHP (?:Warning|Deprecated|Fatal error|Notice)|PHP runtime warning|Unhandled application exception).*$/m', file_get_contents(self::$log), $matches);
            $runtimeFailures = $matches[0];
        }
        if (!isset(self::$root)) return;
        if(in_array(getenv('PLOGGER_TEST_DRIVER'),['mysql','pgsql'],true) && preg_match('/^plogger_phase1_ci_[a-f0-9]{16}$/D',getenv('PLOGGER_TEST_DATABASE') ?: '')
            && (int)getenv('PLOGGER_TEST_PORT')>=20000) {
            $driver=getenv('PLOGGER_TEST_DRIVER');
            $admin=new PDO($driver.':host=127.0.0.1;port='.getenv('PLOGGER_TEST_PORT').($driver==='pgsql'?';dbname=postgres':''),
                getenv('PLOGGER_TEST_USER'),getenv('PLOGGER_TEST_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
            if($driver==='mysql') $admin->prepare("SET SESSION sql_mode = 'ANSI'")->execute();
            $admin->prepare('DROP DATABASE '.plogger_db_identifier(getenv('PLOGGER_TEST_DATABASE')))->execute();
        }
        $authDirectory = dirname(self::$root).'/plog-auth-fixture-'.hash('sha256', str_replace('\\', '/', realpath(self::$root)));
        if (is_dir($authDirectory)) {
            foreach (glob($authDirectory.'/*.json') ?: [] as $ledger) unlink($ledger);
            rmdir($authDirectory);
        }
        // Resolve and constrain the recursive cleanup to this generated fixture directory.
        $resolved = realpath(self::$root);
        $temporary = realpath(sys_get_temp_dir());
        if (!$resolved || !$temporary || dirname($resolved) !== $temporary || !str_starts_with(basename($resolved), 'plog-fixture-')) {
            throw new RuntimeException('Refusing cleanup outside generated fixture directory.');
        }
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($resolved,
            FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) {
            if ($file->isDir() && !$file->isLink()) rmdir($file->getPathname());
            else unlink($file->getPathname());
        }
        rmdir($resolved);
        self::assertSame([], $runtimeFailures, 'Unexpected fixture runtime diagnostics.');
    }

    private function csrfToken(): string
    {
        foreach (['/plog-admin/index.php', '/plog-admin/plog-upload.php'] as $page) {
            [$body] = $this->request($page);
            if (preg_match('/name="_csrf" value="([a-f0-9]{64})"/', $body, $match)) return $match[1];
        }
        throw new RuntimeException('Fixture CSRF token missing.');
    }

    public function testNativeFpmStackDeniesPrivateResourcesAtBothHttpLayers(): void {
        if (getenv('PLOGGER_TEST_FPM_STACK') !== '1') self::markTestSkipped('Explicit disposable Linux nginx/Apache/PHP-FPM stack required.');
        $addresses=json_decode(file_get_contents(self::$root.'/stack-addresses.json'),true,512,JSON_THROW_ON_ERROR);
        [$runtime,$runtimeHeaders]=$this->request('/fixture-runtime.php');
        self::assertStringContainsString('200',$runtimeHeaders[0],$runtime);
        [$sapi,$sessions,$time,$memory,$uid]=json_decode($runtime,true,512,JSON_THROW_ON_ERROR);
        self::assertSame('fpm-fcgi',$sapi);
        self::assertSame('www-data',posix_getpwuid($uid)['name']);
        self::assertSame('60',$time); self::assertSame('256M',$memory);
        self::assertFalse(str_starts_with($sessions,self::$root.'/'));
        self::assertSame(0700,fileperms($sessions)&0777);
        self::assertNotEmpty(glob($sessions.'/sess_*'));
        $tls=json_decode(file_get_contents(self::$root.'/stack-tls.json'),true,512,JSON_THROW_ON_ERROR);
        [, $tlsHeaders]=plogger_test_http_response($tls['url'].'/plog-admin/index.php',
            stream_context_create(['ssl'=>['cafile'=>$tls['certificate'],'verify_peer'=>true,'verify_peer_name'=>true],
                'http'=>['ignore_errors'=>true,'timeout'=>10,'follow_location'=>0]]));
        $cookies=implode("\n",array_filter($tlsHeaders,static fn($line)=>str_starts_with(strtolower($line),'set-cookie:')));
        self::assertStringContainsString('secure',strtolower($cookies));
        self::assertStringContainsString('httponly',strtolower($cookies));
        self::assertStringContainsString('samesite=lax',strtolower($cookies));
        $paths=['/plog-config.php','/composer.json','/.git/config','/tests/bootstrap.php',
            '/plog-includes/plog-functions.php','/plog-includes/lib/phpthumb/phpThumb.php',
            '/plog-admin/includes/install-functions.php','/plog-admin/migrations/0001_schema_version.php',
            '/plog-admin/_install.php','/plog-admin/_upgrade.php','/plog-content/uploads/import.jpg'];
        foreach (['images','thumbs','uploads','themes'] as $directory) {
            file_put_contents(self::$root.'/plog-content/'.$directory.'/fixture-execution.php','<?php echo "UNSAFE_EXECUTION_FIXTURE";');
            $paths[]='/plog-content/'.$directory.'/fixture-execution.php';
            $paths[]='/plog-content/'.$directory.'/fixture-execution.php/path';
        }
        foreach ($addresses as $layer=>$address) foreach ($paths as $path) {
            [$body,$headers]=plogger_test_http_response('http://'.$address.$path,
                stream_context_create(['http'=>['ignore_errors'=>true,'timeout'=>10]]));
            self::assertMatchesRegularExpression('/\s(?:403|404)\s/',$headers[0],$layer.' '.$path);
            self::assertStringNotContainsString('UNSAFE_EXECUTION_FIXTURE',$body);
            self::assertStringNotContainsString('<?php',$body);
        }
    }

    private function nativeConnection(): PDO
    {
        $driver = getenv('PLOGGER_TEST_DRIVER');
        $database = getenv('PLOGGER_TEST_DATABASE');
        $port = (int) getenv('PLOGGER_TEST_PORT');
        if (!in_array($driver, ['mysql', 'pgsql'], true) || !preg_match('/^plogger_phase1_ci_[a-f0-9]{16}$/D', $database) || $port < 20000) {
            throw new RuntimeException('Unsafe native fixture target.');
        }
        $connection = new PDO($driver.':host=127.0.0.1;port='.$port.';dbname='.$database,
            getenv('PLOGGER_TEST_USER'), getenv('PLOGGER_TEST_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        if ($driver === 'mysql') $connection->exec("SET SESSION sql_mode = 'ANSI,STRICT_ALL_TABLES'");
        return $connection;
    }

    private function setFixtureCdn(string $url): void {
        if (getenv('PLOGGER_TEST_DRIVER')) {
            $statement=$this->nativeConnection()->prepare('UPDATE "fixture_config" SET "cdn_url" = ?');
            $statement->execute([$url]);
        } else file_put_contents(self::$root.'/fixture-cdn.json',json_encode($url));
    }

    public function testCdnUrlsThemesDimensionsAndOriginOnlyAdministration(): void {
        $this->request('/plog-admin/plog-upload.php', ['action'=>'log_in', 'plog_username'=>'fixture','plog_password'=>'fixture-password']);
        $this->setFixtureCdn('HTTPS://CDN.EXAMPLE.TEST/public%20gallery');
        $cdn='https://cdn.example.test/public%20gallery/';
        try {
            [$json]=$this->request('/fixture-cdn.php'); $data=json_decode($json,true,512,JSON_THROW_ON_ERROR);
            self::assertSame($cdn.'plog-content/images/fixture/album/photo.jpg',$data['image']);
            self::assertStringStartsWith($cdn.'plog-content/thumbs/', $data['thumb']);
            self::assertGreaterThan(0,$data['info']['width']); self::assertGreaterThan(0,$data['info']['height']);
            self::assertSame($data['image'],$data['single']['url']);
            self::assertSame($data['image'],$data['multiple'][0]['url']);
            self::assertSame($data['image'],$data['pictures'][1]['url']);
            foreach (['default','air','lucid','xip-bs','reloaded'] as $theme) {
                $this->request('/plog-admin/plog-themes.php',['activate'=>$theme]);
                [$body]=$this->request('/index.php?level=picture&id=1&fixture_theme='.$theme);
                $dom=new DOMDocument(); @$dom->loadHTML($body); $xpath=new DOMXPath($dom);
                self::assertGreaterThan(0,$xpath->query('//link[starts-with(@href,"'.$cdn.'plog-content/themes/'.$theme.'/")]')->length,$theme);
                self::assertGreaterThan(0,$xpath->query('//img[starts-with(@src,"'.$cdn.'plog-content/thumbs/")]')->length,$theme);
                foreach ($xpath->query('//a[contains(@href,"plog-download.php")]') as $link) self::assertFalse(str_starts_with($link->getAttribute('href'),$cdn));
            }
            [$body]=$this->request('/plog-rss.php?level=album&id=1');
            self::assertStringContainsString($cdn.'plog-content/', $body);
            self::assertStringNotContainsString('public%2520gallery',$body);
            foreach (['plog-upload.php','plog-import.php','plog-options.php'] as $page) {
                [$body]=$this->request('/plog-admin/'.$page);
                $dom=new DOMDocument(); @$dom->loadHTML($body); $xpath=new DOMXPath($dom);
                self::assertSame(0,$xpath->query('//*[@src and starts-with(@src,"'.$cdn.'")] | //*[@href and starts-with(@href,"'.$cdn.'")] | //form[starts-with(@action,"'.$cdn.'")]')->length,$page);
            }
            $this->setFixtureCdn('');
            [$json]=$this->request('/fixture-cdn.php'); $local=json_decode($json,true,512,JSON_THROW_ON_ERROR);
            self::assertSame(self::$url.'/plog-content/images/fixture/album/photo.jpg',$local['image']);
            self::assertStringStartsWith(self::$url.'/plog-content/thumbs/',$local['thumb']);
            self::assertSame($data['info'],$local['info']);
            self::assertSame(self::$originalHash,hash_file('sha256',self::$root.'/plog-content/images/fixture/album/photo.jpg'));
        } finally { $this->setFixtureCdn(''); $this->request('/plog-admin/plog-themes.php',['activate'=>'default']); self::$cookie=''; }
    }

    public function testReloadedRendersWithoutJavascriptAndAdminAssetsStaySeparate(): void {
        $this->request('/plog-admin/plog-upload.php', ['action'=>'log_in', 'plog_username'=>'fixture', 'plog_password'=>'fixture-password']);
        try {
            $this->request('/plog-admin/plog-themes.php', ['activate'=>'reloaded']);
            foreach (['collections','collection','album','picture','search','404'] as $level) {
                [$body,$headers] = $this->request('/fixture-descriptions.php?fixture_theme=reloaded&fixture_level='.$level.'&searchterms=Fixture');
                self::assertStringContainsString('200', $headers[0], $level);
                $dom = new DOMDocument(); @$dom->loadHTML($body); $xpath = new DOMXPath($dom);
                self::assertSame(1, $xpath->query('//*[@id="plog-main"]')->length, $level);
                self::assertSame(1, $xpath->query('//a[@class="plog-skip" and @href="#plog-main"]')->length);
                self::assertSame(0, $xpath->query('//form//form')->length);
                self::assertStringNotContainsString('plog-content/ui/css/admin.css', $body);
                self::assertStringNotContainsString('bootstrap', strtolower($body));
                self::assertStringNotContainsString('jquery', strtolower($body));
                if (in_array($level,['collections','collection','album'],true)) self::assertSame(1, $xpath->query('//ul[@class="plog-gallery-grid"]')->length, $level);
                foreach ($xpath->query('//img') as $image) self::assertTrue($image->hasAttribute('alt'));
            }
            $this->request('/plog-admin/plog-themes.php', ['activate'=>'default']);
            [$body] = $this->request('/index.php?fixture_theme=default');
            self::assertStringNotContainsString('plog-content/ui/css/', $body);
            $this->request('/plog-admin/plog-themes.php', ['activate'=>'reloaded']);
            [$body] = $this->request('/index.php?fixture_theme=reloaded&level=album&id=1&mode=slideshow');
            $dom = new DOMDocument(); @$dom->loadHTML($body); $xpath = new DOMXPath($dom);
            self::assertSame(1, $xpath->query('//ol[@class="plog-slides"]')->length);
            self::assertGreaterThan(0, $xpath->query('//li[@class="plog-slide"]//a[@href]//img[@loading="lazy"]')->length);
            foreach (['plog-upload.php','plog-import.php','plog-manage.php','plog-options.php'] as $page) {
                [$body] = $this->request('/plog-admin/'.$page);
                self::assertStringContainsString('class="plog-ui plog-admin"', $body);
                self::assertStringContainsString('plog-content/ui/css/admin.css', $body);
                self::assertStringNotContainsString('plog-content/ui/css/gallery.css', $body);
                self::assertStringContainsString('aria-current="page"', $body);
                self::assertStringContainsString('id="content" tabindex="-1"', $body);
            }
        } finally {
            $this->request('/plog-admin/plog-themes.php', ['activate'=>'default']);
            self::$cookie = '';
        }
    }

    public function testCdnSettingsValidateBeforeWritesAndNormalizeStorage(): void {
        $this->request('/plog-admin/plog-upload.php',['action'=>'log_in','plog_username'=>'fixture','plog_password'=>'fixture-password']);
        try {
            foreach (['javascript:fixture','https://cdn.example.test/#fragment','https://user:secret@cdn.example.test/','https://cdn.example.test/?key=secret'] as $bad) {
                [$body,$headers]=$this->request('/plog-admin/plog-options.php',['submit'=>'Save','cdn_url'=>$bad]);
                self::assertStringContainsString('400',$headers[0]);
            }
            [$body]=$this->request('/plog-admin/plog-options.php');
            $dom=new DOMDocument(); @$dom->loadHTML($body); $xpath=new DOMXPath($dom); $fields=[];
            $form=$xpath->query('//form[.//input[@name="cdn_url"]]')->item(0);
            foreach ($xpath->query('.//input[@name] | .//select[@name]', $form) as $input) {
                if (in_array($input->getAttribute('type'),['checkbox','radio'],true) && !$input->hasAttribute('checked')) continue;
                if ($input->tagName==='select') {
                    $option=$xpath->query('.//option[@selected]',$input)->item(0) ?? $input->getElementsByTagName('option')->item(0);
                    $fields[$input->getAttribute('name')]=$option ? $option->getAttribute('value') : '';
                } else $fields[$input->getAttribute('name')]=$input->getAttribute('value');
            }
            $fields['submit']='Save'; $fields['cdn_url']='HTTPS://CDN.EXAMPLE.TEST/base';
            $this->request('/plog-admin/plog-options.php',$fields);
            if (getenv('PLOGGER_TEST_DRIVER')) $stored=$this->nativeConnection()->query('SELECT "cdn_url" FROM "fixture_config"')->fetchColumn();
            else $stored=json_decode(file_get_contents(self::$root.'/fixture-cdn.json'),true);
            self::assertSame('https://cdn.example.test/base/',$stored);
            $this->setFixtureCdn('javascript:legacy-invalid');
            [, $headers]=$this->request('/index.php'); self::assertStringContainsString('500',$headers[0]);
            [$body,$headers]=$this->request('/plog-admin/plog-options.php');
            self::assertStringContainsString('200',$headers[0]); self::assertStringContainsString('name="cdn_url"',$body);
        } finally { $this->setFixtureCdn(''); self::$cookie=''; }
    }

    public function testLegacyAuthenticationUpgradesOnlyAfterSuccess(): void
    {
        $connection = getenv('PLOGGER_TEST_DRIVER') ? $this->nativeConnection() : null;
        $legacy = md5('fixture-password');
        if ($connection) {
            $statement = $connection->prepare('UPDATE "fixture_config" SET "admin_password" = ?');
            $statement->execute([$legacy]);
        } else {
            file_put_contents(self::$root.'/fixture-auth-hash.json', json_encode($legacy));
        }
        $read = static function () use ($connection): string {
            return $connection ? $connection->query('SELECT "admin_password" FROM "fixture_config"')->fetchColumn()
                : json_decode(file_get_contents(self::$root.'/fixture-auth-hash.json'), true);
        };
        self::$cookie = '';
        $this->request('/plog-admin/plog-upload.php', ['action' => 'log_in', 'plog_username' => 'fixture', 'plog_password' => 'wrong']);
        self::assertSame($legacy, $read());
        $oldCookie = self::$cookie;
        [, $headers] = $this->request('/plog-admin/plog-upload.php', ['action' => 'log_in', 'plog_username' => 'fixture', 'plog_password' => 'fixture-password']);
        self::assertStringContainsString('200', $headers[0]);
        self::assertNotSame($oldCookie, self::$cookie);
        self::assertNotSame($legacy, $read());
        self::assertTrue(plogger_password_verify('fixture-password', $read()));
        $modern = $read();
        [, $headers] = $this->request('/plog-admin/plog-upload.php', ['action' => 'log_in', 'plog_username' => 'fixture', 'plog_password' => 'fixture-password']);
        self::assertStringContainsString('200', $headers[0]);
        self::assertSame($modern, $read());
        if ($connection) $statement->execute([$legacy]);
        else file_put_contents(self::$root.'/fixture-auth-hash.json', json_encode($legacy));
        [$body] = $this->request('/plog-remote.php', ['cmd' => 'login', 'uname' => 'fixture', 'password' => 'fixture-password']);
        self::assertStringContainsString("status=0\n", $body);
        self::assertNotSame($legacy, $read());
        self::assertTrue(plogger_password_verify('fixture-password', $read()));
        self::$cookie = '';
    }

    public function testAdminLoginAcceptsSameOriginFormAndRejectsForeignOrigins(): void
    {
        self::$cookie = '';
        [$form, $headers] = $this->request('/plog-admin/index.php');
        self::assertContains('Referrer-Policy: same-origin', $headers);
        self::assertStringContainsString('name="_csrf"', $form);
        $token = $this->csrfToken();
        $login = ['action' => 'log_in', 'plog_username' => 'fixture',
            'plog_password' => 'fixture-password', '_csrf' => $token];

        [, $headers] = $this->request('/plog-admin/plog-upload.php', $login,
            "Origin: null\r\nSec-Fetch-Site: same-origin\r\n");
        self::assertStringContainsString('403', $headers[0]);
        [, $headers] = $this->request('/plog-admin/plog-upload.php', $login,
            "Origin: https://attacker.example\r\nSec-Fetch-Site: cross-site\r\n");
        self::assertStringContainsString('403', $headers[0]);

        [, $headers] = $this->request('/plog-admin/plog-upload.php', $login,
            'Origin: ' . self::$url . "\r\nSec-Fetch-Site: same-origin\r\n");
        self::assertStringContainsString('200', $headers[0]);
    }

    public function testLoginThrottlingSurvivesNewBrowserSessions(): void
    {
        for ($attempt = 0; $attempt < 5; ++$attempt) {
            self::$cookie = '';
            [, $headers] = $this->request('/plog-admin/plog-upload.php?fixture_throttle=1', [
                'action' => 'log_in', 'plog_username' => 'fixture', 'plog_password' => 'wrong']);
            self::assertStringContainsString('302', $headers[0]);
        }
        self::$cookie = '';
        [, $headers] = $this->request('/plog-admin/plog-upload.php?fixture_throttle=1', [
            'action' => 'log_in', 'plog_username' => 'fixture', 'plog_password' => 'fixture-password']);
        self::assertStringContainsString('302', $headers[0]);
        self::$cookie = '';
    }

    public function testOptionalOrderingAndBackfillHttpBoundaries(): void
    {
        [$body] = $this->request('/fixture-oypo.php');
        self::assertSame('', $body);
        [$body, $headers] = $this->request('/fixture-oypo.php?fixture_oypo=1');
        self::assertStringContainsString('200', $headers[0]);
        self::assertStringContainsString('Order photograph', $body);
        self::assertStringContainsString('https://example.invalid/order?album=fixture-album&amp;photo=fixture/photo%20%26.jpg', $body);
        self::assertStringContainsString('rel="noopener noreferrer"', $body);
        $this->request('/plog-admin/plog-upload.php', ['action'=>'log_in', 'plog_username'=>'fixture', 'plog_password'=>'fixture-password']);
        [$body] = $this->request('/plog-admin/plog-manage.php?action=edit-album&id=1&fixture_oypo=1');
        self::assertStringContainsString('name="oypo_album_id"', $body);
        [$body] = $this->request('/plog-admin/plog-manage.php?fixture_oypo=1', ['action'=>'update-album', 'pid'=>1,
            'name'=>'Album', 'description'=>'Synthetic album', 'thumbnail_id'=>0, 'oypo_album_id'=>'<script>bad</script>']);
        self::assertStringContainsString('Enter an Oypo album ID', $body);
        self::assertStringNotContainsString('<script>bad</script>', $body);
        [, $headers] = $this->request('/plog-admin/plog-dimensions.php', ['operation'=>'apply','after'=>0,'confirm'=>'yes','_csrf'=>'forged']);
        self::assertStringContainsString('403', $headers[0]);
        [$body, $headers] = $this->request('/plog-admin/plog-dimensions.php');
        // Earlier HTTP tests may have authenticated this shared fixture session.
        self::assertTrue(str_contains($headers[0], '302') || str_contains($body, 'Preview batch'));
        [, $headers] = $this->request('/plog-content/modules/oypo/module.php');
        self::assertStringContainsString('404', $headers[0]);
        self::$cookie = '';
    }

    public function testRemoteAdministrationIsDisabledByDefault(): void
    {
        [, $headers] = $this->request('/plog-remote.php?fixture_remote_disabled=1');
        self::assertStringContainsString('403', $headers[0]);
    }

    public function testPrivateAndMalformedPublicRequestsAreRejected(): void
    {
        [, $headers] = $this->request('/plog-includes/lib/phpthumb/phpThumb.php?src=plog-config.php');
        self::assertStringContainsString('403', $headers[0]);
        foreach (['level', 'id', 'mode', 'path', 'plog_page', 'searchterms', 'sortby', 'sortdir'] as $key) {
            [, $headers] = $this->request('/index.php?'.$key.'[]=fixture');
            self::assertStringContainsString('400', $headers[0]);
        }
        [$body, $headers] = $this->request('/plog-print.php?id=1');
        self::assertStringContainsString('200', $headers[0]);
        self::assertStringContainsString('<img', $body);
        if (!getenv('PLOGGER_TEST_DRIVER')) {
            [, $headers] = $this->request('/plog-download.php?fixture_disable_download=1', ['checked' => [1]]);
            self::assertStringContainsString('403', $headers[0]);
            [, $headers] = $this->request('/plog-print.php?id=1&fixture_disable_print=1');
            self::assertStringContainsString('403', $headers[0]);
        }
    }

    public function testNativePasswordResetIsCsrfProtectedAndSingleUse(): void
    {
        if (!getenv('PLOGGER_TEST_DRIVER')) self::markTestSkipped('Reset persistence uses native database fixtures.');
        $connection = $this->nativeConnection();
        $key = dechex(time()).'.'.bin2hex(random_bytes(24));
        $seed = $connection->prepare('UPDATE "fixture_config" SET "activation_key" = ?');
        $seed->execute([$key]);
        $before = $connection->query('SELECT "admin_password" FROM "fixture_config"')->fetchColumn();
        self::$cookie = '';
        if (getenv('PLOGGER_TEST_FPM_STACK') === '1') {
            $mail=sys_get_temp_dir().'/plog-session-fixture-'.basename(self::$root).'/mail.eml';
            [, $headers]=$this->request('/plog-admin/plog-admin.php',['action'=>'password_reset','admin_email'=>'fixture','_csrf'=>null]);
            self::assertStringContainsString('403',$headers[0]);
            self::assertFileDoesNotExist($mail);
            [, $headers]=$this->request('/plog-admin/plog-admin.php',['action'=>'password_reset','admin_email'=>'fixture']);
            self::assertStringContainsString('302',$headers[0]);
            $key=$connection->query('SELECT "activation_key" FROM "fixture_config"')->fetchColumn();
            self::assertTrue(plogger_reset_key_valid($key,$key));
            $message=file_get_contents($mail);
            self::assertStringContainsString('action=password_reset&key='.$key,$message);
            self::assertStringNotContainsString('fixture-password',$message);
            self::assertSame($before,$connection->query('SELECT "admin_password" FROM "fixture_config"')->fetchColumn());
        }
        [$body] = $this->request('/plog-admin/plog-admin.php?action=password_reset&key='.$key);
        self::assertStringContainsString('name="new_password"', $body);
        self::assertSame($before, $connection->query('SELECT "admin_password" FROM "fixture_config"')->fetchColumn());
        $fields = ['action' => 'password_reset', 'key' => $key, 'new_password' => "reset'fixture-password", 'confirm_password' => "reset'fixture-password"];
        [, $headers] = $this->request('/plog-admin/plog-admin.php', $fields + ['_csrf' => null]);
        self::assertStringContainsString('403', $headers[0]);
        self::assertSame($before, $connection->query('SELECT "admin_password" FROM "fixture_config"')->fetchColumn());
        [, $headers] = $this->request('/plog-admin/plog-admin.php', $fields);
        self::assertStringContainsString('302', $headers[0]);
        $after = $connection->query('SELECT "admin_password", "activation_key" FROM "fixture_config"')->fetch(PDO::FETCH_ASSOC);
        self::assertTrue(plogger_password_verify($fields['new_password'], $after['admin_password']));
        self::assertSame('', $after['activation_key']);
        $this->request('/plog-admin/plog-admin.php', $fields);
        self::assertSame($after['admin_password'], $connection->query('SELECT "admin_password" FROM "fixture_config"')->fetchColumn());
        [, $headers] = $this->request('/plog-admin/plog-upload.php', ['action' => 'log_in',
            'plog_username' => 'fixture', 'plog_password' => $fields['new_password']]);
        self::assertStringContainsString('200', $headers[0]);
        $restore = $connection->prepare('UPDATE "fixture_config" SET "admin_password" = ?');
        $restore->execute([md5('fixture-password')]);
        self::$cookie = '';
    }

    private function request(string $path, ?array $post = null, string $extraHeaders = ''): array
    {
        if ($post !== null && !array_key_exists('_csrf', $post)) $post['_csrf'] = $this->csrfToken();
        $headers = self::$cookie === '' ? '' : 'Cookie: ' . self::$cookie . "\r\n";
        $headers .= $extraHeaders;
        if ($post !== null) $headers .= "Content-Type: application/x-www-form-urlencoded\r\n";
        $context = stream_context_create(['http' => ['method' => $post === null ? 'GET' : 'POST',
            'header' => $headers, 'content' => $post === null ? '' : http_build_query($post),
            'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 30]]);
        [$body, $responseHeaders] = plogger_test_http_response(self::$url . $path, $context);
        self::assertNotFalse($body);
        foreach ($responseHeaders as $header) {
            if (preg_match('/^Set-Cookie: ([^;]+)/i', $header, $matches)) self::$cookie = $matches[1];
        }
        self::assertDoesNotMatchRegularExpression('/(?:Warning|Deprecated|Fatal error|Notice)(?:<\/b>)?:|A database error occurred|Database error\./', $body, $path . "\n" . $body);
        return [$body, $responseHeaders];
    }

    public function testLegacyLoginRedirectXssIsRejected(): void
    {
        foreach (['plog-upload.php', 'plog-manage.php', 'plog-options.php', 'plog-import.php', 'plog-dimensions.php',
            '" onmouseover="alert(2014)', '&#34; onmouseover=alert(2014)', '//example.com/',
            '../index.php', 'plog-upload.php?x=1', ['plog-upload.php']] as $target) {
            [$body] = $this->request('/plog-admin/index.php?' . http_build_query(['r' => $target]));
            $dom = new DOMDocument();
            @$dom->loadHTML($body);
            $form = $dom->getElementsByTagName('form')->item(0);
            $expected = is_string($target) && in_array($target,
                ['plog-upload.php', 'plog-manage.php', 'plog-options.php', 'plog-import.php', 'plog-dimensions.php'], true)
                ? $target : 'plog-upload.php';
            self::assertSame($expected, $form->getAttribute('action'));
            self::assertFalse($form->hasAttribute('onmouseover'));
        }
    }

    public function testHistoricalAlbumCsrfAndOtherWriteRoutesRejectUnprotectedRequests(): void
    {
        if (getenv('PLOGGER_TEST_DRIVER')) self::markTestSkipped('Mutation tracing requires synthetic PDO double.');
        $this->request('/plog-admin/plog-upload.php', ['action' => 'log_in',
            'plog_username' => 'fixture', 'plog_password' => 'fixture-password']);
        $token = $this->csrfToken();
        foreach (['plog-manage.php', 'plog-options.php', 'plog-upload.php', 'plog-import.php',
            'plog-feedback.php', 'plog-themes.php', 'plog-rpc.php', 'plog-thumb.php', '_install.php'] as $page) {
            [$body, $headers] = $this->request('/plog-admin/' . $page . '?fixture_csrf_trace=1',
                ['action' => 'update-album', 'pid' => '1', 'name' => 'Fixture album',
                    'description' => 'csrf fixture', 'thumbnail_id' => '0', '_csrf' => '']);
            self::assertStringContainsString('403', $headers[0], $page);
        }
        [$body, $headers] = $this->request('/plog-admin/plog-manage.php?action=update-album&pid=1');
        self::assertStringContainsString('405', $headers[0]);
        foreach ([null, 'bad', [], str_repeat('0', 64)] as $bad) {
            [$body, $headers] = $this->request('/plog-admin/plog-manage.php?fixture_csrf_trace=1',
                ['action' => 'update-album', 'pid' => '1', 'name' => 'album', 'description' => 'csrf fixture',
                    'thumbnail_id' => '0', '_csrf' => $bad]);
            self::assertStringContainsString('403', $headers[0]);
        }
        [$body, $headers] = $this->request('/plog-admin/plog-manage.php?fixture_csrf_trace=1',
            ['action' => 'update-album', 'pid' => '1', '_csrf' => $token], "Origin: https://attacker.example\r\n");
        self::assertStringContainsString('403', $headers[0]);
        [$body, $headers] = $this->request('/plog-admin/plog-rpc.php?fixture_csrf_trace=1',
            ['action' => 'update', 'field' => 'album-description-1', 'content' => 'fixture', '_csrf' => $token],
            "Sec-Fetch-Site: cross-site\r\n");
        self::assertStringContainsString('403', $headers[0]);
        self::assertFileDoesNotExist(self::$root . '/csrf-writes.log');
        [$body, $headers] = $this->request('/plog-admin/plog-manage.php?fixture_csrf_trace=1',
            ['action' => 'update-album', 'pid' => '1', 'name' => 'album', 'description' => 'Valid fixture',
                'thumbnail_id' => '0', '_csrf' => $token], 'Origin: ' . self::$url . "\r\n");
        self::assertStringContainsString('200', $headers[0]);
        self::assertStringContainsString('UPDATE "fixture_albums"', file_get_contents(self::$root . '/csrf-writes.log'));
        unlink(self::$root . '/csrf-writes.log');
        [$body, $headers] = $this->request('/plog-admin/_upgrade.php');
        self::assertStringContainsString('403', $headers[0]);
        foreach (['plog-manage.php', 'plog-options.php', 'plog-upload.php', 'plog-import.php', 'plog-themes.php'] as $page) {
            [$body] = $this->request('/plog-admin/' . $page);
            $dom = new DOMDocument();
            @$dom->loadHTML($body);
            $xpath = new DOMXPath($dom);
            foreach ($xpath->query('//form[translate(@method,"POST","post")="post"]') as $form) {
                self::assertSame(1, $xpath->query('.//input[@name="_csrf"]', $form)->length, $page);
            }
            self::assertSame(0, $xpath->query('//a[contains(@href,"action=move-delete") or contains(@href,"activate=") or contains(@href,"action=log_out")]')->length);
        }
        [$body, $headers] = $this->request('/plog-admin/plog-thumb.php', ['img' => md5(realpath(self::$root) . '/plog-content/uploads/import.jpg')]);
        self::assertStringContainsString('200', $headers[0]);
        self::assertStringContainsString('<img', $body);
        [$body, $headers] = $this->request('/plog-admin/plog-manage.php?fixture_csrf_trace=1',
            ['_delete_comment' => '999999', 'level' => 'comments', 'id' => '1']);
        self::assertStringContainsString('200', $headers[0]);
        self::assertStringContainsString('DELETE FROM "fixture_comments"', file_get_contents(self::$root . '/csrf-writes.log'));
        unlink(self::$root . '/csrf-writes.log');
        // A new browser session cannot reuse the authenticated session's token.
        self::$cookie = '';
        [$body, $headers] = $this->request('/plog-admin/plog-upload.php', ['action' => 'log_in',
            'plog_username' => 'fixture', 'plog_password' => 'fixture-password', '_csrf' => $token]);
        self::assertStringContainsString('403', $headers[0]);
    }

    public function testAdministrationCookiesAreHttpOnlyAndSameSite(): void
    {
        self::$cookie = '';
        [$body, $headers] = $this->request('/plog-admin/index.php');
        $cookieHeaders = implode("\n", array_filter($headers, static fn ($header) => stripos($header, 'Set-Cookie:') === 0));
        self::assertStringContainsString('HttpOnly', $cookieHeaders);
        self::assertStringContainsString('SameSite=Lax', $cookieHeaders);
        $dom = new DOMDocument();
        @$dom->loadHTML($body);
        self::assertSame(1, (new DOMXPath($dom))->query('//form//input[@name="_csrf"]')->length);
    }

    public function testDatabaseSettingsPasswordAndCommentValuesAreStoredExactly(): void
    {
        if (!getenv('PLOGGER_TEST_DRIVER')) self::markTestSkipped('Exact stored-value regression uses the native database matrix.');
        $connection = new PDO(getenv('PLOGGER_TEST_DRIVER') . ':host=127.0.0.1;port=' . getenv('PLOGGER_TEST_PORT') . ';dbname=' . getenv('PLOGGER_TEST_DATABASE'),
            getenv('PLOGGER_TEST_USER'), getenv('PLOGGER_TEST_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        if (getenv('PLOGGER_TEST_DRIVER') === 'mysql') $connection->exec("SET SESSION sql_mode = 'ANSI,STRICT_ALL_TABLES'");
        $this->request('/plog-admin/plog-upload.php', ['action' => 'log_in', 'plog_username' => 'fixture', 'plog_password' => 'fixture-password']);
        [$body] = $this->request('/plog-admin/plog-options.php');
        $dom = new DOMDocument();
        @$dom->loadHTML($body);
        $fields = [];
        $xpath = new DOMXPath($dom);
        $form = $xpath->query('//form[.//input[@name="gallery_name"]]')->item(0);
        foreach ($xpath->query('.//input[@name] | .//select[@name] | .//textarea[@name]', $form) as $input) {
            $name = $input->getAttribute('name');
            if (in_array($input->getAttribute('type'), ['checkbox', 'radio'], true) && !$input->hasAttribute('checked')) continue;
            if ($input->tagName === 'select') {
                $option = $xpath->query('.//option[@selected]', $input)->item(0) ?? $input->getElementsByTagName('option')->item(0);
                $fields[$name] = $option ? $option->getAttribute('value') : '';
            } else $fields[$name] = $input->tagName === 'textarea' ? $input->textContent : $input->getAttribute('value');
        }
        $fields['submit'] = 'Save';
        $fields['gallery_name'] = "O'Brien café ' OR 1=1 --";
        $fields['admin_password'] = $fields['confirm_admin_password'] = "new'fixture-password";
        $this->request('/plog-admin/plog-options.php', $fields);
        $row = $connection->query('SELECT "gallery_name", "admin_password" FROM "fixture_config"')->fetch(PDO::FETCH_ASSOC);
        self::assertSame($fields['gallery_name'], $row['gallery_name']);
        self::assertTrue(plogger_password_verify($fields['admin_password'], $row['admin_password']));
        [, $headers] = $this->request('/plog-admin/plog-upload.php', ['action' => 'log_in',
            'plog_username' => 'fixture', 'plog_password' => $fields['admin_password']]);
        self::assertStringContainsString('200', $headers[0]);
        $restore = $connection->prepare('UPDATE "fixture_config" SET "gallery_name" = ?, "admin_password" = ?');
        $restore->execute(['Synthetic gallery', md5('fixture-password')]);
        $this->request('/plog-admin/plog-upload.php', ['action' => 'log_in', 'plog_username' => 'fixture', 'plog_password' => 'fixture-password']);
        $value = "Comment O'Brien café\n' OR 1=1 --";
        $insert = $connection->prepare('INSERT INTO "fixture_comments" ("author", "email", "url", "date", "comment", "parent_id", "approved", "ip") VALUES (?, ?, ?, CURRENT_TIMESTAMP, ?, ?, ?, ?)');
        $insert->execute(['fixture', 'fixture@example.invalid', '', 'before', 1, 1, '127.0.0.1']);
        $id = $connection->lastInsertId(getenv('PLOGGER_TEST_DRIVER') === 'pgsql' ? 'fixture_comments_id_seq' : null);
        $this->request('/plog-admin/plog-rpc.php', ['action' => 'update', 'field' => 'comment-comment-' . $id, 'content' => $value]);
        $read = $connection->prepare('SELECT "comment" FROM "fixture_comments" WHERE "id" = ?');
        $read->execute([(int) $id]);
        self::assertSame($value, $read->fetchColumn());
        $remove = $connection->prepare('DELETE FROM "fixture_comments" WHERE "id" = ?');
        $remove->execute([(int) $id]);
        [$body] = $this->request('/index.php?level=search&searchterms=Fixture%2Bcaption');
        self::assertStringContainsString('Fixture caption', $body);
        [$body] = $this->request('/index.php?level=search&searchterms=' . rawurlencode("' OR 1=1 --"));
        self::assertStringNotContainsString('Fixture caption', $body);
        [$body, $headers] = $this->request('/plog-admin/plog-manage.php?level=' . rawurlencode('albums; DROP TABLE fixture_albums'));
        self::assertStringContainsString('400', $headers[0]);
        // Exercise typed CRUD and case-sensitive identifiers on a temporary fixture table.
        $connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $table = plogger_db_identifier('Case_42_Albums');
        plogger_db_execute($connection, 'CREATE TEMPORARY TABLE '.$table.' ("id" INTEGER NOT NULL, "value" VARCHAR(255) NULL)');
        plogger_db_execute($connection, 'INSERT INTO '.$table.' ("id", "value") VALUES (?, ?)', [1, $value]);
        self::assertSame($value, plogger_db_execute($connection, 'SELECT "value" FROM '.$table.' WHERE "id" = ?', [1])->fetchColumn());
        plogger_db_execute($connection, 'UPDATE '.$table.' SET "value" = ? WHERE "id" = ?', [null, 1]);
        self::assertNull(plogger_db_execute($connection, 'SELECT "value" FROM '.$table.' WHERE "id" = ?', [1])->fetchColumn());
        plogger_db_execute($connection, 'DELETE FROM '.$table.' WHERE "id" = ?', [1]);
        self::assertSame(0, (int) plogger_db_execute($connection, 'SELECT COUNT(*) FROM '.$table)->fetchColumn());
        self::$cookie = '';
    }

    public function testManagementRejectsMalformedWholeSelectionsBeforeAnyChanges(): void
    {
        $this->request('/plog-admin/plog-upload.php',['action'=>'log_in','plog_username'=>'fixture','plog_password'=>'fixture-password']);
        $connection = getenv('PLOGGER_TEST_DRIVER') ? $this->nativeConnection() : null;
        $snapshot = static function () use ($connection): array {
            if (!$connection) return [];
            $rows = [];
            foreach (['collections','albums','pictures','comments'] as $table) {
                $statement = $connection->prepare('SELECT * FROM '.plogger_db_identifier('fixture_'.$table).' ORDER BY "id"');
                $statement->execute(); $rows[$table] = $statement->fetchAll(PDO::FETCH_ASSOC);
            }
            return $rows;
        };
        $before = $snapshot();
        $fileSnapshot = static function (): array {
            $files=[];
            foreach (['images','thumbs'] as $directory) {
                $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::$root.'/plog-content/'.$directory,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::SELF_FIRST);
                foreach ($iterator as $file) {
                    if ($file->isLink()) $files[$file->getPathname()]='symlink';
                    else $files[$file->getPathname()]=$file->isDir() ? 'directory' : hash_file('sha256',$file->getPathname());
                }
            }
            ksort($files); return $files;
        };
        $filesBefore=$fileSnapshot();
        $trace = self::$root.'/csrf-writes.log';
        if (is_file($trace)) unlink($trace);
        try {
            foreach (['albums','collections','pictures'] as $level) {
                foreach (["1' OR 'fixture'='fixture", '1junk', '1e2', '0', '-1', str_repeat('9',40), ['1']] as $bad) {
                    foreach ([['action'=>'move-delete','delete_checked'=>1,'selected'=>[1,$bad]],['_delete_one'=>$bad]] as $fields) {
                        [, $headers] = $this->request('/plog-admin/plog-manage.php?fixture_csrf_trace=1', $fields+['level'=>$level,'id'=>1]);
                        self::assertStringContainsString('400',$headers[0]);
                        self::assertFileDoesNotExist($trace);
                        self::assertSame($before,$snapshot());
                        self::assertSame($filesBefore,$fileSnapshot());
                        self::assertSame(self::$originalHash,hash_file('sha256',self::$root.'/plog-content/images/fixture/album/photo.jpg'));
                    }
                }
            }
            foreach (['1',['bad'=>1],[[1]]] as $selection) {
                [, $headers] = $this->request('/plog-admin/plog-manage.php?fixture_csrf_trace=1', ['action'=>'move-delete','delete_checked'=>1,'level'=>'albums','selected'=>$selection]);
                self::assertStringContainsString('400',$headers[0]);
            }
            foreach (["1' OR 1=1", ['1'],0] as $destination) {
                [, $headers] = $this->request('/plog-admin/plog-manage.php?fixture_csrf_trace=1', ['action'=>'move-delete','move_checked'=>1,'level'=>'albums','selected'=>[1],'group_id'=>$destination]);
                self::assertStringContainsString('400',$headers[0]);
            }
            [, $headers] = $this->request('/plog-admin/plog-manage.php?fixture_csrf_trace=1', ['action'=>'move-delete','delete_checked'=>1,'level'=>'albums','selected'=>[1,'bad'],'_delete_comment'=>1]);
            self::assertStringContainsString('400',$headers[0]);
            self::assertSame($filesBefore,$fileSnapshot());
            [$body,$headers] = $this->request('/plog-admin/plog-manage.php', ['action'=>'move-delete','delete_checked'=>1,'level'=>'albums']);
            self::assertStringContainsString('200',$headers[0]);
            self::assertStringContainsString('Nothing selected to delete',$body);
            self::assertFileDoesNotExist($trace);
            self::assertSame($before,$snapshot());
        } finally { self::$cookie = ''; }
    }

    public function testNativeManagementDeletesOnlyTheSelectedAlbums(): void
    {
        if (!getenv('PLOGGER_TEST_DRIVER')) self::markTestSkipped('Valid deletion persistence requires isolated native MySQL/MariaDB or PostgreSQL.');
        $db = $this->nativeConnection();
        $this->request('/plog-admin/plog-upload.php',['action'=>'log_in','plog_username'=>'fixture','plog_password'=>'fixture-password']);
        $albums = [];
        try {
            for ($index=0; $index<4; ++$index) {
                $name = 'RB01-'.bin2hex(random_bytes(5));
                [$body,$headers] = $this->request('/plog-admin/plog-manage.php',['action'=>'add-album','name'=>$name,'description'=>'Synthetic RB-01 fixture','parent_collection'=>1]);
                self::assertStringContainsString('200',$headers[0]);
                $read=$db->prepare('SELECT "id", "path" FROM "fixture_albums" WHERE "name" = ?');
                $read->execute([$name]); $album=$read->fetch(PDO::FETCH_ASSOC);
                self::assertIsArray($album); $albums[]=$album;
            }
            $sentinel=self::$root.'/plog-content/images/fixture/'.$albums[3]['path'].'/sentinel.txt';
            file_put_contents($sentinel,'Unselected synthetic album');
            $hash=hash_file('sha256',$sentinel);
            $this->request('/plog-admin/plog-manage.php',['level'=>'albums','id'=>1,'_delete_one'=>$albums[0]['id']]);
            $this->request('/plog-admin/plog-manage.php',['level'=>'albums','id'=>1,'action'=>'move-delete','delete_checked'=>1,
                'selected'=>[$albums[1]['id'],$albums[2]['id'],$albums[2]['id']]]);
            $read=$db->prepare('SELECT COUNT(*) FROM "fixture_albums" WHERE "id" = ?');
            foreach (array_slice($albums,0,3) as $album) {
                $read->execute([(int)$album['id']]); self::assertSame(0,(int)$read->fetchColumn());
                self::assertDirectoryDoesNotExist(self::$root.'/plog-content/images/fixture/'.$album['path']);
            }
            $read->execute([(int)$albums[3]['id']]); self::assertSame(1,(int)$read->fetchColumn());
            self::assertSame($hash,hash_file('sha256',$sentinel));
            self::assertSame(self::$originalHash,hash_file('sha256',self::$root.'/plog-content/images/fixture/album/photo.jpg'));
            unlink($sentinel);
            $this->request('/plog-admin/plog-manage.php',['level'=>'albums','id'=>1,'_delete_one'=>$albums[3]['id']]);
        } finally { self::$cookie=''; }
    }

    public function testNativeAlbumCsrfLeavesDatabaseUnchanged(): void
    {
        $driver = getenv('PLOGGER_TEST_DRIVER');
        if (!$driver) self::markTestSkipped('Native database regression runs in the dedicated driver matrix.');
        $database = getenv('PLOGGER_TEST_DATABASE');
        $port = (int) getenv('PLOGGER_TEST_PORT');
        if (!preg_match('/^plogger_phase1_ci_[a-f0-9]{16}$/D', $database) || $port < 20000) {
            throw new RuntimeException('Refusing unsafe database regression target.');
        }
        $connection = new PDO($driver . ':host=127.0.0.1;port=' . $port . ';dbname=' . $database,
            getenv('PLOGGER_TEST_USER'), getenv('PLOGGER_TEST_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        if ($driver === 'mysql') $connection->exec("SET SESSION sql_mode = 'ANSI,STRICT_ALL_TABLES'");
        $read = $connection->prepare('SELECT * FROM "fixture_albums" WHERE "id" = ?');
        $read->execute([1]);
        $before = $read->fetch(PDO::FETCH_ASSOC);
        $this->request('/plog-admin/plog-upload.php', ['action' => 'log_in',
            'plog_username' => 'fixture', 'plog_password' => 'fixture-password']);
        $token = $this->csrfToken();
        $fields = ['action' => 'update-album', 'pid' => '1', 'name' => $before['name'],
            'description' => 'CSRF must not store this', 'thumbnail_id' => $before['thumbnail_id']];
        foreach ([null, 'invalid', []] as $bad) {
            [$body, $headers] = $this->request('/plog-admin/plog-manage.php?level=albums&id=1', $fields + ['_csrf' => $bad]);
            self::assertStringContainsString('403', $headers[0]);
        }
        [$body, $headers] = $this->request('/plog-admin/plog-manage.php?level=albums&id=1',
            $fields + ['_csrf' => $token], "Origin: https://attacker.example\r\n");
        self::assertStringContainsString('403', $headers[0]);
        $read->execute([1]);
        self::assertSame($before, $read->fetch(PDO::FETCH_ASSOC));
        [$body, $headers] = $this->request('/plog-admin/plog-manage.php?level=albums&id=1', $fields + ['_csrf' => $token]);
        self::assertStringContainsString('200', $headers[0]);
        $read->execute([1]);
        self::assertSame($fields['description'], $read->fetch(PDO::FETCH_ASSOC)['description']);
        $fields['description'] = $before['description'];
        $this->request('/plog-admin/plog-manage.php?level=albums&id=1', $fields + ['_csrf' => $token]);
        $read->execute([1]);
        self::assertSame($before, $read->fetch(PDO::FETCH_ASSOC));
        self::assertSame(self::$originalHash, hash_file('sha256', self::$root . '/plog-content/images/fixture/album/photo.jpg'));
        self::$cookie = '';
    }

    public function testLegacyQueryParameterXssIsEncodedAndNavigationPreserved(): void
    {
        $parameters = ['host' => 'a&b=two "quotes"', 'entity' => '&#34; &quot;',
            'injection' => '" onmouseover="alert(2014)', 'jump-menu' => '" onfocus="alert(2014)',
            'filters' => ['name' => 'café & tea'], 'empty' => ''];
        foreach (['default', 'air', 'lucid', 'xip-bs', 'reloaded'] as $theme) {
            [$body] = $this->request('/index.php?' . http_build_query($parameters + ['fixture_theme' => $theme]));
            $dom = new DOMDocument();
            @$dom->loadHTML($body);
            $xpath = new DOMXPath($dom);
            foreach ($xpath->query('//@*') as $attribute) {
                if (str_starts_with(strtolower($attribute->name), 'on')) {
                    self::assertStringNotContainsString('alert(2014)', $attribute->value);
                }
            }
            $inputs = $xpath->query('//form[@id="plogsearch"]//input[@type="hidden"]');
            $values = [];
            foreach ($inputs as $input) $values[$input->getAttribute('name')] = $input->getAttribute('value');
            self::assertSame($parameters['host'], $values['host']);
            self::assertSame($parameters['entity'], $values['entity']);
            self::assertSame($parameters['filters']['name'], $values['filters[name]']);
            self::assertArrayNotHasKey('jump-menu', $values);
            $found = false;
            foreach ($xpath->query('//*[@href or @value]') as $element) {
                $url = $element->getAttribute('href') ?: $element->getAttribute('value');
                $query = parse_url($url, PHP_URL_QUERY);
                if (!is_string($query)) continue;
                parse_str($query, $args);
                if (isset($args['host'])) {
                    self::assertSame($parameters['host'], $args['host']);
                    self::assertSame($parameters['entity'], $args['entity']);
                    self::assertArrayNotHasKey('jump-menu', $args);
                    $found = true;
                }
            }
            self::assertTrue($found);
        }
    }

    public function testStoredDescriptionsAreSafeInThemesAndAdministration(): void
    {
        require_once __DIR__ . '/fixtures/FixtureDatabase.php';
        $db = getenv('PLOGGER_TEST_DRIVER') ? $this->nativeConnection() : null;
        $before = [];
        $originalTheme = $db ? $db->query('SELECT "theme_dir" FROM "fixture_config"')->fetchColumn() : null;
        try {
        if ($db) {
            foreach (['collections','albums','pictures'] as $table) {
                $identifier=plogger_db_identifier('fixture_'.$table);
                $before[$table]=$db->query('SELECT "description" FROM '.$identifier.' WHERE "id" = 1')->fetchColumn();
                $statement=$db->prepare('UPDATE '.$identifier.' SET "description" = ? WHERE "id" = 1');
                $statement->execute([FixtureDatabase::DESCRIPTION_PAYLOAD]);
            }
        }
        $pages = [];
        foreach (['default', 'air', 'lucid', 'xip-bs', 'reloaded'] as $theme) {
            foreach (['collections', 'collection', 'album', 'picture'] as $level) {
                $pages[] = '/fixture-descriptions.php?fixture_stored_description=1&fixture_theme=' . $theme . '&fixture_level=' . $level;
            }
        }
        $this->request('/plog-admin/plog-upload.php', ['action' => 'log_in',
            'plog_username' => 'fixture', 'plog_password' => 'fixture-password']);
        foreach (['collections', 'albums', 'pictures'] as $level) {
            $pages[] = '/plog-admin/plog-manage.php?fixture_stored_description=1&level=' . $level . '&id=1';
        }
        foreach ($pages as $page) {
            if ($db) {
                parse_str((string)parse_url($page,PHP_URL_QUERY),$parameters);
                if (isset($parameters['fixture_theme'])) $this->request('/plog-admin/plog-themes.php',['activate'=>$parameters['fixture_theme']]);
            }
            [$body] = $this->request($page);
            $dom = new DOMDocument();
            @$dom->loadHTML($body);
            $xpath = new DOMXPath($dom);
            self::assertSame(0, $xpath->query('//script[contains(.,"storedFixture")]|//@*[starts-with(name(),"on") and contains(.,"storedFixture")]|//@href[contains(.,"storedFixture")]')->length, $page);
        }
        foreach (['picture', 'album', 'collection'] as $kind) {
            [$body] = $this->request('/plog-admin/plog-rpc.php', ['action' => 'update',
                'field' => $kind . '-description-1', 'content' => FixtureDatabase::DESCRIPTION_PAYLOAD]);
            self::assertStringNotContainsString('<script>', $body);
            self::assertStringNotContainsString('onclick=', $body);
            self::assertStringNotContainsString('onerror=', $body);
        }
        } finally {
            if ($db) {
                foreach ($before as $table=>$description) {
                    $statement=$db->prepare('UPDATE '.plogger_db_identifier('fixture_'.$table).' SET "description" = ? WHERE "id" = 1');
                    $statement->execute([$description]);
                }
                $db->prepare('UPDATE "fixture_config" SET "theme_dir" = ?')->execute([$originalTheme]);
            }
            self::$cookie = '';
        }
    }

    public function testStoredNamesCaptionsAndExifAreInertInBundledThemes(): void
    {
        if (getenv('PLOGGER_TEST_DRIVER')) self::markTestSkipped('Payload rows use the isolated PDO double.');
        foreach (['default', 'air', 'lucid', 'xip-bs', 'reloaded'] as $theme) {
            foreach (['collections', 'collection', 'album', 'picture'] as $level) {
                [$body] = $this->request('/fixture-descriptions.php?fixture_stored_text=1&fixture_theme='.$theme.'&fixture_level='.$level);
                $dom = new DOMDocument(); @$dom->loadHTML($body);
                $xpath = new DOMXPath($dom);
                self::assertSame(0, $xpath->query('//script[normalize-space(.)="storedTextFixture()"]|//@*[starts-with(name(),"on") and contains(.,"storedTextFixture")]')->length, $theme.' '.$level);
            }
        }
        $this->request('/plog-admin/plog-upload.php', ['action' => 'log_in', 'plog_username' => 'fixture', 'plog_password' => 'fixture-password']);
        foreach (['plog-upload.php', 'plog-import.php?nojs=1', 'plog-manage.php?level=albums&id=1', 'plog-options.php'] as $page) {
            [$body] = $this->request('/plog-admin/'.$page.(str_contains($page, '?') ? '&' : '?').'fixture_stored_text=1');
            $dom = new DOMDocument(); @$dom->loadHTML($body);
            self::assertSame(0, (new DOMXPath($dom))->query('//script[normalize-space(.)="storedTextFixture()"]|//@*[starts-with(name(),"on") and contains(.,"storedTextFixture")]')->length, $page);
        }
        self::$cookie = '';
    }

    public function testGalleryPictureThumbnailAndDownloadWithUnchangedOriginal(): void
    {
        [$body] = $this->request('/index.php');
        self::assertStringContainsString('Fixture caption', $body);
        [$body] = $this->request('/index.php?level=picture&id=1');
        self::assertStringContainsString('Fixture description', $body);
        [$body, $headers] = $this->request('/plog-thumb.php?id=1&type=1');
        self::assertStringContainsString('302', $headers[0]);
        [$body, $headers] = $this->request('/plog-thumb.php?id=999&type=1');
        self::assertStringContainsString('404', $headers[0]);
        [$body] = $this->request('/plog-download.php', ['checked' => [1], 'dl_type' => 'album']);
        self::assertStringStartsWith("PK\x03\x04", $body);
        $zipPath = self::$root.'/download-fixture.zip'; file_put_contents($zipPath,$body);
        $zip = new ZipArchive();
        self::assertTrue($zip->open($zipPath) === true);
        try {
            self::assertSame(1,$zip->numFiles);
            self::assertSame(self::$originalHash,hash('sha256',$zip->getFromName('fixture/album/photo.jpg')));
        } finally { $zip->close(); unlink($zipPath); }
        [, $repeatHeaders] = $this->request('/plog-thumb.php?id=1&type=1');
        self::assertStringContainsString('302', $repeatHeaders[0]);
        self::assertSame(self::$originalHash, hash_file('sha256', self::$root . '/plog-content/images/fixture/album/photo.jpg'));
    }

    public function testZipUploadPreviewAndFtpCommitUseNativeArchive(): void {
        require_once __DIR__.'/MetadataTest.php';
        $this->request('/plog-admin/plog-upload.php',['action'=>'log_in',
            'plog_username'=>'fixture','plog_password'=>'fixture-password']);
        $zipPath=self::$root.'/upload-fixture.zip';
        $zip=new ZipArchive(); self::assertTrue($zip->open($zipPath,ZipArchive::CREATE) === true);
        $bytes=MetadataTest::jpeg();
        $zip->addFromString('nested/zip-fixture.jpg',$bytes); $zip->close();
        $boundary='plog-'.bin2hex(random_bytes(8));
        $content='';
        foreach (['_csrf'=>$this->csrfToken(),'upload'=>'Upload'] as $name=>$value) {
            $content.="--$boundary\r\nContent-Disposition: form-data; name=\"$name\"\r\n\r\n$value\r\n";
        }
        $content.="--$boundary\r\nContent-Disposition: form-data; name=\"userfile\"; filename=\"fixture.zip\"\r\nContent-Type: application/zip\r\n\r\n"
            .file_get_contents($zipPath)."\r\n--$boundary--\r\n";
        $context=stream_context_create(['http'=>['method'=>'POST',
            'header'=>"Cookie: ".self::$cookie."\r\nContent-Type: multipart/form-data; boundary=$boundary\r\n",
            'content'=>$content,'ignore_errors'=>true,'timeout'=>30]]);
        $body=file_get_contents(self::$url.'/plog-admin/plog-upload.php',false,$context);
        self::assertStringContainsString('zip-fixture.jpg',$body);
        $folders=glob(self::$root.'/plog-content/uploads/archive-*');
        self::assertCount(1,$folders);
        $image=$folders[0].'/zip-fixture.jpg';
        self::assertSame(hash('sha256',$bytes),hash_file('sha256',$image));
        $key=md5(realpath(self::$root).'/plog-content/uploads/'.basename($folders[0]).'/zip-fixture.jpg');
        [$body]=$this->request('/plog-admin/plog-import.php',
            ['upload'=>'Import','destination_radio'=>'existing','destination'=>'1','selected'=>[$key],
                'captions'=>[$key=>'ZIP fixture'],'descriptions'=>[$key=>'ZIP fixture description']]);
        self::assertStringContainsString('successfully imported',$body);
        self::assertFileDoesNotExist($image);
        self::assertSame(hash('sha256',$bytes),hash_file('sha256',self::$root.'/plog-content/images/fixture/album/zip-fixture.jpg'));
        if (getenv('PLOGGER_TEST_DRIVER')) {
            $statement=$this->nativeConnection()->prepare('SELECT * FROM "fixture_pictures" WHERE "caption" = ?');
            $statement->execute(['ZIP fixture']); $row=$statement->fetch(PDO::FETCH_ASSOC);
            self::assertSame('Fixture Camera',$row['exif_camera']);
            self::assertSame('1/125 sec',$row['exif_shutterspeed']);
            self::assertSame('f/2.8',$row['exif_aperture']);
            self::assertSame('50 mm',$row['exif_focallength']);
            self::assertSame('Flash',$row['exif_flash']);
            self::assertSame('200',$row['exif_iso']);
            self::assertStringStartsWith('2026-10-15 12:34:56',$row['exif_date_taken']);
            self::assertSame('ZIP fixture description',$row['description']);
        }
        self::assertSame(self::$originalHash,hash_file('sha256',self::$root.'/plog-content/images/fixture/album/photo.jpg'));
        unlink($zipPath);
        self::$cookie='';
    }

    public function testThumbnailCacheReusesValidFilesAndRegeneratesCorruption(): void {
        [$url]=$this->request('/fixture-thumbnail.php');
        $relative=rawurldecode(substr($url,strlen(self::$url)+1));
        self::assertStringStartsWith('plog-content/thumbs/',$relative);
        $path=self::$root.'/'.$relative;
        self::assertFileExists($path);
        $hash=hash_file('sha256',$path);
        touch($path,time()+20); clearstatcache(true,$path); $timestamp=filemtime($path);
        [$repeat]=$this->request('/fixture-thumbnail.php'); clearstatcache(true,$path);
        self::assertSame($url,$repeat); self::assertSame($timestamp,filemtime($path));
        file_put_contents($path,'corrupt synthetic derivative');
        [$repeat]=$this->request('/fixture-thumbnail.php');
        self::assertSame($url,$repeat); self::assertSame($hash,hash_file('sha256',$path));
        [$changed]=$this->request('/fixture-thumbnail.php?quality=50');
        self::assertNotSame($url,$changed);
        self::assertSame(self::$originalHash,hash_file('sha256',self::$root.'/plog-content/images/fixture/album/photo.jpg'));
    }

    public function testAdminLoginUploadFormAndFtpImportPreview(): void
    {
        [$body] = $this->request('/plog-admin/index.php');
        self::assertStringContainsString('plog_username', $body);
        [, $headers] = $this->request('/plog-admin/plog-upload.php', [
            'action' => 'log_in', 'plog_username' => 'fixture', 'plog_password' => 'fixture-password']);
        self::assertStringContainsString('200', $headers[0]);
        [$body] = $this->request('/plog-admin/plog-upload.php');
        self::assertStringContainsString('Upload Images', $body);
        $directory = md5(self::$root . '/plog-content/uploads');
        [$body] = $this->request('/plog-admin/plog-import.php?directory=' . $directory);
        self::assertStringContainsString('import.jpg', $body);
        [$body] = $this->request('/plog-admin/plog-options.php');
        self::assertStringContainsString('Synthetic gallery', $body);
        foreach (['plog-manage.php', 'plog-feedback.php', 'plog-plugins.php', 'plog-rpc.php'] as $page) {
            $this->request('/plog-admin/' . $page);
        }
    }

    public function testFtpPreviewPaginationVisitsEachSyntheticSourceOnce(): void
    {
        $directory = self::$root.'/plog-content/uploads/batch-fixture'; mkdir($directory);
        $bytes = file_get_contents(self::$root.'/plog-content/images/fixture/album/photo.jpg');
        for ($i=0; $i<13; ++$i) file_put_contents($directory.'/batch-'.$i.'.jpg', $bytes);
        try {
            $this->request('/plog-admin/plog-upload.php', ['action'=>'log_in', 'plog_username'=>'fixture', 'plog_password'=>'fixture-password']);
            $url = '/plog-admin/plog-import.php'; $seen = [];
            for ($page=0; $url !== '' && $page<8; ++$page) {
                [$body] = $this->request($url);
                $dom = new DOMDocument(); @$dom->loadHTML($body); $xpath = new DOMXPath($dom);
                self::assertLessThanOrEqual(10, $xpath->query('//input[starts-with(@name,"captions[")]')->length);
                preg_match_all('~batch-fixture/batch-(\d+)\.jpg~', $body, $matches);
                foreach ($matches[1] as $number) { self::assertNotContains($number, $seen); $seen[] = $number; }
                $link = $xpath->query('//a[contains(@href,"batch=")]')->item(0);
                $url = $link ? '/plog-admin/'.$link->getAttribute('href') : '';
            }
            self::assertSame('', $url);
            self::assertCount(13, $seen);
            foreach (glob($directory.'/*') as $file) self::assertSame(hash('sha256',$bytes), hash_file('sha256',$file));
        } finally {
            foreach (glob($directory.'/*') as $file) unlink($file);
            rmdir($directory); self::$cookie='';
        }
    }

    public function testMetadataPreviewEscapesPayloadsAndPreservesExplicitEdits(): void
    {
        require_once __DIR__.'/MetadataTest.php';
        $source = self::$root.'/plog-content/uploads/metadata-preview.jpg';
        $unselected = self::$root.'/plog-content/uploads/metadata-unselected.jpg';
        copy(self::$root.'/plog-content/images/fixture/album/photo.jpg', $unselected);
        file_put_contents($source, MetadataTest::jpeg());
        $title = 'Café " onfocus="fixture <script>fixture</script>';
        $description = "First é\n</textarea><img src=x onerror=fixture>\nLast &amp; line";
        $iptc = "\x1c\x02\x05".pack('n',strlen($title)).$title
            ."\x1c\x02\x78".pack('n',strlen($description)).$description;
        file_put_contents($source, iptcembed($iptc, $source));
        $original = hash_file('sha256', $source);
        $this->request('/plog-admin/plog-upload.php', ['action'=>'log_in', 'plog_username'=>'fixture', 'plog_password'=>'fixture-password']);
        [$rejected] = $this->request('/plog-admin/plog-import.php', ['upload'=>'Import',
            'destination_radio'=>'existing', 'destination'=>'1', 'selected'=>[str_repeat('f',32)]]);
        self::assertStringContainsString('preview expired', $rejected);
        self::assertFileExists($source);
        self::assertFileDoesNotExist(self::$root.'/plog-content/images/fixture/album/metadata-preview.jpg');
        [$body] = $this->request('/plog-admin/plog-import.php?nojs=1');
        $dom = new DOMDocument(); @$dom->loadHTML($body); $xpath = new DOMXPath($dom);
        $field = $xpath->query('//input[starts-with(@name,"captions[") and contains(@value,"Café")]')->item(0);
        self::assertInstanceOf(DOMElement::class, $field);
        self::assertSame($title, $field->getAttribute('value'));
        self::assertFalse($field->hasAttribute('onfocus'));
        preg_match('/captions\[([a-f0-9]{32})\]/', $field->getAttribute('name'), $match); $key = $match[1];
        $otherField = $xpath->query('//tr[td[contains(.,"metadata-unselected.jpg")]]//input[starts-with(@name,"captions[")]')->item(0);
        preg_match('/captions\[([a-f0-9]{32})\]/', $otherField->getAttribute('name'), $match); $otherKey = $match[1];
        self::assertSame($description, $xpath->query('//textarea[@name="descriptions['.$key.']"]')->item(0)->textContent);
        self::assertSame(0, $xpath->query('//*[@onerror or @onfocus]')->length);
        self::assertStringContainsString('IPTC.2#005', $body);
        self::assertStringContainsString('Fixture Camera', $body);
        self::assertStringContainsString('80 × 40', $body);
        self::assertSame($original, hash_file('sha256', $source));
        // A destination error must redisplay exact edits rather than metadata defaults.
        $manual = "  Manual é\n\nLast &amp; line  ";
        $post = ['upload'=>'Import', 'destination_radio'=>'new', 'new_album_name'=>'', 'selected'=>[$key],
            'captions'=>[$key=>'', $otherKey=>'Unselected é'], 'descriptions'=>[$key=>$manual, $otherKey=>'Keep this edit']];
        [$body] = $this->request('/plog-admin/plog-import.php', $post);
        @$dom->loadHTML($body); $xpath = new DOMXPath($dom);
        self::assertSame('', $xpath->query('//input[@name="captions['.$key.']"]')->item(0)->getAttribute('value'));
        self::assertSame($manual, $xpath->query('//textarea[@name="descriptions['.$key.']"]')->item(0)->textContent);
        self::assertFileExists($source);
        $post['destination_radio']='existing'; $post['destination']='1';
        [$body] = $this->request('/plog-admin/plog-import.php', $post);
        self::assertStringContainsString('successfully imported', $body);
        self::assertFileExists($unselected);
        @$dom->loadHTML($body); $xpath = new DOMXPath($dom);
        self::assertSame('Unselected é', $xpath->query('//input[@name="captions['.$otherKey.']"]')->item(0)->getAttribute('value'));
        self::assertSame('Keep this edit', $xpath->query('//textarea[@name="descriptions['.$otherKey.']"]')->item(0)->textContent);
        self::assertSame(0, $xpath->query('//input[@name="selected[]" and @value="'.$otherKey.'" and @checked]')->length);
        $target = self::$root.'/plog-content/images/fixture/album/metadata-preview.jpg';
        self::assertSame($original, hash_file('sha256', $target));
        if (getenv('PLOGGER_TEST_DRIVER')) {
            $statement=$this->nativeConnection()->prepare('SELECT caption, description, exif_camera, exif_date_taken, exif_shutterspeed, exif_focallength, exif_aperture, exif_iso FROM "fixture_pictures" WHERE path = ?');
            $statement->execute(['fixture/album/metadata-preview.jpg']); $row=$statement->fetch(PDO::FETCH_ASSOC);
            self::assertSame('', $row['caption']); self::assertSame($manual, $row['description']);
            self::assertSame('Fixture Camera',$row['exif_camera']);
            self::assertSame('2026-10-15 12:34:56',$row['exif_date_taken']);
            self::assertSame('1/125 sec',$row['exif_shutterspeed']);
            self::assertSame('50 mm',$row['exif_focallength']);
            self::assertSame('f/2.8',$row['exif_aperture']);
            self::assertSame('200',$row['exif_iso']);
        }
        self::assertSame(self::$originalHash, hash_file('sha256',self::$root.'/plog-content/images/fixture/album/photo.jpg'));
        unlink($unselected);
        self::$cookie='';
    }

    public function testUploadAndFtpImportCommitUseOnlySyntheticFiles(): void
    {
        $this->request('/plog-admin/plog-upload.php', ['action' => 'log_in',
            'plog_username' => 'fixture', 'plog_password' => 'fixture-password']);
        $boundary = 'plog-' . bin2hex(random_bytes(8));
        $fields = ['_csrf' => $this->csrfToken(), 'upload' => 'Upload', 'destination_radio' => 'existing', 'albums_menu' => '1',
            'caption' => 'Uploaded fixture', 'description' => 'Uploaded fixture description'];
        $content = '';
        foreach ($fields as $name => $value) {
            $content .= "--$boundary\r\nContent-Disposition: form-data; name=\"$name\"\r\n\r\n$value\r\n";
        }
        $image = file_get_contents(self::$root . '/plog-content/images/fixture/album/photo.jpg');
        $content .= "--$boundary\r\nContent-Disposition: form-data; name=\"userfile\"; filename=\"uploaded.jpg\"\r\nContent-Type: image/jpeg\r\n\r\n"
            . $image . "\r\n--$boundary--\r\n";
        $context = stream_context_create(['http' => ['method' => 'POST',
            'header' => "Cookie: " . self::$cookie . "\r\nContent-Type: multipart/form-data; boundary=$boundary\r\n",
            'content' => $content, 'ignore_errors' => true, 'timeout' => 30]]);
        $body = file_get_contents(self::$url . '/plog-admin/plog-upload.php', false, $context);
        self::assertDoesNotMatchRegularExpression('/(?:Warning|Deprecated|Fatal error|Notice)(?:<\/b>)?:/', $body);
        self::assertStringContainsString('was uploaded successfully', $body);
        require_once __DIR__.'/MetadataTest.php';
        $metadataImage=MetadataTest::jpeg();
        $metadataContent=str_replace('filename="uploaded.jpg"','filename="metadata-upload.jpg"',$content);
        $metadataContent=str_replace('Uploaded fixture','Metadata upload fixture',$metadataContent);
        $metadataContent=str_replace($image,$metadataImage,$metadataContent);
        stream_context_set_option($context,'http','content',$metadataContent);
        [$metadataBody]=plogger_test_http_response(self::$url.'/plog-admin/plog-upload.php',$context);
        self::assertStringContainsString('was uploaded successfully',$metadataBody);
        self::assertSame(hash('sha256',$metadataImage),hash_file('sha256',self::$root.'/plog-content/images/fixture/album/metadata-upload.jpg'));
        if (getenv('PLOGGER_TEST_DRIVER')) {
            $statement=$this->nativeConnection()->prepare('SELECT exif_camera, exif_date_taken, exif_iso FROM "fixture_pictures" WHERE path = ?');
            $statement->execute(['fixture/album/metadata-upload.jpg']);
            self::assertSame(['Fixture Camera','2026-10-15 12:34:56','200'],$statement->fetch(PDO::FETCH_NUM));
        }
        // A decoded JPEG can still contain harmless PHP text; its name must not
        // select Apache's PHP handler through an earlier extension component.
        $unsafeContent = str_replace('filename="uploaded.jpg"', 'filename="uploaded.php.jpg"', $content);
        $unsafeContent = str_replace($image, $image.'<?php /* harmless fixture */ ?>', $unsafeContent);
        stream_context_set_option($context, 'http', 'content', $unsafeContent);
        [$rejected, $responseHeaders] = plogger_test_http_response(self::$url.'/plog-admin/plog-upload.php', $context);
        self::assertNotFalse($rejected);
        self::assertStringContainsString('400', $responseHeaders[0]);
        self::assertFileDoesNotExist(self::$root.'/plog-content/images/fixture/album/uploaded.php.jpg');
        $key = md5(realpath(self::$root) . '/plog-content/uploads/import.jpg');
        [$body] = $this->request('/plog-admin/plog-import.php', ['upload' => 'Import',
            'destination_radio' => 'existing', 'destination' => '1', 'selected' => [$key],
            'captions' => [$key => 'Imported fixture'], 'descriptions' => [$key => 'Imported description']]);
        self::assertStringContainsString('successfully imported', $body);
        self::assertFileDoesNotExist(self::$root . '/plog-content/uploads/import.jpg');
        foreach (['Uploaded fixture','Imported fixture'] as $caption) {
            if (getenv('PLOGGER_TEST_DRIVER')) {
                $statement = $this->nativeConnection()->prepare('SELECT original_width, original_height FROM "fixture_pictures" WHERE caption = ?');
                $statement->execute([$caption]); $dimensions = $statement->fetch(PDO::FETCH_NUM);
            } else {
                $dimensions = null;
                foreach (file(self::$root.'/fixture-picture-inserts.jsonl', FILE_IGNORE_NEW_LINES) as $line) {
                    [$sql,$parameters] = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                    if ($parameters[count($parameters)-4] === $caption) {
                        self::assertStringContainsString('"original_width", "original_height"', $sql);
                        $dimensions = array_slice($parameters, -2);
                    }
                }
            }
            self::assertSame([800,600], array_map('intval', $dimensions));
        }
        self::assertSame(self::$originalHash, hash_file('sha256', self::$root . '/plog-content/images/fixture/album/photo.jpg'));
    }

    public function testAllBundledThemesAndFeedEndpointsRenderWithoutPhpDiagnostics(): void
    {
        $this->request('/plog-admin/plog-upload.php', ['action' => 'log_in',
            'plog_username' => 'fixture', 'plog_password' => 'fixture-password']);
        foreach (['default', 'air', 'lucid', 'xip-bs'] as $theme) {
            $this->request('/plog-admin/plog-themes.php', ['activate' => $theme]);
            [$body] = $this->request('/index.php?fixture_theme=' . $theme);
            self::assertStringContainsString('Fixture caption', $body);
            [$body] = $this->request('/index.php?level=picture&id=1&fixture_theme=' . $theme);
            self::assertStringContainsString('Fixture description', $body);
        }
        $this->request('/plog-admin/plog-themes.php', ['activate' => 'default']);
        [$body] = $this->request('/plog-rss.php?level=album&id=1');
        self::assertStringContainsString('<rss', $body);
        [$body] = $this->request('/plog-rss.php?level=collection&id=1');
        self::assertStringContainsString('<rss', $body);
        [$body] = $this->request('/plog-xml.php?level=album&id=1');
        self::assertStringContainsString('<?xml', $body);
    }

    public function testXiPBsUsesBootstrapFiveAssetsAndUtilities(): void
    {
        $this->request('/plog-admin/plog-upload.php', ['action' => 'log_in',
            'plog_username' => 'fixture', 'plog_password' => 'fixture-password']);
        $this->request('/plog-admin/plog-themes.php', ['activate' => 'xip-bs']);

        [$body] = $this->request('/index.php?fixture_theme=xip-bs');
        self::assertStringContainsString('bootstrap@5.3.8/dist/css/bootstrap.min.css', $body);
        self::assertStringNotContainsString('bootstrap/4.', $body);
        self::assertStringContainsString('text-end', $body);
        self::assertStringNotContainsString('text-right', $body);

        [$body] = $this->request('/index.php?level=picture&id=1&fixture_theme=xip-bs');
        self::assertStringContainsString('class="row g-0', $body);
        self::assertStringNotContainsString('no-gutters', $body);
    }

    public function testRemoteWriteRequiresLoginAndConstructsProtocolResponse(): void
    {
        [$body] = $this->request('/plog-remote.php', ['cmd' => 'add-item']);
        self::assertStringContainsString("server_version=2.14\n", $body);
        self::assertStringContainsString("status=202\n", $body);
    }

    public function testMissingImageDoesNotBreakBundledThemes(): void
    {
        if (getenv('PLOGGER_TEST_DRIVER')) self::markTestSkipped('Missing-path injection uses the PDO test double.');
        foreach (['default', 'air', 'lucid', 'xip-bs'] as $theme) {
            [$body] = $this->request('/index.php?fixture_theme=' . $theme . '&fixture_missing_image=1');
            self::assertStringContainsString('Fixture caption', $body);
            [$body] = $this->request('/index.php?level=picture&id=1&fixture_theme=' . $theme . '&fixture_missing_image=1');
            self::assertStringContainsString('Fixture description', $body);
        }
    }

    public function testInstallerReportsAlreadyInstalledWithoutWarnings(): void
    {
        [$body] = $this->request('/plog-admin/_install.php');
        self::assertStringContainsString('Plogger is already installed', $body);
    }

    public function testUpgradeWebAndCliRequireReviewedAuthenticatedBackupFlow(): void
    {
        if(!getenv('PLOGGER_TEST_DRIVER')) self::markTestSkipped('Upgrade persistence requires a native isolated database.');
        $db=$this->nativeConnection();
        $db->prepare('DROP TABLE "fixture_schema_migrations"')->execute();
        $db->prepare('ALTER TABLE "fixture_config" DROP COLUMN "schema_version"')->execute();
        [, $headers]=$this->request('/index.php');
        self::assertStringContainsString('503',$headers[0]);
        $path=self::$root.'/plog-config.php'; $original=file_get_contents($path);
        require_once dirname(__DIR__).'/plog-includes/plog-upgrade-config.php';
        $settings=['PLOGGER_DB_TYPE'=>getenv('PLOGGER_TEST_DRIVER'),'PLOGGER_DB_HOST'=>'127.0.0.1',
            'PLOGGER_DB_PORT'=>getenv('PLOGGER_TEST_PORT'),'PLOGGER_DB_NAME'=>getenv('PLOGGER_TEST_DATABASE'),
            'PLOGGER_DB_USER'=>getenv('PLOGGER_TEST_USER'),'PLOGGER_DB_PW'=>getenv('PLOGGER_TEST_PASSWORD')?:'',
            'PLOGGER_TABLE_PREFIX'=>'fixture_','PLOGGER_ENABLE_UPGRADE_WEB'=>true];
        file_put_contents($path,plogger_upgrade_proposal($settings));
        self::$cookie='';
        try {
            [$body]=$this->request('/plog-admin/_upgrade.php');
            preg_match('/name="_csrf" value="([a-f0-9]+)"/',$body,$match); $token=$match[1];
            [, $headers]=$this->request('/plog-admin/_upgrade.php',['action'=>'apply','_csrf'=>$token]);
            self::assertStringContainsString('403',$headers[0]);
            $login=['action'=>'login','username'=>'fixture','password'=>'fixture-password','_csrf'=>$token];
            [, $headers]=$this->request('/plog-admin/_upgrade.php',array_replace($login,['_csrf'=>null]));
            self::assertStringContainsString('403',$headers[0]);
            [, $headers]=$this->request('/plog-admin/_upgrade.php',$login,"Origin: https://example.invalid\r\n");
            self::assertStringContainsString('403',$headers[0]);
            [$body]=$this->request('/plog-admin/_upgrade.php',$login);
            self::assertStringContainsString('0001: Add schema version',$body);
            [, $headers]=$this->request('/plog-admin/_upgrade.php',['action'=>'apply','_csrf'=>$token]);
            self::assertStringContainsString('400',$headers[0]);
            $columns=$db->prepare('SELECT column_name FROM information_schema.columns WHERE table_schema = '
                .(getenv('PLOGGER_TEST_DRIVER')==='mysql'?'DATABASE()':'current_schema()').' AND table_name = ? AND column_name = ?');
            $columns->execute(['fixture_config','schema_version']); self::assertFalse($columns->fetchColumn());
            for($version=1;$version<=PloggerUpgrade::VERSION;++$version) {
                [$body,$headers]=$this->request('/plog-admin/_upgrade.php',['action'=>'apply','_csrf'=>$token,
                    'backup_database'=>'synthetic external dump','backup_files'=>'synthetic external originals/config',
                    'restore_tested'=>'yes','confirm'=>'yes']);
                self::assertStringContainsString('200',$headers[0],$body);
                self::assertSame($version,(int)$db->query('SELECT "schema_version" FROM "fixture_config"')->fetchColumn());
            }
            self::assertStringContainsString('Upgrade complete',$body);
            [, $headers]=$this->request('/plog-admin/_upgrade.php',['action'=>'apply','_csrf'=>$token]);
            self::assertStringContainsString('403',$headers[0]);
            $child=proc_open([PHP_BINARY,dirname(__DIR__).'/plog-admin/upgrade-cli.php','--config='.$path,'--dry-run'],
                [1=>['pipe','w'],2=>['pipe','w']],$pipes);
            $output=stream_get_contents($pipes[1]).stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
            self::assertSame(0,proc_close($child),$output);
            self::assertSame('dry-run',json_decode($output,true,512,JSON_THROW_ON_ERROR)['mode']);
            self::assertStringNotContainsString($db->query('SELECT "admin_password" FROM "fixture_config"')->fetchColumn(),$output);
            self::assertStringNotContainsString('fixture-password',$output);
        } finally { file_put_contents($path,$original); self::$cookie=''; }
    }

    public function testFreshInstallerAndMissingProceedSessionRenderSetupForm(): void
    {
        [$body] = $this->request('/plog-admin/_install.php?fixture=fresh');
        self::assertStringContainsString('Plogger Configuration Setup', $body);
        [$body] = $this->request('/plog-admin/_install.php?fixture=fresh', ['proceed' => 'Install']);
        self::assertStringContainsString('Plogger Configuration Setup', $body);
    }
}
