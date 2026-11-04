<?php

namespace App\Services;

/**
 * Station catalog.
 *
 * Wraps the configured station list and is the only place that knows how a
 * station is looked up. Legacy code keyed stations by a numeric index and by
 * the `Id` string; the public URL uses a lowercase slug.
 */
class StationService
{
    /** @var array<string, array> keyed by lowercase slug */
    private array $bySlug = [];

    /** @var array<string, array> keyed by the canonical Id, e.g. "Trance" */
    private array $byId = [];

    public function __construct(array $stations)
    {
        foreach ($stations as $station) {
            $this->bySlug[strtolower($station['slug'])] = $station;
            $this->byId[$station['id']] = $station;
        }
    }

    /** @return array<int, array> */
    public function all(): array
    {
        return array_values($this->bySlug);
    }

    /**
     * Resolve a station by its URL slug (case-insensitive) or its canonical Id.
     */
    public function find(string $identifier): ?array
    {
        $key = strtolower($identifier);

        return $this->bySlug[$key] ?? $this->byId[$identifier] ?? null;
    }

    /**
     * The station to use when a request names none.
     *
     * The legacy `include/muses.php` looped over `$Stations` looking for a
     * match and never reset the variable, so a request without `?station=`
     * silently used the *last* station in the list. That is the Ambient
     * stream, and production still serves "HBR1 - Dream Factory" there.
     */
    public function fallback(): array
    {
        $last = end($this->bySlug);

        if ($last === false) {
            throw new \RuntimeException('No stations are configured');
        }

        return $last;
    }

    public function findOrFail(string $identifier): array
    {
        $station = $this->find($identifier);

        if ($station === null) {
            throw new \RuntimeException("Unknown station: {$identifier}");
        }

        return $station;
    }

    public function slug(array $station): string
    {
        return $station['slug'];
    }

    /**
     * The OGG stream URL to hand to a player running inside a browser.
     *
     * Browsers will not fetch an `http://` media resource from an `https://`
     * page: the request is either blocked as mixed content or rewritten to
     * `https://` on the *same port*, which fails because `radio.hbr1.com:19800`
     * speaks plain HTTP only (Icecast has no TLS listener there - the TLS
     * front end is a separate nginx proxy on port 443). Either way the player
     * stays silent while the page itself looks fine.
     *
     * `stream.secure` holds the mounts reached through that proxy, so the
     * player works under both schemes; an HTTPS resource on an HTTP page is
     * allowed. Stations without it (the fixtures in tests) fall back to the
     * plain mount, which is also what desktop players keep using through the
     * `.m3u`/`.pls` files - those are opened by a native application, not by
     * the browser, so they must stay `http://` for the older clients.
     */
    public function browserStreamUrl(array $station): ?string
    {
        return $station['stream']['secure']['ogg']
            ?? $station['stream']['ogg']
            ?? null;
    }
}
