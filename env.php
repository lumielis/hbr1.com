<?php

/**
 * Environment / secrets.
 *
 * Defaults are safe placeholders so a fresh checkout boots without secrets.
 * Real values are supplied by a file that is never committed:
 *
 *   - development: env.local.php  (git-ignored, next to this file)
 *   - production:  /etc/hbr1/env.php  (outside the document root)
 *
 * Override the location with the HBR1_ENV environment variable.
 *
 * The environment file returns an array of key => value pairs; anything it
 * sets replaces the placeholder below. This runs once per process, so
 * repeated includes are harmless.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

/** @var array<string, mixed> $env */
$env = [
    'DEBUG_MODE'    => true,
    'APP_NAME'      => 'HBR1',
    'APP_BASE_URL'  => 'http://hbr1.evo',
    'APP_TIMEZONE'  => 'Europe/Berlin',

    // A freshly cloned checkout boots without secrets; real credentials come
    // from the environment file. One credential covers both the site data and
    // the playout schemas (see deploy/DB-CONNECTIONS.md).
    'DB_HOST'           => 'localhost',
    'DB_PORT'           => 3306,
    'DB_DATABASE'       => 'hbr1',
    'DB_CHARSET'        => 'utf8mb4',
    'DB_USER'           => 'hbr1-web',
    'DB_PASSWORD'       => '',

    'ICECAST_STATS_URL'      => 'http://radio.hbr1.com:19800/admin/stats.xml',
    'ICECAST_STATS_USER'     => '',
    'ICECAST_STATS_PASSWORD' => '',
];

$hbr1EnvFile = getenv('HBR1_ENV') ?: '/etc/hbr1/env.php';

if (!is_file($hbr1EnvFile) && is_file(__DIR__ . '/env.local.php')) {
    $hbr1EnvFile = __DIR__ . '/env.local.php';
}

if (is_file($hbr1EnvFile)) {
    $override = require $hbr1EnvFile;

    if (is_array($override)) {
        $env = [...$env, ...$override];
    }
}

unset($hbr1EnvFile, $override);

// Define constants once. array_key_exists guards against a second include.
foreach ($env as $key => $value) {
    if (!defined($key)) {
        define($key, $value);
    }
}

unset($env, $key, $value);

define('BASE_DIR', __DIR__);
define('CONFIG_PATH', __DIR__ . '/config/config.php');
define('CLARITY_ENGINE_PATH', __DIR__ . '/../../Sailantis/clarity-engine');
define('AZERA_FRAMEWORK_PATH', __DIR__ . '/../../Sailantis/azera-framework');

// Compose the DSN from its parts so hosts and credentials can be rotated
// independently. DB_DSN may also be set outright in the environment file.
if (!defined('DB_DSN')) {
    define('DB_DSN', sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        DB_HOST,
        DB_PORT,
        DB_DATABASE,
        DB_CHARSET
    ));
}
