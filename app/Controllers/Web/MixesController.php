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
    /** How many pages either side of the current one the legacy pager showed. */
    private const PAGER_WINDOW = 7;

    public function indexAction(): string
    {
        return $this->renderFor(null);
    }

    public function stationAction(string $station): string|Response
    {
        return $this->renderFor($station);
    }

    /**
     * The legacy page took its position from the query string. Without one it
     * jumped to a *random* page (`rand(0, COUNT(*) - 20)`), so every visit to
     * the catalogue showed a different slice - production still does. That is
     * reproduced here, but only for a station that resolved and only when the
     * parameter is absent altogether: `?offset=` is honoured as given, and a
     * malformed value is clamped to a non-negative integer so it cannot
     * produce a negative SQL offset.
     */
    private function requestedOffset(?array $station): int
    {
        $offset = $this->context()->request()->query('offset');

        if (is_numeric($offset)) {
            return max(0, (int) $offset);
        }

        if ($station === null) {
            return 0;
        }

        try {
            /** @var SongService $songs */
            $songs = $this->service(SongService::class);

            return $songs->randomOffset($station);
        } catch (\Throwable $e) {
            error_log('mixes: ' . $e->getMessage());

            return 0;
        }
    }

    private function renderFor(?string $identifier, ?int $offset = null): string|Response
    {
        /** @var StationService $stations */
        $stations = $this->service(StationService::class);
        /** @var SongService $songs */
        $songs = $this->service(SongService::class);

        $current = $identifier === null ? null : $stations->find($identifier);

        if ($identifier !== null && $current === null) {
            return Response::status(404);
        }

        $offset ??= $this->requestedOffset($current);
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

        return $this->page($current === null ? 'web/mixes_chooser' : 'web/mixes', [
            'title'       => 'HBR1 Inventory',
            'stationList' => $stations->all(),
            'current'     => $current,
            // The legacy band read "- TRANCE MIXES -" from `strtoupper(Id)`;
            // computed here because Clarity's filter chain does not accept a
            // concatenation inside a filter expression.
            'bandLabel'   => $current === null ? '' : '- ' . strtoupper((string) $current['id']) . ' MIXES -',
            // The legacy page gated the action on the *station* having a
            // request target, not on the row having a title, so blank rows
            // still offered "Play it". Production shows exactly that.
            'canRequest'  => $current !== null && !empty($current['radiodj_db']),
            'page'        => $page,
            'pager'       => $pager,
        ]);
    }

    /**
     * Page arithmetic, reproducing the legacy `page_navigation()` exactly.
     *
     * The legacy window is asymmetric: it holds the current page at the left
     * edge (seven pages after it) until it would run past the last page, then
     * slides back. So page 1 of 18 shows 1..15 and page 9 shows 2..16 - not
     * seven either side of centre.
     */
    private function pager(array $page): array
    {
        $total   = $page['total'];
        $limit   = $page['limit'];
        $offset  = $page['offset'];
        $window  = self::PAGER_WINDOW;
        $current = intdiv($offset, $limit) + 1;
        $last    = max(1, (int) ceil($total / $limit));

        $start = max(1, $current - $window);
        $end   = $current + $window + ($window - ($current - $start));

        // A window that ran past the last page slides back, keeping its
        // width; the current page is then no longer at the left edge.
        if ($end > $last) {
            $start = max(1, $start - ($end - $last));
            $end   = $last;
        }

        $pages = [];

        for ($number = $start; $number <= $end; $number++) {
            $pages[] = [
                'number'  => $number,
                'offset'  => ($number - 1) * $limit,
                'current' => $number === $current,
            ];
        }

        return [
            'current'   => $current,
            'last'      => $last,
            'start'     => $start,
            'end'       => $end,
            'pages'     => $pages,
            'offset'    => $offset,
            'limit'     => $limit,
            'total'     => $total,
            'hasPrev'   => $current > 1,
            'hasNext'   => $current < $last,
            // The legacy "<<"/">>" step, clamped the same way it was.
            'prevOff'   => max(0, $offset - $limit),
            'nextOff'   => $offset + $limit,
            'firstOff'  => 0,
            'lastOff'   => ($last - 1) * $limit,
            'leadEllipsis'  => $start > 1,
            'trailEllipsis' => $end < $last,
        ];
    }
}
