<?php

/**
 * CLI entry point.
 *
 * Usage:
 *   php console.php <task>[:<action>] [args] [--option=value]
 *   php console.php help [task]
 *   php console.php model:sync-all --apply
 */

use Azera\Cli\Console;

require __DIR__ . '/env.php';
require __DIR__ . '/vendor/autoload.php';

date_default_timezone_set(APP_TIMEZONE);

\App\Bootstrap::bootstrap();

set_time_limit(0);
ini_set('memory_limit', '-1');

$console = new Console();

try {
    $console->process(array_slice($argv, 1));
    exit(0);
} catch (Throwable $exception) {
    $console->error($exception->getMessage() . ' in ' . $exception->getFile() . ':' . $exception->getLine());
    if (DEBUG_MODE) {
        $console->stderrln($exception->getTraceAsString());
    }
    exit(1);
}
