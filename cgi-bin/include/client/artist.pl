require 'include/artist.pl';

if( $form{'action'} eq 'artist' ) {
  &ssms::system::add_loop( '<LOOP_ARTIST(.*)>', \&load_artist, \&view_artist, \&init_artist );
  &ssms::system::load( 'client/artist_index.htm' );
}

1;
