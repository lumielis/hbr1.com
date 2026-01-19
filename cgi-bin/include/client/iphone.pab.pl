&PAB::System::require(
	$ENV{'SCRIPT_PATH'} . './include/client/station.pab.pl'
);

&setupStations();

$hr = &PAB::registerLoop(
	'Stations',
	$Setup{'Station'}, \$Station, undef,
	'{ $Setup{\'Station\'} }', 'Station', undef
);
if( $hr != S_OK ) {
	die $PAB::System::LastError;
}
$hr = &PAB::registerLoop(
	'Streams',
	\&getStreams, \&initStream, undef,
	'getStreams', 'initStream', undef
);
if( $hr != S_OK ) {
	die $PAB::System::LastError;
}

$hr = &PAB::makeScriptAndRun(
	'client/iphone.htm',
	'client.iphone.pl'
);
if( $hr != S_OK ) {
	die $PAB::System::LastError;
}

1;

