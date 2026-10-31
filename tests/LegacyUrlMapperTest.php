<?php

namespace App\Tests;

use App\Http\LegacyUrlMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The legacy URL contract.
 *
 * Every case here is a URL that exists in the wild - linked from static
 * pages, bookmarks or search results - and must keep resolving after the
 * Perl tree is gone.
 */
class LegacyUrlMapperTest extends TestCase
{
    public static function canonicalCases(): array
    {
        return [
            'tracklist module'        => ['/cgi-bin/main.php', ['module' => 'tracklist'], '/mixes'],
            'tracklist with station'  => ['/cgi-bin/main.php', ['module' => 'tracklist', 'station' => 'Trance'], '/mixes/trance'],
            'listen with station'     => ['/cgi-bin/main.php', ['module' => 'listen', 'station' => 'Ambient'], '/listen/ambient'],
            'muses'                   => ['/cgi-bin/main.php', ['module' => 'muses'], '/muses'],
            'muses with station'      => ['/cgi-bin/main.php', ['module' => 'muses', 'station' => 'Trance'], '/muses/trance'],
            'playlist'                => ['/cgi-bin/main.php', ['module' => 'playlist'], '/playlist'],
            'topframe'                => ['/cgi-bin/main.php', ['module' => 'topframe'], '/topframe'],
            'gallery'                 => ['/cgi-bin/main.php', ['module' => 'gallery'], '/gallery'],
            'main.cgi'                => ['/cgi-bin/main.cgi', ['module' => 'tracklist'], '/mixes'],
            'legacy .xp party'        => ['/run/main.xp', ['action' => 'party'], '/party'],
            'apax navigation'         => ['/perl/apax.cgi', ['run' => 'navigation'], '/navigation'],
            'apax guestbook'          => ['/perl/apax.cgi', ['run' => 'gbms'], '/guestbook'],
            'apax Part (capitalised)' => ['/perl/apax.cgi', ['Part' => 'Playlist'], '/playlist'],
            'admin_v2'                => ['/perl/admin_v2.cgi', ['run' => 'tracklist.creator'], '/'],
            'asx'                     => ['/cgi-bin/asx.cgi', ['trance' => ''], '/stream/trance.asx'],
            'm3u'                     => ['/cgi-bin/m3u.cgi', ['tronic' => ''], '/stream/tronic.m3u'],
            'mixes (run alias)'       => ['/run/mixes.cgi', [], '/mixes'],
            'mixes (cgi-bin)'         => ['/cgi-bin/mixes.cgi', [], '/mixes'],
            'admin.cgi'               => ['/cgi-bin/admin.cgi', [], '/admin'],
            'listen.cgi'              => ['/cgi-bin/listen.cgi', [], '/listen'],
            'party.cgi'               => ['/cgi-bin/party.cgi', [], '/party'],
        ];
    }

    #[DataProvider('canonicalCases')]
    public function testLegacyUrlsResolveToCanonicalRoutes(string $path, array $query, string $expected): void
    {
        self::assertSame($expected, LegacyUrlMapper::resolve($path, $query));
    }

    public static function traversalCases(): array
    {
        return [
            'dot dot slash'   => ['../../htdocs/index'],
            'absolute path'   => ['/etc/passwd'],
            'nested'          => ['include/config'],
            'null byte'       => ["tracklist\0config"],
            'url encoded'     => ['..%2F..%2Fconfig'],
        ];
    }

    /**
     * The legacy handler concatenated `?module=` into a path, which allowed
     * arbitrary PHP outside include/ to be loaded. Only bare module names may
     * ever be accepted.
     */
    #[DataProvider('traversalCases')]
    public function testModuleParameterCannotTraverse(string $module): void
    {
        self::assertSame(
            '/',
            LegacyUrlMapper::resolve('/cgi-bin/main.php', ['module' => $module])
        );
    }

    public function testUnknownModuleFallsBackToHome(): void
    {
        self::assertSame('/', LegacyUrlMapper::resolve('/cgi-bin/main.php', ['module' => 'nope']));
    }

    public function testNonLegacyPathIsNotMapped(): void
    {
        self::assertNull(LegacyUrlMapper::resolve('/listen/trance', []));
        self::assertNull(LegacyUrlMapper::resolve('/', []));
        self::assertNull(LegacyUrlMapper::resolve('/admin', []));
    }

    public function testStationSuffixIsIgnoredForNonStationRoutes(): void
    {
        self::assertSame(
            '/gallery',
            LegacyUrlMapper::resolve('/cgi-bin/main.php', ['module' => 'gallery', 'station' => 'Trance'])
        );
    }

    public function testInvalidStationNameIsDropped(): void
    {
        self::assertSame(
            '/mixes',
            LegacyUrlMapper::resolve('/cgi-bin/main.php', ['module' => 'tracklist', 'station' => '../x'])
        );
    }

    /**
     * The retired `mixes.cgi` levels - a category and a single mix - now
     * collapse onto the catalogue.
     */
    public function testRetiredMixLevelsCollapse(): void
    {
        self::assertSame('/mixes', LegacyUrlMapper::resolve('/cgi-bin/mixes.cgi', ['action' => 'view', 'cat_id' => '4']));
        self::assertSame('/mixes', LegacyUrlMapper::resolve('/cgi-bin/mixes.cgi', ['action' => 'playlist', 'mix_id' => '889']));
    }
}
