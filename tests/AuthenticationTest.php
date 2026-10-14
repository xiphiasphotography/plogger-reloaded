<?php
use PHPUnit\Framework\TestCase;

final class AuthenticationTest extends TestCase {
    public function testLegacyAndModernPasswords(): void {
        $password = "quotation' marks and Unicode café";
        self::assertTrue(plogger_password_verify($password, md5($password)));
        self::assertFalse(plogger_password_verify('wrong', md5($password)));
        $hash = plogger_password_hash($password);
        self::assertLessThanOrEqual(64, strlen($hash));
        self::assertTrue(plogger_password_verify($password, $hash));
        self::assertFalse(plogger_password_verify('wrong', $hash));
        self::assertFalse(plogger_password_verify([], $hash));
        self::assertFalse(plogger_password_verify($password, 'invalid'));
        self::assertTrue(plogger_password_verify($password, password_hash($password, PASSWORD_BCRYPT)));
    }

    public function testLongPasswordsRemainDistinct(): void {
        $password = str_repeat('a', 80);
        self::assertFalse(plogger_password_verify($password.'b', plogger_password_hash($password)));
    }

    public function testResetKeysExpireAndAreComparedExactly(): void {
        $key = dechex(1700000000).'.'.str_repeat('a', 48);
        self::assertTrue(plogger_reset_key_valid($key, $key, 1700000001));
        self::assertFalse(plogger_reset_key_valid($key, $key, 1700003600));
        self::assertFalse(plogger_reset_key_valid($key, $key, 1699999999));
        self::assertFalse(plogger_reset_key_valid($key.'b', $key, 1700000001));
        self::assertFalse(plogger_reset_key_valid([], $key));
        self::assertFalse(plogger_reset_key_valid(md5('old key'), md5('old key')));
    }

    public function testThrottlingWindowCannotBeResetByNewSessions(): void {
        $state = [];
        for ($attempt = 1; $attempt <= 6; ++$attempt) {
            $state = plogger_login_window($state, 1000 + $attempt);
            self::assertSame($attempt, $state['attempts']);
            self::assertSame(1001, $state['started']);
        }
        self::assertSame(['started' => 1901, 'attempts' => 1], plogger_login_window($state, 1901));
        self::assertSame(['started' => 1, 'attempts' => 1], plogger_login_window(['started' => [], 'attempts' => []], 1));
    }
}
