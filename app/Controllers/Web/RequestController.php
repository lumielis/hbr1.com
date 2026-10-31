<?php

namespace App\Controllers\Web;

use App\Controllers\BaseController;
use App\Services\RequestService;
use App\Services\SongService;
use App\Services\StationService;
use Azera\Http\Response;

/**
 * Song requests (replaces the `cmd=request` / `cmd=reqver` branches of
 * `main.php?module=tracklist`).
 *
 * The legacy flow gated submissions behind a `securimage` captcha. That
 * library no longer parses on PHP 8.3, and a captcha is the wrong tool here
 * anyway: the abuse we actually care about is one IP flooding the queue, which
 * the per-IP rules in RequestService already bound, and which a rate limiter
 * stops before it reaches the database.
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
            return Response::redirect('/tracklist/' . $data['slug'], 302);
        }

        return Response::html($this->page('web/request', [
            'title'   => 'Request a track - HBR1',
            'station' => $data,
            'track'   => $track,
            'csrf'    => $this->csrfToken(),
        ]));
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
            return Response::html($this->page('web/request', [
                'title'   => 'Request a track - HBR1',
                'station' => $data,
                'track'   => null,
                'error'   => 'Requests are not available for this station.',
                'csrf'    => $this->csrfToken(),
            ]), 400);
        }

        $songId = (int) $this->context()->request()->post('song', 0);
        $client = (string) ($this->context()->request()->clientIp(true) ?: '0.0.0.0');
        $now    = time();

        /** @var SongService $songs */
        $songs = $this->service(SongService::class);
        $track = $songs->find($data, $songId);

        if ($track === null) {
            return Response::redirect('/tracklist/' . $data['slug'], 302);
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
            return Response::html($this->page('web/request', [
                'title'   => 'Request a track - HBR1',
                'station' => $data,
                'track'   => $track,
                'error'   => $verdict['message'],
                'csrf'    => $this->csrfToken(),
            ]), 429);
        }

        // Only ask the playout system once the local rules have accepted, so
        // a rejected request never reaches RadioDJ.
        $status = null;

        try {
            $status = $requests->push($data, $songId);
        } catch (\Throwable $e) {
            error_log('request: push ' . $e->getMessage());
        }

        if ($status !== '200') {
            return Response::html($this->page('web/request', [
                'title'   => 'Request a track - HBR1',
                'station' => $data,
                'track'   => $track,
                'error'   => 'The station is offline or did not accept the request. Please try again later.',
                'csrf'    => $this->csrfToken(),
            ]), 502);
        }

        try {
            $requests->record($data, $songId, $client, $now);
        } catch (\Throwable $e) {
            error_log('request: record ' . $e->getMessage());
        }

        return Response::html($this->page('web/request', [
            'title'   => 'Request a track - HBR1',
            'station' => $data,
            'track'   => $track,
            'queued'  => true,
            'csrf'    => $this->csrfToken(),
        ]));
    }
}
