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
        self::assertSame('Pleasure..', LegacyMarkup::cut('Pleasure Addicted (Spiralbooking)', 20, '..'));
    }

    public function testCutWithoutAppendSuffixStillClips(): void
    {
        self::assertSame('12345', LegacyMarkup::cut('1234567890', 5, ''));
    }
}
