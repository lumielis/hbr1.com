<?php

namespace App\Tests;

use App\Controllers\Web\ListenController;
use App\Controllers\Web\MixesController;
use App\Controllers\Web\PlaylistController;
use App\Controllers\Web\TracklistController;
use App\Services\StationService;
use App\Services\TrackService;
use PHPUnit\Framework\TestCase;

/**
 * An unknown station must be a 404, not a rendered page.
 *
 * The first pass of these controllers treated a missing station as "no station
 * selected" and rendered the chooser with HTTP 200. That was wrong twice over:
 * it makes every typo and every stale inbound link a live, indexable URL, and
 * it hid the mistake from the smoke test, which only looked at route matching.
 */
class StationNotFoundTest extends TestCase
{
    public function testListenReturnsNotFoundForUnknownStation(): void
    {
        $this->useStations();

        self::assertSame(404, (new ListenController())->stationAction('nope')->getStatus());
    }

    public function testPlaylistReturnsNotFoundForUnknownStation(): void
    {
        $this->useStations();

        self::assertSame(404, (new PlaylistController())->stationAction('nope')->getStatus());
    }

    public function testTracklistReturnsNotFoundForUnknownStation(): void
    {
        $this->useStations();

        self::assertSame(404, (new TracklistController())->stationAction('nope')->getStatus());
    }

    /**
     * `/mixes/{station}` is the catalogue proper; an unknown slug is a 404
     * there too, now that it replaced the mix-category level.
     */
    public function testMixesReturnsNotFoundForUnknownStation(): void
    {
        $this->useStations();

        self::assertSame(404, (new MixesController())->stationAction('nope')->getStatus());
    }

    /**
     * The retired `/tracklist` name redirects permanently to the catalogue,
     * and validates the slug first so a typo does not become a live 301.
     */
    public function testTracklistRedirectsToTheCatalogue(): void
    {
        $this->useStations();

        $response = (new TracklistController())->indexAction();
        self::assertSame(301, $response->getStatus());
        self::assertSame('/mixes', $response->getHeader('Location'));

        $response = (new TracklistController())->stationAction('Trance');
        self::assertSame(301, $response->getStatus());
        self::assertSame('/mixes/trance', $response->getHeader('Location'));
    }

    /**
     * The chooser pages themselves must stay 200 - only the station-scoped
     * routes can 404 on an unknown name.
     */
    public function testStationChoosersStillRender(): void
    {
        $this->useStations();

        self::assertStringContainsString('<', (new ListenController())->indexAction());
        self::assertStringContainsString('<', (new PlaylistController())->indexAction());
        self::assertStringContainsString('<', (new MixesController())->indexAction());
    }

    private function useStations(): void
    {
        \App\Bootstrap::bootstrap();

        $stations = new StationService([
            [
                'id'         => 'Trance',
                'slug'       => 'trance',
                'title'      => 'Trance',
                'genre'      => 'Trance',
                'stream'     => [],
                'muses_url'  => '',
                'iphone_url' => '',
                'radiodj_db' => 'radiodj_trance',
                'playlists'  => [],
            ],
        ]);

        $ctx = \App\AppContext::instance();
        $ctx->set(StationService::class, static fn() => $stations);
        // Never reached: an unknown station is rejected before any query, so
        // this keeps the test offline.
        $ctx->set(TrackService::class, static fn() => new TrackService($stations));
    }
}
