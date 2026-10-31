<?php

namespace App\Services;

use Azera\Db\Query;
use Azera\Db\Resolver\ChainResolver;
use Azera\Db\Resolver\MappingResolver;
use Azera\Db\Resolver\ModelResolver;

/**
 * Current-track, queue and history reads for a station.
 *
 * All three channels run RadioDJ, so there is one code path. The service hides
 * the schema-qualified table names and the query builder behind plain arrays,
 * so controllers and views never see SQL.
 *
 * All access to the playout databases lives here (plan §5.3). No model classes:
 * these schemas belong to a third-party application (plan §5.2).
 */
class TrackService
{
    /**
     * Hours back that a song request still marks its queued entry.
     *
     * The legacy `init_station_playlist()` used `SUBDATE(NOW(), INTERVAL 12
     * HOUR)`; kept verbatim.
     */
    private const REQUEST_WINDOW_HOURS = 12;

    public function __construct(
        private readonly StationService $stations,
        private readonly ?\Azera\Db\Database $db = null,
    ) {
    }

    /**
     * The track currently playing.
     *
     * `duration` is returned as a float, not an int: RadioDJ stores fractional
     * seconds (e.g. 4340.98 for a 72-minute DJ set). Truncating it to int lost
     * almost a full second per track, which is visible in the countdown.
     *
     * @return array{artist: string, title: string, playedAt: ?string, duration: float}|null
     */
    public function current(array $station): ?array
    {
        $row = $this->query($station, 'RadioDjHistory')
            ->orderBy('ID DESC')
            ->limit(1)
            ->select()
            ->fetchAssoc();

        if ($row === false) {
            return null;
        }

        $row = (array) $row;

        return [
            'artist'   => (string) ($row['artist'] ?? ''),
            'title'    => (string) ($row['title'] ?? ''),
            'playedAt' => isset($row['date_played']) ? (string) $row['date_played'] : null,
            'duration' => (float) ($row['duration'] ?? 0),
        ];
    }

    /**
     * The upcoming queue, in play order.
     *
     * Four behaviours come straight from the legacy `init_station_playlist()`:
     *
     *  - only songs the station may play are listed (`songs.song_type = 0`).
     *    Type 1 rows are jingles and station IDs; the first port listed them
     *    as if they were tracks.
     *  - rows whose `title` is blank are skipped afterwards, because RadioDJ
     *    leaves empty placeholder rows in `queuelist` (they pass the join).
     *  - a song requested in the last {@see REQUEST_WINDOW_HOURS} hours is
     *    flagged so the page can mark it.
     *  - the join to `requests` is by `songID` and is many-to-many, so a song
     *    requested twice would appear twice. The legacy query had that bug and
     *    the page could repeat a line; it is deduplicated here.
     *
     * Written as one prepared statement rather than through the builder: it
     * needs a two-table join plus a time-window predicate, which is the shape
     * plan §5.2 keeps out of the ORM.
     *
     * @return array<int, array{artist: string, title: string, requested: bool}>
     */
    public function queue(array $station, int $limit = 8): array
    {
        $schema = MusicDatabaseMapping::schemaFor($station['id']);

        $sql = "SELECT q.`ID`, q.`artist`, q.`title`,
                       MAX(CASE WHEN r.`ID` IS NULL THEN 0 ELSE 1 END) AS `requested`
                  FROM `{$schema}`.`queuelist` q
                  JOIN `{$schema}`.`songs` s ON s.`ID` = q.`songID`
             LEFT JOIN `{$schema}`.`requests` r
                    ON r.`songID` = q.`songID`
                   AND r.`requested` >= SUBDATE(NOW(), INTERVAL ? HOUR)
                 WHERE s.`song_type` = 0
              GROUP BY q.`ID`, q.`artist`, q.`title`
              ORDER BY q.`ID`";

        $rows = $this->database()
            ->query($sql, [self::REQUEST_WINDOW_HOURS])
            ->fetchAll(\PDO::FETCH_ASSOC);

        $out = [];

        foreach ($rows as $row) {
            if (trim((string) ($row['title'] ?? '')) === '') {
                continue;
            }

            $out[] = [
                'artist'    => (string) ($row['artist'] ?? ''),
                'title'     => (string) ($row['title'] ?? ''),
                'requested' => (int) ($row['requested'] ?? 0) > 0,
            ];

            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    /**
     * The playlist page: what is playing now, then the queue.
     *
     * @return array<int, string> display lines, HTML-free
     */
    public function playlist(array $station, int $limit = 12): array
    {
        $lines = [];
        $now   = $this->current($station);

        if ($now !== null) {
            $lines[] = trim($now['artist'] . ' - ' . $now['title'], ' -');
        }

        foreach ($this->queue($station, $limit) as $item) {
            $lines[] = trim($item['artist'] . ' - ' . $item['title'], ' -');
        }

        return $lines;
    }

    private function query(array $station, string $logicalPrefix): Query
    {
        return Query::new()
            ->using(new ChainResolver([
                new ModelResolver(),
                new MappingResolver(MusicDatabaseMapping::forStation($station['id'])),
            ]))
            ->from($logicalPrefix . '_' . $station['id']);
    }

    private function database(): \Azera\Db\Database
    {
        return $this->db ?? \App\AppContext::instance()->dbManager()->getDefault();
    }
}
