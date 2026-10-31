<?php
/**
 * Single entry point.
 *
 * Every request that is not a real file on disk lands here. The document
 * root is `public/`, so nothing above it (config, views, app code, the
 * legacy Perl tree) is reachable over HTTP.
 */

use App\AppContext;
use Azera\Http\Response;

require __DIR__ . '/../env.php';
require __DIR__ . '/../vendor/autoload.php';

date_default_timezone_set(APP_TIMEZONE);

\App\Bootstrap::bootstrap();

$ctx     = AppContext::instance();
$request = $ctx->request();

// Legacy CGI entry points are resolved before routing: most of them have no
// canonical route of their own, so they would otherwise fall through to 404.
$query   = $request->query();
$legacy  = \App\Http\LegacyUrlMapper::resolve(
    $request->path(),
    is_array($query) ? $query : []
);

if ($legacy !== null) {
    Response::redirect($legacy, 301)->send();
    return;
}

$route = $ctx->router()->match($request->path(), $request->method());

if ($route === null) {
    $response = DEBUG_MODE
        ? Response::html(
            '<h1>404 Not Found</h1><p>No route for '
            . htmlspecialchars($request->method() . ' ' . $request->path(), ENT_QUOTES)
            . '</p>',
            404
        )
        : Response::status(404);

    $response->send();
    return;
}

$ctx->dispatcher()->dispatch($route)->send();
