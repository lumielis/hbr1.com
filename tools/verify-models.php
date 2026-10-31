<?php

/**
 * Verify the generated models against the live database.
 *
 * W2 acceptance check: every model must resolve to a real table, and the
 * read path must work through the ORM. Read-only.
 */

require __DIR__ . '/../env.php';
require __DIR__ . '/../vendor/autoload.php';

App\Bootstrap::bootstrap();

$ctx = App\AppContext::instance();

$models = [];
foreach (glob(__DIR__ . '/../app/Models/*.php') as $file) {
    $class = 'App\\Models\\' . basename($file, '.php');
    if (class_exists($class)) {
        $models[] = $class;
    }
}

printf("%d model classes\n\n", count($models));

$ok     = 0;
$failed = [];

foreach ($models as $class) {
    try {
        $count = $class::count();
        $ok++;
        printf("  %-28s %6d rows\n", substr($class, 12), $count);
    } catch (Throwable $e) {
        $failed[] = substr($class, 12) . ': ' . $e->getMessage();
    }
}

printf("\n%d ok, %d failed\n", $ok, count($failed));

foreach ($failed as $failure) {
    fwrite(STDERR, "  FAIL {$failure}\n");
}

// The music databases are not model-backed by design (plan §5.2).
echo "\n--- music mapping (no model classes) ---\n";

$mapping = App\Services\MusicDatabaseMapping::all();

foreach (['RadioDjHistory_Trance', 'SamHistory_Ambient', 'RadioDjQueue_Tronic'] as $logical) {
    $entry = $mapping->get($logical);
    printf("  %-24s -> %s.%s\n", $logical, $entry['schema'], $entry['source']);
}
