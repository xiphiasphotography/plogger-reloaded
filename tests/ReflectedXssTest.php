<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ReflectedXssTest extends TestCase
{
    public function testOnlyExplicitLocalLoginDestinationsAreAccepted(): void
    {
        foreach (['upload', 'import', 'manage', 'feedback', 'options', 'themes', 'plugins'] as $page) {
            self::assertSame('plog-' . $page . '.php', plogger_admin_login_destination('plog-' . $page . '.php'));
        }
        foreach ([null, [], ['r' => 'plog-upload.php'], 42, 'https://example.com/',
            'javascript:alert(2014)', "plog-upload.php\n", 'plog-upload.php#x',
            '%70log-upload.php', 'plog-upload.php&#34;', './plog-upload.php'] as $target) {
            self::assertSame('plog-upload.php', plogger_admin_login_destination($target));
        }
    }

    public function testUrlEncodingAndHtmlEscapingAreSeparateAndReversible(): void
    {
        global $config;
        $previous = $config ?? null;
        try {
            $parameters = ['host' => 'a&b="quoted"', 'nested' => ['key' => 'café'], 'empty' => '',
                'bad"name' => '&#34;', 'malformed' => '%zz', 'bytes' => "\xff"];
            $config = ['baseurl' => '/gallery/index.php', 'use_mod_rewrite' => false,
                'query_args' => http_build_query($parameters, '', '&amp;', PHP_QUERY_RFC3986)];
            $arguments = ['searchterms' => 'tea & coffee', 'mode' => 'slideshow'];
            $raw = generate_url('search', -1, $arguments, true);
            $html = generate_url('search', -1, $arguments);
            self::assertSame(htmlspecialchars($raw, ENT_QUOTES, 'UTF-8'), $html);
            parse_str(parse_url($raw, PHP_URL_QUERY), $parsed);
            self::assertSame($parameters + ['level' => 'search'] + $arguments, $parsed);
            $collections = generate_url('collections', -1, [], true);
            parse_str(parse_url($collections, PHP_URL_QUERY), $parsed);
            self::assertSame($parameters, $parsed);
            $config['query_args'] = '';
            self::assertSame('/gallery/index.php?level=album&id=1', generate_url('album', 1, [], true));
            self::assertSame('/gallery/index.php', generate_url('collections', -1, [], true));
            $config['use_mod_rewrite'] = true;
            self::assertSame('/gallery/index.phpsearch/tea%20%26%20coffee/',
                generate_url('search', -1, ['tea & coffee'], true));
            self::assertSame('/gallery/index.phpsearch/', generate_url('search', -1, null, true));
        } finally {
            $config = $previous;
        }
    }
}
