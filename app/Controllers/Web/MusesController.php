<?php

namespace App\Controllers\Web;

use App\Controllers\BaseController;
use App\Services\StationService;
use Azera\Http\Response;

/**
 * The Muses HTML player popup (replaces `main.php?module=muses`).
 *
 * `/listen` and `/playlist` link to this from the "Tune In:" row, via
 * `openMuses()`. The route was missing, so that link was a 404. Port of
 * `template/muses.phtml`.
 *
 * The player itself is a third-party script (`hosted.muses.org/mrp.js`) driven
 * by a small config block; only the station's stream URL changes. No data of
 * ours is involved beyond the station lookup.
 */
class MusesController extends BaseController
{
    public function indexAction(): string|Response
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

        $station = $identifier === null
            ? $stations->fallback()
            : $stations->find($identifier);

        if ($station === null) {
            return Response::status(404);
        }

        return $this->fragment('web/muses', [
            'title'   => 'HBR1 - ' . $station['title'] . ' - Muses Radio Player',
            'station' => $station,
            'streamUrl' => $stations->browserStreamUrl($station),
        ]);
    }
}
