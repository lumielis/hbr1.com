<?php

/**
 * Verify the legacy URL -> canonical URL redirects.
 *
 * The middleware reads the path and query from the framework Request, which
 * reads $_SERVER. So rather than dispatching, exercise the mapping through a
 * real HTTP request against a local PHP server (see tools/serve.sh), or run
 * this script which drives the same logic with a synthetic environment.
 */

require __DIR__ . '/../env.php';
require __DIR__ . '/../vendor/autoload.php';

App\Bootstrap::bootstrap();

$ctx = App\AppContext::instance();

$cases = [
    '/cgi-bin/main.php?module=tracklist',
    '/cgi-bin/main.php?module=tracklist&station=Trance',
    '/cgi-bin/main.php?module=listen&station=Ambient',
    '/cgi-bin/main.php?module=topframe',
    '/cgi-bin/main.php?module=gallery',
    '/cgi-bin/main.php?module=../../htdocs/index',
    '/perl/apax.cgi?run=navigation',
    '/perl/apax.cgi?Part=Playlist',
    '/run/main.xp?action=party',
    '/cgi-bin/asx.cgi?trance',
    '/cgi-bin/m3u.cgi?tronic',
    '/run/mixes.cgi',
    '/cgi-bin/admin.cgi',
];

$middleware = new App\Middleware\LegacyRedirectMiddleware();

foreach ($cases as $case) {
    [$path, $query] = array_pad(explode('?', $case, 2), 2, '');
    parse_str($query, $parsed);

    $_SERVER['REQUEST_URI']    = $case;
    $_SERVER['QUERY_STRING']   = $query;
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET                      = $parsed;

    $request = new Azera\Http\Request($_SERVER, $parsed);

    // The middleware reads the request from the context, so build a minimal
    // stub context that returns our prepared Request.
    $stub = new class ($request) extends App\AppContext {
        public function __construct(private Azera\Http\Request $r) {}
        public function request(): Azera\Http\Request
        {
            return $this->r;
        }
    };

    $response = $middleware->process($stub, static fn() => null);

    printf(
        "%-52s -> %-34s %s\n",
        $case,
        $response === null ? '(passthrough)' : (string) $response->getHeader('Location'),
        $response === null ? '' : $response->getStatus()
    );
}
