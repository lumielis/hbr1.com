<?php

/**
 * Repair the double-encoded values in the site database, and optionally
 * convert the schema to utf8mb4.
 *
 *   php tools/charset-repair.php                    # report only, writes nothing
 *   php tools/charset-repair.php --repair           # apply the repair
 *   php tools/charset-repair.php --repair --convert # repair, then migrate
 *   php tools/charset-repair.php --schema=zz_hbr1   # rehearse on a copy
 *
 * Read `docs/README.md` (Character encoding) first. The short version:
 *
 *   --repair  rewrites `MÃ¼nchen` back to `München` in place. It needs no schema
 *             change, and the legacy site keeps working: a latin1 reader still
 *             receives exactly the cp1252 bytes it received before.
 *   --convert runs ALTER TABLE ... CONVERT TO utf8mb4, which is what makes
 *             non-cp1252 text possible at all. It must run *after* the repair,
 *             because afterwards the damage is no longer recognisable.
 *
 * Both steps are idempotent, and neither touches a value it cannot improve.
 *
 * Values whose correct text needs a character cp1252 cannot hold are reported
 * as deferred and left alone; --convert is what makes them fixable.
 */

require __DIR__ . '/../env.php';
require __DIR__ . '/../vendor/autoload.php';

use App\Support\CharsetRepair;

$options = getopt('', ['repair', 'convert', 'table:', 'schema:', 'help']);

if (isset($options['help'])) {
    echo <<<'TXT'
    Usage: php tools/charset-repair.php [--repair] [--convert]
                                        [--table=NAME] [--schema=NAME]

      (no flags)     report what would change, write nothing
      --repair       rewrite the double-encoded values
      --convert      ALTER the tables to utf8mb4 (run after --repair)
      --table=NAME   restrict to one table
      --schema=NAME  work on another database, e.g. a rehearsal copy

    Back up the database first. The repair is not reversible by inspection.

    TXT;

    exit(0);
}

$convert = isset($options['convert']);
$apply   = isset($options['repair']) || $convert;

$only = $options['table'] ?? null;
$only = $only === null ? [] : (array) $only;

$schema = $options['schema'] ?? (defined('DB_DATABASE') ? DB_DATABASE : 'hbr1');

$db = new PDO(DB_DSN, DB_USER, DB_PASSWORD, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// The connection has no default database, so a mistyped --schema fails here
// rather than half-way through the repair.
try {
    $db->query('SELECT 1 FROM `' . str_replace('`', '``', $schema) . '`.`access` LIMIT 1');
} catch (Throwable $e) {
    fwrite(STDERR, sprintf("Cannot read database `%s`: %s\n", $schema, $e->getMessage()));
    exit(1);
}

$repair = new CharsetRepair();

/** @return array<string, string> table => primary-key column */
function primaryKeys(PDO $db, string $schema): array
{
    $keys = [];
    $stmt = $db->prepare(
        'SELECT TABLE_NAME AS t, COLUMN_NAME AS c
           FROM information_schema.KEY_COLUMN_USAGE
          WHERE TABLE_SCHEMA = ? AND CONSTRAINT_NAME = \'PRIMARY\''
    );
    $stmt->execute([$schema]);

    foreach ($stmt as $row) {
        $keys[$row['t']] = $row['c'];
    }

    return $keys;
}

/** @return list<array{table:string,column:string,length_?:int}> */
function latin1Columns(PDO $db, string $schema, array $tables): array
{
    $stmt = $db->prepare(
        'SELECT TABLE_NAME AS t, COLUMN_NAME AS c, CHARACTER_MAXIMUM_LENGTH AS len
           FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = ? AND CHARACTER_SET_NAME = \'latin1\'
       ORDER BY TABLE_NAME, ORDINAL_POSITION'
    );
    $stmt->execute([$schema]);

    $columns = [];
    foreach ($stmt as $row) {
        if ($tables !== [] && !in_array($row['t'], $tables, true)) {
            continue;
        }
        $columns[] = ['table' => $row['t'], 'column' => $row['c'], 'length' => $row['len']];
    }

    return $columns;
}

function baseTables(PDO $db, string $schema): array
{
    $stmt = $db->prepare(
        'SELECT TABLE_NAME AS t FROM information_schema.TABLES
          WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = \'BASE TABLE\' ORDER BY TABLE_NAME'
    );
    $stmt->execute([$schema]);

    return array_column($stmt->fetchAll(), 't');
}

$keys    = primaryKeys($db, $schema);
$columns = latin1Columns($db, $schema, $only);

printf("database %s: %d latin1 column(s)\n\n", $schema, count($columns));

// ------------------------------------------------------------------- survey
$totals = ['clean' => 0, 'repair' => 0, 'deferred' => 0, 'lost' => 0, 'wide' => 0];
$plan   = [];
$tooWide = [];

foreach ($columns as $meta) {
    $table  = $meta['table'];
    $column = $meta['column'];
    $key    = $keys[$table] ?? null;

    $rows = $db->query(
        "SELECT CAST(`{$column}` AS BINARY) AS b" . ($key === null ? '' : ", `{$key}` AS k") . "
           FROM `{$schema}`.`{$table}` WHERE `{$column}` REGEXP '[^[:ascii:]]'"
    )->fetchAll();

    if ($rows === []) {
        continue;
    }

    foreach ($rows as $row) {
        $raw = (string) $row['b'];

        if (!$repair->isSuspect($raw)) {
            $totals['clean']++;
            continue;
        }

        if ($repair->isLost($raw)) {
            $totals['lost']++;
            continue;
        }

        $wanted = $repair->repair($raw);

        if (!$repair->fitsCp1252($wanted)) {
            $totals['deferred']++;
            continue;
        }

        // A varchar that would overflow needs the column widened first, which
        // only the converted schema can absorb.
        if (($meta['length'] ?? null) !== null
            && mb_strlen($wanted, 'UTF-8') > (int) $meta['length']) {
            $totals['wide']++;
            $tooWide[] = sprintf('%s.%s: %d > %d', $table, $column,
                mb_strlen($wanted, 'UTF-8'), $meta['length']);
            continue;
        }

        $totals['repair']++;

        if ($apply && isset($options['repair']) && $key !== null) {
            $plan[$table][] = ['key' => (string) $row['k'], 'column' => $column, 'value' => $wanted];
        }
    }
}

printf("  already correct (cp1252):  %d\n", $totals['clean']);
printf("  double-encoded, repair:    %d\n", $totals['repair']);
printf("  needs utf8mb4 first:       %d\n", $totals['deferred']);
printf("  already lost (U+FFFD):     %d\n", $totals['lost']);
if ($totals['wide'] > 0) {
    printf("  would overflow a varchar:  %d\n", $totals['wide']);
    foreach ($tooWide as $line) {
        printf("      %s\n", $line);
    }
}

// -------------------------------------------------------------------- apply
if (!isset($options['repair'])) {
    if ($convert) {
        echo "\n--convert implies --repair; nothing was written. Re-run with both.\n";
    } else {
        echo "\nReport only. Re-run with --repair to write, --convert to also migrate the schema.\n";
    }
    exit(0);
}

$updated = 0;

foreach ($plan as $table => $changes) {
    $key    = $keys[$table];
    $byColumn = [];

    foreach ($changes as $change) {
        $byColumn[$change['column']][] = $change;
    }

    foreach ($byColumn as $column => $rows) {
        $statement = $db->prepare("UPDATE `{$schema}`.`{$table}` SET `{$column}` = ? WHERE `{$key}` = ?");

        foreach ($rows as $row) {
            $statement->execute([$row['value'], $row['key']]);
            $updated++;
        }
    }
}

printf("\nrepaired %d value(s) in %d table(s)\n", $updated, count($plan));

// ------------------------------------------------------------------ convert
if ($convert) {
    $tables = $only === [] ? baseTables($db, $schema) : $only;

    echo "\nconverting to utf8mb4\n";

    $done = 0;
    foreach ($tables as $table) {
        try {
            $db->exec("ALTER TABLE `{$schema}`.`{$table}`
                       CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
            $done++;
        } catch (Throwable $e) {
            printf("  FAILED %s: %s\n", $table, $e->getMessage());
        }
    }

    printf("  %d/%d table(s) converted\n", $done, count($tables));
    echo "\nRegenerate the baseline now:  php tools/schema-dump.php > db/schema.sql\n";
}
