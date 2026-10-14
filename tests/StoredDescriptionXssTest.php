<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class StoredDescriptionXssTest extends TestCase
{
    public function testExistingDescriptionsCannotCreateExecutableElements(): void
    {
        foreach (['album', 'collection', 'picture'] as $kind) {
            $payload = "Text &amp; café\n<script>storedFixture()</script><img src=x onerror=storedFixture()>"
                . '<strong onclick="storedFixture()">Bold</strong><br />second line';
            $GLOBALS['current_' . $kind] = ['description' => $payload];
            $getter = 'plogger_get_' . $kind . '_description';
            $html = $getter();
            $dom = new DOMDocument();
            @$dom->loadHTML('<html><body>' . $html . '</body></html>');
            $xpath = new DOMXPath($dom);
            self::assertSame(0, $xpath->query('//script|//img|//@onclick|//@onerror')->length);
            self::assertStringContainsString('<strong>Bold</strong>', $html);
            self::assertStringContainsString('<br', $html);
            self::assertStringNotContainsString('&amp;amp;', $html);
            self::assertSame($payload, $GLOBALS['current_' . $kind]['description']);
        }
    }

    public function testDescriptionAttributesPreserveTextAndEntitiesWithoutInjection(): void
    {
        $value = 'A &amp; B &quot; onmouseover=&quot;fixture() <strong>Bold</strong>';
        $attribute = plogger_description_attribute($value);
        $dom = new DOMDocument();
        @$dom->loadHTML('<img title="' . $attribute . '" />');
        $image = $dom->getElementsByTagName('img')->item(0);
        self::assertFalse($image->hasAttribute('onmouseover'));
        self::assertSame('A & B " onmouseover="fixture() Bold', $image->getAttribute('title'));
    }

    public function testMalformedMarkupAndEntitiesRemainInert(): void
    {
        foreach (['<svg><script>fixture()</script></svg>', '<math><mtext><img src=x onerror=fixture()>',
            '<a href="javascript:fixture()">link</a>', '<!--comment--><b style="x" onclick="fixture()">bold</b>',
            '&lt;script&gt;fixture()&lt;/script&gt;', '<iframe srcdoc="<script>fixture()</script>"></iframe>'] as $value) {
            $html = plogger_description_html($value);
            $dom = new DOMDocument();
            @$dom->loadHTML('<html><body>' . $html . '</body></html>');
            $xpath = new DOMXPath($dom);
            self::assertSame(0, $xpath->query('//script|//svg|//math|//img|//iframe|//a|//@*')->length);
        }
        self::assertSame("first<br />\nsecond", plogger_description_html("first\r\nsecond"));
        self::assertSame("first<br />\nsecond", plogger_description_html("first<br />\nsecond"));
        self::assertSame('A &amp; B', plogger_description_html('A &amp; B'));
        self::assertSame("<pre>first\nsecond</pre>", plogger_description_html("<pre>first\nsecond</pre>"));
    }
}
