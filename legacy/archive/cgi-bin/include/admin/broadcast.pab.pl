use Data::Dumper;

$LiveBroadcast = PAB::DBI::Table->new(
	'Table' => 'live_broadcast',
	'FormBase' => 'LiveBroadcast_'
);

if( $Form{'Action'} eq 'Activate' ) {
	$hr = $LiveBroadcast->loadById( $Form{'LB_Id'} );
	if( $hr != S_OK ) {
		&PAB::CGI::pageError( 'Could not load' );
		&PAB::CGI::logError( $PAB::System::LastError );
		$Form{'Action'} = '';
	}
	if( $Form{'Action'} ) {
		if( $LiveBroadcast->{'Station'} ne '-' ) {
			&PAB::DBI::exec(
				'UPDATE live_broadcast SET Status = 0 ' .
				'WHERE Station = ? AND Id <> ?',
				undef,
				$LiveBroadcast->{'Station'},
				$LiveBroadcast->{'Id'}
			);
		}
		$LiveBroadcast->{'Status'} = 1;
		$LiveBroadcast->save();
		$hr = &updateConfig();
		if( $hr == S_OK ) {
			&PAB::CGI::pageMessage( 'Configuration updated' );
		}
	}
	$Form{'Action'} = '';
}
if( $Form{'Action'} eq 'Deactivate' ) {
	$hr = $LiveBroadcast->loadById( $Form{'LB_Id'} );
	if( $hr != S_OK ) {
		&PAB::CGI::pageError( 'Could not load' );
		&PAB::CGI::logError( $PAB::System::LastError );
		$Form{'Action'} = '';
	}
	if( $Form{'Action'} ) {
		if( $LiveBroadcast->{'Station'} ne '-' ) {
			&PAB::CGI::logError(
				'Please activate another Broadcast in station "' .
				$LiveBroadcast->{'Station'} . '"'
			);
		}
		else {
			$LiveBroadcast->{'Status'} = 0;
			$LiveBroadcast->save();
			$hr = &updateConfig();
			if( $hr == S_OK ) {
				&PAB::CGI::pageMessage( 'Configuration updated' );
			}
		}
	}
	$Form{'Action'} = '';
}
if( $Form{'Action'} eq 'Edit' ) {
	if( $Form{'LB_Id'} ) {
		$hr = $LiveBroadcast->loadById( $Form{'LB_Id'} );
		if( $hr != S_OK ) {
			&PAB::CGI::pageError( 'Could not load' );
			&PAB::CGI::logError( $PAB::System::LastError );
			$Form{'Action'} = '';
		}
	}
	if( $Form{'Action'} ) {
		if( $Form{'doSave'} ) {
			$LiveBroadcast->fromForm();
			if( ! defined %FormError ) {
				$hr = $LiveBroadcast->save();
				if( $hr != S_OK ) {
					&PAB::CGI::pageError( 'Could not save' );
					&PAB::CGI::logError( $PAB::System::LastError );
				}
			}
			if( $hr == S_OK ) {
				&PAB::CGI::logMessage( 'Broadcast saved' );
				$hr = &updateConfig();
			}
			if( $hr == S_OK ) {
				&PAB::CGI::logMessage( 'Configuration updated' );
				$Form{'Action'} = '';
			}
		}
		else {
			$LiveBroadcast->toForm();
			&PAB::CGI::printVar( \%Form );
		}
	}
	if( $Form{'Action'} ) {
		$hr = &PAB::makeScriptAndRun(
			'admin/broadcast_edit.htm',
			'admin.broadcast.edit.pl'
		);
		if( $hr != S_OK ) {
			die $PAB::System::LastError;
		}
	}
}
if( $Form{'Action'} eq 'Delete' ) {
	for( $index = 1; $index <= $Form{'RecordCount'}; $index ++ ) {
		if( defined $Form{ 'LB_' . $index } ) {
			$hr = $LiveBroadcast->deleteById( $Form{ 'LB_' . $index } );
			if( $hr == S_OK ) {
				&PAB::CGI::logMessage( 'Deleted "' . $Form{ 'LB_' . $index } . '"' );
			}
			else {
				&PAB::CGI::logError( 'Could not delete "' . $Form{ 'LB_' . $index } . '":' );
				&PAB::CGI::logError( $PAB::System::LastError );
			}
		}
	}
	$Form{'Action'} = '';
}
if( ! $Form{'Action'} ) {
	$LiveBroadcast->build();
	#$LiveBroadcast->getRecordCount( \$RecordCount );
	$RecordCount = scalar $LiveBroadcast->getResult();
	&PAB::registerLoop(
		'LIVE_BROADCAST',
		'enumResult', undef, \$LiveBroadcast,
		'enumResult', undef, 'LiveBroadcast',
		1
	);
	$hr = &PAB::makeScriptAndRun(
		'admin/broadcast_main.htm',
		'admin.broadcast.main.pl'
	);
	if( $hr != S_OK ) {
		die $PAB::System::LastError;
	}
}


1;

sub updateConfig {
	&PAB::DBI::query( 'SELECT * FROM live_broadcast WHERE Status = 1' );
	while( S_OK == &PAB::DBI::fetchRowHashref( $data ) ) {
		$Server{ $data->{'Station'} }->{'Artist'} = $data->{'Artist'};
		$Server{ $data->{'Station'} }->{'Title'} = $data->{'Title'};
		if( $data->{'Station'} eq $data->{'Id'} ) {
			$Server{ $data->{'Station'} }->{'Live'} = 0;
		}
		else {
			$Server{ $data->{'Station'} }->{'Live'} = 1;
		}
	}
	$hr = &PAB::File::write(
		$Setup{'PathCGI'} . 'include/server.inc.pl',
		Data::Dumper->Dump( [\%Server], [qw(*Server)] ) . "\n1;\n"
	);
	if( $hr != S_OK ) {
		&PAB::CGI::logError( 'Could not write configuration:' );
		&PAB::CGI::logError( $PAB::System::LastError );
		return $hr;
	}
	$LiveBroadcast->setBuildClausel( 'Status = 1' );
	$LiveBroadcast->build();
	&PAB::registerLoop(
		'ACTIVE_BROADCAST',
		'enumResult', undef, \$LiveBroadcast,
		'enumResult', undef, 'LiveBroadcast',
		1
	);
	$Content = '';
	&PAB::setOutput( \$Content );
	$hr = &PAB::makeScriptAndRun(
		'admin/broadcast_lbot.htm',
		'admin.broadcast.lbot.pl'
	);
	&PAB::restoreOutput();
	if( $hr != S_OK ) {
		die $PAB::System::LastError;
	}
	$LiveBroadcast->setBuildClausel( '' );
	$hr = &PAB::File::write( $Setup{'PathHtml'} . 'lbot.html', $Content );
	if( $hr != S_OK ) {
		&PAB::CGI::logError( 'Could not write template:' );
		&PAB::CGI::logError( $PAB::System::LastError );
		return $hr;
	}
	return S_OK;
}
