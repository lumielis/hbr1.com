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
 *
 * The page keeps the legacy URL contract: the calendar is driven by a single
 * unix timestamp, and every year / month / day cell links back with another
 * one. The ported name for it is `?from=`; the legacy `?time_from=` is accepted
 * too, because those links are bookmarked and indexed.
 */
class PartyController extends BaseController
{
    public function indexAction(): string
    {
        /** @var PartyService $service */
        $service = $this->service(PartyService::class);

        $timezone  = $this->config()['app']['timezone'] ?? 'Europe/Berlin';
        $timestamp = $this->requestedTimestamp();

        $calendar = $service->calendar($timestamp);

        return $this->page('web/party', [
            'title'    => 'Party Base',
            'calendar' => $calendar,
            'bands'    => [
                ['href' => '/party/new', 'label' => 'ENTER_A_PARTY', 'colour' => '#AAAA22'],
                ['href' => '/party', 'label' => 'RESET_CALENDAR', 'colour' => '#AAAA22'],
            ],
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
     * The legacy page read `time_from` and fell back to "now". It is clamped to
     * five years either side so a hand-crafted value cannot ask the calendar to
     * build rows for year 100000.
     */
    private function requestedTimestamp(): int
    {
        $query = $this->context()->request()->query();

        $value = $query['from'] ?? $query['time_from'] ?? null;

        if (!is_scalar($value) || !is_numeric($value)) {
            return time();
        }

        $timestamp = (int) $value;
        $now       = time();

        return max($now - 5 * 366 * 86400, min($now + 5 * 366 * 86400, $timestamp));
    }
}
