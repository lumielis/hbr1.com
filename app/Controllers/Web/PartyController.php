<?php

namespace App\Controllers\Web;

use App\Controllers\BaseController;
use App\Services\PartyService;
use Azera\Http\Response;

/**
 * Party calendar (replaces `/run/main.xp?action=party`).
 *
 * `/party` is the calendar, `/party/{id}` one event, and `/party/new` the
 * submission form that the `ENTER_A_PARTY` band links to. `/party/{id}/edit`
 * is the same form over an existing row, which the legacy page reached from
 * the `EDIT_PARTY` band on the detail page.
 *
 * The page keeps the legacy URL contract: the calendar is driven by a single
 * unix timestamp, and every year / month / day cell links back with another
 * one. The ported name for it is `?from=`; the legacy `?time_from=` is accepted
 * too, because those links are bookmarked and indexed.
 *
 * Two deliberate differences from `include/client/party.pl`:
 *
 *  - the legacy form was created by *loading* `?part=edit&party_id=new`, which
 *    inserted a draft row before the visitor had typed anything. Every page
 *    view left a row behind; three consecutive GETs during this port produced
 *    drafts 7469, 7470 and 7471. Here the form is a plain GET and the row is
 *    created on POST.
 *
 *  - the legacy password was stored in clear (`varchar(32)`). New parties are
 *    hashed; the clear-text rows keep working through
 *    `PartyService::verifyPassword()`, which upgrades each one the first time
 *    its owner edits it.
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
            // The three legacy SUBMENU buttons. `COMMENTS` is the
            // party_comment thread (W8) and points back here until then; the
            // page has always had three bands and a missing button is more
            // jarring than one that lands where the reader already is.
            'bands' => [
                ['href' => '/party/' . (int) $party['id'], 'label' => 'COMMENTS', 'colour' => '#AAAA22'],
                ['href' => '/party', 'label' => 'CALENDAR', 'colour' => '#77AA00'],
                ['href' => '/party/' . (int) $party['id'] . '/edit', 'label' => 'EDIT_PARTY', 'colour' => '#AAAA22'],
            ],
        ]);
    }

    /** An empty form. Nothing is written until it is submitted. */
    public function newAction(): string
    {
        /** @var PartyService $service */
        $service = $this->service(PartyService::class);

        return $this->form($service, null, $service->blankForm(), []);
    }

    /** The form over an existing row: `?part=edit&party_id=N`. */
    public function editAction(int $id): Response|string
    {
        /** @var PartyService $service */
        $service = $this->service(PartyService::class);

        $party = $service->find($id);

        if ($party === null || $party['status'] === 0) {
            return Response::status(404);
        }

        return $this->form($service, $party, $service->editForm($party), []);
    }

    public function createAction(): Response
    {
        /** @var PartyService $service */
        $service = $this->service(PartyService::class);

        return $this->save($service, null);
    }

    public function updateAction(int $id): Response
    {
        /** @var PartyService $service */
        $service = $this->service(PartyService::class);

        $party = $service->find($id);

        if ($party === null) {
            return Response::status(404);
        }

        return $this->save($service, $party);
    }

    /**
     * The shared POST path for both forms.
     *
     * The order of the checks is the legacy order: the required fields first,
     * then the submitter credentials, then the upload. A rejection re-renders
     * the form rather than redirecting, because the page the visitor was
     * looking at is the only one that can explain what went wrong.
     *
     * @param array|null $party the stored row when editing, null when creating
     */
    private function save(PartyService $service, ?array $party): Response
    {
        $request = $this->context()->request();
        $input   = $this->input();

        $errors = $service->validate($input, $party);

        // On create there is nothing to authenticate against. On update the
        // stored name and password decide whether this visitor may change the
        // row - the legacy form demanded both again on every save.
        if ($errors === [] && $party !== null
            && !$service->mayEdit($party, $input['submitter'], (string) $input['password'])) {
            $errors['password'] = 'Your personal data is not correct.';
        }

        if ($errors !== []) {
            return Response::html(
                $this->form(
                    $service,
                    $party,
                    $input,
                    $errors,
                    'There are some errors. Please look at the red labeled fields.'
                ),
                400
            );
        }

        // The flyer is staged and checked before anything is written, so a
        // rejected image cannot publish a party the visitor was told had
        // failed. `party.pl` ordered it the same way: `upload_file()` first,
        // `$party->save()` second.
        $upload  = $request->file('flyer');
        $staged  = null;
        $flyer   = null;

        if ($upload !== null && $upload->isValid()) {
            $staged = $service->stageFlyer($upload, $flyer);

            if ($staged === null) {
                return Response::html(
                    $this->form($service, $party, $input, ['flyer' => (string) $flyer]),
                    400
                );
            }
        }

        $id = $party === null ? $service->create($input) : (int) $party['id'];

        if ($party !== null) {
            $service->update($id, $input);

            // A new password is only stored when the visitor typed one, so an
            // edit that leaves both password fields empty keeps the old one.
            if (trim($input['password']) !== '') {
                $service->changePassword($id, $input['password']);
            }
        }

        if ($staged !== null) {
            $flyerError = null;
            $name       = $service->commitFlyer($id, $staged, $flyerError);

            if ($name !== '') {
                $service->setFlyer($id, $name);
            } elseif ($flyerError !== null) {
                // The party is already stored at this point. Reporting the
                // flyer without discarding the rest is what the legacy page did.
                return Response::html(
                    $this->form($service, $party, $input, ['flyer' => $flyerError]),
                    400
                );
            }
        }

        return Response::redirect('/party/' . $id, 303);
    }

    /**
     * Render the form.
     *
     * `$values` is merged over the blank form, not substituted for it: Clarity
     * raises on an undefined variable, so every control's value has to exist
     * even when the submission that produced the page omitted a field.
     *
     * @param array|null            $party  the stored row, null for a new party
     * @param array<string, string> $values the values to echo back
     * @param array<string, string> $errors field => message
     */
    private function form(
        PartyService $service,
        ?array $party,
        array $values,
        array $errors,
        ?string $message = null
    ): string {
        return $this->page('web/party_form', [
            'title'     => 'Party Base',
            'action'    => $party === null ? '/party/new' : '/party/' . (int) $party['id'] . '/edit',
            'partyId'   => $party === null ? null : (int) $party['id'],
            'values'    => array_replace($service->blankForm(), $values),
            'errors'    => $this->padErrors($errors),
            'types'     => $service->types(),
            'countries' => $service->countries(),
            'message'   => $message,
            'csrf'      => $this->csrfToken(),
            // The legacy form always offered a fresh file input. Showing the
            // stored flyer as a thumbnail is the one addition: without it there
            // is no way to tell whether one is already on file.
            'flyer'     => $party === null ? '' : PartyService::flyerUrl((string) $party['flyer']),
        ]);
    }

    /**
     * Every field the template tests, so `{% if errors:title %}` never raises.
     *
     * @param  array<string, string> $errors
     * @return array<string, string>
     */
    private function padErrors(array $errors): array
    {
        $padded = [];

        foreach ([
            'title', 'start_timestamp', 'end_timestamp', 'country', 'city',
            'location', 'djs', 'live', 'chillout', 'deco', 'memo', 'flyer',
            'organizer', 'email', 'url', 'hotline', 'submitter', 'password',
        ] as $field) {
            $padded[$field] = (string) ($errors[$field] ?? '');
        }

        return $padded;
    }

    /**
     * The submitted controls, as flat strings.
     *
     * The legacy template rendered the password confirmation only while a party
     * was still a draft, so an existing party could not change its password at
     * all. Both fields are accepted here.
     *
     * @return array<string, string>
     */
    private function input(): array
    {
        $post = $this->context()->request()->post();

        if (!is_array($post)) {
            $post = [];
        }

        $input = [];

        foreach ([
            'title', 'type', 'country', 'city', 'location', 'djs', 'live',
            'chillout', 'deco', 'memo', 'organizer', 'email', 'url', 'hotline',
            'submitter', 'password', 'password2',
            'start_timestamp_date', 'start_timestamp_time',
            'end_timestamp_date', 'end_timestamp_time',
        ] as $field) {
            $value = $post[$field] ?? '';

            $input[$field] = is_scalar($value) ? (string) $value : '';
        }

        return $input;
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
