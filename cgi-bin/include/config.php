<?php

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

$DB_Host = 'mariadb.lan.etwnet.com';
$DB_Port = 3306;
$DB_Database = 'hbr1';
$DB_User = 'hbr1';
$DB_Auth = 'hbr1';
$DB_Charset = 'utf8';

$DB_Music_Host = 'mariadb.lan.etwnet.com';
$DB_Music_Port = 3306;
$DB_Music_Database = '';
$DB_Music_User = 'hbr1-web';
$DB_Music_Auth = 'hbr1-web';
$DB_Music_Charset = 'utf8';

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
