foreach $station( @{$Setup{'Station'}} ) {
	if( $station->{'Id'} eq $Form{'station'} ) {
		$Station = $station;
	}
}

#warn $Station;

$hr = &PAB::makeScriptAndRun(
	'client/jorbis.htm',
	'client.jorbis.pl'
);
if( $hr != S_OK ) {
	die $PAB::System::LastError;
}

1;

