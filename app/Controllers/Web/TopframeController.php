<?php

namespace App\Controllers\Web;

use App\Controllers\BaseController;

/**
 * The clock/header frame (replaces `main.php?module=topframe`).
 *
 * The legacy version asked MySQL to do the timezone conversion with a
 * `CONVERT_TZ()` round-trip. PHP owns timezones natively, so this is now a
 * pure computation with no database access at all.
 */
class TopframeController extends BaseController
{
    /** Zones shown in the header, in display order. */
    private const ZONES = [
        'UTC'            => 'UTC',
        'Europe/Berlin'  => 'Berlin',
        'America/New_York' => 'New York',
        'Europe/Moscow'  => 'Moscow',
        'Asia/Tokyo'     => 'Tokyo',
    ];

    public function indexAction(): string
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $zones = [];
        foreach (self::ZONES as $tzName => $label) {
            $zones[] = [
                'name'    => $label,
                'offset'  => $now->setTimezone(new \DateTimeZone($tzName))->getOffset(),
                'time'    => $now->setTimezone(new \DateTimeZone($tzName))->format('H:i'),
                'hours'   => $now->setTimezone(new \DateTimeZone($tzName))->format('H'),
                'minutes' => $now->setTimezone(new \DateTimeZone($tzName))->format('i'),
            ];
        }

        $offsets = array_map(
            static fn(array $zone): int => $zone['offset'],
            $zones
        );

        return $this->fragment('web/topframe', [
            'title'    => 'HBR1',
            'utc'      => $now->getTimestamp(),
            'zones'    => $zones,
            // Rendered into a JS literal, so it must be a real JSON array and
            // must not be escaped. Passing a comma-joined string produced
            // ["0,3600,..."] - one string element, hence NaN:NaN.
            'offsets'  => json_encode($offsets, JSON_THROW_ON_ERROR),
            'timezone' => $this->config()['app']['timezone'],
        ]);
    }
}
