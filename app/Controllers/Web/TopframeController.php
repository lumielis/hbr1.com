<?php

namespace App\Controllers\Web;

use App\Controllers\BaseController;
use App\Services\ClockService;

/**
 * The clock/header frame (replaces `main.php?module=topframe`).
 *
 * The visual layout is a port of `template/client/topframe.phtml`: four banner
 * images on the left, five staggered clock rows on the right. The legacy
 * clocks were `<img>` digit glyphs driven by JavaScript; they are now plain
 * text over the same `images/clock/*.gif` chrome, which keeps the look and
 * removes ~40 image requests per header load.
 *
 * The legacy page asked MySQL to convert one timestamp into five zones with
 * `CONVERT_TZ()`. That is not just slower, it is broken on this server: the
 * `mysql.time_zone*` tables are empty, so every call returned NULL. PHP owns
 * the IANA database, so the conversion now happens in ClockService and the
 * page needs no database at all.
 */
class TopframeController extends BaseController
{
    public function indexAction(): string
    {
        /** @var ClockService $clock */
        $clock = $this->service(ClockService::class);

        $now = new \DateTimeImmutable('now');

        return $this->fragment('web/topframe', [
            'title'   => 'HBR1',
            'utc'     => $now->getTimestamp(),
            'zones'   => $clock->rows($now),
            // Rendered into a JS literal: a real JSON object, unescaped.
            'offsets' => json_encode($clock->offsets($now), JSON_THROW_ON_ERROR),
        ]);
    }
}
