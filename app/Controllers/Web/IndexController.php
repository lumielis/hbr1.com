<?php

namespace App\Controllers\Web;

use App\Controllers\BaseController;
use App\Services\StationService;
use Azera\Http\Response;

/**
 * The frameset front page.
 *
 * `public/index.html` is a frameset whose frames point at the CGI entry
 * points. This controller serves the same frameset from PHP so the frames can
 * address canonical routes instead of `/cgi-bin/*.cgi`.
 */
class IndexController extends BaseController
{
    public function indexAction(): string
    {
        /** @var StationService $stations */
        $stations = $this->service(StationService::class);

        return $this->fragment('web/index', [
            'title'    => 'HBR1 - Music on Futurenet',
            'stations' => $stations->all(),
        ]);
    }
}
