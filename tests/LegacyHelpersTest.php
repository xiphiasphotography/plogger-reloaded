<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class LegacyHelpersTest extends TestCase
{
    public function testModernPhpPreservesQuotesUnicodeAndLineBreaks(): void
    {
        $value = "O'Brien café\nsecond line";
        self::assertSame($value, SmartAddSlashes($value));
        self::assertSame($value, SmartStripSlashes($value));
    }

    public function testGeneratedPasswordRespectsFixedLengthAndAlphabet(): void
    {
        $password = generate_password(12, 12);
        self::assertSame(12, strlen($password));
        self::assertMatchesRegularExpression('/^[a-zA-Z0-9]+$/', $password);
    }
}
