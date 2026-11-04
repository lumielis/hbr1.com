<?php

namespace App\Tests;

use App\Services\StationService;
use PHPUnit\Framework\TestCase;

class StationServiceTest extends TestCase
{
    private function service(): StationService
    {
        return new StationService([
            ['id' => 'Trance', 'slug' => 'trance', 'title' => 'I.D.M. Tranceponder'],
            ['id' => 'Tronic', 'slug' => 'tronic', 'title' => 'Tronic Lounge'],
        ]);
    }

    public function testFindBySlugIsCaseInsensitive(): void
    {
        self::assertSame('Trance', $this->service()->find('trance')['id']);
        self::assertSame('Trance', $this->service()->find('TRANCE')['id']);
    }

    /** Legacy links and the config use the canonical Id, so both must work. */
    public function testFindByCanonicalId(): void
    {
        self::assertSame('tronic', $this->service()->find('Tronic')['slug']);
    }

    public function testUnknownStationReturnsNull(): void
    {
        self::assertNull($this->service()->find('rock'));
    }

    public function testFindOrFailThrowsForUnknownStation(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->service()->findOrFail('rock');
    }

    public function testAllReturnsEveryStation(): void
    {
        self::assertCount(2, $this->service()->all());
    }

    /**
     * The legacy `muses.php` looped over `$Stations` without resetting the
     * variable, so a request without `?station=` fell through to the *last*
     * configured station. Production serves Dream Factory (Ambient) from the
     * bare `/cgi-bin/main.php?module=muses` for exactly that reason.
     */
    public function testFallbackIsTheLastConfiguredStation(): void
    {
        self::assertSame('tronic', $this->service()->fallback()['slug']);
    }

    public function testFallbackThrowsWhenNothingIsConfigured(): void
    {
        $this->expectException(\RuntimeException::class);
        (new StationService([]))->fallback();
    }

    /**
     * A browser player on an `https://` page cannot load an `http://` stream:
     * the request is blocked as mixed content, or rewritten to `https://` on
     * the same port, where Icecast has no TLS listener - the page then renders
     * but never plays. `stream.secure` keeps the proxy mount that does work.
     */
    public function testBrowserStreamUrlPrefersTheSecureMount(): void
    {
        $service = new StationService([
            [
                'id' => 'Ambient', 'slug' => 'ambient', 'title' => 'Dream Factory',
                'stream' => [
                    'ogg'    => 'http://radio.hbr1.com:19800/ambient.ogg',
                    'aac'    => 'http://radio.hbr1.com:19800/ambient.aac',
                    'secure' => [
                        'ogg' => 'https://radio.hbr1.com/ambient.ogg',
                        'aac' => 'https://radio.hbr1.com/ambient.aac',
                    ],
                ],
            ],
        ]);

        self::assertSame(
            'https://radio.hbr1.com/ambient.ogg',
            $service->browserStreamUrl($service->find('ambient'))
        );
    }

    /** A station without a secure mount still yields its plain URL. */
    public function testBrowserStreamUrlFallsBackToThePlainMount(): void
    {
        $service = new StationService([
            [
                'id' => 'Legacy', 'slug' => 'legacy', 'title' => 'Legacy',
                'stream' => ['ogg' => 'http://radio.hbr1.com:19800/legacy.ogg'],
            ],
        ]);

        self::assertSame(
            'http://radio.hbr1.com:19800/legacy.ogg',
            $service->browserStreamUrl($service->find('legacy'))
        );
    }

    public function testBrowserStreamUrlIsNullWithoutAnyStream(): void
    {
        $service = new StationService([
            ['id' => 'Bare', 'slug' => 'bare', 'title' => 'Bare'],
        ]);

        self::assertNull($service->browserStreamUrl($service->find('bare')));
    }

    /**
     * Every configured station must carry a secure mount: the player popup is
     * served over TLS, so a station whose only mount is `http://` would render
     * a silent player. This guards the config, not the resolver.
     */
    public function testEveryConfiguredStationHasASecureMount(): void
    {
        $config = require BASE_DIR . '/config/config.php';

        self::assertNotEmpty($config['stations']);

        foreach ($config['stations'] as $station) {
            self::assertArrayHasKey(
                'secure',
                $station['stream'],
                "Station {$station['slug']} has no secure stream mount"
            );
            self::assertStringStartsWith(
                'https://',
                $station['stream']['secure']['ogg'],
                "Station {$station['slug']}'s secure mount is not https"
            );
        }
    }
}
