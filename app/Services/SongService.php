<?php

namespace App\Services;

use Azera\Db\Query;
use Azera\Db\Resolver\ChainResolver;
use Azera\Db\Resolver\MappingResolver;
use Azera\Db\Resolver\ModelResolver;

/**
 * The song catalogue for a station.
 *
 * Replaces the tracklist half of `main.php?module=tracklist`, which used
 * `SELECT SQL_CALC_FOUND_ROWS` plus a hand-written page_navigation(). The query
 * builder does the counting; the sort key is the same
 * `UCASE(CONCAT(artist, title))` the legacy code ordered by.
 */
class SongService
{
    public const PAGE_SIZE = 20;

    public function __construct(
        private readonly StationService $stations,
    ) {
    }

    /**
     * One page of the catalogue, alphabetically.
     *
     * @return array{rows: array<int, array>, total: int, offset: int, limit: int}
     */
    public function page(array $station, int $offset = 0, int $limit = self::PAGE_SIZE): array
    {
        $offset = max(0, $offset);
        $limit  = max(1, $limit);

        $total = $this->playable($station)->count();

        $rows = $this->playable($station)
            ->columns(['ID', 'artist', 'title', 'duration'])
            ->orderBy('UCASE(CONCAT(artist, title))')
            ->limit($limit, $offset)
            ->select();

        $out = [];

        foreach ($rows as $row) {
            $row     = (array) $row;
            $seconds = $this->durationSeconds((int) ($row['duration'] ?? 0));

            $out[] = [
                'id'            => (int) ($row['ID'] ?? 0),
                'artist'        => (string) ($row['artist'] ?? ''),
                'title'         => (string) ($row['title'] ?? ''),
                'duration'      => $seconds,
                'durationLabel' => $this->durationLabel($seconds),
            ];
        }

        return [
            'rows'   => $out,
            'total'  => $total,
            'offset' => $offset,
            'limit'  => $limit,
        ];
    }

    /** A random offset, as the legacy page did when none was supplied. */
    public function randomOffset(array $station): int
    {
        $total = $this->playable($station)->count();

        return $total <= self::PAGE_SIZE ? 0 : random_int(0, $total - self::PAGE_SIZE);
    }

    /** One track by id, constrained to what the station can play. */
    public function find(array $station, int $id): ?array
    {
        $row = $this->playable($station)
            ->where('ID', $id)
            ->columns(['ID', 'artist', 'title', 'duration'])
            ->select()
            ->fetchAssoc();

        if ($row === false) {
            return null;
        }

        $row     = (array) $row;
        $seconds = $this->durationSeconds((int) ($row['duration'] ?? 0));

        return [
            'id'            => (int) ($row['ID'] ?? 0),
            'artist'        => (string) ($row['artist'] ?? ''),
            'title'         => (string) ($row['title'] ?? ''),
            'duration'      => $seconds,
            'durationLabel' => $this->durationLabel($seconds),
        ];
    }

    /**
     * RadioDJ stores whole seconds in `duration` but as a float column, so the
     * value arrives as e.g. 4275.14871 and must be truncated, not rounded.
     */
    private function durationSeconds(int $duration): int
    {
        return $duration;
    }

    /**
     * hh:mm:ss, the legacy `format_duration()` output, or `-` when the playout
     * system reports no length. Kept in PHP because Clarity has no
     * integer-division operator.
     */
    private function durationLabel(int $seconds): string
    {
        if ($seconds <= 0) {
            return '-';
        }

        return sprintf(
            '%02d:%02d:%02d',
            intdiv($seconds, 3600),
            intdiv($seconds % 3600, 60),
            $seconds % 60
        );
    }

    /** Rows the station can actually play: song_type 0 excludes jingles/ads. */
    private function playable(array $station): Query
    {
        return $this->query($station)->where('song_type', 0);
    }

    private function query(array $station): Query
    {
        return Query::new()
            ->using(new ChainResolver([
                new ModelResolver(),
                new MappingResolver(MusicDatabaseMapping::forStation($station['id'])),
            ]))
            ->from('RadioDjSongs_' . $station['id']);
    }
}
