<?php

namespace App\Controllers\Stream;

use App\Controllers\BaseController;
use App\Services\StationService;
use Azera\Http\Response;

/**
 * Stream playlist files (replaces `asx.cgi`, `m3u.cgi`, `easx.cgi`,
 * `listen.cgi`).
 *
 * These endpoints are consumed by media players, not browsers, so the
 * responses are byte-shaped to match what the legacy scripts emitted.
 */
class StreamController extends BaseController
{
    public function playlistAction(string $file): Response
    {
        /** @var StationService $stations */
        $stations = $this->service(StationService::class);

        [$slug, $format] = array_pad(explode('.', $file, 2), 2, '');
        $data = $stations->find($slug);

        if ($data === null) {
            return Response::status(404);
        }

        return match (strtolower($format)) {
            'm3u' => $this->m3u($data),
            'asx' => $this->asx($data),
            'pls' => $this->pls($data),
            default => Response::status(404),
        };
    }

    private function m3u(array $station): Response
    {
        $body = "#EXTM3U\n"
            . "#EXTINF:-1,HBR1 - {$station['title']}\n"
            . "{$station['stream']['ogg']}\n";

        return Response::text($body)
            ->setHeader('Content-Type', 'audio/x-mpegurl');
    }

    private function asx(array $station): Response
    {
        $url  = $station['stream']['ogg'];
        $name = htmlspecialchars($station['title'], ENT_QUOTES | ENT_XML1);
        $genre = htmlspecialchars($station['genre'], ENT_QUOTES | ENT_XML1);

        $body = "<ASX VERSION=\"3.0\">\n"
            . "<TITLE>HBR1</TITLE>\n"
            . "<ENTRY>\n"
            . "  <REF HREF=\"{$url}\" />\n"
            . "  <TITLE>HBR1: {$name}</TITLE>\n"
            . "  <ABSTRACT>Futurenet Radio</ABSTRACT>\n"
            . "  <COPYRIGHT>www.HBR1.com</COPYRIGHT>\n"
            . "  <AUTHOR>Futurenet Radio</AUTHOR>\n"
            . "  <PARAM name=\"Album\" value=\"www.HBR1.com\"/>\n"
            . "  <PARAM name=\"Artist\" value=\"Futurenet Radio\"/>\n"
            . "  <PARAM name=\"Genre\" value=\"{$genre}\"/>\n"
            . "</ENTRY>\n"
            . "</ASX>\n";

        return Response::text($body)
            ->setHeader('Content-Type', 'video/x-ms-asf');
    }

    private function pls(array $station): Response
    {
        $body = "[playlist]\n"
            . "NumberOfEntries=1\n"
            . "File1={$station['stream']['ogg']}\n"
            . "Title1=HBR1 - {$station['title']}\n"
            . "Length1=-1\n"
            . "Version=2\n";

        return Response::text($body)
            ->setHeader('Content-Type', 'audio/x-scpls');
    }
}
