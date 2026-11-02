require 'include/settings.pl';

if( $form{'action'} eq 'settings' ) {
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
  }
  &ssms::system::add_tag_handler( \$setting );
  &ssms::system::add_loop( '<LOOP_SETTINGS(.*)>', \&load_settings, \&view_settings, \&init_settings );
  &ssms::system::load( 'admin/settings.htm' );
}

1;
