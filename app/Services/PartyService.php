<?php

namespace App\Services;

/**
 * The party calendar.
 *
 * Replaces `include/client/party.pl` (reached through
 * `/run/main.xp?action=party`). 846 rows, status 1 = published, and the last
 * submission was today - this is a live feature, not an archive.
 *
 * The legacy page had a three-level calendar (years / months / days) where
 * every level was a `<LOOP_…>` computing timestamps from the current
 * `time_from`. That arithmetic is preserved here, in PHP, because the views
 * cannot do integer date maths.
 *
 * Two legacy quirks are deliberately *not* preserved:
 *  - `DELETE FROM party WHERE create_timestamp < time - 86400 AND status = 0`
 *    ran on every page load. A read request should not delete rows.
 *  - the calendar months loop used `timegm` for Berlin wall-clock dates.
 *    Dates here are rendered in the application timezone instead.
 */
class PartyService
{
    /**
     * Hydration needs a timezone for the formatted dates; the calendar takes
     * one explicitly, so this is the fallback used by row rendering.
     */
    private \DateTimeZone $timezone;

    public function __construct(private \Azera\Db\Database $db, string $timezone = 'Europe/Berlin')
    {
        $this->timezone = new \DateTimeZone($timezone);
    }

    /**
     * Parties visible from `$from` onwards, ordered by start.
     *
     * The legacy query kept a party listed while it is still running:
     * `end_timestamp >= time_from` with `status <> 0`.
     *
     * @return array<int, array>
     */
    public function upcoming(int $from, int $limit = 25): array
    {
        $limit = max(1, min($limit, 200));

        $rows = $this->db->query(
            'SELECT * FROM `party` WHERE `status` <> 0 AND `end_timestamp` >= ?'
            . ' ORDER BY `start_timestamp`, `end_timestamp` DESC LIMIT ' . $limit,
            [$from]
        )->fetchAll(\PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrate'], $rows);
    }

    /** Parties that start on a given day, used by the calendar grid. */
    public function onDay(int $dayStart, int $dayEnd): array
    {
        $rows = $this->db->query(
            'SELECT * FROM `party` WHERE `status` <> 0'
            . ' AND `start_timestamp` >= ? AND `start_timestamp` <= ?'
            . ' ORDER BY `start_timestamp`',
            [$dayStart, $dayEnd]
        )->fetchAll(\PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrate'], $rows);
    }

    /**
     * Remove abandoned drafts: unpublished rows with no title, created more
     * than a day ago.
     *
     * The legacy page ran this `DELETE` on every request, which is why the
     * table has a handful of blank rows created minutes apart - each one is a
     * visit that started the "new party" form and walked away. Doing that
     * inside a GET handler is wrong, so this is an explicit call from the
     * console (`party:clean`) instead.
     *
     * Returns the number of rows removed.
     */
    public function pruneDrafts(int $olderThanSeconds = 86400): int
    {
        $statement = $this->db->query(
            'DELETE FROM `party` WHERE `status` = 0 AND `title` = ? AND `create_timestamp` < ?',
            ['', time() - $olderThanSeconds]
        );

        return $statement === false ? 0 : $statement->rowCount();
    }

    public function find(int $id): ?array
    {
        $row = $this->db->query('SELECT * FROM `party` WHERE `id` = ?', [$id])
            ->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    /**
     * The three calendar levels for a given month.
     *
     * Mirrors `load_calendar_years` / `_months` / `_days`: five years either
     * side, all twelve months, every day of the selected month. Each entry
     * carries the timestamps the legacy template needed plus the parties that
     * fall in that range.
     *
     * @return array{years: array, months: array, days: array, current: array}
     */
    public function calendar(int $timestamp, string $timezone): array
    {
        $zone = new \DateTimeZone($timezone);
        $base = (new \DateTimeImmutable('@' . $timestamp))->setTimezone($zone);

        $year  = (int) $base->format('Y');
        $month = (int) $base->format('n');
        $day   = (int) $base->format('j');

        $years = [];
        for ($y = $year - 5; $y <= $year + 5; $y++) {
            $years[] = [
                'year'      => $y,
                'timestamp' => $this->startOf($y, $month, $day, $zone),
                'current'   => $y === $year,
            ];
        }

        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            // Clamp the day so 31 January -> February does not roll over.
            $safeDay = min($day, (int) $base->setDate($year, $m, 1)->format('t'));
            $start   = $this->startOf($year, $m, $safeDay, $zone);

            $months[] = [
                'month'     => $this->monthName($m),
                'number'    => $m,
                'timestamp' => $start,
                'startTime' => $start,
                'endTime'   => $this->endOf($year, $m, $safeDay, $zone),
                'current'   => $m === $month,
            ];
        }

        $daysInMonth = (int) $base->setDate($year, $month, 1)->format('t');
        $days        = [];

        // One query for the whole month, then bucket by day. The legacy page
        // ran a separate `parties_in_time()` query per calendar cell - 31
        // round trips for a grid that only needs a count and a link.
        $monthStart = $this->startOf($year, $month, 1, $zone);
        $monthEnd   = $this->endOf($year, $month, $daysInMonth, $zone);
        $buckets    = $this->countsByDay($monthStart, $monthEnd, $zone);

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $start = $this->startOf($year, $month, $d, $zone);
            $end   = $this->endOf($year, $month, $d, $zone);

            $days[] = [
                'date'      => sprintf('%02d', $d),
                'number'    => $d,
                'day'       => $this->weekdayName($start, $zone),
                'timestamp' => $start,
                'startTime' => $start,
                'endTime'   => $end,
                'current'   => $d === $day,
                'parties'   => $buckets[sprintf('%04d-%02d-%02d', $year, $month, $d)] ?? 0,
            ];
        }

        // The grid needs real week rows. Building them in the template would
        // mean modular arithmetic in Clarity, which it cannot do - there is no
        // integer division operator.
        $weeks  = [];
        $week   = [];
        $offset = (int) (new \DateTimeImmutable('@' . $this->startOf($year, $month, 1, $zone)))
            ->setTimezone($zone)->format('N') - 1;   // Monday = 0

        // Pad the first week so the 1st lands on the right weekday column.
        for ($i = 0; $i < $offset; $i++) {
            $week[] = null;
        }

        foreach ($days as $dayCell) {
            $week[] = $dayCell;

            if (count($week) === 7) {
                $weeks[] = $week;
                $week    = [];
            }
        }

        if ($week !== []) {
            while (count($week) < 7) {
                $week[] = null;
            }
            $weeks[] = $week;
        }

        return [
            'years'   => $years,
            'months'  => $months,
            'days'    => $days,
            'weeks'   => $weeks,
            'current' => ['year' => $year, 'month' => $month, 'day' => $day, 'timestamp' => $timestamp],
        ];
    }

    /**
     * Party counts keyed by `YYYY-MM-DD`, in one query.
     *
     * @return array<string, int>
     */
    private function countsByDay(int $from, int $to, \DateTimeZone $zone): array
    {
        $rows = $this->db->query(
            'SELECT `start_timestamp` FROM `party` WHERE `status` <> 0'
            . ' AND `start_timestamp` >= ? AND `start_timestamp` <= ?',
            [$from, $to]
        )->fetchAll(\PDO::FETCH_COLUMN);

        $buckets = [];
        foreach ($rows as $timestamp) {
            $key = (new \DateTimeImmutable('@' . (int) $timestamp))
                ->setTimezone($zone)->format('Y-m-d');
            $buckets[$key] = ($buckets[$key] ?? 0) + 1;
        }

        return $buckets;
    }

    private function startOf(int $year, int $month, int $day, \DateTimeZone $zone): int
    {
        return (new \DateTimeImmutable('now', $zone))
            ->setDate($year, $month, $day)->setTime(0, 0)->getTimestamp();
    }

    private function endOf(int $year, int $month, int $day, \DateTimeZone $zone): int
    {
        return (new \DateTimeImmutable('now', $zone))
            ->setDate($year, $month, $day)->setTime(23, 59, 59)->getTimestamp();
    }

    private function monthName(int $month): string
    {
        return ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'][$month - 1];
    }

    private function weekdayName(int $timestamp, \DateTimeZone $zone): string
    {
        return (new \DateTimeImmutable('@' . $timestamp))->setTimezone($zone)->format('D');
    }

    /**
     * Normalise a row for the views: resolved country name, formatted dates,
     * comment count, and a safe flyer path.
     */
    private function hydrate(array $row): array
    {
        $row['id']              = (int) $row['id'];
        $row['status']          = (int) $row['status'];
        $row['start_timestamp'] = (int) $row['start_timestamp'];
        $row['end_timestamp']   = (int) $row['end_timestamp'];
        $row['create_timestamp'] = (int) $row['create_timestamp'];

        $row['countryName'] = $row['country'] === ''
            ? ''
            : (string) ($this->db->query(
                'SELECT `name` FROM `country` WHERE `id` = ?',
                [$row['country']]
            )->fetchColumn() ?: $row['country']);

        // Dates are rendered in the application timezone, not the server's.
        $tz = $this->timezone;
        $row['startDate'] = $row['start_timestamp'] > 0
            ? (new \DateTimeImmutable('@' . $row['start_timestamp']))->setTimezone($tz)->format('D, d M Y H:i')
            : '';
        $row['endDate'] = $row['end_timestamp'] > 0
            ? (new \DateTimeImmutable('@' . $row['end_timestamp']))->setTimezone($tz)->format('D, d M Y H:i')
            : '';

        // A flyer is only a filename in the DB; it lives under the legacy tree.
        $flyer = (string) ($row['flyer'] ?? '');
        $row['flyerUrl'] = $flyer === ''
            ? ''
            : '/images/party/flyer/' . rawurlencode($flyer);

        $row['commentCount'] = (int) $this->db->query(
            'SELECT COUNT(*) FROM `party_comment` WHERE `party_id` = ?',
            [$row['id']]
        )->fetchColumn();

        return $row;
    }
}
