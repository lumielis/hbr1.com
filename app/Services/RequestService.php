<?php

namespace App\Services;

use Azera\Db\Query;
use Azera\Db\Resolver\ChainResolver;
use Azera\Db\Resolver\MappingResolver;
use Azera\Db\Resolver\ModelResolver;

/**
 * Song requests.
 *
 * The rules below were implicit in `request_song_radiodj()` (a `while` loop
 * over the last day's requests). They are constants here and covered by tests,
 * because they are the kind of behaviour that silently changes meaning when
 * code is rewritten.
 */
class RequestService
{
    /** Requests per IP per calendar day. */
    public const DAILY_MAX_VOTES = 10;

    /** Requests per IP within TIMER_WINDOW_SECONDS. */
    public const TIMER_MAX_VOTES = 2;

    public const TIMER_WINDOW_SECONDS = 3600;

    /** Minimum gap before the same artist may be requested again. */
    public const REPEAT_ARTIST_SECONDS = 7200;

    /** Minimum gap before the same title may be requested again. */
    public const REPEAT_TITLE_SECONDS = 14400;

    /** Minimum gap before the same track may be requested again. */
    public const REPEAT_TRACK_SECONDS = 14400;

    public const STATUS_OK = 'ok';
    public const STATUS_DAILY_LIMIT = 'daily-limit';
    public const STATUS_TIMER_LIMIT = 'timer-limit';
    public const STATUS_TRACK_REPEAT = 'track-repeat';
    public const STATUS_TITLE_REPEAT = 'title-repeat';
    public const STATUS_ARTIST_REPEAT = 'artist-repeat';
    public const STATUS_UPSTREAM_ERROR = 'upstream-error';

    public function __construct(
        private readonly StationService $stations,
    ) {
    }

    /**
     * Evaluate the request rules for one IP and one song.
     *
     * Pure decision, no side effects: takes the request history as data so it
     * can be tested without a database or a radio station.
     *
     * @param array<int, array{userIP: string, requested: ?string, date_played: ?string, title_played: ?string, artist_played: ?string}> $history
     * @return array{status: string, message: string}
     */
    public function evaluate(array $history, string $clientIp, int $now): array
    {
        $todayStart = (int) strtotime('today', $now);
        $dailyVotes = 0;
        $timerVotes = 0;

        foreach ($history as $request) {
            if (($request['userIP'] ?? '') !== $clientIp) {
                continue;
            }

            $requestedAt = $this->timestamp($request['requested'] ?? null);

            if ($requestedAt !== null && $requestedAt >= $todayStart) {
                if (++$dailyVotes > self::DAILY_MAX_VOTES) {
                    return [
                        'status'  => self::STATUS_DAILY_LIMIT,
                        'message' => 'maximum daily votes reached, please try later again',
                    ];
                }
            }

            if ($requestedAt !== null && $requestedAt + self::TIMER_WINDOW_SECONDS >= $now) {
                if (++$timerVotes > self::TIMER_MAX_VOTES) {
                    return [
                        'status'  => self::STATUS_TIMER_LIMIT,
                        'message' => 'maximum votes reached, please try later again',
                    ];
                }
            }

            // The repeat checks are about what has been *played*, not what was
            // requested, so they apply to the station as a whole.
            if ($this->within($request['date_played'] ?? null, self::REPEAT_TRACK_SECONDS, $now)) {
                return [
                    'status'  => self::STATUS_TRACK_REPEAT,
                    'message' => 'track already played, please choose another one',
                ];
            }

            if ($this->within($request['title_played'] ?? null, self::REPEAT_TITLE_SECONDS, $now)) {
                return [
                    'status'  => self::STATUS_TITLE_REPEAT,
                    'message' => 'title already played, please choose another one',
                ];
            }

            if ($this->within($request['artist_played'] ?? null, self::REPEAT_ARTIST_SECONDS, $now)) {
                return [
                    'status'  => self::STATUS_ARTIST_REPEAT,
                    'message' => 'artist already played, please choose another one',
                ];
            }
        }

        return ['status' => self::STATUS_OK, 'message' => 'request accepted'];
    }

    /**
     * The station's request history for roughly the last day.
     *
     * Only RadioDJ exposes this; the SAM schema is dormant (no samdb_* exists
     * on the server any more).
     *
     * @return array<int, array<string, mixed>>
     */
    public function historyForToday(array $station, int $now): array
    {
        if (empty($station['radiodj_db'])) {
            return [];
        }

        $rows = $this->query($station, 'RadioDjRequests')
            ->columns(['userIP', 'requested', 'songID'])
            ->where('requested >=', date('Y-m-d H:i:s', (int) strtotime('today', $now) - 86400))
            ->select();

        $history = [];

        foreach ($rows as $row) {
            $history[] = (array) $row;
        }

        return $history;
    }

    /**
     * Ask the playout system to queue a track.
     *
     * Returns the upstream status code as a string ('200' on success), or null
     * when the upstream could not be reached or answered with garbage.
     */
    public function push(array $station, int $songId): ?string
    {
        $url = str_replace('{id}', (string) $songId, (string) ($station['radiodj_request_url'] ?? ''));

        if ($url === '') {
            return null;
        }

        $response = @file_get_contents($url);

        if ($response === false || trim($response) === '') {
            return null;
        }

        $document = new \DOMDocument();

        if (!@$document->loadXML($response)) {
            return null;
        }

        $status = trim($document->documentElement?->textContent ?? '');

        return $status === '' ? null : $status;
    }

    /**
     * Record an accepted request so the limits apply next time.
     */
    public function record(array $station, int $songId, string $clientIp, int $now): bool
    {
        if (empty($station['radiodj_db'])) {
            return false;
        }

        $this->query($station, 'RadioDjRequests')
            ->values([
                'songID'    => $songId,
                'userIP'    => $clientIp,
                'requested' => date('Y-m-d H:i:s', $now),
                'played'    => 1,
            ])
            ->insert();

        return true;
    }

    private function within(?string $value, int $windowSeconds, int $now): bool
    {
        $timestamp = $this->timestamp($value);

        return $timestamp !== null && $timestamp + $windowSeconds >= $now;
    }

    private function timestamp(?string $value): ?int
    {
        if ($value === null || $value === '' || str_starts_with($value, '0000-00-00')) {
            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? null : $timestamp;
    }

    private function query(array $station, string $logicalPrefix): Query
    {
        return Query::new()
            ->using(new ChainResolver([
                new ModelResolver(),
                new MappingResolver(MusicDatabaseMapping::forStation($station['id'])),
            ]))
            ->from($logicalPrefix . '_' . $station['id']);
    }
}
