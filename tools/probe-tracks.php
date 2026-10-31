<?php

/**
 * Read-only probe of the music databases through TrackService.
 */

require __DIR__ . '/../env.php';
require __DIR__ . '/../vendor/autoload.php';

App\Bootstrap::bootstrap();

$ctx      = App\AppContext::instance();
$stations = $ctx->get(App\Services\StationService::class);
$tracks   = $ctx->get(App\Services\TrackService::class);

foreach ($stations->all() as $station) {
    printf(
        "\n=== %s (%s) — %s ===\n",
        $station['title'],
        $station['id'],
        $tracks->usesRadioDj($station) ? 'RadioDJ' : 'SAM'
    );

    try {
        $now = $tracks->current($station);
        printf("  now: %s\n", $now === null ? '(none)' : "{$now['artist']} - {$now['title']}");

        $queue = $tracks->queue($station);
        printf("  queue: %d entries\n", count($queue));

        foreach (array_slice($queue, 0, 3) as $item) {
            printf("    %s%s - %s\n", $item['requested'] ? '* ' : '', $item['artist'], $item['title']);
        }
    } catch (Throwable $e) {
        printf("  ERROR: %s\n", $e->getMessage());
    }
}
