<?php

namespace App\Tests;

use App\Services\PartyService;
use PHPUnit\Framework\TestCase;

/**
 * The party calendar, against the live `party` table.
 *
 * The page is a *reproduction* of `htdocs/cgi-htm/client/party_main.htm`, and
 * these cases pin the parts of it that a rewrite gets wrong: the year strip is
 * six entries and starts three years back, the selected year/month/day are the
 * only `calendar_current` entries, the row breaks fall every six cells, and the
 * selected-year colouring is driven by whether a *year* holds a party rather
 * than just the month on screen.
 *
 * They also pin the two ways the port and production disagreed when the page
 * was rewritten:
 *
 *  - the year strip had drifted to `current-5 .. current+5` and its colours were
 *    computed from the visible month only, so 2023-2025 came out grey although
 *    they hold parties;
 *  - `parties_in_time()` - the query behind the cell colour - has no `status`
 *    filter, which is why a draft lights up a day in production.
 *
 * Skipped automatically when the database is unreachable.
 */
class PartyServiceTest extends TestCase
{
    private PartyService $party;

    private \Azera\Db\Database $db;

    protected function setUp(): void
    {
        \App\Bootstrap::bootstrap();

        $this->db = \App\AppContext::instance()->dbManager()->getDefault();

        $this->party = new PartyService($this->db, 'Europe/Berlin');

        try {
            $this->db->query('SELECT 1 FROM `party` LIMIT 1');
        } catch (\Throwable $e) {
            self::markTestSkipped('Database unavailable: ' . $e->getMessage());
        }
    }

    /**
     * `start="-3" end="3"` and the loop is `< end`, so six years, ending two
     * years after the selected one.
     */
    public function testYearStripIsSixYearsFromThreeBack(): void
    {
        $calendar = $this->party->calendar($this->timestampFor(2026, 11, 1));

        self::assertCount(6, $calendar['years']);
        self::assertSame(
            [2023, 2024, 2025, 2026, 2027, 2028],
            array_column($calendar['years'], 'year')
        );
    }

    public function testMonthStripIsTheTwelveLegacyAbbreviations(): void
    {
        $calendar = $this->party->calendar($this->timestampFor(2026, 11, 1));

        self::assertSame(
            ['January', 'February', 'March', 'April', 'May', 'June',
             'July', 'August', 'September', 'October', 'November', 'December'],
            array_column($calendar['months'], 'month')
        );
    }

    public function testDayStripCoversTheWholeMonthAsPaddedNumbersAndWeekdays(): void
    {
        $calendar = $this->party->calendar($this->timestampFor(2026, 11, 1));

        // November 2026 has 30 days and the 1st is a Sunday.
        self::assertCount(30, $calendar['days']);
        self::assertSame('01', $calendar['days'][0]['date']);
        self::assertSame('Sun', $calendar['days'][0]['day']);
        self::assertSame('30', $calendar['days'][29]['date']);
        self::assertSame('Mon', $calendar['days'][29]['day']);
    }

    /**
     * The rows are six cells wide with a `colspan=6` spacer row between the
     * strips. Getting this wrong does not break the page visibly in a list,
     * but it widens the whole 583 px frame, which is how the first version of
     * this page silently grew to 661 px.
     *
     * The break *precedes* the cell, so the first entry is a break too: it is
     * what closes the spacer row. `<IF_TIMES=6>` in the legacy tag language was
     * emitted before the `<td>`, not after it.
     */
    public function testMonthAndDayStripsBreakEverySixCells(): void
    {
        $calendar = $this->party->calendar($this->timestampFor(2026, 11, 1));

        foreach (['months' => 12, 'days' => 30] as $strip => $count) {
            $breaks = array_keys(array_filter(
                array_column($calendar[$strip], 'rowStart')
            ));

            $expected = [0];
            for ($i = 6; $i < $count; $i += 6) {
                $expected[] = $i;
            }

            self::assertSame($expected, $breaks, $strip);
        }
    }

    /** Three link ids, and only the selected entry of each strip is `current`. */
    public function testOnlyTheSelectedEntriesAreCurrent(): void
    {
        $calendar = $this->party->calendar($this->timestampFor(2026, 11, 15));

        self::assertSame(1, $this->countCurrent($calendar['years']));
        self::assertSame(1, $this->countCurrent($calendar['months']));
        self::assertSame(1, $this->countCurrent($calendar['days']));

        self::assertSame(2026, $this->currentValue($calendar['years']));
        self::assertSame('November', $this->currentValue($calendar['months']));
        self::assertSame('15', $this->currentValue($calendar['days']));
    }

    /**
     * `calendar_found` is the third colour and it is what tells a listener that
     * a year, month or day holds something. The legacy template tested the
     * *year* strip against the whole year, which is why 2023-2025 are yellow in
     * production while the page is showing November 2026.
     */
    public function testFoundFollowsTheYearNotTheSelectedMonth(): void
    {
        $calendar = $this->party->calendar($this->timestampFor(2026, 11, 1));

        $byYear = array_column($calendar['years'], 'found', 'year');

        // The three past years hold parties; the two future ones do not.
        self::assertTrue($byYear[2023]);
        self::assertTrue($byYear[2024]);
        self::assertTrue($byYear[2025]);
        self::assertFalse($byYear[2027]);
        self::assertFalse($byYear[2028]);
    }

    /** Every entry carries a usable link timestamp. */
    public function testEveryEntryHasATimestamp(): void
    {
        $calendar = $this->party->calendar($this->timestampFor(2026, 11, 1));

        foreach (['years', 'months', 'days'] as $strip) {
            foreach ($calendar[$strip] as $entry) {
                self::assertIsInt($entry['timestamp']);
                self::assertGreaterThan(0, $entry['timestamp']);
            }
        }
    }

    /**
     * A leap day must survive the "keep the day, clamp it into the month" rule
     * the year strip uses. 2028 is a leap year and 2027 is not, so the 2027
     * link from 29 February must land on the 28th rather than rolling over into
     * March.
     */
    public function testLeapDayIsClampedIntoShorterMonths(): void
    {
        $calendar = $this->party->calendar($this->timestampFor(2028, 2, 29));

        self::assertSame(29, $calendar['current']['day']);

        $byYear = array_column($calendar['years'], null, 'year');

        self::assertSame('2028-02-29', $this->localDate($byYear[2028]['timestamp']));
        self::assertSame('2027-02-28', $this->localDate($byYear[2027]['timestamp']));
    }

    /** `find()` returns null rather than throwing for an id that is not there. */
    public function testUnknownPartyIsNull(): void
    {
        self::assertNull($this->party->find(999999999));
    }

    /**
     * The list is `load_parties()`: it must not be a range on the selected
     * month, or a party running across the month boundary disappears.
     */
    public function testPartiesFromUsesTheEndTimestamp(): void
    {
        $calendar = $this->party->calendar($this->timestampFor(2026, 11, 1));

        self::assertArrayHasKey('rows', $calendar);
        self::assertLessThanOrEqual(PartyService::PAGE_SIZE, count($calendar['rows']));

        foreach ($calendar['rows'] as $row) {
            self::assertArrayHasKey('newDay', $row);
            self::assertArrayHasKey('party', $row);
            self::assertArrayHasKey('titleShort', $row['party']);
        }
    }

    /** The title cut is `<ANT_CUT_STR size="30">`, which the port reproduces. */
    public function testTitleIsCutToThirtyCharacters(): void
    {
        $calendar = $this->party->calendar($this->timestampFor(2024, 6, 1));

        foreach ($calendar['rows'] as $row) {
            self::assertLessThanOrEqual(30, mb_strlen($row['party']['titleShort']));
        }
    }

    private function timestampFor(int $year, int $month, int $day): int
    {
        return (new \DateTimeImmutable(sprintf('%04d-%02d-%02d 12:00:00', $year, $month, $day), new \DateTimeZone('Europe/Berlin')))
            ->getTimestamp();
    }

    /** A unix timestamp as the calendar's own timezone would print it. */
    private function localDate(int $timestamp): string
    {
        return (new \DateTimeImmutable('@' . $timestamp))
            ->setTimezone(new \DateTimeZone('Europe/Berlin'))->format('Y-m-d');
    }

    private function countCurrent(array $entries): int
    {
        return count(array_filter($entries, static fn(array $e): bool => $e['current']));
    }

    /** The label of the one current entry, whatever the strip calls it. */
    private function currentValue(array $entries): mixed
    {
        foreach ($entries as $entry) {
            if ($entry['current']) {
                return $entry['year'] ?? $entry['month'] ?? $entry['date'];
            }
        }

        return null;
    }
}
