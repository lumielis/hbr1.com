<?php

namespace App\Controllers\Web;

use App\Controllers\BaseController;
use App\Services\StationService;
use App\Services\TrackService;

/**
 * Station listening page (replaces `main.php?module=listen`).
 */
class ListenController extends BaseController
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
        /** @var TrackService $tracks */
        $tracks = $this->service(TrackService::class);

        $current = $identifier === null ? null : $stations->find($identifier);
        $now     = null;
        $queue   = [];

        if ($current !== null) {
            try {
                $now   = $tracks->current($current);
                $queue = $tracks->queue($current);
            } catch (\Throwable $e) {
                // A playout database being unreachable must not take the page
                // down: the streams keep running regardless.
                error_log('listen: ' . $e->getMessage());
            }
        }

        return $this->page('web/listen', [
            'title'       => 'Listen - HBR1',
            'stationList' => $stations->all(),
            'current'     => $current,
            'now'         => $now,
            'queue'       => $queue,
        ]);
    }
}
