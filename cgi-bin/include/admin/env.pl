&ssms::system::add_loop( '<LOOP_ENV>', \&load_env, \&view_env, \&init_env );
&ssms::system::load( 'admin/env.htm' );

sub load_env {
  my( @res );
  foreach( keys %ENV ) {
    #%row = ( key => $_, value => $ENV{ $_ } );
    push @res, { key => $_, value => $ENV{ $_ } };
  }
  return @res;
}

sub init_env {
  *env = shift;
}

sub view_env {
  $tag = shift if defined @_;
  if( $tag =~ /<ENV_(\w+)>/ ) { &out( $env{ lc( $1 ) } ); }
  elsif( $tag =~ /<IF_ENV_(\w+)>/ ) { &con( $env{ lc( $1 ) } ); }
  else { return 0; }
  return 1;
}

1;
