<?php

namespace App\Tests;

use App\Controllers\Web\ListenController;
use App\Controllers\Web\MixesController;
use App\Controllers\Web\MusesController;
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
     * `/muses` without a station must still render the player, as it did in
     * production; an unknown slug is still a 404.
     */
    public function testMusesRendersTheFallbackStationAnd404sAnUnknownOne(): void
    {
        $this->useStations();

        $response = (new MusesController())->indexAction();
        self::assertIsString($response);
        self::assertStringContainsString('MRP.insert', $response);

        self::assertSame(404, (new MusesController())->stationAction('nope')->getStatus());
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

    /**
     * The two catalogue states are different pages in the legacy tree and must
     * stay different: the chooser (`tracklist.phtml`) has no SUBMENU band, no
     * `th_*.gif` title cap and no `<br>` above its list; the station catalogue
     * (`tracklist.mix.phtml`) has all three. A previous port rendered the
     * chooser chrome for both, which is why the page looked wrong next to
     * production.
     */
    public function testTheChooserAndTheCatalogueCarryDifferentChrome(): void
    {
        $this->useStations();

        $chooser = (new MixesController())->indexAction();

        self::assertStringNotContainsString('submenu_bg.gif', $chooser);
        self::assertStringNotContainsString('table/th_left.gif', $chooser);
        self::assertStringContainsString('Choose a station', $chooser);

        // The station page needs a database, so only its chrome is asserted
        // through the view itself rather than a rendered response.
        $view = file_get_contents(BASE_DIR . '/views/web/mixes.clarity.html');

        self::assertStringContainsString('/images/table/th_left.gif', $view);
        self::assertStringContainsString('/images/table/tr_bg.gif', $view);
        self::assertStringContainsString('{% set submenu = true %}', $view);
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
                'stream'     => ['ogg' => 'http://radio.hbr1.com:19800/trance.ogg'],
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
