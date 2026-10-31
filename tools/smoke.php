<?php

require __DIR__ . '/../env.php';
require __DIR__ . '/../vendor/autoload.php';

App\Bootstrap::bootstrap();

$ctx = App\AppContext::instance();

$checks = [
    ['/', 'GET'],
    ['/listen', 'GET'],
    ['/listen/trance', 'GET'],
    ['/listen/Trance', 'GET'],
    ['/playlist/tronic', 'GET'],
    ['/tracklist', 'GET'],
    ['/tracklist/ambient', 'GET'],
    ['/topframe', 'GET'],
    ['/gallery', 'GET'],
    ['/gallery/1', 'GET'],
    ['/gallery/1/2', 'GET'],
    ['/gallery/pic/9', 'GET'],
    ['/stream/trance.m3u', 'GET'],
    ['/stream/trance.asx', 'GET'],
];

foreach ($checks as [$path, $method]) {
    $route = $ctx->router()->match($path, $method);
    printf("%-24s %s\n", $path, $route === null ? 'NO ROUTE' : 'ok');
}

echo "\n--- dispatcher smoke test ---\n";

foreach (['/', '/listen/trance', '/topframe', '/stream/trance.m3u', '/stream/trance.asx'] as $path) {
    $route = $ctx->router()->match($path, 'GET');
    $response = $ctx->dispatcher()->dispatch($route);
    printf(
        "%-22s status=%d type=%s bytes=%d\n",
        $path,
        $response->getStatus(),
        (string) $response->getHeader('Content-Type'),
        strlen($response->getBody())
    );
}
