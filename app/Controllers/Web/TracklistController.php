<?php

namespace App\Controllers\Web;

use App\Controllers\BaseController;

/**
 * Tracklist page (replaces `main.php?module=tracklist`).
 *
 * The data side lands in workstream W4; this controller already owns the
 * routing so the legacy redirect target exists.
 */
class TracklistController extends BaseController
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
        $stations = $this->service(\App\Services\StationService::class);

        return $this->page('web/tracklist', [
            'title'       => 'HBR1 Inventory',
            'stationList' => $stations->all(),
            'current'     => $identifier === null ? null : $stations->find($identifier),
        ]);
    }
}
