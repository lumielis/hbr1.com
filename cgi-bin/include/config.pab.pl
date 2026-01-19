$Setup{'PathBase'} = q(/home/christian/WWWRoot/www.hbr1.com/);
$Setup{'PathTemplate'} = q(/home/christian/WWWRoot/www.hbr1.com/template/);
$Setup{'PathCache'} = q(/home/christian/WWWRoot/www.hbr1.com/cache/);
$Setup{'PathLog'} = q(/home/christian/WWWRoot/www.hbr1.com/log/);
$Setup{'PathPlaylist'} = q(/home/christian/WWWRoot/www.hbr1.com/playlist/);
$Setup{'PathCGI'} = q(/home/christian/WWWRoot/www.hbr1.com/cgi-bin/);
$Setup{'PathHtml'} = q(/home/christian/WWWRoot/www.hbr1.com/htdocs/html/);
$Setup{'PathData'} = q(/home/christian/WWWRoot/www.hbr1.com/data/);

$Setup{'CookiePath'} = '';
$Setup{'CookieDomain'} = '';

$Domainname = 'www.hbr1.com';

$Setup{'DBI'} = {
	'Driver'		=> 'Mysql',
#	'Socket'		=> '/tmp/mysql.sock',
	'Host'			=> 'mariadb.lan.etwnet.com',
	'DbName'		=> 'hbr1',
	'DbUser'		=> 'hbr1',
	'DbPass'		=> 'hbr1',
	'AutoReconnect' => 1,
};

$Setup{'PAB3::DB'} = {
	'driver'		=> 'Mysql',
#	'socket'		=> '/tmp/mysql.sock',
	'host'			=> 'mariadb.lan.etwnet.com',
	'db'			=> 'hbr1',
	'user'			=> 'hbr1',
	'auth'			=> 'hbr1',
	'options'		=> {
		'reconnect'		=> 1,
	},
};

$Setup{'System'} = {
	LogOut			=> $ENV{'PATH_LOG'} . './hbr1.log',
	LogLevel		=> $ENV{'PAB_RUNTIMEMODE'},
	UseTimer		=> 1,
};

$Setup{'Mail'} = {
	Type			=> qq(sendmail),
	SendMail		=> qq(/usr/sbin/sendmail),
	Dispatcher		=> qq(sendmail),
};

$Setup{'ChatShowEntries'} = 66;
$Setup{'ChatOnlineTime'} = 600;

$Setup{'Station'} = [
	{
		Id => 'Trance',
		Title => 'I.D.M. Tranceponder',
		Genre => 'Psychedelic, Progressive Trance, Full-On',
		JOrbisPL => 'http://www.hbr1.com:19800/trance.ogg',
		SAMDB => 'samdb_trance',
		SAMHOST => 'radio.hbr1.com',
		SAMPORT => 21501,
		IPhoneUrl => 'http://radio.hbr1.com:19800/trance.aac.m3u',
		Stream => [
			{
				Title => '<font color="#FFFFAA">Tune In: </font>',
			},
			{
				Type => 'icecast2',
				Title => 'Ogg Vorbis',
				CSS_Class => 'sl1',
				IC2_Host => 'radio.hbr1.com',
				IC2_Port => 19800,
				IC2_Mount => '/trance.ogg',
				IC2_Stat => 'http://admin:RadioHbr1Eurotip@radio.hbr1.com:19800/admin/stats.xml',
				Playlist => '/playlist/trance.pls',
				LoadStats => 1,
				ShowStats => 1,
			},
			{
				Title => ' <font color="#66dd99">[</font>',
			},
			{
				Type => 'icecast2',
				Title => 'SSL',
				CSS_Class => 'sl1',
				IC2_Host => 'radio.hbr1.com',
				IC2_Port => 19800,
				IC2_Mount => '/trance.ogg',
				IC2_Stat => 'http://admin:RadioHbr1Eurotip@radio.hbr1.com:19800/admin/stats.xml',
				Playlist => '/playlist/trance_ogg_ssl.m3u',
				LoadStats => 1,
				ShowStats => 1,
			},
			{
				Title => '<font color="#66dd99">]</font>, ',
			},
			{
				Type => 'icecast2',
				Title => 'aacPlus',
				CSS_Class => 'sl1',
				IC2_Host => 'radio.hbr1.com',
				IC2_Port => 19800,
				IC2_Mount => '/trance.aac',
				IC2_Stat => 'http://admin:RadioHbr1Eurotip@radio.hbr1.com:19800/admin/stats.xml',
				Playlist => '',
				LoadStats => 1,
				ShowStats => 1,
			},
			{
				Title => ' <font color="#66dd99">[</font>',
			},
			{
				Type => 'icecast2',
				Title => 'SSL',
				CSS_Class => 'sl1',
				IC2_Host => 'radio.hbr1.com',
				IC2_Port => 19800,
				IC2_Mount => '/trance.aac',
				IC2_Stat => 'http://admin:RadioHbr1Eurotip@radio.hbr1.com:19800/admin/stats.xml',
				Playlist => '/playlist/trance_aac_ssl.m3u',
				LoadStats => 1,
				ShowStats => 1,
			},
			{
				Title => '<font color="#66dd99">]</font>',
			},
		]
	},
	{
		Id => 'Tronic',
		Title => 'Tronic Lounge',
		Genre => 'Tech House, Progressive House, Techno, Minimal',
		JOrbisPL => 'http://www.hbr1.com:19800/tronic.ogg',
		SAMDB => 'samdb_tronic',
		SAMHOST => 'radio.hbr1.com',
		SAMPORT => 21502,
		IPhoneUrl => 'http://radio.hbr1.com:19800/tronic.aac.m3u',
		Stream => [
			{
				Title => '<font color="#FFFFAA">Tune In: </font>',
			},
			{
				Type => 'icecast2',
				Title => 'Ogg Vorbis',
				CSS_Class => 'sl1',
				IC2_Host => 'radio.hbr1.com',
				IC2_Port => 19800,
				IC2_Mount => '/tronic.ogg',
				IC2_Stat => 'http://admin:RadioHbr1Eurotip@radio.hbr1.com:19800/admin/stats.xml',
				Playlist => '/playlist/tronic.pls',
				LoadStats => 1,
				ShowStats => 1,
			},
			{
				Title => ' <font color="#66dd99">[</font>',
			},
			{
				Type => 'icecast2',
				Title => 'SSL',
				CSS_Class => 'sl1',
				IC2_Host => 'radio.hbr1.com',
				IC2_Port => 19800,
				IC2_Mount => '/tronic.ogg',
				IC2_Stat => 'http://admin:RadioHbr1Eurotip@radio.hbr1.com:19800/admin/stats.xml',
				Playlist => '/playlist/tronic_ogg_ssl.m3u',
				LoadStats => 1,
				ShowStats => 1,
			},
			{
				Title => '<font color="#66dd99">]</font>, ',
			},
			{
				Type => 'icecast2',
				Title => 'aacPlus',
				CSS_Class => 'sl1',
				IC2_Host => 'radio.hbr1.com',
				IC2_Port => 19800,
				IC2_Mount => '/tronic.aac',
				IC2_Stat => 'http://admin:RadioHbr1Eurotip@radio.hbr1.com:19800/admin/stats.xml',
				Playlist => '',
				LoadStats => 1,
				ShowStats => 1,
			},
			{
				Title => ' <font color="#66dd99">[</font>',
			},
			{
				Type => 'icecast2',
				Title => 'SSL',
				CSS_Class => 'sl1',
				IC2_Host => 'radio.hbr1.com',
				IC2_Port => 19800,
				IC2_Mount => '/tronic.aac',
				IC2_Stat => 'http://admin:RadioHbr1Eurotip@radio.hbr1.com:19800/admin/stats.xml',
				Playlist => '/playlist/tronic_aac_ssl.m3u',
				LoadStats => 1,
				ShowStats => 1,
			},
			{
				Title => '<font color="#66dd99">]</font>',
			},
		]
	},
	{
		Id => 'Ambient',
		Title => 'Dream Factory',
		Genre => 'Ambient, Chillout, Downbeat, Trance',
		JOrbisPL => 'http://www.hbr1.com:19800/ambient.ogg',
		SAMDB => 'samdb_ambient',
		SAMHOST => 'radio.hbr1.com',
		SAMPORT => 21503,
		RadioDJ_DB => 'radiodj_ambient',
		IPhoneUrl => 'http://radio.hbr1.com:19800/ambient.aac.m3u',
		Stream => [
			{
				Title => '<font color="#FFFFAA">Tune In: </font>',
			},
			{
				Type => 'icecast2',
				Title => 'Ogg Vorbis',
				CSS_Class => 'sl1',
				IC2_Host => 'radio.hbr1.com',
				IC2_Port => 19800,
				IC2_Mount => '/ambient.ogg',
				IC2_Stat => 'http://admin:RadioHbr1Eurotip@radio.hbr1.com:19800/admin/stats.xml',
				Playlist => '/playlist/ambient.pls',
				LoadStats => 1,
				ShowStats => 1,
			},
			{
				Title => ' <font color="#66dd99">[</font>',
			},
			{
				Type => 'icecast2',
				Title => 'SSL',
				CSS_Class => 'sl1',
				IC2_Host => 'radio.hbr1.com',
				IC2_Port => 19800,
				IC2_Mount => '/ambient.ogg',
				IC2_Stat => 'http://admin:RadioHbr1Eurotip@radio.hbr1.com:19800/admin/stats.xml',
				Playlist => '/playlist/ambient_ogg_ssl.m3u',
				LoadStats => 1,
				ShowStats => 1,
			},
			{
				Title => '<font color="#66dd99">]</font>, ',
			},
			{
				Type => 'icecast2',
				Title => 'aacPlus',
				CSS_Class => 'sl1',
				IC2_Host => 'radio.hbr1.com',
				IC2_Port => 19800,
				IC2_Mount => '/ambient.aac',
				IC2_Stat => 'http://admin:RadioHbr1Eurotip@radio.hbr1.com:19800/admin/stats.xml',
				Playlist => '',
				LoadStats => 1,
				ShowStats => 1,
			},
			{
				Title => ' <font color="#66dd99">[</font>',
			},
			{
				Type => 'icecast2',
				Title => 'SSL',
				CSS_Class => 'sl1',
				IC2_Host => 'radio.hbr1.com',
				IC2_Port => 19800,
				IC2_Mount => '/ambient.aac',
				IC2_Stat => 'http://admin:RadioHbr1Eurotip@radio.hbr1.com:19800/admin/stats.xml',
				Playlist => '/playlist/ambient_aac_ssl.m3u',
				LoadStats => 1,
				ShowStats => 1,
			},
			{
				Title => '<font color="#66dd99">]</font>',
			},
		]
	},
];


1;
