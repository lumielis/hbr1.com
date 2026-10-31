<?php

namespace App\Controllers\Web;

use App\Controllers\BaseController;
use App\Services\SongService;
use App\Services\StationService;
use Azera\Http\Response;

/**
 * The station catalogue (W4).
 *
 * This is one page under two legacy names:
 *
 *   - `main.php?module=tracklist` - the "Inventory" button. The legacy page
 *     listed every playable row of a station's `songs` table, twenty at a
 *     time, with a `Play it` link on each.
 *   - `mixes.cgi` - the "Tracklist" button. A DJ-set archive with a per-mix
 *     track listing that was never filled in.
 *
 * HBR1 plays mixes only, so the two are the same list: the RadioDJ `songs`
 * rows *are* the DJ sets. They are merged here rather than kept as two
 * half-empty pages. The mix-category and single-mix levels are gone with the
 * editor (see docs/README.md); `mixes`, `releases`, `labels` and
 * `media_category` stay in the database until the admin work in W8 decides
 * their fate.
 */
class MixesController extends BaseController
{
    public function indexAction(): string
    {
        return $this->renderFor(null, $this->requestedOffset());
    }

    public function stationAction(string $station): string|Response
    {
        return $this->renderFor($station, $this->requestedOffset());
    }

    /**
     * The legacy page took its position from the query string. It is clamped
     * to a non-negative integer so a hand-crafted value cannot produce a
     * negative SQL offset.
     */
    private function requestedOffset(): int
    {
        $offset = $this->context()->request()->query('offset');

        return is_numeric($offset) ? max(0, (int) $offset) : 0;
    }

    private function renderFor(?string $identifier, int $offset): string|Response
    {
        /** @var StationService $stations */
        $stations = $this->service(StationService::class);
        /** @var SongService $songs */
        $songs = $this->service(SongService::class);

        $current = $identifier === null ? null : $stations->find($identifier);

        if ($identifier !== null && $current === null) {
            return Response::status(404);
        }

        $page  = null;
        $pager = null;

        if ($current !== null) {
            try {
                $page = $songs->page($current, $offset);

                // An offset past the end would otherwise render an empty
                // table with no explanation.
                if ($page['total'] > 0 && $offset >= $page['total']) {
                    $page = $songs->page($current, max(0, $page['total'] - SongService::PAGE_SIZE));
                }

                $pager = $this->pager($page);
            } catch (\Throwable $e) {
                error_log('mixes: ' . $e->getMessage());
            }
        }

        return $this->page('web/mixes', [
            'title'       => 'HBR1 Inventory',
            'stationList' => $stations->all(),
            'current'     => $current,
            'page'        => $page,
            'pager'       => $pager,
        ]);
    }

    /**
     * Page arithmetic, matching the legacy page_navigation() window
     * (five pages either side of the current one).
     */
    private function pager(array $page): array
    {
        $total   = $page['total'];
        $limit   = $page['limit'];
        $offset  = $page['offset'];
        $current = intdiv($offset, $limit) + 1;
        $last    = max(1, (int) ceil($total / $limit));
        $window  = 5;

        $start = max(1, $current - $window);
        $end   = min($last, $start + $window * 2);

        if ($end - $start < $window * 2) {
            $start = max(1, $end - $window * 2);
        }

        return [
            'current' => $current,
            'last'    => $last,
            'start'   => $start,
            'end'     => $end,
            'offset'  => $offset,
            'limit'   => $limit,
            'total'   => $total,
            'hasPrev' => $current > 1,
            'hasNext' => $current < $last,
            'prevOff' => max(0, $offset - $limit),
            'nextOff' => min(max(0, $total - 1), $offset + $limit),
        ];
    }
}
