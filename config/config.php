<?php

/**
 * Application configuration.
 *
 * Pure data - no logic. Everything that needs to reach the framework is
 * assembled here and read in App\Bootstrap. Credentials come from the
 * constants declared in env.php, never from literals in this file.
 *
 * The station list is the single source of truth for the three channels.
 * It is a straight port of the `$Stations` array from the legacy
 * cgi-bin/include/config.php, which the PHP modules already used.
 */

$stations = [
    [
        'id'    => 'Trance',
        'slug'  => 'trance',
        'title' => 'I.D.M. Tranceponder',
        'genre' => 'Psychedelic, Progressive Trance, Full-On',
        'stream' => [
            'ogg' => 'http://radio.hbr1.com:19800/trance.ogg',
            'aac' => 'http://radio.hbr1.com:19800/trance.aac',
            'secure' => [
                'ogg' => 'https://radio.hbr1.com/trance.ogg',
                'aac' => 'https://radio.hbr1.com/trance.aac',
            ],
        ],
        'muses_url'  => 'http://radio.hbr1.com:19800/trance',
        'iphone_url' => 'http://radio.hbr1.com:19800/trance.aac.m3u',
        'radiodj_db' => 'radiodj_trance',
        'radiodj_request_url' => 'http://192.168.9.134:8080/opt?auth=plain&command=LoadTrackToTop&arg={id}',
        'playlists' => [
            ['title' => 'OGG Vorbis', 'file' => '/playlist/trance.ogg.m3u'],
            ['title' => 'OGG Vorbis (low)', 'file' => '/playlist/trance-low.ogg.m3u'],
            ['title' => 'HE-AAC v2', 'file' => '/playlist/trance.aac.m3u'],
            ['title' => 'HE-AAC v2 (low)', 'file' => '/playlist/trance-low.aac.m3u'],
        ],
    ],
    [
        'id'    => 'Tronic',
        'slug'  => 'tronic',
        'title' => 'Tronic Lounge',
        'genre' => 'Tech House, Progressive House, Electro, Minimal',
        'stream' => [
            'ogg' => 'http://radio.hbr1.com:19800/tronic.ogg',
            'aac' => 'http://radio.hbr1.com:19800/tronic.aac',
            'secure' => [
                'ogg' => 'https://radio.hbr1.com/tronic.ogg',
                'aac' => 'https://radio.hbr1.com/tronic.aac',
            ],
        ],
        'muses_url'  => 'http://radio.hbr1.com:19800/tronic',
        'iphone_url' => 'http://radio.hbr1.com:19800/tronic.aac.m3u',
        'radiodj_db' => 'radiodj_tronic',
        'radiodj_request_url' => 'http://192.168.9.135:8080/opt?auth=plain&command=LoadTrackToTop&arg={id}',
        'playlists' => [
            ['title' => 'OGG Vorbis', 'file' => '/playlist/tronic.ogg.m3u'],
            ['title' => 'OGG Vorbis (low)', 'file' => '/playlist/tronic-low.ogg.m3u'],
            ['title' => 'HE-AAC v2', 'file' => '/playlist/tronic.aac.m3u'],
            ['title' => 'HE-AAC v2 (low)', 'file' => '/playlist/tronic-low.aac.m3u'],
        ],
    ],
    [
        'id'    => 'Ambient',
        'slug'  => 'ambient',
        'title' => 'Dream Factory',
        'genre' => 'Ambient, Chillout, Downbeat, Trance',
        'stream' => [
            'ogg' => 'http://radio.hbr1.com:19800/ambient.ogg',
            'aac' => 'http://radio.hbr1.com:19800/ambient.aac',
            'secure' => [
                'ogg' => 'https://radio.hbr1.com/ambient.ogg',
                'aac' => 'https://radio.hbr1.com/ambient.aac',
            ],
        ],
        'muses_url'  => 'http://radio.hbr1.com:19800/ambient',
        'iphone_url' => 'http://radio.hbr1.com:19800/ambient.aac.m3u',
        'radiodj_db' => 'radiodj_ambient',
        'radiodj_request_url' => 'http://192.168.9.133:8080/opt?auth=plain&command=LoadTrackToTop&arg={id}',
        'playlists' => [
            ['title' => 'OGG Vorbis', 'file' => '/playlist/ambient.ogg.m3u'],
            ['title' => 'OGG Vorbis (low)', 'file' => '/playlist/ambient-low.ogg.m3u'],
            ['title' => 'HE-AAC v2', 'file' => '/playlist/ambient.aac.m3u'],
            ['title' => 'HE-AAC v2 (low)', 'file' => '/playlist/ambient-low.aac.m3u'],
        ],
    ],
];

return [
    'app' => [
        'name'     => APP_NAME,
        'base_url' => APP_BASE_URL,
        'timezone' => APP_TIMEZONE,
        'debug'    => DEBUG_MODE,
    ],

    'paths' => [
        'base'   => BASE_DIR,
        'views'  => BASE_DIR . '/views',
        'cache'  => BASE_DIR . '/var/cache',
        'log'    => BASE_DIR . '/var/log',
        'public' => BASE_DIR . '/public',
        'data'   => BASE_DIR . '/data',
        // The TrueType face drawn by the request form's security image. It is
        // the identical file the legacy `securimage` used, so the digits look
        // the same; `security.ttf` is 38 KB and committed.
        'font_security' => BASE_DIR . '/data/security.ttf',
        // `public/` is the document root and the only web-reachable tree. The
        // legacy static assets (images, style, gp, playlist, chat, webtv) and
        // the handful of still-live `.htm` pages were moved into it, so their
        // URLs did not change and the vhost needs no aliases.
        'legacy_docs' => BASE_DIR . '/public',
        // The retired trees, kept read-only for reference. Nothing serves from
        // here: the vhost denies it and no code path reads it.
        'legacy' => BASE_DIR . '/legacy',
    ],

    'db' => [
        // One server, one credential, one connection.
        //
        // The legacy cgi-bin/main.php opened two handles ($Db and $Db_Music)
        // purely because the two MySQL users had different grants: 'hbr1' was
        // SELECT-only on the playout schemas while 'hbr1-web' could INSERT
        // song requests. 'hbr1-web' now holds ALL on `hbr1`.* as well, so the
        // split has no remaining purpose.
        //
        // The connection has no default database: the playout tables are
        // always schema-qualified (`radiodj_trance.history`) and the site
        // tables are qualified by their model. Keeping the connection
        // database-less means a missing qualification fails loudly instead of
        // silently resolving against `hbr1`.
        'default' => [
            'dsn'  => DB_DSN,
            'user' => DB_USER,
            'pass' => DB_PASSWORD,
        ],
    ],

    'icecast' => [
        'stats_url' => ICECAST_STATS_URL,
        'user'      => ICECAST_STATS_USER,
        'password'  => ICECAST_STATS_PASSWORD,
    ],

    'stations' => $stations,
];
