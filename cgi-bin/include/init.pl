use Digest::MD5 qw(md5_hex);

require $ENV{SCRIPT_PATH} . './include/settings.inc';
require $path{'ssms'} . 'system.pl';
require $path{'ssms'} . 'dbi.pl';
require $ENV{SCRIPT_PATH} . './include/client.pl';
#&ssms::system::init( 1 );
&ssms::dbi::connect( $dbi{'dsn'}, $dbi{'user'}, $dbi{'pass'} );
$client = new ssms::client();

if( $form{'second_action'} eq 'access' ) {
  $cookie{'id_101'} = $form{'id_101'} if defined $form{'id_101'};
  $cookie{'id_102'} = $form{'id_102'} if defined $form{'id_102'};
}

#$ssms::http::debug = 2;
&ssms::http::save_cookie( 'id_101=' . $cookie{'id_101'}, $setup{'cookie_domain'}, $setup{'cookie_path'} );
&ssms::http::save_cookie( 'id_102=' . $cookie{'id_102'}, $setup{'cookie_domain'}, $setup{'cookie_path'} );

#die &ssms::access::ip_info( $ENV{'REMOTE_ADDR'} );
if( $save_log ) {
  &ssms::access::write_log( $path{'access_log'}, undef, 'access_log', $path{'logresolve'}, $path{'access_sav'} );
  $now = time;
  $sql = 'SELECT id FROM access_log_index WHERE time BETWEEN "' . ( $now - 86400 ) . '" AND "' . $now . '";';
  if( ! ( &ssms::dbi::query( $sql ) )[0] ) {
    %now = &ssms::time::as_hash( $now );
    $tmb = Time::Local::timelocal( 0, 0, 0, $now{'day'}, $now{'month'} - 1, $now{'year'} );
    $sql = 'SELECT id FROM access_log_index WHERE time = "' . ( $tmb - 86400 ) . '";';
    $id = ( &ssms::dbi::query( $sql ) )[0];
    $id = 'id > "' . $id . '" AND ' if $id;
    $sql = 'SELECT id FROM access_log WHERE ' . $id . 'time BETWEEN "' . $tmb . '" AND "' . ( $tmb + 86400 ) . '";';
    &ssms::dbi::query( $sql, 1 );
    $id = ( &ssms::dbi::fetch() )[0];
    if( $id ) {
      $sql = 'INSERT INTO access_log_index (id,time) VALUES("' . $id . '","' . $tmb . '");';
      &ssms::dbi::exec( $sql );
    }
  }
}

#if( $ENV{'FILE'} eq 'admin.cgi' ) {
#  if( $form{'action'} ne 'setup' && ! &check_access( 2 ) ) {
#    $form{'id_101'} = $cookie{'id_101'};
#    &ssms::system::add_tag_handler( \&display_login );
#    &ssms::system::load( 'admin/login_old.htm' );
#    &ssms::system::main();
#    exit;
#  }
#}

sub check_access {
  my $level = shift;
  $level = 2 if ! $level;
  $admin = ssms::client->new();
  return 0 if ! $admin->load( $cookie{'id_101'} );
  return 0 if $admin->get( 'status' ) < $level;
  return 0 if $admin->get( 'pin' ) != $cookie{'id_102'};
  1;
}

sub check_client {
  $client = ssms::client->new();
  if( $form{'id_101'} ) {
    $client->load( $form{'id_101'} );
    if( ! $form{'id_102'} || $form{'id_102'} ne $client->get( 'pin' ) ) {
      &page_error( 'This account does not exist!' );
      return 0;
    }
    return 1;
  }
  elsif( $form{'client'} ) {
    $client->load( ( &ssms::dbi::query( 'SELECT id FROM client_person WHERE fingerprint = "' . $form{'client'} . '";' ) )[0] );
    if( $client->get( 'fingerprint' ) ne $form{'client'} ) {
      &page_error( 'This key does not exist!' );
      return 0;
    }
    return 1;
  }
  return 0;
}

sub load_client {
  require 'include/admin/userman.pl';
  &ssms::system::add_tag_handler( \&display_client );
  return 1 if &check_client();
  &ssms::system::add_tag_handler( \&display_login );
  &ssms::system::load( 'client/' . $_[0] );
  $setup{'load_denied'} = 1;
}

sub display_login {
  $tag = shift if @_;
  if( $tag =~ /<INPUT_LOGIN_USER(.*)>/i ) { &out( &input( 'id_101', $xt = $1, 'text' ) ); }
  elsif( $tag =~ /<INPUT_LOGIN_PASS(.*)>/i ) { &out( &input( 'id_102', $xt = $1, 'pass' ) ); }
  else { return 0; }
  1;
}

sub log_action {
  my( $action, $client_id, $time ) = @_;
  return;
  return 0 if ! $action;
  $time = time if ! $time;
  $client_id = ( $ENV{'FILE'} eq 'admin.cgi' ? $admin->get( 'id' ) : $client->get( 'id' ) ) if ! defined $client_id;
  $sql = 'INSERT INTO action_log (action,client,time) VALUES( '
       . '"' . $action . '","' . $client_id . '","' . $time . '");';
  &ssms::dbi::exec( $sql );
  return 1;
}

sub get_media_category {
  my $type = shift;
  my $sql = 'SELECT * FROM media_category WHERE type = "' . $type . '";';
  &ssms::dbi::query( $sql, 1 );
  return &ssms::dbi::fetch( 1 );
}

1;
