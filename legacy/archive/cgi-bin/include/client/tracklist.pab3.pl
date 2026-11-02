use LWP::Simple ();

if( $P->{'show'} eq 'secimg' ) {
	header( "Content-Type: image/$_SESSION{'SECIMG_CT'}" );
	header( "Content-Length: " . length( $_SESSION{'SECIMG_DAT'} ) );
	header( "Cache-Control: no-cache" );
	header( "Expires: Thu, 01 Dec 1994 16:00:00 GMT" );
	binmode STDOUT;
	print $_SESSION{'SECIMG_DAT'};
	return 1;
}

if( $P->{'cmd'} eq 'request' ) {
	find_station()
		or goto show_main;
	$P->{'songid'} = int( $P->{'songid'} );
	$song = $db->selectrow_hashref(
		qq/
		SELECT * FROM $station->{'SAMDB'}.songlist
		WHERE ID = '$P->{"songid"}'
		/
	);
	$song && $song->{'songtype'} eq 'S'
		or return redirect( $P->{'backlink'} );
	my $image = GD::SecurityImage->new(
		'width' => 100,
	    'height' => 30,
	    'lines' => 10,
	    'angle' => 0,
	    'font' => $Setup{'PathData'} . 'security.ttf',
	    'ptsize' => 18,
	    'rnd_data' => [0..9],
	    'bgcolor' => '#100060',
	    'rndmax' => 5,
	);
	$image->random();
	$image->create( 'ttf' => 'ec', '#33ccff', '#0077ff' );
	$image->particle( 100, 0 );
	my ($image_data, $mime_type, $random_str) = $image->out;
	$_SESSION{'SECIMG_DAT'} = $image_data;
	$_SESSION{'SECIMG_CT'} = 'image/' . $mime_type;
	$_SESSION{'SECIMG_VAL'} = $random_str;
   	$pab3->make_script_and_run( 'client/tracklist.req.htm' );
	return 1;
}
elsif( $_POST{'cmd'} eq 'reqver' ) {
	find_station()
		or goto show_main;
	$_POST{'songid'} = int( $_POST{'songid'} );
	$song = $db->selectrow_hashref(
		qq/
		SELECT * FROM $station->{'SAMDB'}.songlist
		WHERE ID = '$_POST{"songid"}'
		/
	);
	$song && $song->{'songtype'} eq 'S'
		or return redirect( $_POST{'backlink'} );
	$_SESSION{'SECIMG_VAL'} && $_POST{'code'} eq $_SESSION{'SECIMG_VAL'}
		or return redirect( $_POST{'backlink2'} );
	$status = request_song( $song->{'ID'}, $ENV{'REMOTE_ADDR'} || 'none' );
	$status ||= 'Station might be offline or return invalid data.';
	delete $_SESSION{'SECIMG_VAL'};
   	$pab3->make_script_and_run( 'client/tracklist.req.htm' );
	return 1;
}
elsif( $P->{'station'} ) {
	find_station()
		or goto show_main;
	if( ! defined $P->{'offset'} ) {
		($max) = $db->selectrow_array(
			"SELECT COUNT(*) FROM $station->{'SAMDB'}.songlist" );
		$P->{'offset'} = int( rand( $max ) );
		$ENV{'QUERY_STRING'} .= '&offset=' . $P->{'offset'};
	}
	$P->{'limit'} = 20;
	$r_sng = $db->query(
		qq/
		SELECT SQL_CALC_FOUND_ROWS *, UCASE( CONCAT( artist, title ) ) as sort
		FROM $station->{'SAMDB'}.songlist
		WHERE songtype = 'S'
		ORDER BY sort
		LIMIT $P->{'limit'} OFFSET $P->{'offset'}
		/
	);
	
	($rows) = $db->selectrow_array( 'SELECT FOUND_ROWS()' );
	pageNavigation( $P->{'offset'}, $P->{'limit'}, $rows, 7 );
	
	$pab3->add_hashmap( 'MIXES', 'mix', [ $r_sng->fetch_names() ] );
	
	$pab3->make_script_and_run( 'client/tracklist.mix.htm' );
	return 1;
}

show_main:
$pab3->make_script_and_run( 'client/tracklist.htm' );

1;

sub find_station {
	$station = undef;
	$st->{'Id'} ||= '';
	foreach my $st( @{$Setup{'Station'}} ) {
		if( $P->{'station'} eq $st->{'Id'} ) {
			return undef unless $st->{'SAMDB'};
			return $station = $st;
		}
	}
	return undef;
}

sub request_song {
	my( $songid, $host ) = @_;
	my( $res, $doc, $status, $code, $msg );
	$res = LWP::Simple::get(
		'http://' . $station->{'SAMHOST'} . ':' . $station->{'SAMPORT'}
			. "/req/?songID=$songid&host=" . encode_uri_component( $host )
	) or return undef;
	$doc = $parser->parse_string( $res ) or return undef;
	if( 0 ) {
		my $tmp = $doc->toString( 1 );
		$tmp =~ s/</&lt;/gso;
		$tmp =~ s/>/&gt;/gso;
		print "<pre>$tmp</pre>\n";
	}
	($status) = $doc->getElementsByTagName( 'status' );
	$status or return undef;
	($code) = $status->getElementsByTagName( 'code' );
	$code or return undef;
	return 'OK' if $code->textContent eq '200';
	($msg) = $status->getElementsByTagName( 'message' );
	$msg or return undef;
	return $msg->textContent;
}
