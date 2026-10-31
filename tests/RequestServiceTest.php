<?php

namespace App\Tests;

use App\Services\RequestService;
use App\Services\StationService;
use PHPUnit\Framework\TestCase;

/**
 * The song-request rules.
 *
 * These were implicit in the legacy `request_song_radiodj()` loop, which is
 * exactly the kind of logic that silently changes meaning when rewritten. They
 * are pinned here before the endpoint is wired up.
 *
 * The history rows mirror the legacy query: requests from roughly the last day,
 * joined to the song's last-played timestamps.
 */
class RequestServiceTest extends TestCase
{
    private RequestService $service;

    /** A fixed "now": 2026-03-15 12:00:00 UTC. */
    private int $now;

    protected function setUp(): void
    {
        $this->service = new RequestService(new StationService([
            ['id' => 'Trance', 'slug' => 'trance', 'title' => 'Trance', 'radiodj_db' => 'radiodj_trance'],
        ]));

        $this->now = strtotime('2026-03-15 12:00:00 UTC');
    }

    private function row(array $overrides = []): array
    {
        return [
            'userIP'        => '10.0.0.1',
            'requested'     => null,
            'date_played'   => null,
            'title_played'  => null,
            'artist_played' => null,
            ...$overrides,
        ];
    }

    public function testEmptyHistoryIsAccepted(): void
    {
        $result = $this->service->evaluate([], '10.0.0.1', $this->now);

        self::assertSame(RequestService::STATUS_OK, $result['status']);
    }

    public function testOtherIpDoesNotCountTowardsLimits(): void
    {
        $history = array_fill(0, 20, $this->row([
            'userIP'    => '10.0.0.99',
            'requested' => date('Y-m-d H:i:s', $this->now - 60),
        ]));

        $result = $this->service->evaluate($history, '10.0.0.1', $this->now);

        self::assertSame(RequestService::STATUS_OK, $result['status']);
    }

    public function testElevenDailyRequestsAreRejected(): void
    {
        // Spread more than an hour apart so the hourly limit is not the one
        // that fires first; all still within the same calendar day.
        $history = [];
        for ($i = 0; $i < 11; $i++) {
            $history[] = $this->row(['requested' => date('Y-m-d H:i:s', $this->now - 3900 * ($i + 1))]);
        }

        $result = $this->service->evaluate($history, '10.0.0.1', $this->now);

        self::assertSame(RequestService::STATUS_DAILY_LIMIT, $result['status']);
    }

    public function testTenDailyRequestsAreStillAllowed(): void
    {
        $history = [];
        for ($i = 0; $i < 10; $i++) {
            // Spread across the day but all outside the one-hour window.
            $history[] = $this->row(['requested' => date('Y-m-d H:i:s', $this->now - 7200 - $i * 600)]);
        }

        $result = $this->service->evaluate($history, '10.0.0.1', $this->now);

        self::assertSame(RequestService::STATUS_OK, $result['status']);
    }

    public function testThirdRequestWithinAnHourIsRejected(): void
    {
        $history = [
            $this->row(['requested' => date('Y-m-d H:i:s', $this->now - 600)]),
            $this->row(['requested' => date('Y-m-d H:i:s', $this->now - 300)]),
            $this->row(['requested' => date('Y-m-d H:i:s', $this->now - 60)]),
        ];

        $result = $this->service->evaluate($history, '10.0.0.1', $this->now);

        self::assertSame(RequestService::STATUS_TIMER_LIMIT, $result['status']);
    }

    public function testTwoRequestsWithinAnHourAreAllowed(): void
    {
        $history = [
            $this->row(['requested' => date('Y-m-d H:i:s', $this->now - 600)]),
            $this->row(['requested' => date('Y-m-d H:i:s', $this->now - 300)]),
        ];

        $result = $this->service->evaluate($history, '10.0.0.1', $this->now);

        self::assertSame(RequestService::STATUS_OK, $result['status']);
    }

    public function testTrackPlayedWithinFourHoursIsRejected(): void
    {
        $history = [$this->row([
            'date_played' => date('Y-m-d H:i:s', $this->now - (RequestService::REPEAT_TRACK_SECONDS - 60)),
        ])];

        $result = $this->service->evaluate($history, '10.0.0.1', $this->now);

        self::assertSame(RequestService::STATUS_TRACK_REPEAT, $result['status']);
    }

    public function testTrackPlayedLongAgoIsAllowed(): void
    {
        $history = [$this->row([
            'date_played' => date('Y-m-d H:i:s', $this->now - (RequestService::REPEAT_TRACK_SECONDS + 60)),
        ])];

        $result = $this->service->evaluate($history, '10.0.0.1', $this->now);

        self::assertSame(RequestService::STATUS_OK, $result['status']);
    }

    public function testArtistPlayedWithinTwoHoursIsRejected(): void
    {
        $history = [$this->row([
            'artist_played' => date('Y-m-d H:i:s', $this->now - (RequestService::REPEAT_ARTIST_SECONDS - 60)),
        ])];

        $result = $this->service->evaluate($history, '10.0.0.1', $this->now);

        self::assertSame(RequestService::STATUS_ARTIST_REPEAT, $result['status']);
    }

    public function testTitlePlayedWithinFourHoursIsRejected(): void
    {
        $history = [$this->row([
            'title_played' => date('Y-m-d H:i:s', $this->now - (RequestService::REPEAT_TITLE_SECONDS - 60)),
        ])];

        $result = $this->service->evaluate($history, '10.0.0.1', $this->now);

        self::assertSame(RequestService::STATUS_TITLE_REPEAT, $result['status']);
    }

    public function testZeroDateDoesNotTriggerRepeatRules(): void
    {
        $history = [$this->row([
            'date_played'   => '0000-00-00 00:00:00',
            'title_played'  => '0000-00-00 00:00:00',
            'artist_played' => '0000-00-00 00:00:00',
        ])];

        $result = $this->service->evaluate($history, '10.0.0.1', $this->now);

        self::assertSame(RequestService::STATUS_OK, $result['status']);
    }
}
