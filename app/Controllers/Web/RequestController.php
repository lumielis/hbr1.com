<?php

namespace App\Controllers\Web;

use App\Controllers\BaseController;
use App\Services\CaptchaService;
use App\Services\RequestService;
use App\Services\SongService;
use App\Services\StationService;
use Azera\Http\Response;

/**
 * Song requests (replaces the `cmd=request` / `cmd=reqver` branches of
 * `main.php?module=tracklist`).
 *
 * The legacy flow gated submissions behind a `securimage` captcha
 * (`?show=secimg`). That library does not parse on PHP 8.3 at all, so it was
 * replaced, not restored: `CaptchaService` reproduces the same picture - same
 * font, same colours, same distortion - with the same 15-minute, single-use
 * code. The per-IP limits in RequestService remain the real control; the
 * captcha stops scripted bulk submission, which the limits only bound.
 */
class RequestController extends BaseController
{
    public function formAction(string $station, int $song): Response
    {
        /** @var StationService $stations */
        $stations = $this->service(StationService::class);
        $data     = $stations->find($station);

        if ($data === null) {
            return Response::status(404);
        }

        /** @var SongService $songs */
        $songs = $this->service(SongService::class);
        $track = $songs->find($data, $song);

        if ($track === null) {
            return Response::redirect('/mixes/' . $data['slug'], 302);
        }

        return Response::html($this->page('web/request', [
            'title'   => 'HBR1 Inventory Request',
            'station' => $data,
            'track'   => $track,
            'csrf'    => $this->csrfToken(),
            'captcha' => $this->captchaUrl(),
            // The GO_BACK target. The catalogue picks a *random* page when
            // `?offset=` is absent, so without carrying the offset through the
            // round-trip the listener would return to a different slice of the
            // catalogue than the one they left.
            'backUrl' => $this->backUrl($data),
            'from'    => $this->requestedPageOffset(),
        ]));
    }

    /**
     * The security image.
     *
     * The code is issued here, not in the form, so it is bound to the image the
     * browser actually displays: the form emits only an `<img>` tag, and the
     * session value is written when that tag is fetched.
     */
    public function imageAction(): Response
    {
        /** @var CaptchaService $captcha */
        $captcha = $this->service(CaptchaService::class);

        $code = $captcha->issue($this->context()->session());
        $png  = $captcha->render($code);

        return new Response(200, [
            'Content-Type'  => 'image/png',
            // The legacy securimage sent the same headers. The URL carries a
            // cache-buster, so these only stop a proxy reusing an image whose
            // code has since been replaced in the session.
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma'        => 'no-cache',
        ], $png);
    }

    public function submitAction(string $station): Response
    {
        /** @var StationService $stations */
        $stations = $this->service(StationService::class);
        $data     = $stations->find($station);

        if ($data === null) {
            return Response::status(404);
        }

        if (empty($data['radiodj_db'])) {
            return $this->reject($data, null, 'Requests are not available for this station.', 400);
        }

        $songId = (int) $this->context()->request()->post('song', 0);
        $client = (string) ($this->context()->request()->clientIp(true) ?: '0.0.0.0');
        $now    = time();

        /** @var SongService $songs */
        $songs = $this->service(SongService::class);
        $track = $songs->find($data, $songId);

        if ($track === null) {
            return Response::redirect('/mixes/' . $data['slug'], 302);
        }

        // The captcha is checked before the request rules and before RadioDJ.
        // A wrong code is a typo, not an attempt to flood the queue, so it
        // answers 400 and re-renders with a fresh image rather than 429. The
        // legacy page redirected back to the form; this is that outcome
        // without losing the track the listener picked.
        /** @var CaptchaService $captcha */
        $captcha = $this->service(CaptchaService::class);

        if (!$captcha->verify($this->context()->session(), (string) $this->context()->request()->post('code', ''))) {
            return $this->reject(
                $data,
                $track,
                'The code you entered did not match the image. Please try again.',
                400
            );
        }

        /** @var RequestService $requests */
        $requests = $this->service(RequestService::class);

        try {
            $history = $requests->historyForToday($data, $now);
        } catch (\Throwable $e) {
            error_log('request: history ' . $e->getMessage());
            $history = [];
        }

        $verdict = $requests->evaluate($history, $client, $now);

        if ($verdict['status'] !== RequestService::STATUS_OK) {
            return $this->reject($data, $track, $verdict['message'], 429);
        }

        // Only ask the playout system once the local rules have accepted, so a
        // rejected request never reaches RadioDJ.
        $status = null;

        try {
            $status = $requests->push($data, $songId);
        } catch (\Throwable $e) {
            error_log('request: push ' . $e->getMessage());
        }

        if ($status !== '200') {
            return $this->reject(
                $data,
                $track,
                'The station is offline or did not accept the request. Please try again later.',
                502
            );
        }

        try {
            $requests->record($data, $songId, $client, $now);
        } catch (\Throwable $e) {
            error_log('request: record ' . $e->getMessage());
        }

        // A delivered request has consumed the code, so the confirmation page
        // carries no image - the legacy template did not render one either.
        return Response::html($this->page('web/request', [
            'title'   => 'HBR1 Inventory Request',
            'station' => $data,
            'track'   => $track,
            'queued'  => true,
            'csrf'    => $this->csrfToken(),
            'captcha' => null,
            'backUrl' => $this->backUrl($data),
            'from'    => $this->requestedPageOffset(),
        ]));
    }

    /**
     * Re-render the form with an error.
     *
     * Every rejection draws a fresh security image. The captcha mismatch case
     * included: re-fetching an image the listener has already failed against
     * would leave them typing the same code again.
     */
    private function reject(array $station, ?array $track, string $message, int $status): Response
    {
        return Response::html($this->page('web/request', [
            'title'   => 'HBR1 Inventory Request',
            'station' => $station,
            'track'   => $track,
            'error'   => $message,
            'csrf'    => $this->csrfToken(),
            'captcha' => $this->captchaUrl(),
            'backUrl' => $this->backUrl($station),
            'from'    => $this->requestedPageOffset(),
        ]), $status);
    }

    /**
     * The value for the form's `<img src>`.
     *
     * `?t=` is a cache-buster, exactly as the legacy template used `time()`.
     * Without it a browser that cached the previous image would show a code the
     * session no longer holds.
     */
    private function captchaUrl(): string
    {
        return '/request/code.png?t=' . time();
    }

    /**
     * The catalogue page this request came from.
     *
     * The legacy page passed the whole backlink as a query parameter and sent
     * the browser wherever it pointed. Only the numeric offset is taken from
     * the request; host and path are rebuilt here, so it cannot be made to point
     * somewhere else.
     */
    private function backUrl(array $station): string
    {
        $offset = $this->requestedPageOffset();

        return '/mixes/' . $station['slug'] . ($offset === null ? '' : '?offset=' . $offset);
    }

    /**
     * The page offset the listener came from.
     *
     * The GET form reaches it as `?from=`; the POST carries it in the body so
     * the round-trip survives a re-rendered form after a rejected request.
     * Anything that is not a plain integer is ignored rather than clamped: a
     * nonsense value means "no page was recorded", and the catalogue's own
     * behaviour for that is the random page.
     */
    private function requestedPageOffset(): ?int
    {
        $request = $this->context()->request();

        foreach ([$request->query('from'), $request->post('from')] as $candidate) {
            if (is_scalar($candidate) && preg_match('/^\d{1,9}$/', (string) $candidate) === 1) {
                return (int) $candidate;
            }
        }

        return null;
    }
}
