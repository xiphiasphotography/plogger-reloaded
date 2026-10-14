<?php
use PHPUnit\Framework\TestCase;

final class ErrorHandlingTest extends TestCase {
    public function testExceptionOutputAndLogsDoNotExposeSecrets(): void {
        $path = tempnam(sys_get_temp_dir(), 'plog-error-');
        $previous = ini_set('error_log', $path);
        try {
            ob_start();
            plogger_exception_handler(new RuntimeException('secret-password SELECT private metadata <script>fixture()</script>'));
            $body = ob_get_clean();
            self::assertMatchesRegularExpression('/Reference: [a-f0-9]{16}$/', $body);
            $log = file_get_contents($path);
            self::assertStringContainsString('RuntimeException ErrorHandlingTest.php:', $log);
            foreach (['secret-password', 'SELECT', 'private metadata', '<script>', __DIR__] as $secret) {
                self::assertStringNotContainsString($secret, $log.$body);
            }
        } finally {
            ini_set('error_log', $previous);
            http_response_code(200);
            unlink($path);
        }
    }
}
