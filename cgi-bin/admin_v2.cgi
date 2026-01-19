#!/opt/perl/bin/perl

package HBR1;

BEGIN {
	push @INC, '/srv/www/perl-lib';
}

#$GLOBAL::REQUEST = shift;

use PAB;
use PAB::CGI qw(:var);
use PAB::Constant qw(S_OK);
use PAB::System qw(ERROR);
use PAB::File;
use PAB::DBI;
use PAB::DBI::Table qw(:table);
use PAB::DBI::Database;
use PAB::Format qw(:all);
use PAB::XML;

&PAB::CGI::SETENV();

$ENV{'TZ'} = 'GMT';
$ENV{'LC_ALL'} = 'en_US';
$ENV{'LANG'} = 'en';
$ENV{'CHARSET'} = "iso-8859-15";

&PAB::System::require( $ENV{'SCRIPT_PATH'} . './include/config.pab.pl' );
&PAB::System::require( $ENV{'SCRIPT_PATH'} . './include/server.inc.pl' );

#$ENV{'LANG'} = 'de';
#$ENV{'LC_ALL'} = 'de_DE@euro';

&PAB::System::init(
	'LogOut' => $Setup{'PathLog'} . './hbr1.log',
	'LogLevel' => 2,#$ENV{PAB_RUNTIMEMODE},
	'LogHeader' =>
		'------- Running a new session ('
		. ( $ENV{'MOD_PERL'} || $ENV{'GATEWAY_INTERFACE'} || $ENV{'USER'} || '' )
		. ( " PAB/${PAB::VERSION}" )
		. ') -------',	
	'Package' => __PACKAGE__,
);

$hr = &PAB::init(
	'PathTemplate' => $Setup{'PathTemplate'},
	'PathCache' => $Setup{'PathCache'},
);
if( $hr != S_OK ) {
	die $PAB::System::LastError;
}

$hr = &PAB::CGI::init();
if( $hr != S_OK ) {
	die $PAB::System::LastError;
}

$hr = &PAB::Format::init();
if( $hr != S_OK ) {
	die $PAB::System::LastError;
}

$hr = &PAB::DBI::init( %{ $Setup{'DBI'} } );
if( $hr != S_OK ) {
	die $PAB::System::LastError;
}

&PAB::DBI::Database::init(
	'PathCache' => $Setup{'PathCache'},
	'AutoCache' => 1,
);
die $PAB::System::LastError if $hr != S_OK;

if( $Form{'run'} ) {
	&PAB::System::requireAndRun(
		$ENV{'SCRIPT_PATH'} . './include/admin/' . $Form{'run'} . '.pab.pl'
	);
}
elsif( $Form{'Part'} ) {
	&PAB::System::requireAndRun(
		$ENV{'SCRIPT_PATH'} . './include/admin/'
			. lc( $Form{'Part'} ) . '.pab.pl'
	);
}

#&PAB::DBI::disconnect();

&PAB::System::sendLog( "Finished" );
