<?php

/**
 * Rehearse the charset repair without touching live data.
 *
 *   php tools/charset-rehearse.php
 *
 * Copies only the tables that actually hold affected values to a `zz_` twin
 * inside the same schema, applies the repair to the twins, reports exactly what
 * changed, then drops the twins. The live tables are only ever read.
 *
 * Done this way rather than with a scratch database because the application
 * credential deliberately has no CREATE DATABASE grant, which is worth keeping.
 */

require __DIR__ . '/../env.php';
require __DIR__ . '/../vendor/autoload.php';

use App\Support\CharsetRepair;

$schema = 'hbr1';
$prefix = 'zz_';

$db = new PDO(DB_DSN, DB_USER, DB_PASSWORD, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$repair = new CharsetRepair();

// --------------------------------------------------------------- inventory
$keys = [];
$stmt = $db->prepare("SELECT TABLE_NAME AS t, COLUMN_NAME AS c
                        FROM information_schema.KEY_COLUMN_USAGE
                       WHERE TABLE_SCHEMA = ? AND CONSTRAINT_NAME = 'PRIMARY'");
$stmt->execute([$schema]);
foreach ($stmt as $row) {
    $keys[$row['t']] = $row['c'];
}

$stmt = $db->prepare("SELECT TABLE_NAME AS t, COLUMN_NAME AS c, CHARACTER_MAXIMUM_LENGTH AS len
                        FROM information_schema.COLUMNS
                       WHERE TABLE_SCHEMA = ? AND CHARACTER_SET_NAME = 'latin1'
                    ORDER BY TABLE_NAME, ORDINAL_POSITION");
$stmt->execute([$schema]);

$columns = [];
foreach ($stmt as $row) {
    $columns[$row['t']][] = ['column' => $row['c'], 'length' => $row['len']];
}

// Capture the work from the live tables first; nothing is written to them.
$work  = [];
$stats = ['clean' => 0, 'repair' => 0, 'deferred' => 0, 'lost' => 0];

foreach ($columns as $table => $meta) {
    if (!isset($keys[$table])) {
        continue;
    }

    foreach ($meta as $entry) {
        $column = $entry['column'];

        $rows = $db->query("SELECT `{$keys[$table]}` AS k, CAST(`{$column}` AS BINARY) AS b
                              FROM `{$schema}`.`{$table}` WHERE `{$column}` REGEXP '[^[:ascii:]]'")->fetchAll();

        foreach ($rows as $row) {
            $raw = (string) $row['b'];

            if (!$repair->isSuspect($raw)) {
                $stats['clean']++;
                continue;
            }

            if ($repair->isLost($raw)) {
                $stats['lost']++;
                continue;
            }

            $wanted = $repair->repair($raw);

            if (!$repair->fitsCp1252($wanted) || mb_strlen($wanted, 'UTF-8') > (int) $entry['length']) {
                $stats['deferred']++;
                continue;
            }

            $stats['repair']++;
            $work[] = [
                'table'  => $table,
                'column' => $column,
                'key'    => (string) $row['k'],
                'before' => $raw,
                'after'  => $wanted,
            ];
        }
    }
}

printf("rehearsal against a copy of %s\n\n", $schema);
printf("  already correct (cp1252):  %d\n", $stats['clean']);
printf("  would be repaired:         %d\n", $stats['repair']);
printf("  deferred (needs utf8mb4):  %d\n", $stats['deferred']);
printf("  already lost (U+FFFD):     %d\n\n", $stats['lost']);

// ------------------------------------------------------------------ clone
$clones = [];
foreach (array_values(array_unique(array_column($work, 'table'))) as $table) {
    $clone = $prefix . $table;
    $db->exec("DROP TABLE IF EXISTS `{$schema}`.`{$clone}`");
    $db->exec("CREATE TABLE `{$schema}`.`{$clone}` LIKE `{$schema}`.`{$table}`");
    $db->exec("INSERT INTO `{$schema}`.`{$clone}` SELECT * FROM `{$schema}`.`{$table}`");
    $clones[] = $clone;
}

printf("cloned %d table(s)\n", count($clones));

// ------------------------------------------------------------------ repair
$applied = 0;
foreach ($work as $item) {
    $clone  = $prefix . $item['table'];
    $update = $db->prepare("UPDATE `{$schema}`.`{$clone}` SET `{$item['column']}` = ?
                             WHERE `{$keys[$item['table']]}` = ?");
    $update->execute([$item['after'], $item['key']]);
    $applied++;
}
printf("repaired %d value(s) in the copies\n\n", $applied);

// ------------------------------------------------------------------ verify
$exact = 0;
$problems = [];

foreach ($work as $item) {
    $clone  = $prefix . $item['table'];
    $select = $db->prepare("SELECT `{$item['column']}` AS v FROM `{$schema}`.`{$clone}`
                             WHERE `{$keys[$item['table']]}` = ?");
    $select->execute([$item['key']]);
    $actual = (string) $select->fetch()['v'];

    if ($actual === $item['after']) {
        $exact++;
    } else {
        $problems[] = sprintf('%s.%s #%s', $item['table'], $item['column'], $item['key']);
    }
}

printf("verified: %d/%d exact\n", $exact, count($work));
foreach (array_slice($problems, 0, 5) as $problem) {
    printf("  MISMATCH %s\n", $problem);
}

// ------------------------------------------------------------------ sample
// Read the repaired rows back through THIS connection - the same utf8mb4 one
// the application uses - so this is literally the text a template would render,
// not a re-derivation of it.
echo "\nwhat the page will show, by column:\n\n";
$byColumn = [];
foreach ($work as $item) {
    $byColumn[$item['table'] . '.' . $item['column']][] = $item;
}

foreach ($byColumn as $label => $items) {
    printf("  %s  (%d)\n", $label, count($items));

    foreach (array_slice($items, 0, 2) as $item) {
        $column = $item['column'];
        $key    = $keys[$item['table']];

        $live = $db->prepare("SELECT `{$column}` AS v FROM `{$schema}`.`{$item['table']}` WHERE `{$key}` = ?");
        $live->execute([$item['key']]);

        $fixed = $db->prepare("SELECT `{$column}` AS v FROM `{$schema}`.`{$prefix}{$item['table']}` WHERE `{$key}` = ?");
        $fixed->execute([$item['key']]);

        printf("      today  %s\n", mb_strimwidth((string) $live->fetch()['v'], 0, 66, '...'));
        printf("      after  %s\n", mb_strimwidth((string) $fixed->fetch()['v'], 0, 66, '...'));
    }
}

// ---------------------------------------------------------------- cleanup
foreach ($clones as $clone) {
    $db->exec("DROP TABLE IF EXISTS `{$schema}`.`{$clone}`");
}

printf("\ndropped %d clone(s); live tables untouched\n", count($clones));

$left = $db->query('SELECT COUNT(*) AS n FROM information_schema.TABLES
                     WHERE TABLE_SCHEMA = ' . $db->quote($schema) . '
                       AND TABLE_NAME LIKE ' . $db->quote($prefix . '%'))->fetch();
printf("zz_ tables remaining: %d\n", (int) $left['n']);
