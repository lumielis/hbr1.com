<?php

/**
 * Template for the environment file - commit this, never a real one.
 *
 * `env.php` looks for credentials in this order:
 *
 *   1. $HBR1_ENV, if set (absolute path)
 *   2. /etc/hbr1/env.php        <- production
 *   3. ./env.local.php          <- a checkout, git-ignored
 *
 * Anything not overridden keeps the safe placeholder from `env.php`, so a
 * fresh clone boots without secrets and without this file at all.
 *
 * Copy to `env.local.php` for development:
 *
 *   cp deploy/env.example.php env.local.php
 *
 * and to `/etc/hbr1/env.php` on the host, with `install -m 0640 -o www-data`.
 * Both are outside the document root, so neither is reachable over HTTP even
 * if a web server is misconfigured.
 */

return [
    // Application
    'DEBUG_MODE'   => false,
    'APP_BASE_URL' => 'https://www.hbr1.com',

    // One credential covers the site data and the playout schemas; the legacy
    // hbr1 / hbr1-web pair is retired. See deploy/DB-CONNECTIONS.md.
    'DB_HOST'     => 'mariadb.lan.etwnet.com',
    'DB_PORT'     => 3306,
    'DB_DATABASE' => 'hbr1',
    'DB_CHARSET'  => 'utf8mb4',
    'DB_USER'     => 'hbr1-web',
    'DB_PASSWORD' => 'CHANGE-ME',

    // The Icecast admin endpoint. It carries HTTP basic credentials in the
    // URL, which is why it is here and not in cgi-bin/include/config.pab.pl.
    // Shape: scheme, credentials, @, host, port, /admin/stats.xml
    'ICECAST_STATS_URL'      => 'scheme://CREDENTIALS@radio.hbr1.com:19800/admin/stats.xml',
    'ICECAST_STATS_USER'     => 'USER',
    'ICECAST_STATS_PASSWORD' => 'PASSWORD',
];
