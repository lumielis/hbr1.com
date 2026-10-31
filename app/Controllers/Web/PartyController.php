<?php

namespace App\Controllers\Web;

use App\Controllers\BaseController;
use App\Services\PartyService;
use Azera\Http\Response;

/**
 * Party calendar (replaces `/run/main.xp?action=party`).
 *
 * `/party` is the calendar, `/party/{id}` a single event. Submitting a party
 * needs an account (the legacy form was a password-protected edit view), so
 * that lives in the admin workstream, not here.
 */
class PartyController extends BaseController
{
    public function indexAction(): string
    {
        /** @var PartyService $service */
        $service = $this->service(PartyService::class);

        $timezone  = $this->config()['app']['timezone'] ?? 'Europe/Berlin';
        $timestamp = $this->requestedTimestamp();

        $calendar = $service->calendar($timestamp, $timezone);

        return $this->page('web/party', [
            'title'    => 'Party Calendar - HBR1',
            'calendar' => $calendar,
            'upcoming' => $service->upcoming($timestamp, 25),
            'weekdays' => ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'],
            'tz'       => $timezone,
        ]);
    }

    public function detailAction(int $id): Response|string
    {
        /** @var PartyService $service */
        $service = $this->service(PartyService::class);

        $party = $service->find($id);

        if ($party === null || $party['status'] === 0) {
            return Response::status(404);
        }

        return $this->page('web/party_detail', [
            'title' => ($party['title'] !== '' ? $party['title'] : 'Party') . ' - HBR1',
            'party' => $party,
            'tz'    => $this->config()['app']['timezone'] ?? 'Europe/Berlin',
        ]);
    }

    /**
     * The legacy page took `time_from` as a unix timestamp. It is clamped to a
     * sane range so a hand-crafted value cannot ask for year 100000 and make
     * the calendar build thousands of rows.
     */
    private function requestedTimestamp(): int
    {
        $value = $this->context()->request()->query('from');

        if (!is_numeric($value)) {
            return time();
        }

        $timestamp = (int) $value;
        $now       = time();

        // Five years either side, matching the year list.
        return max($now - 5 * 366 * 86400, min($now + 5 * 366 * 86400, $timestamp));
    }
}
