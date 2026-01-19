#!/opt/perl/bin/perl

require 'include/settings.inc';
require $path{'ssms'} . 'system.pl';
require $path{'ssms'} . 'dbi.pl';
&ssms::dbi::connect( $dbi{'dsn'}, $dbi{'user'}, $dbi{'pass'} );

$redir = '';
if( $form{'object'} eq 'label' ) {
  if( $form{'id'} ) {
    $sql = 'SELECT home FROM labels WHERE id = ' . $form{'id'} . ';';
    $redir = ( &ssms::dbi::query( $sql ) )[0];
    $sql = 'UPDATE labels SET hits = hits + 1 WHERE id = ' . $form{'id'} . ';';
    &ssms::dbi::exec( $sql );
  }
}
elsif( $form{'object'} eq 'release' ) {
  if( $form{'id'} ) {
    $sql = 'SELECT url_order FROM releases WHERE id = ' . $form{'id'} . ';';
    $redir = ( &ssms::dbi::query( $sql ) )[0];
    $sql = 'UPDATE releases SET hits = hits + 1 WHERE id = ' . $form{'id'} . ';';
    &ssms::dbi::exec( $sql );
  }
}
elsif( $form{'object'} eq 'partner' ) {
  if( $form{'id'} ) {
    $sql = 'SELECT home FROM partner WHERE id = "' . $form{'id'} . '";';
    $redir = ( &ssms::dbi::query( $sql ) )[0];
    $sql = 'UPDATE partner SET hits = hits + 1 WHERE id = "' . $form{'id'} . '";';
    &ssms::dbi::exec( $sql );
  }
}
if( $redir ) {
	print "Status: 302 Moved\n";
  print "Location: $redir \n\n";
}
else {
  &ssms::system::init( 1 );
  &ssms::system::load( 'error/404.htm' );
  &ssms::system::main();
}
