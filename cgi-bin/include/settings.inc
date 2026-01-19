$path{'system'} = qq(/home/christian/WWWRoot/www.hbr1.com/cgi-bin);
$path{'display'} = qq(/home/christian/WWWRoot/www.hbr1.com/htdocs/cgi-htm);
$path{'ssms'} = qq(/home/christian/WWWRoot/perl-ssms/);
$path{'images'} = qq(/home/christian/WWWRoot/www.hbr1.com/htdocs/images);
#$path{'data'} = qq(/home/christian/WWWRoot/www.hbr1.com/cgi-bin/include/data);
$path{'webtv'} = qq(/home/christian/WWWRoot/www.hbr1.com/htdocs/webtv);
$path{'chat'} = qq(/home/christian/WWWRoot/www.hbr1.com/htdocs/chat);
$path{'cache'} = qq(/home/christian/WWWRoot/www.hbr1.com/htdocs/html);
$path{'base'} = qq(/home/christian/WWWRoot/www.hbr1.com/htdocs);
$path{'www'} = $path{'base'};

$url{'system'} = qq(http://www.hbr1.com/cgi-bin);
$url{'display'} = qq(http://www.hbr1.com/cgi-htm);
$url{'images'} = qq(http://www.hbr1.com/images);
$url{'webtv'} = qq(http://www.hbr1.com/webtv);
$url{'base'} = qq(http://www.hbr1.com);
$url{'cache'} = qq(http://www.hbr1.com/html);
$url{'www'} = $url{'base'};

$setup{'time_format'} = qq(h:m p);
$setup{'date_format'} = qq(d. M y);
$setup{'time_gmt'} = 1;
#$setup{'check_counter'} = 1;
$setup{'quickpost_lifetime'} = 30;
#$setup{'cookie_domain'} = qw(www.hbr1.com);

$setup{'ssms_reg_path'} = 1;

$setup{'chat_history_length'} = 30;
$setup{'chat_history_copy'} = 50;

%station_def = (
  1 => {
    id => 'trance',
    name => 'I.D.M. Tranceponder',
    genre => 'Psychedelic Goa Trance',
    logo => '',
    shout_host => 'radio.hbr1.com',
    shout_port => 19700,
    shout_stat => 'http://217.64.169.133:19700/admin.cgi?mode=viewxml&pass=RadioHbr1Eurotip',
    asx_file => 'http://www.hbr1.com/playlist/trance.asx',
    mms_alias => '',
    streams => {
		  0 => {
		  	type => 'title',
		  	title => ' <font color="FFFFAA">Tune in:</font><font color="#6666AA"> ',
		  },
    	1 => {
	    	type => 'shoutcast',
		    title => 'MP3-Stream',
		    shoutcast_host => 'radio.hbr1.com',
		    shoutcast_port => 19700,
		    shoutcast_stats => 'http://217.64.169.133:19700/admin.cgi?mode=viewxml&pass=RadioHbr1Eurotip',
		  },
		  2 => {
		  	type => 'title',
		  	title => ', ',
		  },
    	3 => {
	    	type => 'icecast2',
		    title => 'Ogg-Vorbis',
		    icecast2_host => 'radio.hbr1.com',
		    icecast2_port => 19800,
		    icecast2_mount => 'trance.ogg',
		  },
		  4 => {
		  	type => 'title',
		  	title => ', ',
		  },
    	5 => {
	    	type => 'asx',
		    title => 'WM-Player (MP3)',
		    asx_file => 'http://www.hbr1.com/playlist/trance.asx',
		  },
    },
  },
  2 => {
    id => 'house',
    name => 'In da House',
    genre => 'Soulful House Music',
    logo => '',
    shout_host => 'radio.hbr1.com',
    shout_port => 19710,
    shout_stat => 'http://217.64.169.133:19710/admin.cgi?mode=viewxml&pass=RadioHbr1Eurotip',
    asx_file => 'http://www.hbr1.com/playlist/house.asx',
    #mms_alias => 'http://media.hbr1.com/house',
    mms_alias => '',
    streams => {
		  0 => {
		  	type => 'title',
		  	title => ' <font color="FFFFAA">Tune in:</font><font color="#6666AA"> ',
		  },
    	1 => {
	    	type => 'shoutcast',
		    title => 'MP3-Stream',
		    shoutcast_host => 'radio.hbr1.com',
		    shoutcast_port => 19710,
		    shoutcast_stats => 'http://217.64.169.133:19710/admin.cgi?mode=viewxml&pass=RadioHbr1Eurotip',
		  },
		  2 => {
		  	type => 'title',
		  	title => ', ',
		  },
    	3 => {
	    	type => 'icecast2',
		    title => 'Ogg-Vorbis',
		    icecast2_host => 'radio.hbr1.com',
		    icecast2_port => 19800,
		    icecast2_mount => 'house.ogg',
		  },
		  4 => {
		  	type => 'title',
		  	title => ', ',
		  },
    	5 => {
	    	type => 'asx',
		    title => 'WM-Player (MP3)',
		    asx_file => 'http://www.hbr1.com/playlist/house.asx',
		  },
    },
  },
  3 => {
    id => 'ambient',
    name => 'Dream Factory',
    genre => 'Finest Ambient Trance',
    logo => '',
    shout_host => 'radio.hbr1.com',
    shout_port => 19720,
    shout_stat => 'http://217.64.169.133:19720/admin.cgi?mode=viewxml&pass=RadioHbr1Eurotip',
    asx_file => 'http://www.hbr1.com/playlist/ambient.asx',
    #mms_alias => 'http://media.hbr1.com/ambient',
    mms_alias => '',
    streams => {
		  0 => {
		  	type => 'title',
		  	title => ' <font color="FFFFAA">Tune in:</font><font color="#6666AA"> ',
		  },
    	1 => {
	    	type => 'shoutcast',
		    title => 'MP3-Stream',
		    shoutcast_host => 'radio.hbr1.com',
		    shoutcast_port => 19720,
		    shoutcast_stats => 'http://217.64.169.133:19720/admin.cgi?mode=viewxml&pass=RadioHbr1Eurotip',
		  },
		  2 => {
		  	type => 'title',
		  	title => ', ',
		  },
    	3 => {
	    	type => 'icecast2',
		    title => 'Ogg-Vorbis',
		    icecast2_host => 'radio.hbr1.com',
		    icecast2_port => 19800,
		    icecast2_mount => 'ambient.ogg',
		  },
		  4 => {
		  	type => 'title',
		  	title => ', ',
		  },
    	5 => {
	    	type => 'asx',
		    title => 'WM-Player (MP3)',
		    asx_file => 'http://www.hbr1.com/playlist/ambient.asx',
		  },
    },
  }
);
%station_unused = (
  4 => {
    id => 'rock',
    name => 'The Rock Box',
    genre => 'Pop, Rock, 80s and Today',
    logo => '',
    shout_host => 'radio.hbr1.com',
    shout_port => 4989,
    shout_stat => 'http://127.0.0.1:4989/admin.cgi?mode=viewxml&pass=MilkyWall',
    asx_file => '',
    mms_alias => ''
  },
  5 => {
    id => 'hiphop',
    name => 'Ghetto Blaster',
    genre => 'HipHop Rnb Rap and all between',
    logo => '',
    shout_host => 'radio.hbr1.com',
    shout_port => 19730,
    shout_stat => 'http://127.0.0.1:19730/admin.cgi?mode=viewxml&pass=RadioHbr1Eurotip',
    asx_file => 'http://www.hbr1.com/playlist/hiphop.asx',
    mms_alias => ''
  }
);

$flyer{'page'} = 6;
$flyer{'width'} = 180;

$chat{'hold'} = 300;
$chat{'time'} = 10;
$chat{'auto_user'} = 1;
$chat{'bgcolor'} = qq(000000);
$chat{'syscheck'} = 30000;

@{ $set_guestbook{'filter_deny'} } = (
  { object => 'all', filter => 'stimpy' }
);

$dbi{'dsn'} = qq(database=hbr1;host=sql.alien-heads.net);
$dbi{'user'} = qq(hbr1);
$dbi{'auth'} = $dbi{'pass'} = qq(hbr1);
$dbi{'driver'} = qq(mysql);

$setup{'dbi'} = $dbi{'driver'};

$setup{'userfile'} = qq($path{'system'}/include/user.inc.pl);
do $setup{'userfile'};

1;
