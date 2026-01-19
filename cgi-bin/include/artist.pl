package main;

use vars qw( %artist );

our $artist_category = 4;

sub load_artist {
  my( $arg, %arg, $sql, @res, $index, %row );
  $arg = shift;
  %arg = &ssms::http::split_param( $arg );
  $sql = 'SELECT client_person.*, country.name AS country_name '
       . 'FROM client_person, country '
       . 'WHERE client_person.category = ' . $artist_category . ' AND client_person.country = country.id';
  if( $arg{'order'} ) {
    $sql .= ' ORDER BY ' . $arg{'order'} . ' ' . $arg{'ordir'};
  }
  @res = ();
  $index = 0;
  &ssms::dbi::query( $sql, 1 );
  while( %row = &ssms::dbi::fetch( 1 ) ) {
    %{ $res[ $index ++ ] } = %row;
  }
  return @res;
}

sub init_artist {
  my $id = shift;
  my( $sql );
  if( $id =~ /^HASH/ ) {
    *artist = $id;
  }
  else {
    $sql = 'SELECT client_person.*, country.name AS country_name '
         . 'FROM client_person, country '
         . 'WHERE client_person.category = ' . $artist_category . ' AND client_person.country = country.id';
    &ssms::dbi::query( $sql, 1 );
    %artist = &ssms::dbi::fetch( 1 );
  }
}

sub view_artist {
  $tag = shift if defined @_;
  if( $tag =~ /<ARTIST_(\w+)>/ ) { &out( $artist{ lc( $1 ) } ); }
  elsif( $tag =~ /<IF_ARTIST_(\w+)>/ ) { &con( $artist{ lc( $1 ) } ); }
  else { return 0; }
  return 1;
}

1;
