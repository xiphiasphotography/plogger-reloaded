<?php
use PHPUnit\Framework\TestCase;

final class PluralSecurityTest extends TestCase {
    public function testCommonTranslationPluralForms(): void {
        require_once dirname(__DIR__).'/plog-includes/lib/gettext/gettext.php';
        $reader = new gettext_reader(false);
        $reader->pluralheader = 'nplurals=2; plural=(n != 1);';
        foreach ([0 => 1, 1 => 0, 2 => 1, 100 => 1] as $number => $expected) self::assertSame($expected, $reader->select_string($number));
        $reader->pluralheader = 'nplurals=3; plural=n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2;';
        foreach ([1 => 0, 2 => 1, 5 => 2, 11 => 2, 21 => 0, 22 => 1] as $number => $expected) self::assertSame($expected, $reader->select_string($number));
        $reader->pluralheader = 'nplurals=2; plural=n==1 ? 0 : 1/0;';
        self::assertSame(0, $reader->select_string(1));
    }

    public function testTranslationHeadersCannotExecutePhp(): void {
        require_once dirname(__DIR__).'/plog-includes/lib/gettext/plural.php';
        foreach (['nplurals=2; plural=phpinfo();', 'nplurals=2; plural=0; $GLOBALS["injected"]=1;',
            'nplurals=2; plural='.str_repeat('(', 100).'n'.str_repeat(')', 100).';',
            'nplurals=999; plural=n;', 'nplurals=2; plural=[];'] as $header) {
            self::assertSame(1, PloggerPluralExpression::select($header, 2));
        }
        self::assertArrayNotHasKey('injected', $GLOBALS);
    }
}
