<?php

namespace App\Tests;

use App\Support\LegacyMarkup;
use PHPUnit\Framework\TestCase;

/**
 * The small markup helpers the gallery pages inherited from Perl.
 *
 * `bbc_to_html()` and `cut_string()` are not incidental: the gallery pages put
 * a photographer credit on every tile, and production stores those credits as
 * bracketed BBC markup. The legacy renderer emitted broken anchors
 * (`href\="..."`), and this port deliberately fixes that while keeping the
 * visible text identical.
 */
class LegacyMarkupTest extends TestCase
{
    public function testNamedBbcLinkBecomesAWorkingAnchor(): void
    {
        self::assertSame(
            '<a href="http://example.com" target="_blank" rel="noopener">Synaptic</a>',
            LegacyMarkup::bbcToHtml('[a=http://example.com]Synaptic[/a]')
        );
    }

    public function testBareBbcLinkUsesItsTextAsTheTarget(): void
    {
        self::assertSame(
            '<a href="http://example.com/x" target="_blank" rel="noopener">http://example.com/x</a>',
            LegacyMarkup::bbcToHtml('[a]http://example.com/x[/a]')
        );
    }

    public function testUnsafeSchemesAreRenderedAsPlainText(): void
    {
        foreach (['javascript:alert(1)', 'data:text/html,x', 'vbscript:msgbox'] as $url) {
            $html = LegacyMarkup::bbcToHtml("[a={$url}]click[/a]");

            self::assertStringNotContainsString('href=', $html, $url);
            self::assertStringContainsString('click', $html, $url);
        }
    }

    public function testMailtoLinksAreKept(): void
    {
        self::assertStringContainsString(
            'href="mailto:artist@example.com"',
            LegacyMarkup::bbcToHtml('[a=mailto:artist@example.com]Karen[/a]')
        );
    }

    public function testBoldItalicAndUnderlineAreConverted(): void
    {
        self::assertSame(
            '<b>bold</b> <i>italic</i> <u>under</u>',
            LegacyMarkup::bbcToHtml('[b]bold[/b] [i]italic[/i] [u]under[/u]')
        );
    }

    public function testPlainTextDropsLinksAndDecodesEntities(): void
    {
        self::assertSame(
            'Kütral Tribe',
            LegacyMarkup::bbcToPlain('[a=http://example.com]K&uuml;tral Tribe[/a]')
        );
    }

    public function testShortTitlesAreNotCut(): void
    {
        self::assertSame('World of Maya', LegacyMarkup::cut('World of Maya', 20, '..'));
    }

    public function testLongTitlesAreCutAtTwentyCharacters(): void
    {
        self::assertSame('Spectral-Light-Objec..', LegacyMarkup::cut('Spectral-Light-Objects', 20, '..'));
    }

    public function testLongTitlesBreakAtTheLastSpaceWhenThereIsOne(): void
    {
        // `cut_string()` walks backwards from character 19 to 4 and stops at
        // the first space it meets, so the break is after "Addicted".
        self::assertSame('Pleasure Addicted..', LegacyMarkup::cut('Pleasure Addicted (Spiralbooking)', 20, '..'));
    }

    public function testCutWithoutAppendSuffixStillClips(): void
    {
        self::assertSame('12345', LegacyMarkup::cut('1234567890', 5, ''));
    }

    /**
     * The review finding: strip_tags() keeps attributes on the tags it allows,
     * and the result is printed with |raw.
     */
    public function testAllowedTagsLoseTheirAttributes(): void
    {
        self::assertSame(
            '<a href="http://example.com" target="_blank" rel="noopener"><b>x</b></a>',
            LegacyMarkup::bbcToHtml('[a=http://example.com]<b onclick="alert(1)">x</b>[/a]')
        );

        self::assertStringNotContainsString(
            'onmouseover',
            LegacyMarkup::bbcToHtml('[a=http://example.com]<br onmouseover="alert(1)">x[/a]')
        );
    }

    public function testProtocolRelativeUrlsAreNotTreatedAsLocal(): void
    {
        $html = LegacyMarkup::bbcToHtml('[a=//evil.example]x[/a]');

        self::assertStringNotContainsString('href=', $html);
        self::assertStringContainsString('x', $html);
    }

    public function testGuestbookContentKeepsBreaksAndEntities(): void
    {
        $html = LegacyMarkup::formatGuestbookContent('one<br>two &lt;3');

        self::assertStringContainsString('one<br>two', $html);
        self::assertStringContainsString('&lt;3', $html);
    }

    public function testGuestbookContentAutolinksUrlsEmailsAndDomains(): void
    {
        $html = LegacyMarkup::formatGuestbookContent(
            'see http://example.com/x and mail a@example.com or example.org'
        );

        self::assertStringContainsString('<a href="http://example.com/x" target="_blank" rel="noopener">http://example.com/x</a>', $html);
        self::assertStringContainsString('<a href="mailto:a@example.com">a@example.com</a>', $html);
        self::assertStringContainsString('<a href="http://example.org" target="_blank" rel="noopener">example.org</a>', $html);
    }

    public function testGuestbookContentDoesNotRelinkInsideAnchors(): void
    {
        $html = LegacyMarkup::formatGuestbookContent('<a href="http://example.com">http://example.com</a>');

        self::assertSame(1, substr_count($html, '<a '));
    }

    public function testGuestbookContentDropsScriptsAndUnsafeAnchors(): void
    {
        $html = LegacyMarkup::formatGuestbookContent(
            '<script>alert(1)</script><a href="javascript:alert(1)">click</a><b onmouseover="alert(1)">bold</b>'
        );

        self::assertStringNotContainsString('<script', $html);
        self::assertStringNotContainsString('javascript:', $html);
        self::assertStringNotContainsString('onmouseover', $html);
        self::assertStringContainsString('click', $html);
        self::assertStringContainsString('<b>bold</b>', $html);
    }

    public function testXorHandshakeRoundTrips(): void
    {
        $plain = 'Tester <tester@example.com>';

        self::assertSame($plain, LegacyMarkup::xorDecryptHex(LegacyMarkup::xorEncryptHex($plain)));
    }

    /**
     * A blob captured from production proves the port uses the same key as the
     * legacy page. Only the shape is asserted, so no visitor's address is
     * copied into the repository.
     */
    public function testXorHandshakeMatchesTheLegacyKey(): void
    {
        $plain = LegacyMarkup::xorDecryptHex('b6afb5a7a29b6b94a69b998fb0a092baa89ba3bfaa86b69bae80');

        self::assertStringStartsWith('ribbon <', $plain);
        self::assertStringEndsWith('>', $plain);
    }
}
