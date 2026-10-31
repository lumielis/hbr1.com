<?php

/**
 * Parse-check every PHP file in the tree with the running interpreter.
 *
 * Cheaper than a full test run and catches the class of breakage that took
 * the legacy site down (syntax that only a newer PHP rejects).
 *
 * Usage:
 *   php tools/lint.php            quiet; prints only the summary and failures
 *   php tools/lint.php -v         also list every directory as it is entered
 */

$root    = dirname(__DIR__);
$verbose = in_array('-v', $argv, true) || in_array('--verbose', $argv, true);

/**
 * Directories to leave unvisited, relative to the project root.
 *
 * `legacy/` holds the frozen Perl tree and `vendor/` third-party code; neither
 * is ours to fix. `var/`, `temp/` and `.git/` hold generated or internal files.
 */
$skipRelative = ['vendor', 'temp', 'var', 'legacy', '.git', '.vscode', 'node_modules'];

/**
 * Normalise a path for comparison: forward slashes, no trailing separator.
 *
 * Required on Windows, where `getPathname()` returns backslashes. Building the
 * skip list with forward slashes and comparing it raw meant no exclusion ever
 * matched, so the walk descended into `vendor/` and `.git/objects/`.
 */
$normalise = static fn(string $path): string => rtrim(str_replace('\\', '/', $path), '/');

$skipAbsolute = array_map(
    static fn(string $dir): string => $normalise($root . '/' . $dir),
    $skipRelative
);

$iterator = new RecursiveIteratorIterator(
    new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        static function (SplFileInfo $file) use ($skipAbsolute, $normalise, $verbose): bool {
            if (!$file->isDir()) {
                return true;
            }

            $path = $normalise($file->getPathname());

            foreach ($skipAbsolute as $skip) {
                // A bare prefix match would let `/var` swallow `/variable`, so
                // the separator is part of the test.
                if ($path === $skip || str_starts_with($path, $skip . '/')) {
                    return false;
                }
            }

            if ($verbose) {
                echo 'Checking directory: ', $file->getPathname(), PHP_EOL;
            }

            return true;
        }
    )
);

$failures = [];
$checked  = 0;

foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }

    $checked++;

    $output = [];
    $status = 0;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file->getPathname()) . ' 2>&1', $output, $status);

    if ($status !== 0) {
        $failures[] = $file->getPathname() . "\n    " . implode("\n    ", $output);
    }
}

printf("Checked %d PHP files\n", $checked);

if ($failures !== []) {
    fwrite(STDERR, "\nFAILED:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "  {$failure}\n");
    }
    exit(1);
}

echo "All PHP files parse OK\n";
