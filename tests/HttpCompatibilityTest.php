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
        foreach (['plog-admin', 'plog-includes', 'plog-content/themes', 'plog-content/plugins'] as $path) {
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
        self::$server = proc_open([PHP_BINARY, '-d', 'auto_prepend_file=' . __DIR__ . '/fixtures/http-bootstrap.php',
            '-d', 'display_errors=1', '-d', 'error_reporting=-1', '-S', $address, '-t', self::$root],
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
        if (!isset(self::$root)) return;
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
    }

    private function csrfToken(): string
    {
        foreach (['/plog-admin/index.php', '/plog-admin/plog-upload.php'] as $page) {
            [$body] = $this->request($page);
            if (preg_match('/name="_csrf" value="([a-f0-9]{64})"/', $body, $match)) return $match[1];
        }
        throw new RuntimeException('Fixture CSRF token missing.');
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
        $body = file_get_contents(self::$url . $path, false, $context);
        self::assertNotFalse($body);
        foreach ($http_response_header as $header) {
            if (preg_match('/^Set-Cookie: ([^;]+)/i', $header, $matches)) self::$cookie = $matches[1];
        }
        self::assertDoesNotMatchRegularExpression('/(?:Warning|Deprecated|Fatal error|Notice)(?:<\/b>)?:|A database error occurred|Database error\./', $body, $path . "\n" . $body);
        return [$body, $http_response_header];
    }

    public function testLegacyLoginRedirectXssIsRejected(): void
    {
        foreach (['plog-upload.php', 'plog-manage.php', 'plog-options.php', 'plog-import.php',
            '" onmouseover="alert(2014)', '&#34; onmouseover=alert(2014)', '//example.com/',
            '../index.php', 'plog-upload.php?x=1', ['plog-upload.php']] as $target) {
            [$body] = $this->request('/plog-admin/index.php?' . http_build_query(['r' => $target]));
            $dom = new DOMDocument();
            @$dom->loadHTML($body);
            $form = $dom->getElementsByTagName('form')->item(0);
            $expected = is_string($target) && in_array($target,
                ['plog-upload.php', 'plog-manage.php', 'plog-options.php', 'plog-import.php'], true)
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
        self::assertStringContainsString('UPDATE fixture_albums', file_get_contents(self::$root . '/csrf-writes.log'));
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
        foreach (['default', 'air', 'lucid', 'xip-bs'] as $theme) {
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
        if (getenv('PLOGGER_TEST_DRIVER')) self::markTestSkipped('Stored-payload fixture uses the PDO double.');
        require_once __DIR__ . '/fixtures/FixtureDatabase.php';
        $pages = [];
        foreach (['default', 'air', 'lucid', 'xip-bs'] as $theme) {
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
        self::assertSame(self::$originalHash, hash_file('sha256', self::$root . '/plog-content/images/fixture/album/photo.jpg'));
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
        $key = md5(realpath(self::$root) . '/plog-content/uploads/import.jpg');
        [$body] = $this->request('/plog-admin/plog-import.php', ['upload' => 'Import',
            'destination_radio' => 'existing', 'destination' => '1', 'selected' => [$key],
            'captions' => [$key => 'Imported fixture'], 'descriptions' => [$key => 'Imported description']]);
        self::assertStringContainsString('successfully imported', $body);
        self::assertFileDoesNotExist(self::$root . '/plog-content/uploads/import.jpg');
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

    public function testFreshInstallerAndMissingProceedSessionRenderSetupForm(): void
    {
        [$body] = $this->request('/plog-admin/_install.php?fixture=fresh');
        self::assertStringContainsString('Plogger Configuration Setup', $body);
        [$body] = $this->request('/plog-admin/_install.php?fixture=fresh', ['proceed' => 'Install']);
        self::assertStringContainsString('Plogger Configuration Setup', $body);
    }
}
