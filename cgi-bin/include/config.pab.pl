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

# Authenticating values come from the same environment file the PHP side uses
# (env.php -> env.local.php in development, /etc/hbr1/env.php in production).
# Nothing below may be a literal: this file is in git and the repository is
# public. Set HBR1_ENV to point somewhere else.
BEGIN {
	my ($fh, $line, %env);

	# Same precedence as env.php: an explicit override, then the production
	# location, then the checkout that contains this file. __FILE__ is the path
	# this file was loaded from, so two levels up from cgi-bin/include is the
	# project root.
	my @candidates = ($ENV{'HBR1_ENV'});

	if (__FILE__ =~ m{^(.*)/cgi-bin/include/config\.pab\.pl$}) {
		push @candidates, "$1/env.local.php", '/etc/hbr1/env.php';
	} else {
		push @candidates, '/etc/hbr1/env.php', '/home/christian/WWWRoot/www.hbr1.com/env.local.php';
	}

	my ($file) = grep { defined $_ && -f $_ } @candidates;

	if (defined $file && open $fh, '<', $file) {
		while ($line = <$fh>) {
			$env{$1} = $2 if $line =~ /'([A-Z0-9_]+)'\s*=>\s*'([^']*)'/;
		}
		close $fh;
		$ENV{'HBR1_ENV_FILE'} = $file;
	}

	$ENV{'HBR1_DB_HOST'}     = $env{'DB_HOST'}           || 'localhost';
	$ENV{'HBR1_DB_USER'}     = $env{'DB_USER'}           || '';
	$ENV{'HBR1_DB_PASSWORD'} = $env{'DB_PASSWORD'}       || '';
	$ENV{'HBR1_DB_NAME'}     = $env{'DB_DATABASE'}       || 'hbr1';
	$ENV{'HBR1_ICECAST_URL'} = $env{'ICECAST_STATS_URL'} || 'http://radio.hbr1.com:19800/admin/stats.xml';
}

$Setup{'DBI'} = {
	'Driver'		=> 'Mysql',
#	'Socket'		=> '/tmp/mysql.sock',
	'Host'			=> $ENV{'HBR1_DB_HOST'},
	'DbName'		=> $ENV{'HBR1_DB_NAME'},
	'DbUser'		=> $ENV{'HBR1_DB_USER'},
	'DbPass'		=> $ENV{'HBR1_DB_PASSWORD'},
	'AutoReconnect' => 1,
};

$Setup{'PAB3::DB'} = {
	'driver'		=> 'Mysql',
#	'socket'		=> '/tmp/mysql.sock',
	'host'			=> $ENV{'HBR1_DB_HOST'},
	'db'			=> $ENV{'HBR1_DB_NAME'},
	'user'			=> $ENV{'HBR1_DB_USER'},
	'auth'			=> $ENV{'HBR1_DB_PASSWORD'},
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
				IC2_Stat => $ENV{'HBR1_ICECAST_URL'},
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
				IC2_Stat => $ENV{'HBR1_ICECAST_URL'},
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
				IC2_Stat => $ENV{'HBR1_ICECAST_URL'},
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
				IC2_Stat => $ENV{'HBR1_ICECAST_URL'},
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
				IC2_Stat => $ENV{'HBR1_ICECAST_URL'},
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
				IC2_Stat => $ENV{'HBR1_ICECAST_URL'},
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
				IC2_Stat => $ENV{'HBR1_ICECAST_URL'},
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
				IC2_Stat => $ENV{'HBR1_ICECAST_URL'},
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
				IC2_Stat => $ENV{'HBR1_ICECAST_URL'},
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
				IC2_Stat => $ENV{'HBR1_ICECAST_URL'},
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
				IC2_Stat => $ENV{'HBR1_ICECAST_URL'},
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
				IC2_Stat => $ENV{'HBR1_ICECAST_URL'},
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
