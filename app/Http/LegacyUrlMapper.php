<?php

namespace App\Http;

/**
 * Legacy URL translation.
 *
 * Years of pages, bookmarks and search results point at the old CGI entry
 * points. This maps each known legacy request onto the canonical route so the
 * Perl tree can be retired without breaking inbound links.
 *
 * Pure mapping - no response handling - so it can be used before routing (in
 * the front controller) and inside the middleware pipeline alike.
 */
final class LegacyUrlMapper
{
    /**
     * Legacy module names that map straight onto a canonical route.
     *
     * `tracklist` is the odd one: in the legacy tree it was the *station song
     * catalogue* (`main.php?module=tracklist`, the "Inventory" button), while
     * the button labelled "Tracklist" pointed at `mixes.cgi`. HBR1 plays mixes
     * only - the "songs" table holds the DJ sets - so both are now the same
     * page, and `/mixes` is the canonical URL for it.
     */
    private const MODULE_ROUTES = [
        'tracklist' => '/mixes',
        'listen'    => '/listen',
        'playlist'  => '/playlist',
        'topframe'  => '/topframe',
        'gallery'   => '/gallery',
        'muses'     => '/muses',
    ];

    /** Legacy `?run=` / `?action=` / `?Part=` values. */
    private const RUN_ROUTES = [
        'navigation' => '/navigation',
        'gbms'       => '/guestbook',
        'guestbook'  => '/guestbook',
        'tracklist'  => '/mixes',
        'jorbis'     => '/listen',
        'iphone'     => '/iphone',
        'playlist'   => '/playlist',
        'party'      => '/party',
        'mixes'      => '/mixes',
        'expired'    => '/',
        'ticker'     => '/admin/ticker',
        'news'       => '/admin/news',
        'setup'      => '/admin',
        'userman'    => '/admin/members',
        'crypt'      => '/admin/crypt',
    ];

    /** Station-scoped routes that accept an optional `?station=` suffix. */
    private const STATION_SCOPED = ['/mixes', '/listen', '/playlist', '/muses'];

    /**
     * Resolve a legacy request to its canonical path.
     *
     * Returns null when the path is not a legacy entry point, so the caller
     * can continue with normal routing.
     */
    public static function resolve(string $path, array $query): ?string
    {
        $path = rtrim($path, '/');

        if ($path === '') {
            $path = '/';
        }

        return match ($path) {
            '/cgi-bin/main.php', '/cgi-bin/main.cgi', '/cgi-bin/main.xp', '/run/main.xp'
                => self::fromModuleOrAction($query),
            '/cgi-bin/apax.cgi', '/perl/apax.cgi', '/cgi-pab/apax.cgi',
            '/cgi-bin/admin_v2.cgi', '/perl/admin_v2.cgi'
                => self::fromRun($query),
            '/cgi-bin/asx.cgi' => self::fromStream($query, 'asx'),
            '/cgi-bin/m3u.cgi' => self::fromStream($query, 'm3u'),
            '/cgi-bin/listen.cgi' => '/listen',
            '/cgi-bin/mixes.cgi', '/run/mixes.cgi', '/perl/mixes.cgi' => self::fromMixes($query),
            '/cgi-bin/party.cgi' => '/party',
            '/cgi-bin/chat.cgi', '/perl/chat.cgi' => '/chat',
            '/cgi-bin/gallery.cgi', '/perl/gallery.cgi' => '/gallery',
            '/cgi-bin/vimg.cgi', '/perl/vimg.cgi', '/cgi-pab/vimg.cgi' => self::fromViewer(),
            '/cgi-bin/admin.cgi' => '/admin',
            default => null,
        };
    }

    /**
     * `mixes.cgi` used `?action=` for its three levels:
     *   (none)                   -> categories
     *   action=view&cat_id=N     -> mixes in a category
     *   action=playlist&mix_id=N -> one mix
     *
     * The category and single-mix levels were retired with the mix editor;
     * everything lands on the catalogue now.
     */
    private static function fromMixes(array $query): string
    {
        return '/mixes';
    }

    private static function fromModuleOrAction(array $query): string
    {
        $module = $query['module'] ?? null;

        if (is_string($module) && $module !== '') {
            // Only a bare module name is accepted. The legacy handler
            // concatenated this into a path, which allowed traversal.
            if (preg_match('/^[a-z0-9_]+$/i', $module) !== 1) {
                return '/';
            }

            $base = self::MODULE_ROUTES[strtolower($module)] ?? null;

            if ($base === null) {
                return '/';
            }

            return self::withStation($base, $query);
        }

        $action = $query['action'] ?? null;

        if (is_string($action) && $action !== '') {
            return self::RUN_ROUTES[strtolower($action)] ?? '/';
        }

        return '/';
    }

    private static function fromRun(array $query): string
    {
        $run = $query['run'] ?? $query['Part'] ?? $query['part'] ?? null;

        if (!is_string($run) || $run === '') {
            return '/';
        }

        return self::RUN_ROUTES[strtolower($run)] ?? '/';
    }

    private static function withStation(string $base, array $query): string
    {
        if (!in_array($base, self::STATION_SCOPED, true)) {
            return $base;
        }

        $station = $query['station'] ?? null;

        if (is_string($station) && preg_match('/^[a-z]+$/i', $station) === 1) {
            return $base . '/' . strtolower($station);
        }

        return $base;
    }

    /**
     * The legacy playlist generators took the station as a bare query string
     * with no parameter name (`/cgi-bin/asx.cgi?trance`).
     */
    private static function fromStream(array $query, string $format): string
    {
        $raw = array_key_first($query);

        if ($raw === null) {
            $raw = trim(explode('&', (string) ($_SERVER['QUERY_STRING'] ?? ''))[0]);
        }

        if (is_string($raw) && preg_match('/^[a-z]+$/i', $raw) === 1) {
            return '/stream/' . strtolower($raw) . '.' . $format;
        }

        return '/';
    }

    /**
     * `vimg.cgi?../images/djs/maya.jpg` - the image path is the whole query
     * string, unencoded and unnamed, which is why the parameter cannot be a
     * normal slug. `./` and `../` prefixes simply resolve from the root.
     */
    private static function fromViewer(): string
    {
        $raw = (string) ($_SERVER['QUERY_STRING'] ?? '');

        if ($raw === '') {
            return '/';
        }

        $path = preg_replace('#^\.{1,2}/#', '', rawurldecode(trim($raw))) ?? '';

        // A plain relative path only: no scheme, no host, no traversal, no
        // dotfile, and it has to look like a file.
        if ($path === ''
            || preg_match('#^[a-z][a-z0-9+.-]*:#i', $path) === 1
            || str_contains($path, '//')
            || preg_match('#(^|/)\.\.?(/|$)#', $path) === 1
            || preg_match('#(^|/)\.#', $path) === 1
            || !str_contains($path, '.')
        ) {
            return '/';
        }

        return '/viewer/' . ltrim($path, '/');
    }
}
