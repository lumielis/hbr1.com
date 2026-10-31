<?php

namespace App\Tests;

use App\Services\MusicDatabaseMapping;
use PHPUnit\Framework\TestCase;

/**
 * The playout databases belong to RadioDJ, so they are reached through
 * ModelMapping instead of model classes (plan §5.2). These assertions pin the
 * schema-qualified targets, which are easy to get wrong because all three
 * stations use identically named tables.
 */
class MusicDatabaseMappingTest extends TestCase
{
    public function testStationTablesAreSchemaQualified(): void
    {
        $entry = MusicDatabaseMapping::forStation('Trance')->get('RadioDjHistory_Trance');

        self::assertNotNull($entry);
        self::assertSame('radiodj_trance', $entry['schema']);
        self::assertSame('history', $entry['source']);
    }

    public function testStationsDoNotCollide(): void
    {
        $trance = MusicDatabaseMapping::forStation('Trance')->get('RadioDjQueue_Trance');
        $tronic = MusicDatabaseMapping::forStation('Tronic')->get('RadioDjQueue_Tronic');

        self::assertSame('radiodj_trance', $trance['schema']);
        self::assertSame('radiodj_tronic', $tronic['schema']);
    }

    public function testAllCoversEveryStationAndTable(): void
    {
        $mapping = MusicDatabaseMapping::all();

        foreach (MusicDatabaseMapping::stationIds() as $station) {
            foreach (['RadioDjSongs', 'RadioDjHistory', 'RadioDjQueue', 'RadioDjRequests'] as $table) {
                self::assertNotNull(
                    $mapping->get("{$table}_{$station}"),
                    "Missing {$table}_{$station}"
                );
            }
        }
    }

    public function testUnknownStationIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        MusicDatabaseMapping::forStation('Rock');
    }

    /**
     * SAM Broadcaster is retired - no samdb_* schema exists, and all three
     * stations run RadioDJ - so nothing may map to a SAM table any more.
     */
    public function testThereAreNoSamTables(): void
    {
        $mapping = MusicDatabaseMapping::all();

        foreach (MusicDatabaseMapping::stationIds() as $station) {
            foreach (['SamSonglist', 'SamHistory', 'SamQueue'] as $table) {
                self::assertNull($mapping->get("{$table}_{$station}"));
            }
        }
    }

    public function testStationIdsMatchTheConfiguredStations(): void
    {
        self::assertSame(['Trance', 'Tronic', 'Ambient'], MusicDatabaseMapping::stationIds());
    }
}
