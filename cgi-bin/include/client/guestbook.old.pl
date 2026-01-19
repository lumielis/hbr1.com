$setup{'time_gmt'} = 1;

require $path{'ssms'} . 'optimize.pl';

require 'include/guestbook.pl';

$cache{'guestbook'} = 'guestbook.html';
$cache{'guestbook_submit'} = 'guestbook_submit.html';

if( $form{'part'} eq 'submit' ) {
  $guestbook = new ssms::guestbook();
  #$guestbook->load( $form{'guestbook_id'} );
  if( $form{'step'} == 1 ) {
    $guestbook->from_form();
    if( defined %error ) {
      &page_error( 'There are errors. Please check the fields with red asterix.' );
    }
    unless( defined %error ) {
      foreach( @{ $set_guestbook{'filter_deny'} } ) {
        if( ${ $_ }{ 'object' } =~ /all/ ) {
          foreach $key( ( 'submitter', 'email', 'content' ) ) {
            if( $guestbook->{ $key } =~ /${ $_ }{ 'filter' }/ ) {
              $error{ 'guestbook_' .  $key } = 1;
            }
          }
        }
        else {
          if( $guestbook->{ ${ $_ }{ 'object' } } =~ /${ $_ }{ 'filter' }/ ) {
            $error{ 'guestbook_' .  ${ $_ }{ 'object' } } = 1;
          }
        }
      }
      if( defined %error ) {
        &page_error( 'Spam Filter: One or more items are not allowed.' );
      }
    }
    unless( defined %error ) {
      $sql = 'SELECT * FROM guestbook WHERE remote_addr = "' . $ENV{'REMOTE_ADDR'} . '" ORDER BY timestamp DESC LIMIT 0, 1';
      &ssms::dbi::query( $sql, 1 );
      if( %row = &ssms::dbi::fetch( 1 ) ) {
        if( $row{'timestamp'} + $setup{'guestbook_repost_delay'} >= time ) {
          $error{'guestbook_timestamp'} = 1;
          &page_error( 'System: To prevent double entries, your address is blocked for a few time.' );
        }
      }
    }
    unless( defined %error ) {
      $guestbook->{'timestamp'} = time unless $guestbook->{'timestamp'};
      $guestbook->{'status'} = 1;
      $guestbook->{'remote_addr'} = $ENV{'REMOTE_ADDR'};
      $guestbook->save();
      #&ssms::optimize::precache_ex( 'action=guestbook', $cache{'guestbook'} );
      &page_message( 'Thank You.' );
      delete $form{'part'};
    }
  }
  else {
    #&ssms::optimize::preload_ex( $cache{'guestbook_submit'}, 1, 'action=guestbook&part=submit' );
  }
  if( $form{'part'} ) {
    &ssms::system::add_tag_handler( \$guestbook );
    &ssms::system::load( 'client/guestbook_submit.htm' );
  }
}
elsif( $form{'part'} eq 'precache' ) {
  &ssms::optimize::precache_ex( 'action=guestbook', $cache{'guestbook'} );
  &ssms::optimize::precache_ex( 'action=guestbook&part=submit', $cache{'guestbook_submit'} );
}
else {
  delete $form{'part'};
}
unless( $form{'part'} ) {
  #&ssms::optimize::preload_ex( $cache{'guestbook'}, 1, 'action=guestbook' );
  &ssms::system::add_tag_handler( \&view_guestbook );
  &ssms::system::add_loop( '<LOOP_GUESTBOOK(.*)>', \&load_guestbook, \&view_guestbook, \&init_guestbook );
  &ssms::system::load( 'client/guestbook_main.htm' );
}


1;

