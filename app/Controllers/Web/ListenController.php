<?php

namespace App\Controllers\Web;

use App\Controllers\BaseController;
use App\Services\MixesService;
use App\Services\StationService;
use App\Services\StationViewService;
use App\Services\TrackService;
use Azera\Http\Response;

/**
 * Station listening page (replaces `main.php?module=listen`).
 *
 * The legacy page listed *all* stations on one page, each with its own panel,
 * and ignored the `?station=` parameter completely - only `tracklist` ever used
 * it. The first port invented a single-station view, which is why production's
 * page and this one did not line up.
 *
 * So both routes render the same three panels. The station-scoped route still
 * exists because the legacy URL mapper and the front page link to it; a valid
 * one moves that station to the top, and an unknown one is a 404 rather than a
 * 200 that search engines will index.
 */
class ListenController extends BaseController
{
    public function indexAction(): string
    {
        return $this->renderFor(null);
    }

    public function stationAction(string $station): string|Response
    {
        return $this->renderFor($station);
    }

    private function renderFor(?string $identifier): string|Response
    {
        /** @var StationService $stations */
        $stations = $this->service(StationService::class);
        /** @var TrackService $tracks */
        $tracks = $this->service(TrackService::class);
        /** @var StationViewService $view */
        $view = $this->service(StationViewService::class);
        /** @var MixesService $mixes */
        $mixes = $this->service(MixesService::class);

        $current = $identifier === null ? null : $stations->find($identifier);

        if ($identifier !== null && $current === null) {
            return Response::status(404);
        }

        $all = $stations->all();

        if ($current !== null) {
            $all = StationViewService::promote($all, (string) $current['slug']);
        }

        return $this->page('web/listen', [
            'title'       => 'HBR1 Stations',
            'stationList' => array_map(
                fn(array $station): array => $this->panel($station, $tracks, $view, $mixes, 'listen'),
                $all
            ),
        ]);
    }

    /**
     * Everything the panel template needs for one station.
     *
     * A playout database being unreachable must not take the page down - the
     * streams keep running regardless - so a failed read degrades to "no track
     * information" instead of a 500.
     */
    private function panel(
        array $station,
        TrackService $tracks,
        StationViewService $view,
        MixesService $mixes,
        string $route,
    ): array {
        $running = null;
        $mixId   = null;

        try {
            $now = $tracks->current($station);

            if ($now !== null) {
                $running = $view->remaining($now['playedAt'], $now['duration']);
                $running['caption'] = $view->fit(
                    $view->caption($now['artist'], $now['title']),
                    45
                );

                $mixId = $mixes->matchTrack($now['artist'], $now['title']);
            }
        } catch (\Throwable $e) {
            error_log($route . ': ' . $e->getMessage());
            $running = null;
            $mixId   = null;
        }

        return array_merge($station, [
            'running' => $running,
            'mixId'   => $mixId,
            'stream'  => $view->streamSegments(
                $station['playlists'] ?? [],
                '/muses/' . $station['slug']
            ),
        ]);
    }
}
