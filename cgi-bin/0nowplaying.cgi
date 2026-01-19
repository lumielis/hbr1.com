#!/opt/perl/bin/perl

package HBR1;

use PAB;
use PAB::CGI qw(:var);
use PAB::Constant qw(S_OK);
use PAB::System qw(ERROR);
use PAB::File;
use PAB::DBI;
use PAB::DBI::Table;
use PAB::Format ();
#use PAB::Utilities;

&PAB::CGI::SETENV();

&PAB::System::require( $ENV{'SCRIPT_PATH'} . './include/config.pab.pl' );

&PAB::System::init(
	'LogOut' => $Setup{'PathLog'} . './nowplaying.log',
	'LogLevel' => 2,
	'LogHeader' =>
		'------- Running a new session ('
		. ( $ENV{'MOD_PERL'} || $ENV{'GATEWAY_INTERFACE'} || $ENV{'USER'} || '' )
		. ( " PAB/${PAB::VERSION}" )
		. ') -------',	
);

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

$hr = &PAB::DBI::connect( %{ $Setup{'DBI'} } );
if( $hr != S_OK ) {
	die $PAB::System::LastError;
}

#&PAB::CGI::header( 'Content-Type: text/html' );
#&PAB::CGI::header( 'Content-Language: ' . $ENV{LANG} );

if( $ENV{'REQUEST_METHOD'} ne 'POST' ||
	$Form{'TRANSFERID'} ne 'DFSE3-KX427-A2RRG-BL5XM-CB336' ||
	! $Form{'Station'} ) {
	print 'wrong input';
	exit;
}

$NowPlaying = PAB::DBI::Table->new( Table => 'nowplaying' );

$NowPlaying->deleteById( $Form{'Station'} . '5' );
for( $index = 4; $index >= 1; $index -- ) {
	if( $NowPlaying->loadById( $Form{'Station'} . $index ) == S_OK ) {
		$NowPlaying->{'Id'} = $Form{'Station'} . ( $index + 1 );
		$NowPlaying->save();
	}
}

$index = 1;
$hr = $NowPlaying->loadById( $Form{'Station'} . $index );
if( $hr != S_OK ) {
	$NowPlaying->clear();
}
$NowPlaying->{'Title'} = $Form{ 'Title' . $index };
$NowPlaying->{'Artist'} = $Form{ 'Artist' . $index };
$NowPlaying->{'Album'} = $Form{ 'Album' . $index };
$NowPlaying->{'Genre'} = $Form{ 'Genre' . $index };
$NowPlaying->{'Length'} = $Form{ 'Length' . $index };
$NowPlaying->{'Quality'} = $Form{ 'Quality' . $index };
$NowPlaying->{'Filename'} = $Form{ 'Filename' . $index };
$NowPlaying->{'Track'} = $Form{ 'Track' . $index };
$NowPlaying->{'Year'} = $Form{ 'Year' . $index };
$NowPlaying->{'Comment'} = $Form{ 'Comment' . $index };
$NowPlaying->{'Station'} = $Form{'Station'};
$NowPlaying->{'Id'} = $Form{'Station'} . $index;
$NowPlaying->{'Time'} = &PAB::Format::unixAsDatetime( time - 100 );
$NowPlaying->save();

#$NowPlaying->optimize();

&PAB::DBI::disconnect();

&PAB::System::sendLog( "Finished" );

print "ok";
