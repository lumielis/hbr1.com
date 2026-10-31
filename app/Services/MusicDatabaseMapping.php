<?php

namespace App\Services;

use Azera\Db\ModelMapping;

/**
 * Table mapping for the databases we do not own.
 *
 * SAM Broadcaster and RadioDJ own their schemas: they are created by those
 * applications, we cannot migrate them, and coupling third-party storage to
 * PHP model classes would be a liability. So they get `ModelMapping` entries
 * instead - logical names resolving to schema-qualified tables on the `music`
 * connection, readable through the query builder with no Active Record.
 *
 * See plan.md §5.2.
 */
final class MusicDatabaseMapping
{
    /** Station id (as used in config) => schema name. */
    private const SCHEMAS = [
        'Trance'  => ['sam' => 'samdb_trance', 'radiodj' => 'radiodj_trance'],
        'Tronic'  => ['sam' => 'samdb_tronic', 'radiodj' => 'radiodj_tronic'],
        'Ambient' => ['sam' => 'samdb_ambient', 'radiodj' => 'radiodj_ambient'],
    ];

    /**
     * Build the mapping for one station.
     *
     * Logical names are namespaced per station so a single resolver can serve
     * all three without collisions: `RadioDjSongs_Trance`,
     * `SamHistory_Trance`, and so on.
     */
    public static function forStation(string $stationId): ModelMapping
    {
        $schemas = self::SCHEMAS[$stationId] ?? null;

        if ($schemas === null) {
            throw new \InvalidArgumentException("Unknown station: {$stationId}");
        }

        $mapping = new ModelMapping();

        foreach (self::entriesFor($stationId, $schemas['sam'], $schemas['radiodj']) as $logical => $entry) {
            $mapping->add($logical, $entry['source'], $entry['schema'], 'music');
        }

        return $mapping;
    }

    /**
     * Every station's mapping in one resolver, for call sites that work
     * across all channels (the playlist and tracklist pages).
     */
    public static function all(): ModelMapping
    {
        $mapping = new ModelMapping();

        foreach (self::SCHEMAS as $stationId => $schemas) {
            foreach (self::entriesFor($stationId, $schemas['sam'], $schemas['radiodj']) as $logical => $entry) {
                $mapping->add($logical, $entry['source'], $entry['schema'], 'music');
            }
        }

        return $mapping;
    }

    /** @return array<int, string> */
    public static function stationIds(): array
    {
        return array_keys(self::SCHEMAS);
    }

    /**
     * Logical table definitions, shared by forStation() and all().
     *
     * @return array<string, array{source: string, schema: string}>
     */
    private static function entriesFor(string $stationId, string $sam, string $radioDj): array
    {
        return [
            "RadioDjSongs_{$stationId}"    => ['source' => 'songs',      'schema' => $radioDj],
            "RadioDjHistory_{$stationId}"  => ['source' => 'history',    'schema' => $radioDj],
            "RadioDjQueue_{$stationId}"    => ['source' => 'queuelist',  'schema' => $radioDj],
            "RadioDjRequests_{$stationId}" => ['source' => 'requests',   'schema' => $radioDj],
            "SamSonglist_{$stationId}"     => ['source' => 'songlist',   'schema' => $sam],
            "SamHistory_{$stationId}"      => ['source' => 'historylist', 'schema' => $sam],
            "SamQueue_{$stationId}"        => ['source' => 'queuelist',  'schema' => $sam],
        ];
    }
}
