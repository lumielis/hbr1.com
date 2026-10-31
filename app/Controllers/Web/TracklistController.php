<?php

namespace App\Controllers\Web;

use App\Controllers\BaseController;
use App\Services\SongService;
use App\Services\StationService;

/**
 * Tracklist page (replaces `main.php?module=tracklist`).
 *
 * With no station selected it is the station chooser (the legacy
 * "HBR1 Inventory" page). With a station it pages through the catalogue.
 */
class TracklistController extends BaseController
{
    public function indexAction(): string
    {
        return $this->renderFor(null, $this->requestedOffset());
    }

    public function stationAction(string $station): string
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

    private function renderFor(?string $identifier, int $offset): string
    {
        /** @var StationService $stations */
        $stations = $this->service(StationService::class);
        /** @var SongService $songs */
        $songs = $this->service(SongService::class);

        $current = $identifier === null ? null : $stations->find($identifier);
        $page    = null;
        $pager   = null;

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
                error_log('tracklist: ' . $e->getMessage());
            }
        }

        return $this->page('web/tracklist', [
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
