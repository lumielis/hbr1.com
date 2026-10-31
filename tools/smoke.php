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
    // W6 community pages.
    ['/navigation', 200],
    ['/guestbook', 200],
    ['/party', 200],
    ['/mixes', 200],
    ['/mixes/9999', 404],
    ['/chat', 200],
    // W7 webtv ticker. The left frame loads the frameset, which then walks the
    // meta-refresh ring of content pages.
    ['/webtv/ticker', 200],
    ['/webtv/ticker/content.htm', 200],
    ['/webtv/ticker/content1.htm', 200],
    ['/webtv/ticker/content999.htm', 404],
    // Legacy static pages still reached by the frameset.
    ['/ltop.htm', 200],
    ['/djs.htm', 200],
    ['/nope', 404],
];

$failed = 0;

foreach ($checks as [$path, $expected]) {
    $route = $ctx->router()->match($path, 'GET');

    // No route may still be a legacy static page (the frameset loads several
    // .htm files), so fall through to the static handler before calling it 404.
    if ($route === null) {
        $config = $ctx->Config;
        $static = new \App\Http\LegacyStatic($config['paths']['legacy'] ?? dirname(__DIR__) . '/htdocs');
        $hit    = $static->resolve($path);

        // No route and no file is the framework's 404 path.
        $status = $hit === null ? 404 : 200;
        $ok     = $status === $expected;

        printf(
            "%-34s %s %d%s\n",
            $path,
            $ok ? 'ok  ' : 'FAIL',
            $status,
            $ok ? ' (static)' : ' (expected ' . $expected . ')'
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
