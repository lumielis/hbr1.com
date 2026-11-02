require $path{'ssms'} . 'xml.pl';

if( $form{'action'} eq 'station' ) {
  &ssms::system::add_loop( '<LOOP_STATIONS(.*)>', \&load_station, \&view_station, \&init_station );
  &ssms::system::add_loop( '<LOOP_STREAMS(.*)>', \&load_stream, \&view_stream, \&init_stream );
  &ssms::system::load( 'client/station.htm' );
}

sub load_station {
  return sort keys %station_def;
}

sub init_station {
  my $id = shift;
  *station = $station_def{ $id };
  &ssms::xml::clear();
  #die $station{'shout_stat'};
  $xml_data = &ssms::xml::load( $station{'shout_stat'}, 1 );
  $station{'mix_id'} = &find_mix( &find_xml_data( $xml_data, 'SONGTITLE' ) );
}

sub view_station {
  $tag = shift if defined @_;
  if( $tag =~ /<STATION_(\w+)>/ ) { &out( $station{ lc( $1 ) } ); }
  elsif( $tag =~ /<SHOUTCASTSERVER_(\w+)>/ ) { &out( &find_xml_data( $xml_data, $1 ) ); }
  elsif( $tag =~ /<IF_STATION_(\w+) == (\d+)>/ ) { &con( $station{ lc( $1 ) } == $2 ); }
  elsif( $tag =~ /<IF_STATION_(\w+) eq (\w+)>/ ) { &con( $station{ lc( $1 ) } eq $2 ); }
  elsif( $tag =~ /<IF_STATION_(\w+)>/ ) { &con( $station{ lc( $1 ) } ); }
  elsif( $tag =~ /<IF_SHOUTCASTSERVER_(\w+) == (\d+)>/ ) { &con( &find_xml_data( $xml_data, $1 ) == $2 ); }
  elsif( $tag =~ /<IF_SHOUTCASTSERVER_(\w+) eq (\w+)>/ ) { &con( &find_xml_data( $xml_data, $1 ) eq $2 ); }
  elsif( $tag =~ /<IF_SHOUTCASTSERVER_(\w+)>/ ) { &con( &find_xml_data( $xml_data, $1 ) ); }
  elsif( $tag =~ /<IF_SSMS_XML_LOADED>/ ) { &con( @{$xml_data} ); }
  else { return 0; }
  return 1;
}

sub load_stream {
	#%stream_arg = &ssms::http::split_param( shift );
	return sort keys %{ $station{'streams'} };
}

sub init_stream {
  my $id = shift;
  *stream = ${ $station{'streams'} }{ $id };
  #if( $stream{'type'} eq 'shoutcast' ) {
	  #&ssms::xml::clear();
	  #$xml_data = &ssms::xml::load( $stream{'shoutcast_stats'}, 1 );
  #}
}

sub view_stream {
  $tag = shift if defined @_;
  if( $tag =~ /<STREAM_(\w+)>/ ) { &out( $stream{ lc( $1 ) } ); }
  elsif( $tag =~ /<IF_STREAM_(\w+) == (\d+)>/ ) { &con( $stream{ lc( $1 ) } == $2 ); }
  elsif( $tag =~ /<IF_STREAM_(\w+) eq (\w+)>/ ) { &con( $stream{ lc( $1 ) } eq $2 ); }
  elsif( $tag =~ /<IF_STREAM_(\w+)>/ ) { &con( $stream{ lc( $1 ) } ); }
  else { return 0; }
  return 1;
}

sub find_xml_data {
  my( $data, $keyword ) = @_;
  my $i = 0;  
  return '' if $data !~ /^ARRAY/;
  foreach( @{$data} ) {
    if( /$keyword/ ) {
      return ${${$data}[$i+1]}[2];
    }
    $i ++;
  }
  foreach( @{$data} ) {
    if( /^ARRAY/ ) {
      return &find_xml_data( $_, $keyword );
    }
  }
}

sub find_mix {
  my $mix = shift;
  $mix =~ s/.+? - (.+)/$1/;
  #$mix =~ s/[\_\|\(\)]\$\&\%\@\/\\\'\"]/ /g;
  $mix =~ s/\W+/ /g;
  my $sql = 'SELECT id, name FROM mixes;';
  my @found = ();
  &ssms::dbi::query( $sql, 1 );
  while( %mix = &ssms::dbi::fetch( 1 ) ) {
    #$mix{'name'} =~ s/[\_\|\(\)]\$\&\%\@\/\\\'\"]/ /g;
    $mix{'name'} =~ s/\W+/ /g;
    if( $mix =~ /$mix{'name'}/i ) {
      push @found, length( $mix{'name'} ) . '|' . $mix{'id'} . '|' . $mix{'name'};
    }
    elsif( $mix{'name'} =~ /$mix/i ) {
      push @found, length( $mix{'name'} ) . '|' . $mix{'id'} . '|' . $mix{'name'};
    }
  }
  @found = sort @found;
  %found = ( best => 99999, id => 0 );
  foreach( @found ) {
    @row = split( /\|/, $_ );
    $found{'length'} = $row[0] - length( $mix );
    $found{'length'} = - $found{'length'} if $found{'length'} < 0;
    if( $found{'length'} < $found{'best'} ) {
      $found{'best'} = $found{'length'};
      $found{'id'} = $row[1];
    }
  }
  return $found{'id'};
}

1;
