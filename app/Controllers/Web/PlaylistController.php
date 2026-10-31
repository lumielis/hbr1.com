<?php

namespace App\Controllers\Web;

use App\Controllers\BaseController;
use App\Services\StationService;
use App\Services\TrackService;
use Azera\Http\Response;

/**
 * Playlist page (replaces `main.php?module=playlist` and
 * `apax.cgi?Part=Playlist`).
 */
class PlaylistController extends BaseController
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

        $current = $identifier === null ? null : $stations->find($identifier);

        if ($identifier !== null && $current === null) {
            return Response::status(404);
        }

        $lines = [];

        if ($current !== null) {
            try {
                $lines = $tracks->playlist($current, 12);
            } catch (\Throwable $e) {
                error_log('playlist: ' . $e->getMessage());
            }
        }

        return $this->page('web/playlist', [
            'title'       => 'Playlist - HBR1',
            'stationList' => $stations->all(),
            'current'     => $current,
            'lines'       => $lines,
        ]);
    }
}
