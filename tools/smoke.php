<?php

/**
 * End-to-end smoke test: routes every check through the real dispatcher and
 * asserts the resulting status, so a 404 cannot pass as "ok".
 *
 * Gallery ids are resolved from the database first - the old fixture ids
 * (category 1, gallery 2, picture 9) do not exist, which made this script
 * report success on 404 responses.
 */

require __DIR__ . '/../env.php';
require __DIR__ . '/../vendor/autoload.php';

App\Bootstrap::bootstrap();

$ctx = App\AppContext::instance();

$gallery = $ctx->get(App\Services\GalleryService::class);
$category = $gallery->categories()[0] ?? null;
$categoryId = $category['id'] ?? 0;
$galleries = $categoryId !== 0 ? $gallery->galleriesFor($categoryId) : [];
$galleryId = $galleries[0]['id'] ?? 0;
$pictures = $galleryId !== 0 ? $gallery->picturesFor($galleryId) : [];
$pictureId = $pictures[0]['id'] ?? 0;

/** @var array<int, array{0: string, 1: int}> $checks path => expected status */
$checks = [
    ['/', 200],
    ['/listen', 200],
    ['/listen/trance', 200],
    ['/listen/Trance', 200],
    ['/listen/nope', 404],
    ['/playlist/tronic', 200],
    ['/tracklist', 200],
    ['/tracklist/ambient', 200],
    ['/topframe', 200],
    ['/gallery', 200],
    ["/gallery/{$categoryId}", 200],
    ["/gallery/{$categoryId}/{$galleryId}", 200],
    ["/gallery/pic/{$pictureId}", 200],
    ['/gallery/9999', 404],
    ['/stream/trance.m3u', 200],
    ['/stream/trance.asx', 200],
    ['/stream/trance.pls', 200],
    ['/nope', 404],
];

$failed = 0;

foreach ($checks as [$path, $expected]) {
    $route = $ctx->router()->match($path, 'GET');

    // No route is the framework's 404 path, not a bug in the check.
    if ($route === null) {
        $ok = $expected === 404;
        printf(
            "%-34s %s %d%s\n",
            $path,
            $ok ? 'ok  ' : 'FAIL',
            404,
            $ok ? ' (unrouted)' : ' (unrouted, expected ' . $expected . ')'
        );
        $failed += $ok ? 0 : 1;
        continue;
    }

    $response = $ctx->dispatcher()->dispatch($route);
    $status = $response->getStatus();
    $ok = $status === $expected;

    printf(
        "%-34s %s %d%s\n",
        $path,
        $ok ? 'ok  ' : 'FAIL',
        $status,
        $ok ? '' : " (expected {$expected})"
    );

    $failed += $ok ? 0 : 1;
}

printf("\n%d checks, %d failed\n", count($checks), $failed);

exit($failed === 0 ? 0 : 1);
