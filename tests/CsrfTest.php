<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/plog-includes/plog-csrf.php';

final class CsrfTest extends TestCase
{
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
