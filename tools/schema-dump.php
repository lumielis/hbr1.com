<?php

/**
 * Dump the live schema of the site database to db/schema.sql.
 *
 * W2 in the migration plan. Run against a READ-ONLY replica, never against the
 * primary: introspection is harmless, but this is the same connection string
 * that later write paths use, so keep the habit.
 *
 *   php tools/schema-dump.php > db/schema.sql
 *   php tools/schema-dump.php --tables
 */

require __DIR__ . '/../env.php';
require __DIR__ . '/../vendor/autoload.php';

$tablesOnly = in_array('--tables', $argv, true);

$pdo = new PDO(DB_DSN, DB_USER, DB_PASSWORD, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$dbName = defined('DB_DATABASE') ? DB_DATABASE : 'hbr1';

$tables = $pdo->query(
    'SELECT TABLE_NAME FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = ' . $pdo->quote($dbName) . '
        AND TABLE_TYPE = \'BASE TABLE\'
   ORDER BY TABLE_NAME'
)->fetchAll(PDO::FETCH_COLUMN);

if ($tablesOnly) {
    foreach ($tables as $table) {
        echo $table, PHP_EOL;
    }
    exit(0);
}

printf("-- HBR1 schema baseline\n-- database: %s\n-- generated: %s\n\n", $dbName, date('c'));

foreach ($tables as $table) {
    $create = $pdo->query('SHOW CREATE TABLE `' . str_replace('`', '``', $table) . '`')
        ->fetch(PDO::FETCH_ASSOC);

    echo '-- ', $table, PHP_EOL;
    echo $create['Create Table'] ?? '', ';', PHP_EOL, PHP_EOL;
}

fwrite(STDERR, sprintf("Wrote %d tables\n", count($tables)));
