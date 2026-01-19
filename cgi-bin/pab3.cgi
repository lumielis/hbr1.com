package HBR1_PAB3;

use Time::HiRes ();
use XML::LibXML ();
use GD::SecurityImage;
$_ts = Time::HiRes::time();

use PAB3;
use PAB3::DB::Max;
use PAB3::HashmapCache;
use PAB3::CGI qw(:default);
use PAB3::Utils qw(:default);
use PAB3::Session;

&PAB3::CGI::setenv();
&PAB3::CGI::init();

&PAB3::Session::start();

&PAB3::Utils::set_locale( 'de_DE.UTF-8' );
&PAB3::Utils::set_timezone( 'Europe/Berlin' );

&PAB3::require( $ENV{'SCRIPT_PATH'} . 'include/config.pab.pl' );
&PAB3::require( $ENV{'SCRIPT_PATH'} . 'include/server.inc.pl' );
&PAB3::require( $ENV{'SCRIPT_PATH'} . 'include/common.pab.pl' );

$ENV{'CHARSET'} = 'utf-8';

$pab3 = PAB3->new(
	'path_template' => $Setup{'PathTemplate'},
	'path_cache' => $Setup{'PathCache'},
	'hashmap_cache' => PAB3::HashmapCache->new(),
	'auto_cache' => 1,
);

$db = PAB3::DB->connect( %{$Setup{'PAB3::DB'}} );
$db->set_charset( 'utf8' );

#$db_music = PAB3::DB->connect( %{$Setup{'PAB3::DB::Music'}} );
#$db_music->set_charset( 'utf8' );

$parser = XML::LibXML->new();

$P = \%_REQUEST;

if( $_REQUEST{'run'} ) {
	$pab3->require_and_run(
		$ENV{'SCRIPT_PATH'} . 'include/client/' . $_REQUEST{'run'} . '.pab3.pl'
	);
}
else {
	&print_r( 'ENV:', \%ENV );
}

$_te = sprintf( "%0.3f", Time::HiRes::time() - $_ts );
#print "<p>generated within $_te seconds<p>\n";

1;
