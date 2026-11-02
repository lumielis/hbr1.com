$NewsTicker = PAB::DBI::Table->new(
	'Table' => 'news_ticker',
	'FormBase' => 'NewsTicker'
);

unless( $Form{'Action'} ) {
	$hr = &PAB::makeScriptAndRun(
		'admin/newsticker.htm',
		'admin.newsticker.pl'
	);
	if( $hr != S_OK ) {
		die $PAB::System::LastError;
	}
}

1;
