<?php

/**
 * Probe SongService pagination against all three stations. Read-only.
 */

require __DIR__ . '/../env.php';
require __DIR__ . '/../vendor/autoload.php';

App\Bootstrap::bootstrap();

$ctx      = App\AppContext::instance();
$stations = $ctx->get(App\Services\StationService::class);
$songs    = new App\Services\SongService($stations);

foreach ($stations->all() as $station) {
    echo "\n=== {$station['title']} ===\n";

    try {
        $page = $songs->page($station, 0);
        printf("  total: %d, page rows: %d\n", $page['total'], count($page['rows']));

        foreach (array_slice($page['rows'], 0, 3) as $row) {
            printf("    #%-5d %-28s %-34s %ds\n", $row['id'], $row['artist'], $row['title'], $row['duration']);
        }

        $mid = $songs->page($station, 40);
        printf("  offset 40: %d rows, first id %s\n", count($mid['rows']), $mid['rows'][0]['id'] ?? '-');

        if ($page['rows'] !== []) {
            $one = $songs->find($station, $page['rows'][0]['id']);
            printf("  find(%d): %s\n", $page['rows'][0]['id'], $one === null ? 'null' : $one['title']);
        }

        printf("  find(99999999): %s\n", $songs->find($station, 99999999) === null ? 'null (correct)' : 'FOUND (bug)');
        printf("  randomOffset: %d\n", $songs->randomOffset($station));
    } catch (Throwable $e) {
        printf("  ERROR: %s\n", $e->getMessage());
    }
}
