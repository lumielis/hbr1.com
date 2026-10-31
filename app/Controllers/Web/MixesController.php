<?php

namespace App\Controllers\Web;

use App\Controllers\BaseController;
use App\Services\MixesService;
use Azera\Http\Response;

/**
 * Mixes (replaces `mixes.cgi`).
 *
 * Three levels, matching the legacy `action` parameter:
 *   /mixes                   categories
 *   /mixes/{category}        mixes in a style
 *   /mixes/playlist/{id}     one mix and its track listing
 */
class MixesController extends BaseController
{
    public function indexAction(): string
    {
        /** @var MixesService $service */
        $service = $this->service(MixesService::class);

        return $this->page('web/mixes', [
            'title'      => 'Mixes - HBR1',
            'categories' => $service->categories(),
            'mixes'      => $service->mixes(),
            'search'     => (string) ($this->context()->request()->query('q') ?? ''),
        ]);
    }

    public function categoryAction(int $category): Response|string
    {
        /** @var MixesService $service */
        $service = $this->service(MixesService::class);

        $data = $service->category($category);

        if ($data === null) {
            return Response::status(404);
        }

        return $this->page('web/mixes_category', [
            'title'    => $data['name'] . ' - Mixes - HBR1',
            'category' => $data,
            'mixes'    => $service->mixes($category),
        ]);
    }

    public function playlistAction(int $id): Response|string
    {
        /** @var MixesService $service */
        $service = $this->service(MixesService::class);

        $mix = $service->find($id);

        // The one published row in the live database has no name; showing an
        // untitled mix would be a blank page, so it is not reachable.
        if ($mix === null || $mix['status'] === 0 || $mix['name'] === '') {
            return Response::status(404);
        }

        return $this->page('web/mixes_playlist', [
            'title' => $mix['name'] . ' - Mixes - HBR1',
            'mix'   => $mix,
        ]);
    }
}
