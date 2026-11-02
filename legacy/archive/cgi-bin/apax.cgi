#!/usr/local/bin/perl

package HBR1;

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
use PAB::Mail;

&PAB::CGI::SETENV();

$ENV{'TZ'} = 'Europe/Berlin';
$ENV{'LC_ALL'} = 'en_US';
$ENV{'LANG'} = 'en';
$ENV{'CHARSET'} = 'iso-8859-15';

&PAB::System::require( $ENV{'SCRIPT_PATH'} . './include/config.pab.pl' );
&PAB::System::require( $ENV{'SCRIPT_PATH'} . './include/server.inc.pl' );
&PAB::System::require( $ENV{'SCRIPT_PATH'} . './include/common.pab.pl' );

&PAB::System::init(
	'LogFile' => $Setup{'PathLog'} . './hbr1.log',
	'LogLevel' => 2,
	'LogHeader' =>
		'------- Running a new session ('
		. ( $ENV{'MOD_PERL'} || $ENV{'GATEWAY_INTERFACE'} || $ENV{'USER'} || '' )
		. ( " PAB/${PAB::VERSION}" )
		. ') -------',	
);

&PAB::System::requireAndRun( $ENV{'SCRIPT_PATH'} . 'include/lang/en.pl' );

$hr = &PAB::init(
	'PathTemplate' => $Setup{'PathTemplate'},
	'PathCache' => $Setup{'PathCache'},
	'Package' => __PACKAGE__,
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
$hr = &PAB::Mail::init();
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

$ENV{'DEBUG'} = $Form{'debug'};

$hr = &initSystem();
die $PAB::System::LastError if $hr != S_OK;

if( $Form{'run'} ) {
	&PAB::System::requireAndRun(
		$ENV{'SCRIPT_PATH'} . './include/client/'
			. $Form{'run'} . '.pab.pl'
	);
}
elsif( $Form{'Part'} ) {
	&PAB::System::requireAndRun(
		$ENV{'SCRIPT_PATH'} . './include/client/'
			. lc( $Form{'Part'} ) . '.pab.pl'
	);
}

#&PAB::DBI::disconnect();

&PAB::System::sendLog( 'Finished' );
