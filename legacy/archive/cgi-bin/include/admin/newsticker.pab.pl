$NewsTicker = PAB::DBI::Table->new(
	'Table' => 'news_ticker',
	'FormBase' => 'NT'
);

if( $Form{'Action'} eq 'delete' ) {
	if( $Form{'doDelete'} ) {
		$hr = $NewsTicker->deleteById( $Form{'NT_Id'} );
		if( $hr == S_OK ) {
			&PAB::CGI::logMessage( 'Entry has been removed' );
			&writeNewsTicker();
		}
		else {
			&PAB::CGI::logError(
				'Unabled to remove entry.',
				$PAB::System::LastError
			);
		}
	}
	$Form{'Action'} = '';
}
if( $Form{'Action'} eq 'edit' ) {
	if( $Form{'doAdd'} ) {
		$NewsTicker->fromForm();
		$NewsTicker->{'Id'} = undef;
		if( ! defined %FormError ) {
			$NewsTicker->{'Time'} = &PAB::Format::unixAsDatetime( time );
			$hr = $NewsTicker->save();
			if( $hr == S_OK ) {
				&PAB::CGI::logMessage( 'Entry has been added' );
				$NewsTicker->toForm();
				&writeNewsTicker();
			}
			else {
				&PAB::CGI::logError(
					'Unabled to add entry.',
					$PAB::System::LastError
				);
			}
		}
	}
	elsif( $Form{'doSave'} && $Form{'NT_Id'} ) {
		$hr = $NewsTicker->loadById( $Form{'NT_Id'} );
		$NewsTicker->fromForm();
		if( ! defined %FormError ) {
			$NewsTicker->{'Time'} = &PAB::Format::unixAsDatetime( time );
			$NewsTicker->save();
			if( $hr == S_OK ) {
				&PAB::CGI::logMessage( 'Entry has been saved' );
				$NewsTicker->toForm();
				&writeNewsTicker();
			}
			else {
				&PAB::CGI::logError(
					'Unabled to save entry.',
					$PAB::System::LastError
				);
			}
		}
	}
	else {
		if( $Form{'NT_Id'} ) {
			$hr = $NewsTicker->loadById( $Form{'NT_Id'} );
		}
		$NewsTicker->toForm();
	}
	$Form{'Action'} = '';
}
unless( $Form{'Action'} ) {
	$NewsTicker->setBuildClausel();
	$NewsTicker->setOrderField( 'Time' );
	$NewsTicker->setOrderDesc();
	$NewsTicker->build();
	&PAB::registerLoop(
		'NEWS_TICKER',
		'enumResult', undef, $NewsTicker,
		'enumResult', undef, 'NewsTicker',
		1
	);
	$hr = &PAB::makeScriptAndRun(
		'admin/newsticker.htm',
		'admin.newsticker.pl'
	);
	if( $hr != S_OK ) {
		die $PAB::System::LastError;
	}
}

1;

sub writeNewsTicker {
	my $out = &PAB::getOutput();
	$NewsTicker->setOrderField( 'Time' );
	$NewsTicker->setOrderDesc();
	$NewsTicker->setBuildClausel( 'Status = 1' );
	$NewsTicker->build();
	&PAB::registerLoop(
		'NEWS_TICKER',
		'enumResult', undef, $NewsTicker,
		'enumResult', undef, 'NewsTicker',
		1
	);
	&PAB::setOutput( \&output );
	$hr = &PAB::makeScriptAndRun(
		'admin/newsticker_template.htm',
		'admin.newsticker.template.pl'
	);
	if( $hr != S_OK ) {
		die $PAB::System::LastError;
	}
	open FILE, '> ' . $Setup{'PathHtml'} . 'newsticker.html';
		or die "unable to open file: $!";
	print FILE $Output;
	close FILE;
	&PAB::setOutput( $out );
}

sub output {
	foreach( @_ ) {
		if( defined $_ ) {
			$Output .= $_;
		}
	}
}

__END__
