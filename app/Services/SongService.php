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
 * `SELECT SQL_CALC_FOUND_ROWS` plus a hand-written page_navigation(). The
 * query builder does the counting; the sort key is the same
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

        $query = $this->songs($station);

        if ($station['radiodj_db'] ?? null) {
            $query->where('song_type', 0);
        } else {
            $query->where('songtype', 'S');
        }

        $total = (int) $query->count();

        $rows = $this->songs($station);

        if ($station['radiodj_db'] ?? null) {
            $rows->where('song_type', 0);
        } else {
            $rows->where('songtype', 'S');
        }

        $rows = $rows
            ->columns(['ID', 'artist', 'title', 'duration'])
            ->orderBy('UCASE(CONCAT(artist, title))')
            ->limit($limit, $offset)
            ->select();

        $out = [];

        foreach ($rows as $row) {
            $row = (array) $row;

            $duration = $this->durationSeconds($station, (int) ($row['duration'] ?? 0));

            $out[] = [
                'id'            => (int) ($row['ID'] ?? 0),
                'artist'        => (string) ($row['artist'] ?? ''),
                'title'         => (string) ($row['title'] ?? ''),
                'duration'      => $duration,
                'durationLabel' => $this->durationLabel($duration),
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
        $total = (int) $this->songs($station)->count();

        return $total <= self::PAGE_SIZE ? 0 : random_int(0, $total - self::PAGE_SIZE);
    }

    /**
     * One track by id, constrained to whatever the station can play.
     */
    public function find(array $station, int $id): ?array
    {
        $query = $this->songs($station);

        if ($station['radiodj_db'] ?? null) {
            $query->where('song_type', 0)->where('ID', $id);
        } else {
            $query->where('songtype', 'S')->where('ID', $id);
        }

        $row = $query->columns(['ID', 'artist', 'title', 'duration'])->select()->fetchAssoc();

        if (!$row) {
            return null;
        }

        $row = (array) $row;

        $duration = $this->durationSeconds($station, (int) ($row['duration'] ?? 0));

        return [
            'id'            => (int) ($row['ID'] ?? 0),
            'artist'        => (string) ($row['artist'] ?? ''),
            'title'         => (string) ($row['title'] ?? ''),
            'duration'      => $duration,
            'durationLabel' => $this->durationLabel($duration),
        ];
    }

    /**
     * mm:ss, or an empty string when the playout system reports no length.
     * The legacy tracklist template did this arithmetic inline; keeping it in
     * PHP avoids relying on template-side integer division.
     */
    private function durationLabel(int $seconds): string
    {
        if ($seconds < 0) {
            return '';
        }

        return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }

    /**
     * RadioDJ stores seconds, SAM stores milliseconds - the legacy code
     * divided by 1000 for SAM (`durationDiv`).
     */
    private function durationSeconds(array $station, int $duration): int
    {
        if ($duration < 0) {
            return -1;
        }

        return ($station['radiodj_db'] ?? null) ? $duration : intdiv($duration, 1000);
    }

    private function songs(array $station): Query
    {
        $prefix = ($station['radiodj_db'] ?? null) ? 'RadioDjSongs' : 'SamSonglist';

        return Query::new()
            ->using(new ChainResolver([
                new ModelResolver(),
                new MappingResolver(MusicDatabaseMapping::forStation($station['id'])),
            ]))
            ->from($prefix . '_' . $station['id']);
    }
}
