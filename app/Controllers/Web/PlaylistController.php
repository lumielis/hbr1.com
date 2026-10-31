<?php

namespace App\Controllers\Web;

use App\Controllers\BaseController;
use App\Services\MixesService;
use App\Services\StationService;
use App\Services\StationViewService;
use App\Services\TrackService;
use Azera\Http\Response;

/**
 * Playlist page (replaces `main.php?module=playlist` and
 * `apax.cgi?Part=Playlist`).
 *
 * Same three panels as /listen, but each shows an eight-line numbered
 * "now and soon" list instead of the queue, exactly as `playlist.phtml` did.
 * The legacy script also ignored `?station=`; the scoped route promotes a valid
 * station and 404s an unknown one, for the same reasons as /listen.
 */
class PlaylistController extends BaseController
{
    /** The legacy ordered list was always eight items long. */
    private const LINES = 8;

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

        return $this->page('web/playlist', [
            'title'       => 'HBR1 Station Playlist',
            'stationList' => array_map(
                fn(array $station): array => $this->panel($station, $tracks, $view, $mixes),
                $all
            ),
        ]);
    }

    /**
     * One station's panel: the same header as /listen, then the list.
     *
     * Line 1 of the list is the currently playing track, flagged as `#current`
     * by the template; the rest is the queue. The legacy page did the same by
     * prepending the latest `history` row to the queue.
     */
    private function panel(
        array $station,
        TrackService $tracks,
        StationViewService $view,
        MixesService $mixes,
    ): array {
        $running = null;
        $mixId   = null;
        $queue   = [];
        $lead    = null;

        try {
            $now = $tracks->current($station);

            if ($now !== null) {
                $running = $view->remaining($now['playedAt'], $now['duration']);
                $running['caption'] = $view->fit(
                    $view->caption($now['artist'], $now['title']),
                    45
                );

                $lead  = ['artist' => $now['artist'], 'title' => $now['title']];
                $mixId = $mixes->matchTrack($now['artist'], $now['title']);
            }

            // One fewer queue row when the current track occupies line 1.
            $queue = $tracks->queue($station, self::LINES - 1);
        } catch (\Throwable $e) {
            error_log('playlist: ' . $e->getMessage());
            $running = null;
            $mixId   = null;
            $queue   = [];
            $lead    = null;
        }

        return array_merge($station, [
            'running' => $running,
            'mixId'   => $mixId,
            'lines'   => $view->playlistLines($queue, self::LINES, (string) $station['slug'], $lead),
            'stream'  => $view->streamSegments(
                $station['playlists'] ?? [],
                '/muses/' . $station['slug']
            ),
        ]);
    }
}