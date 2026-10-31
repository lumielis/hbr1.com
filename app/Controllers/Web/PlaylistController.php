<?php

namespace App\Controllers\Web;

use App\Controllers\BaseController;
use App\Services\StationService;

/**
 * Playlist page (replaces `main.php?module=playlist`).
 */
class PlaylistController extends BaseController
{
    public function indexAction(): string
    {
        return $this->renderFor(null);
    }

    public function stationAction(string $station): string
    {
        return $this->renderFor($station);
    }

    private function renderFor(?string $identifier): string
    {
        /** @var StationService $stations */
        $stations = $this->service(StationService::class);

        return $this->page('web/playlist', [
            'title'       => 'Playlist - HBR1',
            'stationList' => $stations->all(),
            'current'     => $identifier === null ? null : $stations->find($identifier),
        ]);
    }
}
