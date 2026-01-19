&PAB::System::require(
	$ENV{'SCRIPT_PATH'} . './include/client/station.pab.pl'
);

&setupStations();

$hr = &PAB::registerLoop(
	'Stations',
	$Setup{'Station'}, \&initStationPlaylist, undef,
	'{ $Setup{\'Station\'} }', 'initStationPlaylist', undef
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
$hr = &PAB::registerLoop(
	'Playlist',
	\&getPlaylist, \&initPlaylist, undef,
	'getPlaylist', 'initPlaylist', undef
);
if( $hr != S_OK ) {
	die $PAB::System::LastError;
}

$hr = &PAB::makeScriptAndRun(
	'client/playlist.htm',
	'client.playlist.pl'
);
if( $hr != S_OK ) {
	die $PAB::System::LastError;
}

1;

sub initStationPlaylist {
	my( $file, $index, $hr, $row, $pl, $tmp );
	
	$Station = $_[0];
#	&initStation( $Station );
	
	if( $Station->{'SAMDB'} ) {
		$pl = [];
		$hr = &PAB::DBI::query(
			"SELECT artist, title FROM $Station->{'SAMDB'}.historylist"
			. " ORDER BY ID DESC LIMIT 1"
		);
		die $PAB::System::LastError if $hr != S_OK;
		$hr = &PAB::DBI::fetchRowArrayref( $row );
		if( $hr == S_OK ) {
			if( $row->[0] ) {
				push @$pl, $row->[0] . ' - ' . $row->[1];
			}
			else {
				push @$pl, $row->[1];
			}
		}
		$Station->{'CTO'} = 0;
		$hr = &PAB::DBI::query(
			"SELECT artist, title, requestID FROM $Station->{'SAMDB'}.queuelist"
			. " LEFT JOIN $Station->{'SAMDB'}.songlist"
				. " ON queuelist.songID = songlist.ID"
			. " WHERE songtype = 'S'"
			. " ORDER BY queuelist.sortID"
		);
		die $PAB::System::LastError if $hr != S_OK;
		while( &PAB::DBI::fetchRowArrayref( $row ) == S_OK ) {
			$tmp = ($row->[0] ? $row->[0] . ' - ' : '') . $row->[1];
			$tmp = '<font color="#9999cc"><b>*</b></font> ' . $tmp if $row->[2] > 0;
			push @$pl, $tmp;
		}
		#warn join( "<br>", @$pl );
		$Station->{'Playlist'} = $pl;
	}
	else {
		$file = $Setup{'PathPlaylist'} . $Station->{'Id'} . '_Playlist.txt';
		open FILE, '< ' . $file or warn "could not open file: $!";
		@{$Station->{'Playlist'}} = <FILE>;
		close FILE;
		$index = 0;
		foreach( @{$Station->{'Playlist'}} ) {
			chomp;
			next unless $_;
			if( substr( $_, 0, 3 ) eq '<b>' ) {
				$_ = substr( $_, 3, -9 );
				$Station->{'Current_Song_Id'} = $index;
			}
			else {
				$_ = substr( $_, 0, -5 );
			}
			$index ++;
		}
	}
	#&PAB::CGI::printVar( $Station->{'Playlist'} );
	return S_OK;
}

sub getPlaylist {
	my( $id, $arg, $r_result, $sub ) = @_;
	my( $i, $len, $offset );
	$len = @{$Station->{'Playlist'}};
	$offset = $Station->{'CTO'};
	$$r_result = [];
	for $i( 1 .. 8 ) {
		if( $offset >= $len ) {
			push @{$$r_result},
				'<a href="pab3.cgi?run=tracklist&station=' . $Station->{'Id'} . '">'
					. '<font color="#999999">[play another mix]</font></a>';
		}
		else {
			push @{$$r_result}, $Station->{'Playlist'}->[$offset ++];
		}
	}
	return S_OK;
}

sub initPlaylist {
	$Playlist = $_[0];
	return S_OK;
}

