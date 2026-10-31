<?php

namespace App\Controllers\Web;

use App\Controllers\BaseController;
use App\Services\StationService;
use App\Services\StationViewService;
use App\Services\TrackService;
use Azera\Http\Response;

/**
 * The iPhone station chooser (replaces `main.php?module=iphone`, which
 * `apax.cgi?run=iphone` maps onto).
 *
 * A deliberately oversized page: 30 pt body text, 32 pt station names, one
 * full-width button per station, built for a phone held at arm's length.
 * Port of `template/client/iphone.htm`.
 *
 * The legacy page printed the station list through a PAB3 loop and read the
 * live track from the playout database to caption each button. The route was
 * mapped to `/iphone` but never registered, so the redirect landed on a 404.
 */
class IphoneController extends BaseController
{
    public function indexAction(): string|Response
    {
        /** @var StationService $stations */
        $stations = $this->service(StationService::class);
        /** @var TrackService $tracks */
        $tracks = $this->service(TrackService::class);
        /** @var StationViewService $view */
        $view = $this->service(StationViewService::class);

        $panels = [];

        foreach ($stations->all() as $station) {
            $running = null;

            try {
                $now = $tracks->current($station);

                if ($now !== null) {
                    $running = $view->remaining($now['playedAt'], $now['duration']);
                    $running['caption'] = $view->fit(
                        $view->caption($now['artist'], $now['title']),
                        45
                    );
                }
            } catch (\Throwable $e) {
                // A playout database being down must not take the chooser
                // down: the streams keep running regardless.
                error_log('iphone: ' . $e->getMessage());
            }

            $panels[] = array_merge($station, ['running' => $running]);
        }

        return $this->page('web/iphone', [
            'title'  => 'HBR1 Stations',
            'panels' => $panels,
        ]);
    }
}
