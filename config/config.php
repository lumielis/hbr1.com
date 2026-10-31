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
        ],
        'muses_url'  => 'http://radio.hbr1.com:19800/trance',
        'iphone_url' => 'http://radio.hbr1.com:19800/trance.aac.m3u',
        'sam_db'     => 'samdb_trance',
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
        ],
        'muses_url'  => 'http://radio.hbr1.com:19800/tronic',
        'iphone_url' => 'http://radio.hbr1.com:19800/tronic.aac.m3u',
        'sam_db'     => 'samdb_tronic',
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
        ],
        'muses_url'  => 'http://radio.hbr1.com:19800/ambient',
        'iphone_url' => 'http://radio.hbr1.com:19800/ambient.aac.m3u',
        'sam_db'     => 'samdb_ambient',
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
    ],

    'db' => [
        // One server, one connection.
        //
        // The legacy cgi-bin/main.php opened two handles ($Db and $Db_Music).
        // They always pointed at the same host/port - the split existed purely
        // because 'hbr1' has SELECT-only grants on the music schemas while
        // 'hbr1-web' has SELECT/INSERT/UPDATE/DELETE on radiodj_%.
        //
        // Verified against the live server:
        //   hbr1      : ALL on hbr1.*, SELECT on radiodj_%.* and samdb_%.*
        //   hbr1-web  : SELECT on samdb_%.*, SELECT/INSERT/UPDATE/DELETE on radiodj_%.*
        //
        // So the two roles are grants, not destinations. They are kept as two
        // named roles (least privilege: reads default to the site user, the
        // song-request write uses the music user) but they share a host. See
        // deploy/DB-CONNECTIONS.md for the grant change that would collapse
        // them into one credential.
        'default' => [
            'dsn'  => DB_DSN,
            'user' => DB_USER,
            'pass' => DB_PASSWORD,
        ],
        'music' => [
            'dsn'  => DB_MUSIC_DSN,
            'user' => DB_MUSIC_USER,
            'pass' => DB_MUSIC_PASSWORD,
        ],
    ],

    'icecast' => [
        'stats_url' => ICECAST_STATS_URL,
        'user'      => ICECAST_STATS_USER,
        'password'  => ICECAST_STATS_PASSWORD,
    ],

    'stations' => $stations,
];
