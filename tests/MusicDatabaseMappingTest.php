<?php

namespace App\Tests;

use App\Services\MusicDatabaseMapping;
use PHPUnit\Framework\TestCase;

/**
 * The music databases belong to SAM Broadcaster and RadioDJ, so they are
 * reached through ModelMapping instead of model classes (plan §5.2). These
 * assertions pin the schema-qualified targets, which are easy to get wrong
 * because all three stations use identically named tables.
 */
class MusicDatabaseMappingTest extends TestCase
{
    public function testStationTablesAreSchemaQualified(): void
    {
        $mapping = MusicDatabaseMapping::forStation('Trance');
        $entry   = $mapping->get('RadioDjHistory_Trance');

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

    public function testSamFallbackTablesAreMapped(): void
    {
        $entry = MusicDatabaseMapping::forStation('Ambient')->get('SamHistory_Ambient');

        self::assertSame('samdb_ambient', $entry['schema']);
        self::assertSame('historylist', $entry['source']);
    }

    public function testAllCoversEveryStationAndTable(): void
    {
        $mapping = MusicDatabaseMapping::all();

        foreach (MusicDatabaseMapping::stationIds() as $station) {
            foreach (['RadioDjSongs', 'RadioDjHistory', 'RadioDjQueue', 'RadioDjRequests', 'SamSonglist', 'SamHistory', 'SamQueue'] as $table) {
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

    /** Reads default to the music role; the request write is the only writer. */
    public function testEntriesUseTheMusicConnectionRole(): void
    {
        $entry = MusicDatabaseMapping::forStation('Trance')->get('RadioDjRequests_Trance');

        self::assertSame('music', $entry['connection']);
    }
}
