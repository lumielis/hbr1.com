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
    // Every Inventory row's "Play it" link, and the shape of any bookmark to
    // the old request page. `reqver` is the form's own POST target: a browser
    // that re-fetches it (back button, bookmark) must land on the form.
    '/cgi-bin/main.php?module=tracklist&cmd=request&station=Trance&songid=2',
    '/cgi-bin/main.php?module=tracklist&cmd=reqver&station=Trance&songid=2',
    // The request form's security image, as an `<img src>` on a cached page.
    '/cgi-bin/main.php?module=tracklist&show=secimg&time=1',
    '/cgi-bin/main.php?module=listen&station=Ambient',
    '/cgi-bin/main.php?module=topframe',
    '/cgi-bin/main.php?module=gallery',
    '/cgi-bin/main.php?module=../../htdocs/index',
    '/perl/apax.cgi?run=navigation',
    '/perl/apax.cgi?Part=Playlist',
    '/run/main.xp?action=party',
    '/cgi-bin/asx.cgi?trance',
    '/cgi-bin/m3u.cgi?tronic',
    '/perl/vimg.cgi?../images/djs/maya.jpg',
    '/cgi-bin/vimg.cgi?http://evil.example/x.jpg',
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

/*
 * Chrome parity between the two catalogue states.
 *
 * They are separate legacy templates with different frames - the chooser has
 * none, the station page has a SUBMENU band and a `th_*.gif` title cap - and
 * rendering the wrong one is invisible to a status-code check. This asserts the
 * markers instead.
 */
$chrome = [
    // path, must contain, must not contain
    ['/mixes', ['Choose a station'], ['submenu_bg.gif', 'table/th_left.gif']],
];

echo "\n";

foreach ($chrome as [$path, $must, $mustNot]) {
    try {
        $rendered = (string) file_get_contents(
            'http://' . ($_SERVER['HTTP_HOST'] ?? 'hbr1.evo') . $path,
            false,
            stream_context_create(['http' => ['timeout' => 5]])
        );
    } catch (\Throwable $e) {
        printf("%-52s skipped (server unreachable)\n", $path);
        continue;
    }

    $missing = array_filter($must, static fn($needle) => !str_contains($rendered, $needle));
    $extra   = array_filter($mustNot, static fn($needle) => str_contains($rendered, $needle));

    printf(
        "%-52s %s\n",
        $path,
        ($missing === [] && $extra === []) ? 'chrome ok' : 'chrome FAIL'
            . ($missing ? ' missing=' . implode(',', $missing) : '')
            . ($extra ? ' unexpected=' . implode(',', $extra) : '')
    );
}
