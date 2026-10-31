<?php

namespace App\Controllers\Web;

use App\Controllers\BaseController;
use App\Services\StationService;
use Azera\Http\Response;

/**
 * The retired `/tracklist` URLs.
 *
 * `/tracklist` was the legacy `main.php?module=tracklist` - the station song
 * catalogue behind the "Inventory" button. The button labelled "Tracklist"
 * pointed somewhere else entirely: `mixes.cgi`, a DJ-set archive whose
 * per-mix track listing was never used.
 *
 * HBR1 plays mixes only, so the catalogue and the mix archive are the same
 * thing and now share one page at `/mixes`. These routes exist only to carry
 * the old name, the bookmarks and the search results across, with a permanent
 * redirect so the canonical URL is the one that gets indexed.
 *
 * A 301 is correct here even though the two pages had different titles in the
 * legacy tree: they now render identical content, so there is nothing to lose
 * by collapsing them.
 */
class TracklistController extends BaseController
{
    public function indexAction(): Response
    {
        return Response::redirect('/mixes', 301);
    }

    /**
     * An unknown station must not become a live redirect target, so the slug
     * is validated before the 301 is issued.
     */
    public function stationAction(string $station): Response
    {
        /** @var StationService $stations */
        $stations = $this->service(StationService::class);

        if ($stations->find($station) === null) {
            return Response::status(404);
        }

        return Response::redirect('/mixes/' . strtolower($station), 301);
    }
}
