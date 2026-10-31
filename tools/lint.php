<?php

/**
 * Parse-check every PHP file in the tree with the running interpreter.
 *
 * Cheaper than a full test run and catches the class of breakage that took
 * the legacy site down (syntax that only a newer PHP rejects).
 */

$root = dirname(__DIR__);

$skip = [
    $root . '/vendor',
    $root . '/temp',
    $root . '/var',
    $root . '/legacy',
    $root . '/.git',
];

$iterator = new RecursiveIteratorIterator(
    new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        function (SplFileInfo $file) use ($skip) {
            if (!$file->isDir()) {
                return true;
            }

            foreach ($skip as $dir) {
                if (str_starts_with($file->getPathname(), $dir)) {
                    return false;
                }
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
