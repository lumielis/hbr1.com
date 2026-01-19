use PAB::Constant qw(S_OK);

$PAB::Crypt::Xor::Key = 'DFSE3-KX427-A2RRG-BL5XM-CB336';

$sid = &PAB::Crypt::Xor::encryptHex( join( '', reverse split( //, time ) ) );

$hr = &PAB::makeScriptAndRun(
	'client/navigation.htm',
	'client.navigation.pl'
);
if( $hr != S_OK ) {
	die $PAB::System::LastError;
}

1;
