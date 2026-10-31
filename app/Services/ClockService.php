<?php

namespace App\Services;

/**
 * The world clock in the header frame.
 *
 * Replaces the `CONVERT_TZ()` round-trip in
 * `cgi-bin/include/client/topframe.pab.pl`, which asked MySQL to convert one
 * timestamp into five zones. PHP ships the IANA timezone database compiled in
 * and `DateTimeZone` already knows every DST rule, so the conversion is a pure
 * computation - no connection, no `mysql.time_zone*` tables.
 *
 * That last point is not academic: the loaded MySQL server has *empty*
 * timezone tables, so every legacy `CONVERT_TZ()` returned NULL and the old
 * page had no offsets to work with at all.
 *
 * The zone list and the row geometry are a straight port of
 * `template/client/topframe.phtm`, which laid the rows out as images and
 * right-aligned them with a per-row spacer.
 */
final class ClockService
{
    /**
     * Zones shown in the header, in the legacy display order.
     *
     * `labelWidth` is the measured width of each label cell as the legacy page
     * actually rendered it. The old template asked for 91/83/70/78/82 px, but
     * the header frame is only 600 px wide and the banner takes 446 of it, so
     * the browser squeezed every label cell by the same 38 px. The measured
     * results are recorded here so the port does not depend on that squeeze.
     */
    public const ZONES = [
        ['key' => 'newyork', 'label' => 'New York', 'tz' => 'America/New_York', 'labelWidth' => 53, 'spacer' => 1],
        ['key' => 'berlin',  'label' => 'Berlin',   'tz' => 'Europe/Berlin',    'labelWidth' => 46, 'spacer' => 8],
        ['key' => 'gmt',     'label' => 'UTC',      'tz' => 'UTC',              'labelWidth' => 32, 'spacer' => 22],
        ['key' => 'tokyo',   'label' => 'Tokyo',    'tz' => 'Asia/Tokyo',       'labelWidth' => 40, 'spacer' => 14],
        ['key' => 'moscow',  'label' => 'Moscow',   'tz' => 'Europe/Moscow',    'labelWidth' => 53, 'spacer' => 1],
    ];

    /**
     * Each zone resolved at one instant.
     *
     * The digits come back twice: as text (`hour`/`minute`/`second`) and as
     * the char codes the legacy digit glyphs are named after
     * (`/images/courier11/_cNN.gif`, where NN is the code point). The glyphs
     * are 7x10 px and are the original artwork, so drawing them keeps the
     * header pixel-identical and gives every row a deterministic width instead
     * of one that depends on which fonts the client has.
     *
     * @return list<array<string, mixed>>
     */
    public function at(?\DateTimeImmutable $moment = null): array
    {
        $utc = ($moment ?? new \DateTimeImmutable('now'))->setTimezone(new \DateTimeZone('UTC'));

        $zones = [];

        foreach (self::ZONES as $zone) {
            $local = $utc->setTimezone(new \DateTimeZone($zone['tz']));
            $hour  = (int) $local->format('G');

            $hourText   = str_pad((string) ($hour % 12 ?: 12), 2, '0', STR_PAD_LEFT);
            $minuteText = $local->format('i');
            $secondText = $local->format('s');
            $meridiem   = $hour < 12 ? 'a' : 'p';

            $zones[] = $zone + [
                'offset'   => $local->getOffset(),
                'hour'     => $hourText,
                'minute'   => $minuteText,
                'second'   => $secondText,
                'meridiem' => $meridiem,
                // Char codes for the GIF names: "0" is 48, "a" 97, "p" 112.
                'hour1'    => ord($hourText[0]),
                'hour2'    => ord($hourText[1]),
                'minute1'  => ord($minuteText[0]),
                'minute2'  => ord($minuteText[1]),
                'second1'  => ord($secondText[0]),
                'second2'  => ord($secondText[1]),
                'dt1'      => ord($meridiem),
                'dt2'      => ord('m'),
            ];
        }

        return $zones;
    }

    /**
     * The same rows, with the inter-row separator closed up after the last one.
     *
     * The legacy template emitted the 4 px spacer *after* every clock row,
     * including the last, which left the block 4 px taller than the space the
     * frame centres it in. Keeping the separator between rows is what makes the
     * five clocks total 101 px and sit centred, exactly as before.
     *
     * @return list<array<string, mixed>>
     */
    public function rows(?\DateTimeImmutable $moment = null): array
    {
        $rows  = $this->at($moment);
        $last  = count($rows) - 1;

        foreach ($rows as $index => $row) {
            $rows[$index]['separator'] = $index === $last ? 0 : 4;
        }

        return $rows;
    }

    /**
     * UTC offsets keyed by zone, for the client-side ticker.
     *
     * Seconds east of UTC. A JSON object rather than a positional array so the
     * view never has to rely on the two lists staying in the same order.
     *
     * @return array<string, int>
     */
    public function offsets(?\DateTimeImmutable $moment = null): array
    {
        $offsets = [];

        foreach ($this->at($moment) as $zone) {
            $offsets[(string) $zone['key']] = (int) $zone['offset'];
        }

        return $offsets;
    }
}
