<?php

namespace App\Services;

use Azera\Db\Query;
use Azera\Db\Resolver\ChainResolver;
use Azera\Db\Resolver\MappingResolver;
use Azera\Db\Resolver\ModelResolver;

/**
 * Current-track and queue reads for a station.
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
    public function __construct(
        private readonly StationService $stations,
    ) {
    }

    /**
     * The track currently playing.
     *
     * @return array{artist: string, title: string, playedAt: ?string, duration: int}|null
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
            'playedAt' => $row['date_played'] ?? null,
            'duration' => (int) ($row['duration'] ?? 0),
        ];
    }

    /**
     * The upcoming queue, in play order.
     *
     * RadioDJ denormalises artist and title onto `queuelist`, so no join is
     * needed.
     *
     * @return array<int, array{artist: string, title: string}>
     */
    public function queue(array $station, int $limit = 8): array
    {
        $rows = $this->query($station, 'RadioDjQueue')
            ->columns(['artist', 'title'])
            ->orderBy('ID')
            ->limit($limit)
            ->select();

        $out = [];

        foreach ($rows as $row) {
            $row = (array) $row;

            if (trim((string) ($row['title'] ?? '')) === '') {
                continue;
            }

            $out[] = [
                'artist' => (string) ($row['artist'] ?? ''),
                'title'  => (string) ($row['title'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * The playlist page: the current track followed by the queue.
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
}
