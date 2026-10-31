<?php

namespace App\Controllers\Web;

use App\Controllers\BaseController;
use App\Services\GuestbookService;
use Azera\Http\Response;

/**
 * Guestbook (replaces `/perl/apax.cgi?run=gbms`).
 *
 * The listing is public; posting is CSRF-protected and rate limited. Entries
 * that match a `guestbook_filter` pattern are stored with status 2 and never
 * shown, which is how the legacy code handled spam.
 */
class GuestbookController extends BaseController
{
    public function indexAction(): string
    {
        /** @var GuestbookService $service */
        $service = $this->service(GuestbookService::class);
        $offset  = $this->offset();

        $page = $service->page($offset);

        return $this->page('web/guestbook', [
            'title'   => 'Guestbook - HBR1',
            'page'    => $page,
            'pager'   => $this->pager($page['total'], $offset, $page['limit']),
            'errors'  => $this->padErrors([]),
            'values'  => $this->blankValues(),
            'posted'  => false,
            'pending' => false,
            'csrf'    => $this->csrfToken(),
        ]);
    }

    /**
     * Clarity raises on an undefined variable, so every field the form echoes
     * must exist even on a fresh GET.
     *
     * @return array<string, string>
     */
    private function blankValues(array $input = []): array
    {
        return [
            'submitter' => (string) ($input['submitter'] ?? ''),
            'email'     => (string) ($input['email'] ?? ''),
            'content'   => (string) ($input['content'] ?? ''),
        ];
    }

    /**
     * Same reason: the template asks `{% if errors:submitter %}`, so every key
     * has to exist. Missing fields become empty strings.
     *
     * @param array<string, string> $errors
     * @return array<string, string>
     */
    private function padErrors(array $errors): array
    {
        return [
            'submitter' => (string) ($errors['submitter'] ?? ''),
            'email'     => (string) ($errors['email'] ?? ''),
            'content'   => (string) ($errors['content'] ?? ''),
        ];
    }

    public function submitAction(): Response|string
    {
        /** @var GuestbookService $service */
        $service = $this->service(GuestbookService::class);

        $request = $this->context()->request();
        $input   = [
            'submitter' => (string) ($request->post('submitter') ?? ''),
            'email'     => (string) ($request->post('email') ?? ''),
            'content'   => (string) ($request->post('content') ?? ''),
        ];

        $errors = $service->validate($input);

        if ($errors !== []) {
            $page = $service->page(0);

            return Response::html($this->page('web/guestbook', [
                'title'   => 'Guestbook - HBR1',
                'page'    => $page,
                'pager'   => $this->pager($page['total'], 0, $page['limit']),
                'errors'  => $this->padErrors($errors),
                'values'  => $this->blankValues($input),
                'posted'  => false,
                'pending' => false,
                'csrf'    => $this->csrfToken(),
            ]), 400);
        }

        $result = $service->submit(
            $input,
            (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
            (string) ($request->header('User-Agent') ?? '')
        );

        return $this->page('web/guestbook_thanks', [
            'title'   => 'Thank you - HBR1',
            'pending' => $result['status'] !== GuestbookService::STATUS_PUBLIC,
            'entry'   => $input,
        ]);
    }

    private function offset(): int
    {
        $offset = $this->context()->request()->query('offset');

        return is_numeric($offset) ? max(0, (int) $offset) : 0;
    }

    /** Same five-either-side window the legacy page_navigation() produced. */
    private function pager(int $total, int $offset, int $limit): array
    {
        $current = intdiv($offset, max(1, $limit)) + 1;
        $last    = max(1, (int) ceil($total / max(1, $limit)));
        $window  = 5;

        $start = max(1, $current - $window);
        $end   = min($last, $start + $window * 2);

        if ($end - $start < $window * 2) {
            $start = max(1, $end - $window * 2);
        }

        return [
            'current' => $current,
            'last'    => $last,
            'start'   => $start,
            'end'     => $end,
            'offset'  => $offset,
            'limit'   => $limit,
            'total'   => $total,
            'hasPrev' => $current > 1,
            'hasNext' => $current < $last,
            'prevOff' => max(0, $offset - $limit),
            'nextOff' => $offset + $limit,
        ];
    }
}
