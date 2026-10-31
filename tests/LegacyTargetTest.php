<?php

namespace App\Tests;

use App\Controllers\Web\AdminController;
use App\Controllers\Web\IphoneController;
use App\Services\StationService;
use App\Services\StationViewService;
use App\Services\TrackService;
use PHPUnit\Framework\TestCase;

/**
 * Two pages that the legacy URL mapper already pointed at, but that had no
 * route behind them.
 *
 * `/iphone` was a 404 straight after a 301, and `/admin` fell through to the
 * legacy frameset in `htdocs/admin/`, whose content frame is
 * `/cgi-bin/admin.cgi` - which redirects back to `/admin`. The page nested
 * itself recursively, and served the old admin chrome with nothing in front
 * of it.
 */
class LegacyTargetTest extends TestCase
{
    protected function setUp(): void
    {
        \App\Bootstrap::bootstrap();

        $stations = new StationService([[
            'id'         => 'Ambient',
            'slug'       => 'ambient',
            'title'      => 'Dream Factory',
            'genre'      => 'Ambient',
            'stream'     => ['ogg' => 'http://radio.hbr1.com:19800/ambient.ogg'],
            'muses_url'  => '',
            'iphone_url' => 'http://radio.hbr1.com:19800/ambient.aac.m3u',
            'radiodj_db' => 'radiodj_ambient',
            'playlists'  => [],
        ]]);

        $ctx = \App\AppContext::instance();
        $ctx->set(StationService::class, static fn() => $stations);
        $ctx->set(StationViewService::class, static fn() => new StationViewService());
        // The track read would need the live database; the chooser is the
        // thing under test, and a failed read degrades instead of throwing.
        $ctx->set(TrackService::class, static fn() => new TrackService($stations));
    }

    public function testIphoneRendersTheStationButtons(): void
    {
        $html = (new IphoneController())->indexAction();

        self::assertStringContainsString('HBR1 @ iPhone', $html);
        self::assertStringContainsString('ambient.aac.m3u', $html);
        self::assertStringContainsString('Dream Factory', $html);
        // The oversized type is the point of the page.
        self::assertStringContainsString('font-size: 32pt', $html);
    }

    public function testAdminDoesNotServeTheLegacyFrameset(): void
    {
        $html = (new AdminController())->indexAction();

        self::assertStringNotContainsString('<frameset', $html);
        self::assertStringNotContainsString('admin.cgi', $html);
        self::assertStringContainsString('not ported yet', $html);
    }
}
