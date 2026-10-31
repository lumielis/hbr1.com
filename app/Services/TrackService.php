<?php

namespace App\Services;

use Azera\AppContext;
use Azera\Db\Query;
use Azera\Db\Resolver\ChainResolver;
use Azera\Db\Resolver\MappingResolver;
use Azera\Db\Resolver\ModelResolver;

/**
 * Current-track and queue reads for a station.
 *
 * The three channels are on different playout systems: `Tronic` and `Ambient`
 * are on RadioDJ, and all three still carry a SAM Broadcaster schema. The
 * service hides that split so controllers and views see one shape.
 *
 * All access to the music databases lives here (plan §5.3). No model classes:
 * these schemas belong to third-party applications (plan §5.2).
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
        $query = $this->usesRadioDj($station)
            ? $this->query($station, 'RadioDjHistory')->orderBy('ID DESC')->limit(1)
            : $this->query($station, 'SamHistory')->orderBy('ID DESC')->limit(1);

        $row = $query->select()->fetchAssoc();

        if (!$row) {
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
     * RadioDJ denormalises artist/title onto `queuelist`, so no join is
     * needed there. SAM keeps only `songID`, so it still joins `songlist`.
     *
     * @return array<int, array{artist: string, title: string, requested: bool}>
     */
    public function queue(array $station, int $limit = 8): array
    {
        return $this->queueRows($station, $limit);
    }

    /**
     * The full playlist shown on the playlist page: the current track followed
     * by the queue, with a marker for requested tracks.
     *
     * @return array<int, string> display lines, HTML-free
     */
    public function playlist(array $station, int $limit = 8): array
    {
        $lines = [];
        $now   = $this->current($station);

        if ($now !== null) {
            $lines[] = trim($now['artist'] . ' - ' . $now['title'], ' -');
        }

        foreach ($this->queueRows($station, $limit) as $item) {
            $lines[] = trim($item['artist'] . ' - ' . $item['title'], ' -');
        }

        return $lines;
    }

    /**
     * @return array<int, array{artist: string, title: string, requested: bool}>
     */
    private function queueRows(array $station, int $limit): array
    {
        if ($this->usesRadioDj($station)) {
            $rows = $this->query($station, 'RadioDjQueue')
                ->columns(['artist', 'title'])
                ->orderBy('ID')
                ->limit($limit)
                ->select();
        } else {
            $rows = $this->query($station, 'SamQueue')
                ->innerJoin('SamSonglist_' . $station['id'], 's', 'SamQueue.songID = s.ID')
                ->columns(['s.artist', 's.title'])
                ->orderBy('SamQueue.sortID')
                ->limit($limit)
                ->select();
        }

        $out = [];

        foreach ($rows as $row) {
            $row = (array) $row;

            if (trim((string) ($row['title'] ?? '')) === '') {
                continue;
            }

            $out[] = [
                'artist'    => (string) ($row['artist'] ?? ''),
                'title'     => (string) ($row['title'] ?? ''),
                'requested' => false,
            ];
        }

        return $out;
    }

    public function usesRadioDj(array $station): bool
    {
        return !empty($station['radiodj_db']);
    }

    /**
     * Build a query against a logical music table for a station.
     */
    private function query(array $station, string $logicalPrefix): Query
    {
        $logical = $logicalPrefix . '_' . $station['id'];

        return Query::new()
            ->using(new ChainResolver([
                new ModelResolver(),
                new MappingResolver(MusicDatabaseMapping::forStation($station['id'])),
            ]))
            ->from($logical);
    }
}
