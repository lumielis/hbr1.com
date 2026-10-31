<?php

namespace App\Services;

use Azera\Db\ModelMapping;

/**
 * Table mapping for the playout databases.
 *
 * RadioDJ owns these schemas: they are created by that application, we cannot
 * migrate them, and coupling third-party storage to PHP model classes would be
 * a liability. So they get `ModelMapping` entries instead - logical names
 * resolving to schema-qualified tables, readable through the query builder with
 * no Active Record.
 *
 * SAM Broadcaster is retired: no `samdb_*` schema exists on the server any
 * more, and all three stations run RadioDJ. See plan.md §5.2 and §12.1.
 */
final class MusicDatabaseMapping
{
    /** Station id (as used in config) => RadioDJ schema name. */
    private const SCHEMAS = [
        'Trance'  => 'radiodj_trance',
        'Tronic'  => 'radiodj_tronic',
        'Ambient' => 'radiodj_ambient',
    ];

    /**
     * Build the mapping for one station.
     *
     * Logical names are namespaced per station so one resolver can serve all
     * three without collisions: `RadioDjSongs_Trance`, and so on.
     */
    public static function forStation(string $stationId): ModelMapping
    {
        $schema = self::SCHEMAS[$stationId] ?? null;

        if ($schema === null) {
            throw new \InvalidArgumentException("Unknown station: {$stationId}");
        }

        $mapping = new ModelMapping();

        foreach (self::entriesFor($stationId, $schema) as $logical => $entry) {
            $mapping->add($logical, $entry['source'], $entry['schema']);
        }

        return $mapping;
    }

    /**
     * Every station's mapping in one resolver, for call sites that work
     * across all channels.
     */
    public static function all(): ModelMapping
    {
        $mapping = new ModelMapping();

        foreach (self::SCHEMAS as $stationId => $schema) {
            foreach (self::entriesFor($stationId, $schema) as $logical => $entry) {
                $mapping->add($logical, $entry['source'], $entry['schema']);
            }
        }

        return $mapping;
    }

    /**
     * The RadioDJ schema for a station, for the few places that must write the
     * statement by hand (the queue join in {@see TrackService::queue()}).
     */
    public static function schemaFor(string $stationId): string
    {
        return self::SCHEMAS[$stationId]
            ?? throw new \InvalidArgumentException("Unknown station: {$stationId}");
    }

    /** @return array<int, string> */
    public static function stationIds(): array
    {
        return array_keys(self::SCHEMAS);
    }

    /** @return array<string, array{source: string, schema: string}> */
    private static function entriesFor(string $stationId, string $schema): array
    {
        return [
            "RadioDjSongs_{$stationId}"    => ['source' => 'songs',     'schema' => $schema],
            "RadioDjHistory_{$stationId}"  => ['source' => 'history',   'schema' => $schema],
            "RadioDjQueue_{$stationId}"    => ['source' => 'queuelist', 'schema' => $schema],
            "RadioDjRequests_{$stationId}" => ['source' => 'requests',  'schema' => $schema],
        ];
    }
}
