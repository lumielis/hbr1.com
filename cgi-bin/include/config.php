<?php

/**
 * Legacy PHP configuration.
 *
 * Paths and the station list are public - they can live in git. Every value
 * that authenticates does not: it comes from the shared environment, which is
 * `env.php` -> `env.local.php` in development and `/etc/hbr1/env.php` in
 * production (see env.php for the lookup order and the HBR1_ENV override).
 *
 * This file is included by `cgi-bin/main.php`, which is *not* routed by the
 * new front controller - it is served straight from `cgi-bin/` if a web server
 * still maps that directory, so it must not depend on the app's bootstrap.
 */

require_once __DIR__ . '/../../env.php';

$PathBase = '/home/christian/WWWRoot/www.hbr1.com/';
$PathTemplate = $PathBase . 'template/';
$PathCache = $PathBase . 'cache/';
$PathLog = $PathBase . 'log/';
$PathPlaylist = $PathBase . 'playlist/';
$PathCGI = $PathBase . 'cgi-bin/';
$PathHtml = $PathBase . 'htdocs/html/';
$PathData = $PathBase . 'data/';

#$CookiePath = '';
#$CookieDomain = '';

$Domainname = 'www.hbr1.com';

// The legacy connection parameters, named after the constants env.php already
// defines so there is one place to edit rather than two.
$DB_Host     = DB_HOST;
$DB_Port     = DB_PORT;
$DB_Database = DB_DATABASE;
$DB_User     = DB_USER;
$DB_Auth     = DB_PASSWORD;
$DB_Charset  = 'utf8';

// `main.php` opens two handles because the playout schemas are read through a
// narrower grant than the site data. One credential covers both, so these are
// kept as names the legacy code still expects, not as separate secrets.
$DB_Music_Host     = DB_HOST;
$DB_Music_Port     = DB_PORT;
$DB_Music_Database = '';
$DB_Music_User     = DB_USER;
$DB_Music_Auth     = DB_PASSWORD;
$DB_Music_Charset  = 'utf8';

$Stations = array(
	array(
		'Id' => 'Trance',
		'Title' => 'I.D.M. Tranceponder',
		'Genre' => 'Psychedelic, Progressive Trance, Full-On',
		'JOrbisPL' => 'http://radio.hbr1.com:19800/trance.ogg',
		'MusesURL' => 'http://radio.hbr1.com:19800/trance',
		'SAMDB' => 'samdb_trance',
		'SAMHOST' => '192.168.7.75',
		'SAMPORT' => 1221,
		'RadioDJ_DB' => 'radiodj_trance',
		'RadioDJ_ReqUrl' => 'http://192.168.9.134:8080/opt?auth=plain&command=LoadTrackToTop&arg={id}',
		'IPhoneUrl' => 'http://radio.hbr1.com:19800/trance.aac.m3u',
		'Stream' => array(
			array(
				'Title' => '<font color="FFFFAA">Tune In: </font>',
			),
			array(
				'Type' => 'icecast2',
				'Title' => 'OGG Vorbis',
				'CSS_Class' => 'sl1',
				'Playlist' => '/playlist/trance.ogg.m3u',
			),
                        array(
                                'Title' => ' <font color="#66dd99">[</font>',
                        ),
                        array(
                                'Type' => 'icecast2',
                                'Title' => 'Low',
                                'CSS_Class' => 'sl1',
                                'Playlist' => '/playlist/trance-low.ogg.m3u',
                        ),
                        array(
                                'Title' => '<font color="#66dd99">]</font>, ',
                        ),
			array(
				'Type' => 'icecast2',
				'Title' => 'HE-AAC v2',
				'CSS_Class' => 'sl1',
				'Playlist' => '/playlist/trance.aac.m3u',
			),
                        array(
                                'Title' => ' <font color="#66dd99">[</font>',
                        ),
                        array(
                                'Type' => 'icecast2',
                                'Title' => 'Low',
                                'CSS_Class' => 'sl1',
                                'Playlist' => '/playlist/trance-low.aac.m3u',
                        ),
                        array(
                                'Title' => '<font color="#66dd99">]</font>',
                        ),
 		)
	),
	array(
		'Id' => 'Tronic',
		'Title' => 'Tronic Lounge',
		'Genre' => 'Tech House, Progressive House, Electro, Minimal',
		'JOrbisPL' => 'http://radio.hbr1.com:19800/tronic.ogg',
		'MusesURL' => 'http://radio.hbr1.com:19800/tronic',
		'SAMDB' => 'samdb_tronic',
		'SAMHOST' => '192.168.7.76',
		'SAMPORT' => 1221,
		'RadioDJ_DB' => 'radiodj_tronic',
		'RadioDJ_ReqUrl' => 'http://192.168.9.135:8080/opt?auth=plain&command=LoadTrackToTop&arg={id}',
		'IPhoneUrl' => 'http://radio.hbr1.com:19800/tronic.aac.m3u',
		'Stream' => array(
			array(
				'Title' => '<font color="FFFFAA">Tune In: </font>',
			),
			array(
				'Type' => 'icecast2',
				'Title' => 'OGG Vorbis',
				'CSS_Class' => 'sl1',
				'Playlist' => '/playlist/tronic.ogg.m3u',
			),
                        array(
                                'Title' => ' <font color="#66dd99">[</font>',
                        ),
                        array(
                                'Type' => 'icecast2',
                                'Title' => 'Low',
                                'CSS_Class' => 'sl1',
                                'Playlist' => '/playlist/tronic-low.ogg.m3u',
                        ),
                        array(
                                'Title' => '<font color="#66dd99">]</font>, ',
                        ),
			array(
				'Type' => 'icecast2',
				'Title' => 'HE-AAC v2',
				'CSS_Class' => 'sl1',
				'Playlist' => '/playlist/tronic.aac.m3u',
			),
                        array(
                                'Title' => ' <font color="#66dd99">[</font>',
                        ),
                        array(
                                'Type' => 'icecast2',
                                'Title' => 'Low',
                                'CSS_Class' => 'sl1',
                                'Playlist' => '/playlist/tronic-low.aac.m3u',
                        ),
                        array(
                                'Title' => '<font color="#66dd99">]</font>',
                        ),
		)
	),
	array(
		'Id' => 'Ambient',
		'Title' => 'Dream Factory',
		'Genre' => 'Ambient, Chillout, Downbeat, Trance',
		'JOrbisPL' => 'http://radio.hbr1.com:19800/ambient.ogg',
		'MusesURL' => 'http://radio.hbr1.com:19800/ambient',
		'SAMDB' => 'samdb_ambient',
		'SAMHOST' => '192.168.7.77',
		'SAMPORT' => 1221,
		'RadioDJ_DB' => 'radiodj_ambient',
		'RadioDJ_ReqUrl' => 'http://192.168.9.133:8080/opt?auth=plain&command=LoadTrackToTop&arg={id}',
		'IPhoneUrl' => 'http://radio.hbr1.com:19800/ambient.aac.m3u',
		'Stream' => array(
			array(
				'Title' => '<font color="FFFFAA">Tune In: </font>',
			),
			array(
				'Type' => 'icecast2',
				'Title' => 'OGG Vorbis',
				'CSS_Class' => 'sl1',
				'Playlist' => '/playlist/ambient.ogg.m3u',
			),
                        array(
                                'Title' => ' <font color="#66dd99">[</font>',
                        ),
                        array(
                                'Type' => 'icecast2',
                                'Title' => 'Low',
                                'CSS_Class' => 'sl1',
                                'Playlist' => '/playlist/ambient-low.ogg.m3u',
                        ),
                        array(
                                'Title' => '<font color="#66dd99">]</font>, ',
                        ),
			array(
				'Type' => 'icecast2',
				'Title' => 'HE-AAC v2',
				'CSS_Class' => 'sl1',
				'Playlist' => '/playlist/ambient.aac.m3u',
			),
                        array(
                                'Title' => ' <font color="#66dd99">[</font>',
                        ),
                        array(
                                'Type' => 'icecast2',
                                'Title' => 'Low',
                                'CSS_Class' => 'sl1',
                                'Playlist' => '/playlist/ambient-low.aac.m3u',
                        ),
                        array(
                                'Title' => '<font color="#66dd99">]</font>',
                        ),
		)
	),
);

$StationsOFF = 0;
