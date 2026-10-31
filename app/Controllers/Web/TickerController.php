<?php

namespace App\Controllers\Web;

use App\Controllers\BaseController;
use App\Services\TickerService;
use Azera\Http\Response;

/**
 * WebTV ticker frames (replaces `/webtv/ticker/index.html` + the generated
 * `content*.htm` chain).
 *
 * The legacy pipeline wrote one static file per ticker row and chained them
 * with `<meta http-equiv="refresh">`. This renders the same pages from the
 * database instead, under the same file names, so the frameset keeps working
 * unchanged and a page saved in the admin shows up on the next refresh.
 *
 * Only the *middle* frame is dynamic; `index_left.html` and `index_right.html`
 * are still the static camera chrome and are served from disk.
 */
class TickerController extends BaseController
{
    /**
     * The ticker frameset (`/webtv/ticker/`).
     *
     * The left edge is the camera chrome, the middle is the rotating page, the
     * right edge is more chrome. It used to be a static `index.html` in the
     * document root; serving it from a view means the middle frame can point at
     * a route instead of at a file that has to exist.
     */
    public function startAction(): string
    {
        return $this->fragment('web/ticker', ['title' => 'TV Ticker']);
    }

    /**
     * A ticker page. `/webtv/ticker/content.htm`, `content1.htm`, …
     */
    public function pageAction(string $file): string|Response
    {
        $index = TickerService::indexFromFileName($file);

        if ($index === null) {
            return Response::status(404);
        }

        /** @var TickerService $service */
        $service = $this->service(TickerService::class);
        $page = $service->page($index);

        if ($page === null || $page['isFlash']) {
            // Flash pages have no renderer any more (the plugin is gone); the
            // legacy template also required the plugin, so the honest answer is
            // a 404 rather than an empty black box.
            return Response::status(404);
        }

        return $this->fragment('web/ticker_page', [
            'title' => 'TV Ticker',
            'page'  => $page,
            'next'  => $page['next'],
        ]);
    }
}
