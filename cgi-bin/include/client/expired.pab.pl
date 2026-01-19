use PAB::Constant qw(S_OK);

$hr = &PAB::makeScriptAndRun(
	'client/expired.htm',
	'client.expired.pl'
);
if( $hr != S_OK ) {
	die $PAB::System::LastError;
}

1;
