use PAB::Constant qw(E_NORESULT);

1;

sub getStreams {
	my ( $id, $arg, $r_result, $sub ) = @_;
	$$r_result = $Station->{'Stream'};
	return S_OK;
}

sub initStream {
	$Stream = $_[0];
	if( $Stream->{'Type'} eq 'shoutcast' ) {
		unless( $Stream->{'Playlist'} ) {
			$Stream->{'Playlist'} =
				'http://' . $Stream->{'SC_Host'} . ':' . $Stream->{'SC_Port'} .
				'/listen.pls';
		}
	}
	elsif( $Stream->{'Type'} eq 'icecast2' ) {
		unless( $Stream->{'Playlist'} ) {
			$Stream->{'Playlist'} =
				'http://' . $Stream->{'IC2_Host'} . ':' .
				$Stream->{'IC2_Port'} .
				$Stream->{'IC2_Mount'} . '.m3u';
		}
	}
	return S_OK;
}

sub findMix {
	my( $artist, $title, $r_res ) = @_;
	my( $mix, $hr, $result, $artist1, $title1 );
	$hr = &PAB::DBI::query(
		'SELECT * FROM '
			. &PAB::DBI::quoteId( 'mixes' )
			. ' WHERE MATCH(dj) AGAINST(?)'
			. ' OR MATCH(name) AGAINST(?)'
		, undef
		, $artist, $title
	);
	return $hr if $hr != S_OK;
	if( $PAB::DBI::RowsAffected ) {
		$artist = lc( $artist );
		$artist =~ tr!\_! !;
		$title = lc( $title );
		$title =~ tr!\_! !;
		while( &PAB::DBI::fetchRowHashref( $mix ) == S_OK ) {
			$artist1 = lc( $mix->{'dj'} );
			$artist1 =~ tr!\_! !;
			$title1 = lc( $mix->{'name'} );
			$title1 =~ tr!\_! !;
			if( ( index( $artist, $artist1 ) >= 0
				|| index( $artist1, $artist ) >= 0 )
				&& ( index( $title, $title1 ) >= 0
				|| index( $title1, $title ) >= 0 )
			) {
				$$r_res = $mix->{'id'};
				return S_OK;
			}
		}
	}
	return E_NORESULT;
}

sub stationError {
	my $station = $_[0];
	my( $rt, $sql, $hr, $row, $title );
	$rt = PAB::DBI::Table->new( 'Table' => 'runtime' );
	$hr = $rt->loadById( $station->{'Id'} . '_Error' );
	$title = $station->{'Track_Artist'} . ' - ' . $station->{'Track_Title'};
	return if $hr == S_OK && $rt->{'Value'} eq $title;
	$rt->{'Name'} = $station->{'Id'} . '_Error';
	$rt->{'Value'} = $title;
	$rt->save();
	my $mail = &PAB::Mail::create();
	&PAB::Mail::addHeader( $mail,
		'From' => 'HBR1 ' . $station->{'Id'} . ' Station <station@hbr1.com>',
		'To' => 'pille@hbr1.com,spiralhead@hbr1.com,earlgrey@hbr1.com',
		'Subject' => $Station->{'Id'} . ' Station Fehler',
	);
	&PAB::Mail::addText( $mail,
		'Die Musik im ' . $station->{'Id'} .
			' Kanal ist abgelaufen.' .
			"\nMöglicherweise hängt der Stream",
	);
	$hr = &PAB::Mail::send( $mail );
	#die $PAB::System::LastError if $hr != S_OK;
}

sub setupStations {
	my(
		$np_fields, $np_data, %np_fm, $f_id, $f_station, $f_time, $f_length,
		$hr, $index, $station_id, $time, $start_time, $end_time, $npf,
		$time_left, $station, $np2f, $np2d, $duration
	);
	
	$hr = &PAB::DBI::query(
		'SELECT * FROM `nowplaying` ORDER BY `Station`, `Id`'
	);
	die $PAB::System::LastError if $hr != S_OK;
	
	$np_fields = $np_data = [];
	$hr = &PAB::DBI::fetchTable( $np_fields, $np_data );
	die $PAB::System::LastError if $hr != S_OK;
	
	$index = 0;
	%np_fm = ();
	foreach( @$np_fields ) {
		$np_fm{$_} = $index ++;
	}
	$f_id = $np_fm{'Id'};
	$f_station = $np_fm{'Station'};
	$f_time = $np_fm{'Time'};
	$f_length = $np_fm{'Length'};
	
	foreach $station( @{$Setup{'Station'}} ) {
		$station_id = $station->{'Id'};
		$npf = undef;
		if ($station->{'RadioDJ_DB'}) {
			$hr = &PAB::DBI::query(
				"SELECT * FROM $station->{'RadioDJ_DB'}.history ORDER BY ID DESC LIMIT 1"
			);
			die $PAB::System::LastError if $hr != S_OK;
			&PAB::DBI::fetchTable( $np2f, $np2d );
			$npf = $np2d->[0];
			$durationDiv = 1;
		} elsif( $station->{'SAMDB'} ) {
			$hr = &PAB::DBI::query(
				"SELECT * FROM $station->{'SAMDB'}.historylist ORDER BY ID DESC LIMIT 1"
			);
			die $PAB::System::LastError if $hr != S_OK;
			&PAB::DBI::fetchTable( $np2f, $np2d );
			$npf = $np2d->[0];
			$durationDiv = 1000;
		} else {
			foreach( @$np_data ) {
				if( $_->[$f_id] eq "${station_id}1" ) {
					$npf = $_;
					last;
				}
			}
		}
		if( $Server{$station_id}->{'Live'}  ) {
			$station->{'Track_Data'} = 1;
			$station->{'Track_Live'} = 1;
			$station->{'Track_Artist'} =
				$Server{$station_id}->{'Artist'};
			$station->{'Track_Title'} =
				$Server{$station_id}->{'Title'};
		}
		elsif( $npf ) {
			$station->{'Track_Data'} = 1;
			if( $station->{'RadioDJ_DB'} || $station->{'SAMDB'} ) {
				$index = 0;
				foreach( @$np2f ) {
					my $name = uc( substr( $_, 0, 1 ) ) . lc( substr( $_, 1 ) );
					$station->{'Track_' . $name} = $npf->[$index ++];
				}
				$start_time = &PAB::Format::datetimeAsUnix($station->{'Track_Date_played'});
				$duration = $station->{'Track_Duration'};
				$duration = $duration < 0 ? -1 : int($duration / $durationDiv);
			}
			else {
				$index = 0;
				foreach( @$np_fields ) {
					$station->{'Track_' . $_} = $npf->[$index ++];
				}
				$start_time = &PAB::Format::datetimeAsUnix( $npf->[$f_time] );
				$duration = &PAB::Format::unformatDuration( $npf->[$f_length] );
			}
			$end_time = $start_time + $duration;
			$time = time;
			$time_left = $end_time - $time;
			if( $duration < 0 && $time_left > -14400 ) {
				$station->{'Track_Error'} = 0;
				$station->{'Track_Duration'} = '';
			}
			elsif( $time_left < -600 ) {
				$station->{'Track_Error'} = 1;
				$station->{'Track_Duration'} = '';
				&stationError( $station );
			}
			elsif( $time_left < 0 ) {
				$station->{'Track_Error'} = 0;
				$station->{'Track_Duration'} = '??:??:??';
			}
			else {
				$station->{'Track_Error'} = 0;
				$station->{'Track_Duration'} =
					&PAB::Format::formatDuration( $time_left );
			}
			$station->{'MixID'} = undef;
			$hr = &findMix(
				$station->{'Track_Artist'},
				$station->{'Track_Title'},
				\$station->{'MixID'}
			);
			die $PAB::System::LastError if $hr != S_OK && $hr != E_NORESULT;
		}
		else {
			$station->{'Track_Data'} = 0;
		}
		if( $station->{'Track_Data'} ) {
			if( $station->{'Track_Artist'} && $station->{'Track_Title'} ) {
				$station->{'Track_Caption'} = $station->{'Track_Artist'} .
					' - ' . $station->{'Track_Title'};
			}
			elsif( $station->{'Track_Artist'} ) {
				$station->{'Track_Caption'} = $station->{'Track_Artist'};
			}
			elsif( $station->{'Track_Title'} ) {
				$station->{'Track_Caption'} = $station->{'Track_Title'};
			}
			else {
				$station->{'Track_Caption'} = '[no title]';
			}
		}
	}
}

