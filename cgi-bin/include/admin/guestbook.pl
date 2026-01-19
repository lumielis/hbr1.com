require 'include/guestbook.pl';
require 'include/settings.pl';

if( $form{'part'} eq 'status' ) {
  $guestbook = new ssms::guestbook();
  $guestbook->load( $form{'guestbook_id'} );
  $guestbook->{'status'} = $form{'status'};
  $guestbook->save();
  &page_message( 'Der Eintrag wurde erfolgreich aktualisiert' );
  delete $form{'part'};
}
elsif( $form{'part'} eq 'kill' ) {
  $guestbook = new ssms::guestbook();
  $guestbook->load( $form{'guestbook_id'} );
  $guestbook->kill();
  &page_message( 'Der Eintrag wurde erfolgreich gelöscht' );
  delete $form{'part'};
}
elsif( $form{'part'} eq 'settings' ) {
  $setting = new ssms::setting();
  if( $form{'step'} == 1 ) {
    $index = 1;
    while( $form{ 'id_' . $index } ) {
      if( defined $form{ 'value_' . $index } ) {
        $setting->load( $form{ 'id_' . $index } );
        $value = &from_form( 'value_' . $index, $setting->{'type'}, $setting->{'required'} );
        unless( $error{ 'value_' . $index } ) {
          $setting->{'value'} = $value;
          $setting->save();
          delete $form{ 'value_' . $index };
        }
      }
      $index ++;
    }
    if( defined %error ) {
      &page_error( 'Es sind Fehler aufgetreten. Alle fehlerfreien Felder wurden jedoch aktualisiert.' );
    }
    else {
      &page_message( 'Die Einstellungen wurden in das System &uuml;bernommen.' );
    }
    &write_settings( $setup{'userfile'} );
    do $setup{'userfile'};
    delete $form{'part'};
  }
}
elsif( $form{'part'} eq 'update' ) {
	$guestbook = new ssms::guestbook();
	@result = $guestbook->build( '1' );
	foreach( @result ) {
		$guestbook->load( ${ $_ }{'id'} );
		$guestbook->{'content'} = &ssms::system::to_sql( &ssms::system::to_input( ${ $_ }{'content'} ) );
		$guestbook->{'submitter'} = &ssms::system::to_sql( &ssms::system::to_input( ${ $_ }{'submitter'} ) );
		$guestbook->save();
	}
}
else {
  delete $form{'part'};
}
unless( $form{'part'} ) {
  $setting = new ssms::setting();
  &ssms::system::add_tag_handler( \&view_guestbook );
  &ssms::system::add_tag_handler( \$setting );
  &ssms::system::add_loop( '<LOOP_SETTINGS(.*)>', \&load_settings, \&view_settings, \&init_settings );
  &ssms::system::add_loop( '<LOOP_GUESTBOOK(.*)>', \&load_guestbook, \&view_guestbook, \&init_guestbook );
  &ssms::system::load( 'admin/guestbook_index.htm' );
}

1;
