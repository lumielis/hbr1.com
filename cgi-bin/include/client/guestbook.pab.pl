use PAB::Crypt::Xor;

$Guestbook = PAB::DBI::Table->new(
	'Table' => 'guestbook',
	'FormBase' => 'gb_',
);

$Form{'cmd'} ||= '';

$PAB::Crypt::Xor::Key = 'DFSE3-KX427-A2RRG-BL5XM-CB336';

die 'this guestbook has been closed';

if( %GLOBAL::GBFILTER ) {
	#&PAB::CGI::printVar( \%GLOBAL::GBFILTER );
	foreach( keys %GLOBAL::GBFILTER ) {
		$GLOBAL::GBFILTER{$_} --;
		if( $GLOBAL::GBFILTER{$_} <= 0 ) {
			&PAB::DBI::exec(
				'UPDATE '
					. &PAB::DBI::quoteId( 'guestbook' )
				. ' SET '
					. &PAB::DBI::quoteId( 'status' )
					. ' = '
					. &PAB::DBI::quote( '0' )
				. ' WHERE '
					. &PAB::DBI::quoteId( 'id' )
					. ' = '
					. &PAB::DBI::quote( $_ )
			);
			delete $GLOBAL::GBFILTER{$_};
		}
	}
}

if( $Form{'cmd'} eq 'submit' ) {
	$Guestbook->setFieldFormat(
		'submitter' => {
			'InputType' => 'exp',
			'InputFormat' => '^[0-9a-zA-ZöüäÖÜÄß \-_]+$',
			'ErrorFormat' => $RC{$UC_GB_SUB_ERR_FMT},
			'InputRequired' => 1,
			'ErrorRequired' => 1, #$RC{$UC_GB_SUB_ERR_REQ},
		},
		'email' => {
			'InputType' => 'email',
			'ErrorFormat' => $RC{$UC_GB_EML_ERR_FMT},
			'InputRequired' => 1,
			'ErrorRequired' => 1, #$RC{$UC_GB_EML_ERR_REQ},
		},
		'content' => {
			'InputType' => 'html',
			'InputRequired' => 1,
			'ErrorRequired' => 1, #$RC{$UC_GB_MSG_ERR_REQ},
		}
	);
	if( $Form{'send'} ) {
		if( $ENV{'REQUEST_METHOD'} ne 'POST' ||
			index( $ENV{'HTTP_REFERER'}, 'http://' . $Domainname ) != 0
		) {
			&PAB::CGI::pageError( $RC{$UC_INTERNALERROR} );
			goto GBSSUBMIT;
		}
		$Guestbook->fromForm();
		if( %FormError ) {
			&PAB::CGI::pageError( $RC{$UC_FORMERROR} );
		}
		else {
			$Guestbook->{'status'} = 1;
			$Guestbook->{'timestamp'} = time;
			$Guestbook->{'remote_addr'} = $ENV{'REMOTE_ADDR'};
			&filter2GuestbookEntry();
			$Guestbook->{'status'} = 2 if $ENV{'HTTP_USER_AGENT'} =~ m!^s*$!;
			$hr = $Guestbook->save();
			if( $hr != S_OK ) {
				&PAB::CGI::pageError( $RC{$UC_INTERNALERROR} );
				&PAB::CGI::logError( $PAB::System::LastError );
			}
			else {
				#&filterGuestbookEntry();
				$Form{'cmd'} = '';
				&PAB::CGI::pageMessage( $RC{$UC_GB_POSTING_COMPLETE} );
			}
		}
	}
	if( $Form{'cmd'} ) {
GBSSUBMIT:
		$hr = &PAB::makeScriptAndRun(
			'client/guestbook.submit.htm',
			'client.guestbook.submit.pl'
		);
		die $PAB::System::LastError if $hr != S_OK;
	}
}
if( ! $Form{'cmd'} ) {
	$Form{'offset'} ||= 0;
	$Form{'limit'} ||= 33;
	
	#&PAB::CGI::pageMessage( 'bla bla bla' );
	
	$Guestbook->setBuildClausel( 'status = 1' );
	$Guestbook->setCalcFoundRows( 1 );
	$hr = $Guestbook->setOrderField( 'timestamp DESC' );
	die $PAB::System::LastError if $hr != S_OK;
	$Guestbook->setLimit( $Form{'limit'}, $Form{'offset'} );
	$hr = $Guestbook->buildMapped( 'status > 0' );
	die $PAB::System::LastError if $hr != S_OK;
	$hr = $Guestbook->getRecordCount( $Record_Max );
	die $PAB::System::LastError if $hr != S_OK;
	&pageNavigation( $Form{'offset'}, $Form{'limit'}, $Record_Max );
	&PAB::registerLoop(
		'GUESTBOOK',
		'useResultMapped', \$GbEntry, $Guestbook,
		'useResultMapped', 'GbEntry', 'Guestbook'
	);
	$FM_Gb = $Guestbook->getFieldMap();
	&PAB::addHashMap( 'GUESTBOOK', 'GbEntry', $FM_Gb );
	$F_Gb_ID = $FM_Gb->{'id'};
	$F_Gb_Status = $FM_Gb->{'status'};
	@Filter = ();
	foreach $entry( $Guestbook->getResultMapped() ) {
		if( $entry->[$F_Gb_Status] == 2 ) {
			push @Filter, $entry->[$F_Gb_ID];
		}
	}
	if( @Filter ) {
		&PAB::DBI::exec(
			'UPDATE '
				. &PAB::DBI::quoteId( 'guestbook' )
			. ' SET '
				. &PAB::DBI::quoteId( 'status' )
				. ' = '
				. &PAB::DBI::quote( '0' )
			. ' WHERE '
				. &PAB::DBI::quoteId( 'id' )
				. ' = ?'
			, undef
			, \@Filter
		);
	}
	$hr = &PAB::makeScriptAndRun(
		'client/guestbook.main.htm',
		'client.guestbook.main.pl'
	);
	die $PAB::System::LastError if $hr != S_OK;
}

1;

sub filterGuestbookEntry {
	my( $f, $m );
	my( $f_gbf_field, $f_gbf_match, $f_gbf_case ) =
		( $FM_Gb_Filter->{'Field'}, $FM_Gb_Filter->{'Match'}
			, $FM_Gb_Filter->{'Case'} );
	$m = 0;
	foreach $f( @$Gb_Filter ) {
		if( $f->[$f_gbf_case] ) {
			if( $Guestbook->{$f->[$f_gbf_field]} =~ m!$f->[$f_gbf_match]! ) {
				$m ++;
			}
		}
		else {
			if( $Guestbook->{$f->[$f_gbf_field]} =~ m!$f->[$f_gbf_match]!i ) {
				$m ++;
			}
		}
	}
	if( $m ) {
		$GLOBAL::GBFILTER{$Guestbook->{'id'}} = 3;
	}
}

sub filter2GuestbookEntry {
	my( $f, $m );
	my( $f_gbf_field, $f_gbf_match, $f_gbf_case ) =
		( $FM_Gb_Filter->{'Field'}, $FM_Gb_Filter->{'Match'}
			, $FM_Gb_Filter->{'Case'} );
	$m = 0;
	foreach $f( @$Gb_Filter ) {
#		warn $Guestbook->{$f->[$f_gbf_field]} . ' ' . $f->[$f_gbf_match];
		if( $f->[$f_gbf_case] ) {
			if( $Guestbook->{$f->[$f_gbf_field]} =~ m!$f->[$f_gbf_match]! ) {
				$m = 1;
				last;
			}
		}
		else {
			if( $Guestbook->{$f->[$f_gbf_field]} =~ m!$f->[$f_gbf_match]!i ) {
				$m = 1;
				last;
			}
		}
	}
#	warn $m;
	if( $m ) {
		$Guestbook->{'status'} = 2;
	}
}
