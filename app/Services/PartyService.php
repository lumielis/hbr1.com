<?php

namespace App\Services;

/**
 * The party calendar.
 *
 * Replaces `include/client/party.pl` (reached through
 * `/run/main.xp?action=party` and `/cgi-bin/main.xp?action=party`). 846 rows,
 * status 1 = published, and submissions were still arriving - this is a live
 * feature, not an archive.
 *
 * The legacy page had a three-level calendar (years / months / days) that was
 * rebuilt from a single `time_from` timestamp on every request. The maths below
 * is that arithmetic, kept faithfully, because the *output* is what the page
 * looks like: the selected year is a plain `calendar_current` entry, every
 * other year is `calendar_found` when it contains a party and `calendar`
 * otherwise, and the same three colours repeat for months and days.
 *
 * Two things are deliberately not reproduced:
 *
 *  - `DELETE FROM party WHERE create_timestamp < time - 86400 AND status = 0`
 *    ran on every page load. A read request should not delete rows; it is
 *    `pruneDrafts()`, called from the console.
 *
 *  - the legacy code fed Berlin wall-clock values to `timegm`/`timelocal` in an
 *    inconsistent mix. Dates here go through one `DateTimeZone`, so a day is
 *    the local day and the timestamps in the links round-trip through
 *    `requestedTimestamp()` unchanged.
 */
class PartyService
{
    /**
     * How many parties one page of the list shows. The legacy default was
     * `$form{'count'} <= 0 ? 5`, and the `count` parameter was never set by any
     * link the site generated.
     */
    public const PAGE_SIZE = 5;

    private \DateTimeZone $timezone;

    public function __construct(private \Azera\Db\Database $db, string $timezone = 'Europe/Berlin')
    {
        $this->timezone = new \DateTimeZone($timezone);
    }

    /**
     * Everything the calendar page needs for one month.
     *
     * @return array{years: array, months: array, days: array, rows: array, current: array}
     */
    public function calendar(int $timestamp): array
    {
        $zone = $this->timezone;
        $base = (new \DateTimeImmutable('@' . $timestamp))->setTimezone($zone);

        $year  = (int) $base->format('Y');
        $month = (int) $base->format('n');
        $day   = (int) $base->format('j');

        // The list is `load_parties()`: `end_timestamp >= time_from`, so a party
        // that started before the selected day but is still running stays on
        // the page.
        $parties = $this->partiesFrom($this->startOfDay($year, $month, $day));

        // One scan answers every calendar colour. The legacy page ran a
        // `parties_in_time()` query per cell - six years, twelve months and up
        // to thirty-one days - but the result was only ever used as a boolean
        // for the link's colour. That query had no status filter, so drafts
        // counted; see `partiesInRange()`.
        $marks = $this->marks($year - 3, $year + 2);

        return [
            'years'   => $this->years($year, $month, $day, $marks),
            'months'  => $this->months($year, $month, $day, $marks),
            'days'    => $this->days($year, $month, $day, $marks),
            'rows'    => $this->rows(array_map([$this, 'hydrate'], $parties)),
            'current' => [
                'year'      => $year,
                'month'     => $month,
                'day'       => $day,
                'timestamp' => $timestamp,
            ],
        ];
    }

    /**
     * Which years, months and days hold at least one party.
     *
     * Three lookup sets over one result set. The legacy page asked the same
     * question per cell with `parties_in_time()`; the only difference that
     * matters is the status filter, which is passed through.
     *
     * @return array{years: array<int, true>, months: array<string, true>, days: array<string, true>}
     */
    private function marks(int $fromYear, int $toYear, bool $publishedOnly = false): array
    {
        $from = $this->startOfDay($fromYear, 1, 1);
        $to   = $this->startOfDay($toYear + 1, 1, 1) - 1;

        $rows = $this->partiesInRange($from, $to, $publishedOnly);

        $years  = [];
        $months = [];
        $days   = [];

        foreach ($rows as $row) {
            $local = (new \DateTimeImmutable('@' . (int) $row['start_timestamp']))
                ->setTimezone($this->timezone);

            $years[(int) $local->format('Y')]        = true;
            $months[$local->format('Y-n')]          = true;
            $days[$local->format('Y-m-d')]          = true;
        }

        return ['years' => $years, 'months' => $months, 'days' => $days];
    }

    /**
     * The year strip: `current-3 .. current+2`.
     *
     * Every year links into the same day of the same month, clamped into
     * February, so following a year link keeps the listener roughly where they
     * were. `id` is the legacy link id, which is what carries the colour:
     * `calendar_current` for the selected year (`#0DBD43`), `calendar_found`
     * for a year that holds at least one party (`#ccee00`), `calendar`
     * otherwise (`#999999`).
     *
     * The colours matter more than they look: on production 2026 is green
     * because it is selected, 2023-2025 are yellow because they hold parties,
     * and 2027/2028 are grey because they hold none.
     *
     * @param  array{years: array<int, true>, months: array<string, true>, days: array<string, true>} $marks
     * @return array<int, array>
     */
    public function years(int $year, int $month, int $day, array $marks): array
    {
        $out = [];

        for ($y = $year - 3; $y < $year + 3; $y++) {
            $current = $y === $year;
            $found   = isset($marks['years'][$y]);

            $out[] = [
                'year'      => $y,
                'timestamp' => $this->startOfDay($y, $month, min($day, $this->daysIn($y, $month))),
                'current'   => $current,
                'found'     => $found,
                'id'        => $this->linkId($current, $found),
            ];
        }

        return $out;
    }

    /**
     * The twelve month names. The legacy markup broke the row every six cells
     * (`<IF_TIMES=6></tr><tr></IF>`), so `rowStart` marks cells 6 and 12 here.
     *
     * @param  array{years: array<int, true>, months: array<string, true>, days: array<string, true>} $marks
     * @return array<int, array>
     */
    public function months(int $year, int $month, int $day, array $marks): array
    {
        $out = [];

        for ($m = 1; $m <= 12; $m++) {
            $current = $m === $month;
            $found   = isset($marks['months'][$year . '-' . $m]);

            $out[] = [
                'month'     => $this->monthName($m),
                'number'    => $m,
                'timestamp' => $this->startOfDay($year, $m, min($day, $this->daysIn($year, $m))),
                'current'   => $current,
                'found'     => $found,
                'id'        => $this->linkId($current, $found),
                'rowStart'  => ($m - 1) % 6 === 0,
            ];
        }

        return $out;
    }

    /**
     * Every day of the selected month, as `01. Sun`, with the same six-cell row
     * breaks. `found` is true when a party starts that day.
     *
     * @param  array{years: array<int, true>, months: array<string, true>, days: array<string, true>} $marks
     * @return array<int, array>
     */
    public function days(int $year, int $month, int $day, array $marks): array
    {
        $out   = [];
        $count = $this->daysIn($year, $month);

        for ($d = 1; $d <= $count; $d++) {
            $start   = $this->startOfDay($year, $month, $d);
            $current = $d === $day;
            $found   = isset($marks['days'][sprintf('%04d-%02d-%02d', $year, $month, $d)]);

            $out[] = [
                'date'      => sprintf('%02d', $d),
                'day'       => (new \DateTimeImmutable('@' . $start))->setTimezone($this->timezone)->format('D'),
                'timestamp' => $start,
                'current'   => $current,
                'found'     => $found,
                'id'        => $this->linkId($current, $found),
                'rowStart'  => ($d - 1) % 6 === 0,
            ];
        }

        return $out;
    }

    /** The three legacy link ids, in the order the template tested them. */
    private function linkId(bool $current, bool $found): string
    {
        if ($current) {
            return 'calendar_current';
        }

        return $found ? 'calendar_found' : 'calendar';
    }

    /**
     * One page of the party list, each entry tagged with whether a new date
     * header is needed before it.
     *
     * The legacy `PARTY_TIMER` loop printed a `#200080` day header whenever the
     * start *day* changed and a `#000066` hairline whenever it did not, so two
     * events on the same day are separated by a rule instead of repeating the
     * date.
     *
     * @param  array<int, array> $parties
     * @return array<int, array>
     */
    public function rows(array $parties, int $offset = 0): array
    {
        $page = array_slice($parties, max(0, $offset), self::PAGE_SIZE);
        $out  = [];
        $last = null;

        foreach ($page as $party) {
            $date = $party['start_timestamp'] > 0
                ? (new \DateTimeImmutable('@' . $party['start_timestamp']))
                    ->setTimezone($this->timezone)->format('D, d. M Y')
                : '';

            $out[] = [
                'date'   => $date,
                'newDay' => $date !== $last,
                'party'  => $party,
            ];

            $last = $date;
        }

        return $out;
    }

    /**
     * Remove abandoned drafts: unpublished rows with no title, created more
     * than a day ago.
     *
     * The legacy page ran this `DELETE` on every request, which is why the
     * table has a handful of blank rows created minutes apart - each one is a
     * visit that started the "new party" form and walked away. Doing that
     * inside a GET handler is wrong, so this is an explicit console call
     * (`party:clean`) instead.
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
     * Parties that start inside a date range.
     *
     * This is `parties_in_time()`, used only for the calendar cell colour. It
     * is the one query in the legacy code with **no `status` filter** - drafts
     * light up a day, a month and a year exactly like published parties do, and
     * the page's `DELETE` of abandoned drafts ran before the calendar was built,
     * so in practice it filtered them out. The port keeps that `DELETE` out of
     * the request path (see `pruneDrafts()`), so this method takes the status
     * flag and lets the caller decide.
     *
     * @return array<int, array>
     */
    public function partiesInRange(int $from, ?int $to = null, bool $publishedOnly = false): array
    {
        $to = $to === null || $to <= $from ? $from + 86399 : $to;

        return $this->db->query(
            'SELECT * FROM `party` WHERE `start_timestamp` >= ? AND `start_timestamp` <= ?'
            . ($publishedOnly ? ' AND `status` <> 0' : '')
            . ' ORDER BY `start_timestamp`, `end_timestamp` DESC',
            [$from, $to]
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * The list under the grid: `load_parties()`.
     *
     * `status <> 0 AND end_timestamp >= time_from`, ordered by start with the
     * longest-running first among equal starts. Note the `end_timestamp`
     * filter rather than a range on `start_timestamp` - a party that began
     * before the selected month and is still running is expected to appear.
     *
     * @return array<int, array>
     */
    public function partiesFrom(int $from): array
    {
        return $this->db->query(
            'SELECT * FROM `party` WHERE `status` <> 0 AND `end_timestamp` >= ?'
            . ' ORDER BY `start_timestamp`, `end_timestamp` DESC',
            [$from]
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function startOfDay(int $year, int $month, int $day): int
    {
        return (new \DateTimeImmutable('now', $this->timezone))
            ->setDate($year, $month, $day)->setTime(0, 0)->getTimestamp();
    }

    private function daysIn(int $year, int $month): int
    {
        return (int) (new \DateTimeImmutable('now', $this->timezone))
            ->setDate($year, $month, 1)->format('t');
    }

    private function monthName(int $month): string
    {
        return ['January', 'February', 'March', 'April', 'May', 'June',
                'July', 'August', 'September', 'October', 'November', 'December'][$month - 1];
    }

    /**
     * Normalise a row for the views: resolved country name, formatted dates,
     * comment count, and a safe flyer path.
     */
    private function hydrate(array $row): array
    {
        $row['id']               = (int) $row['id'];
        $row['status']           = (int) $row['status'];
        $row['start_timestamp']  = (int) $row['start_timestamp'];
        $row['end_timestamp']    = (int) $row['end_timestamp'];
        $row['create_timestamp'] = (int) $row['create_timestamp'];

        $row['countryName'] = (string) $row['country'] === ''
            ? ''
            : (string) ($this->db->query(
                'SELECT `name` FROM `country` WHERE `id` = ?',
                [$row['country']]
            )->fetchColumn() ?: $row['country']);

        $tz = $this->timezone;

        $row['startDate'] = $row['start_timestamp'] > 0
            ? (new \DateTimeImmutable('@' . $row['start_timestamp']))->setTimezone($tz)->format('D, d. M Y')
            : '';
        $row['startTime'] = $row['start_timestamp'] > 0
            ? (new \DateTimeImmutable('@' . $row['start_timestamp']))->setTimezone($tz)->format('H:i')
            : '';
        $row['endDate'] = $row['end_timestamp'] > 0
            ? (new \DateTimeImmutable('@' . $row['end_timestamp']))->setTimezone($tz)->format('D, d. M Y')
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

        // The legacy third row printed these flags with a trailing space each,
        // only when set, so `Flyer Live DJs Chill Deco` is the literal output.
        $flags = '';
        foreach ([['flyer', 'Flyer'], ['live', 'Live'], ['djs', 'DJs'], ['chillout', 'Chill'], ['deco', 'Deco']] as [$key, $label]) {
            if (!empty($row[$key]) && (string) $row[$key] !== '0') {
                $flags .= $label . ' ';
            }
        }
        $row['flags'] = $flags === '' ? '' : substr($flags, 0, -1);

        // `title_data_type => 'tagout'` in the model: entity-encoded, then cut to
        // 30 characters with an ellipsis, which is what `<ANT_CUT_STR size="30">`
        // did.
        $title = trim(htmlspecialchars((string) ($row['title'] ?? ''), ENT_QUOTES, 'UTF-8'));
        $row['titleShort'] = mb_strlen($title) > 30 ? mb_substr($title, 0, 27) . '...' : $title;

        return $row;
    }
}
