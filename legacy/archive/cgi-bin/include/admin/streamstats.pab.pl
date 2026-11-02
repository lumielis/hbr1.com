%SHOUTCAST = ();
%ICECAST2 = ();

$ENV{XML_SIMPLE_PREFERRED_PARSER} = 'XML::Parser';

&PAB::registerLoop(
	'Stations',
	$Setup{Station}, \&initStation, undef,
	'{ $Setup{\'Station\'} }', 'initStation', undef
);
&PAB::registerLoop(
	'Streams',
	\&getStreams, \&initStream, undef,
	'getStreams', 'initStream', undef
);

$hr = &PAB::makeScriptAndRun(
	'admin/streamstats.htm',
	'admin.streamstats.pl'
);
if( $hr != S_OK ) {
	die $PAB::System::LastError;
}
#$hr = &PAB::load( 'admin/station.htm', 'admin$station.ppc' );
#die $PAB::Error::LastError if $hr != S_OK;

1;

sub initStation {
	( $Station ) = @_;
	if( $Station->{'Type'} eq 'shoutcast' ) {
		if( $Station->{'LoadStats'} ) {
			$hr = &getShoutcastStats( $Station->{'SC_Stat'}, $Station );
			#warn $PAB::Error::LastError if $hr != S_OK;
		}
		unless( $Station->{'Playlist'} ) {
			$Station->{'Playlist'} =
				'http://' . $Station->{'SC_Host'} . ':' . $Station->{'SC_Port'} .
				'/listen.pls';
		}
	}
	elsif( $Station->{'Type'} eq 'icecast2' ) {
		if( $Station->{'LoadStats'} ) {
			$hr = &getIcecast2Stats(
				$Station->{'IC2_Stat'}, $Station->{'IC2_Mount'}, $Station
			);
			#warn $PAB::Error::LastError if $hr != S_OK;
		}
		unless( $Station->{'Playlist'} ) {
			$Station->{'Playlist'} =
				'http://' . $Station->{'IC2_Host'} . ':' . $Station->{'IC2_Port'} .
				$Station->{'IC2_Mount'} . '.m3u';
		}
	}
	if( $Station->{'Songtitle'} ) {
		$hr = &findMix( $Station->{'Songtitle'}, \$Station->{'MixID'} );
		if( $hr != S_OK && $hr != E_NORESULT ) {
#			warn $PAB::Error::LastError;
		}
	}
	return S_OK;
}

sub getStreams {
	my ( $id, $arg, $r_result, $sub ) = @_;
	$$r_result = $Station->{'Stream'};
	return S_OK;
}

sub initStream {
	( $Stream ) = @_;
	if( $Stream->{'Type'} eq 'shoutcast' ) {
		if( $Stream->{'LoadStats'} ) {
			$hr = &getShoutcastStats( $Stream->{'SC_Stat'}, $Stream );
			warn $PAB::Error::LastError if $hr != S_OK;
		}
		unless( $Stream->{'Playlist'} ) {
			$Stream->{'Playlist'} =
				'http://' . $Stream->{'SC_Host'} . ':' . $Stream->{'SC_Port'} .
				'/listen.pls';
		}
	}
	elsif( $Stream->{'Type'} eq 'icecast2' ) {
		if( $Stream->{'LoadStats'} ) {
			$hr = &getIcecast2Stats(
				$Stream->{'IC2_Stat'}, $Stream->{'IC2_Mount'}, $Stream
			);
			warn $PAB::Error::LastError if $hr != S_OK;
		}
		unless( $Stream->{'Playlist'} ) {
			$Stream->{'Playlist'} =
				'http://' . $Stream->{'IC2_Host'} . ':' . $Stream->{'IC2_Port'} .
				$Stream->{'IC2_Mount'} . '.m3u';
		}
	}
	return S_OK;
}

sub getShoutcastStats {
	my( $stat_url, $r_dat ) = @_;
	my( $xml, $hr );
	$r_dat->{'ServerStatus'} = 0;
	if( $SHOUTCAST{ $stat_url } ) {
		$xml = $SHOUTCAST{ $stat_url };
	}
	else {
		&PAB::System::sendLog( 'loading xml data from: ' . $stat_url );
		$hr = &PAB::XML::load( $stat_url, $xml );
		return $hr if $hr != S_OK;
		&PAB::System::sendLog( 'xml data loaded' );
		$SHOUTCAST{ $stat_url } = $xml;
	}
	return ERROR( E_NORESULT, $stat_url ) unless $xml;
	$r_dat->{'ServerStatus'} = 1;
	$r_dat->{'StreamStatus'} = $xml->{'STREAMSTATUS'};
	$r_dat->{'StationTitle'} = $xml->{'SERVERTITLE'};
	$r_dat->{'StationGenre'} = $xml->{'SERVERGENRE'};
	$r_dat->{'Listeners'} = $xml->{'CURRENTLISTENERS'};
	$r_dat->{'Songtitle'} = $xml->{'SONGTITLE'};
	$r_dat->{'ContentType'} = $xml->{'CONTENT'} . ' (' . $xml->{'BITRATE'} . 'kbps)';
	$r_dat->{'Status'} =
		'<b>' . $xml->{'SERVERTITLE'} . ' / <font color="#990099">Shoutcast ' . $xml->{'VERSION'} .
		' - ' . $r_dat->{'ContentType'} . '</font></b>' .
		"\n----------------------------------------\n" .
		'current: <b>' . &formatNumber( $xml->{'CURRENTLISTENERS'} ) . '</b>; ' .
		'peak: <b>' . &formatNumber( $xml->{'PEAKLISTENERS'} ) . '</b>; ' .
		'max: <b>' . &formatNumber( $xml->{'MAXLISTENERS'} ) . '</b>; ' .
		'stream-hits: <b>' . &formatNumber( $xml->{'STREAMHITS'} ) . '</b>; ' .
		'web-hits: <b>' . &formatNumber( $xml->{'WEBHITS'} ) . '</b>' .
		"\n----------------------------------------\n" .
		$r_dat->{'Songtitle'};
	return S_OK;
}

sub getIcecast2Stats {
	my( $stat_url, $mount, $r_dat ) = @_;
	my( $xml, $hr, $source );
	$r_dat->{'ServerStatus'} = 0;
	if( $ICECAST2{ $stat_url } ) {
		$xml = $ICECAST2{ $stat_url };
	}
	else {
		&PAB::System::sendLog( 'loading xml data from: ' . $stat_url );
		$hr = &PAB::XML::loadSecure( $stat_url, $xml );
		return $hr if $hr != S_OK;
		&PAB::System::sendLog( 'xml data loaded' );
		$ICECAST2{ $stat_url } = $xml;
	}
	return ERROR( E_NORESULT, $stat_url ) unless $xml;
	$r_dat->{'ServerStatus'} = 1;
	foreach $source(
		ref( $xml->{'source'} ) eq 'ARRAY' ?
			@{ $xml->{'source'} } :
			( $xml->{'source'} )
	) {
		if( $source->{'mount'} eq $mount ) {
			$r_dat->{'StreamStatus'} = 1;
			$r_dat->{'StationTitle'} = $source->{'server_name'};
			$r_dat->{'StationGenre'} = $source->{'genre'};
			$r_dat->{'Listeners'} = $source->{'listeners'};
			$r_dat->{'Songtitle'} = $source->{'artist'} . ' - ' . $source->{'title'};
			#$r_dat->{'Server'} = $xml->{'server'};
			$r_dat->{'ContentType'} =
				$source->{'subtype'} . ' (' . $source->{'bitrate'} . ')';
			$r_dat->{'Status'} =
				'<b>' . $source->{'server_name'} . ' / <font color="#000099">' . $xml->{'server'} .
				' - ' . $r_dat->{'ContentType'} . '</font></b>' .
				"\n----------------------------------------\n" .
				'current: <b>' . &formatNumber( $source->{'listeners'} ) . '</b>; ' .
				'peak: <b>' . &formatNumber( $source->{'listener_peak'} ) . '</b>; ' .
				'max: <b>' . (
					$source->{'max_listeners'} !~ /\D/
						? &formatNumber( $source->{'max_listeners'} )
						: $source->{'max_listeners'}
					)
					. '</b>; (' .
				'clients: <b>' . &formatNumber( $xml->{'clients'} ) . '</b>; ' .
				'client_con: <b>' . &formatNumber( $xml->{'client_connections'} ) . '</b>; ' .
				'connect: <b>' . &formatNumber( $xml->{'connections'} ) . '</b>; ' .
				'sources: <b>' . &formatNumber( $xml->{'sources'} ) . '</b>;) ' .
				"\n----------------------------------------\n" .
				'src_client_con: <b>' . &formatNumber( $xml->{'source_client_connections'} ) . '</b>; ' .
				'src_relay_con: <b>' . &formatNumber( $xml->{'source_relay_connections'} ) . '</b>; ' .
				'src_total_con: <b>' . &formatNumber( $xml->{'source_total_connections'} ) . '</b>' .
				"\n----------------------------------------\n" .
				$r_dat->{'Songtitle'};
			return S_OK;
		}
	}
	return ERROR( E_NOTFOUND, $mount );
}

use constant {
	MIX_ID		=> 0,
#	MIX_ARTIST	=> 1,
	MIX_TITLE	=> 1,
};

sub findMix {
	my( $song, $r_res ) = @_;
	my( $mix, $title, $artist, $hr, $result );
	if( $song =~ /(.+)? +- +(.+)/ ) {
		$artist = lc( $1 );
		$title = lc( $2 );
	}
	else {
		$artist = '';
		$title = lc( $song );
	}
	if( $artist ) {
		$hr = &PAB::DBI::selectAllArrayref(
			'SELECT id, LCASE(name) FROM `mixes` WHERE '
			. 'LCASE(dj) LIKE "%' . $artist . '%"',
			$result
		);
	}
	else {
		$hr = &PAB::DBI::selectAllArrayref(
			'SELECT id, CONCAT( LCASE(dj), " - ", LCASE(name) ) FROM `mixes`',
			$result
		);
	}
	return $hr if $hr != S_OK;
	foreach $mix( @{ $result } ) {
		if( index( $title, $mix->[MIX_TITLE] ) >= 0 ||
			index( $mix->[MIX_TITLE], $title ) >= 0 )
		{
			$$r_res = $mix->[MIX_ID];
			return S_OK;
		}
	}
	return E_NORESULT;
}
