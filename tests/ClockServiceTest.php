<?php

namespace App\Tests;

use App\Services\ClockService;
use PHPUnit\Framework\TestCase;

/**
 * The header clock.
 *
 * Two things went wrong here that a screenshot alone will not catch, so both
 * are pinned: the offsets must be a JSON *object* keyed by zone (the old code
 * emitted a comma-joined string, which made `offsets[i]` index characters and
 * every cell read NaN:NaN), and the zone set must stay the legacy one.
 */
class ClockServiceTest extends TestCase
{
    private ClockService $clock;

    protected function setUp(): void
    {
        $this->clock = new ClockService();
    }

    /** The five zones from template/client/topframe.phtml, in display order. */
    public function testZoneOrderMatchesTheLegacyTemplate(): void
    {
        self::assertSame(
            ['newyork', 'berlin', 'gmt', 'tokyo', 'moscow'],
            array_column($this->clock->at(new \DateTimeImmutable('2026-10-31 12:00:00')), 'key')
        );
    }

    /**
     * The legacy page asked MySQL for these five conversions. PHP's own
     * timezone database has to agree, including across a DST boundary.
     */
    public function testOffsetsMatchTheLegacyConversion(): void
    {
        $winter = $this->clock->offsets(new \DateTimeImmutable('2026-01-15 12:00:00 UTC'));
        self::assertSame(
            ['newyork' => -18000, 'berlin' => 3600, 'gmt' => 0, 'tokyo' => 32400, 'moscow' => 10800],
            $winter
        );

        $summer = $this->clock->offsets(new \DateTimeImmutable('2026-07-01 12:00:00 UTC'));
        self::assertSame(
            ['newyork' => -14400, 'berlin' => 7200, 'gmt' => 0, 'tokyo' => 32400, 'moscow' => 10800],
            $summer
        );
    }

    public function testMoscowNoLongerObservesDaylightSaving(): void
    {
        foreach (['2026-01-15', '2026-07-01'] as $day) {
            $zones = $this->clock->at(new \DateTimeImmutable($day . ' 12:00:00 UTC'));
            $moscow = $this->zone($zones, 'moscow');

            self::assertSame(10800, $moscow['offset'], $day);
        }
    }

    /** A wall clock, so the rows read 04:05:09 pm rather than 16:05:09. */
    public function testHoursAreTwelveHourWithMeridiem(): void
    {
        $zones = $this->clock->at(new \DateTimeImmutable('2026-10-31 16:05:09 UTC'));
        $berlin = $this->zone($zones, 'berlin');

        self::assertSame('05', $berlin['hour']);
        self::assertSame('05', $berlin['minute']);
        self::assertSame('09', $berlin['second']);
        self::assertSame('p', $berlin['meridiem']);
    }

    public function testMidnightIsTwelveNotZero(): void
    {
        $zones = $this->clock->at(new \DateTimeImmutable('2026-10-31 00:30:00 UTC'));

        self::assertSame('12', $this->zone($zones, 'gmt')['hour']);
        self::assertSame('a', $this->zone($zones, 'gmt')['meridiem']);
    }

    /**
     * The glyph names are `/images/courier11/_cNN.gif` where NN is the
     * character's code point, so these have to be the digits themselves.
     */
    public function testGlyphCodesAreCharacterCodes(): void
    {
        $zones = $this->clock->at(new \DateTimeImmutable('2026-10-31 16:05:09 UTC'));
        $berlin = $this->zone($zones, 'berlin');

        self::assertSame(ord('0'), $berlin['hour1']);
        self::assertSame(ord('5'), $berlin['hour2']);
        self::assertSame(ord('p'), $berlin['dt1']);
        self::assertSame(ord('m'), $berlin['dt2']);
    }

    /**
     * The rows must be emit-able as a JS object. Encoding the *offsets* (not a
     * joined string) is the whole point of the fix.
     */
    public function testOffsetsEncodeAsAJsonObject(): void
    {
        $json = json_encode($this->clock->offsets(), JSON_THROW_ON_ERROR);
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($decoded);
        self::assertSame('newyork', array_key_first($decoded));
        self::assertSame(5, count($decoded));
        self::assertIsInt($decoded['berlin']);
    }

    /**
     * The 4 px separator belongs *between* rows. The legacy template emitted it
     * after the last row as well, which made the block 4 px too tall for the
     * 136 px frame it is centred in.
     */
    public function testTheLastRowHasNoSeparator(): void
    {
        $rows = $this->clock->rows(new \DateTimeImmutable('2026-10-31 12:00:00 UTC'));

        self::assertSame(4, $rows[0]['separator']);
        self::assertSame(4, $rows[3]['separator']);
        self::assertSame(0, $rows[4]['separator']);
    }

    /**
     * The five clock rows plus the separators must fit the 136 px header frame,
     * otherwise the last row is clipped.
     */
    public function testRowsFitTheHeaderFrame(): void
    {
        $rows = $this->clock->rows();

        $height = array_sum(array_column($rows, 'separator')) + count($rows) * 17;

        self::assertLessThanOrEqual(136, $height);
    }

    /**
     * @param list<array<string, mixed>> $zones
     * @return array<string, mixed>
     */
    private function zone(array $zones, string $key): array
    {
        foreach ($zones as $zone) {
            if ($zone['key'] === $key) {
                return $zone;
            }
        }

        self::fail("Zone {$key} missing");
    }
}
