if( $form{'action'} eq 'userman' ) {
  $client = ssms::client->new();
  if( $form{'part'} eq 'profile' ) {
    &part_profile();
  }
  elsif( $form{'part'} eq 'account' ) {
    &part_account();
  }
  elsif( $form{'part'} eq 'remove' ) {
    &part_remove();
  }
  else {
    &ssms::system::add_loop( '<CLIENTS>', \&load_clients, \&display_client, \&init_client );
    &ssms::system::load( 'admin/clients.htm' );
  }
}

# form handler

sub part_profile {
  &init_client( $form{'client'} );
  $res = 1;
  if( $form{'step'} == 1 ) { # update profile
    if( ! $form{'client_id'} ) {
      $res = 0;
      &page_error( 'Please insert a valid Id first' );
    }
    elsif( $form{'client_id'} != $form{'client'} ) {
      $client2 = ssms::client->new();
      $client2->load( $form{'client_id'} );
      if( $client2->get( 'first_name' ) ne '' ) {
        $res = 0;
        &page_error( 'Cannot take Id ' . $client2->get( 'id' ) . ', because it is the id for client <a href="' . $url{'system'} . '/' . $ENV{'FILE'} . '?action=userman&part=profile&client=' . $client2->get( 'id' ) . '">' . $client2->get( 'first_name' ) . ' ' . $client2->get( 'last_name' ) . '</a>' );
      }
    }
    else {
      $charge = &ssms::system::money_unformat( $form{'client_charge'} );
      delete $form{'client_charge'};
      &form_to_client();
      if( defined %error ) {
        $res = 0;
        &page_error( 'There are some errors, please check your input' );
      }
    }
    if( $res ) {
      $diff = $charge - $client->get( 'charge' );
      if( $diff != 0 ) {
        $client->account()->commission( $diff, 'Automatic Transfer', $admin->get( 'first_name' ) . ' ' . $admin->get( 'last_name' ) );
        &log_action( 'Charge for client ' . $client->get( 'first_name' ) . ' '
                   . $client->get( 'last_name' ) . ' (' . $client->get( 'id' )
                   . ') hase been set from ' . &ssms::system::money_format( $client->get( 'charge' ) )
                   . ' to ' . &ssms::system::money_format( $charge ) );
      }
      $client->set( 'fingerprint', md5_hex( $client->get( 'id' ) . $client->get( 'pin' ) ) );
      $client->save();
      &log_action( 'Client ' . $client->get( 'first_name' ) . ' ' . $client->get( 'last_name' )
                 . ' (' . $client->get( 'id' ) . ') has been updated' );
      $client->load( $client->get( 'id' ) );
      $sql = 'SELECT id FROM newsletter_client WHERE email = "' . $client->get( 'email' ) . '";';
      if( ! ( &ssms::dbi::query( $sql ) )[0] ) {
        $sql = 'INSERT INTO newsletter_client (client,email,status) VALUES("'
             . $client->get( 'id' ) . '","' . $client->get( 'email' ) . '","1");';
        &ssms::dbi::exec( $sql );
        &log_action( 'Client ' . $client->get( 'id' ) . ' has been added to the newsletter' );
      }
      &page_message( 'Client has been updated' );
    }
  }
  &client_to_form() if $res;
  &ssms::system::add_tag_handler( \&display_client );
  &ssms::system::load( 'admin/client_profile.htm' );
}

sub part_account {
  $client->load( $form{'client'} );
  if( $form{'step'} == 1 ) { # add commission
    $amount = &ssms::system::money_unformat( $form{'commission_amount'} );
    if( $amount !~ /\d+/g ) {
      &page_error( 'Please specify the amount first' );
    }
    elsif( ! $form{'commission_info'} ) {
      &page_error( 'Please enter an information' );
    }
    elsif( $client->get( 'charge' ) + $amount < 0 ) {
      &page_error( 'You can not take more then you have' );
      $form{'commission_amount'} = - &ssms::system::money_format( $client->get( 'charge' ) );
    }
    else {
      $client->account()->commission( $amount, &ssms::http::area_to_html( $form{'commission_info'} ), $form{'commission_sender'} );
      &log_action( 'Bank transfer for client ' . $client->get( 'first_name' ) . ' '
                 . $client->get( 'last_name' ) . ' (' . $client->get( 'id' )
                 . '), amount = ' . &ssms::system::money_format( $amount ) );
      $form{'commission_amount'} = '0,00';
      $form{'commission_info'} = '';
    }
  }
  else {
    $form{'commission_sender'} = $admin->get( 'first_name' ) . ' ' . $admin->get( 'last_name' );
    $form{'commission_amount'} = '0,00';
  }
  &ssms::system::add_tag_handler( \&display_client, \&display_commission );
  &ssms::system::add_loop( '<COMMISSIONS>', \&load_commissions, \&display_commission, \&init_commission );
  &ssms::system::load( 'admin/client_account.htm' );
}

sub part_remove {
  $client->load( $form{'client'} );
  $client->kill();
  &log_action( 'Client ' . $client->get( 'first_name' ) . ' ' . $client->get( 'last_name' )
             . ' (' . $client->get( 'id' ) . ') has been removed' );
  &ssms::system::add_loop( '<CLIENTS>', \&load_clients, \&display_client, \&init_client );
  &ssms::system::load( 'admin/clients.htm' );
}

# client(s) person

sub client_to_form {
  $form{'client_id'} = $client->get( 'id' );
  $form{'client_pin'} = $client->get( 'pin' );
  $form{'client_category'} = $client->get( 'category' );
  $form{'client_title'} = $client->get( 'title' );
  $form{'client_first_name'} = $client->get( 'first_name' );
  $form{'client_last_name'} = $client->get( 'last_name' );
  $form{'client_street'} = $client->get( 'street' );
  $form{'client_city'} = $client->get( 'city' );
  $form{'client_zip'} = $client->get( 'zip' );
  $form{'client_state'} = $client->get( 'state' );
  $form{'client_country'} = $client->get( 'country' );
  $form{'client_email'} = $client->get( 'email' );
  $form{'client_phone'} = $client->get( 'phone' );
  $form{'client_charge'} = &ssms::system::money_format( $client->get( 'charge' ) );
  $form{'client_status'} = $client->get( 'status' );
  $tmp = $client->get( 'birthday' );
  if( $tmp ) {
    $form{'client_birthday'} = substr( $tmp, 6, 2 ) . '.' . substr( $tmp, 4, 2 ) . '.' . substr( $tmp, 0, 4 );
  }
  else {
    $form{'client_birthday'} = 'TT.MM.YYYY';
  }
}

sub form_to_client {
  $error{'client_id'} = 1 if ! $form{'client_id'};
  $client->set( 'id', $form{'client_id'} );
  $error{'client_pin'} = 1 if ! $form{'client_pin'};
  $client->set( 'pin', $form{'client_pin'} );
  $error{'client_category'} = 1 if ! $form{'client_category'};
  $client->set( 'category', $form{'client_category'} );
  $client->set( 'title', $form{'client_title'} );
  $error{'client_first_name'} = 1 if ! $form{'client_first_name'};
  $client->set( 'first_name', $form{'client_first_name'} );
  $error{'client_last_name'} = 1 if ! $form{'client_last_name'};
  $client->set( 'last_name', $form{'client_last_name'} );
  $error{'client_street'} = 1 if ! $form{'client_street'};
  $client->set( 'street', $form{'client_street'} );
  $error{'client_city'} = 1 if ! $form{'client_city'};
  $client->set( 'city', $form{'client_city'} );
  $error{'client_zip'} = 1 if ! $form{'client_zip'};
  $client->set( 'zip', $form{'client_zip'} );
  #$error{'client_state'} = 1 if ! $form{'client_state'};
  $client->set( 'state', $form{'client_state'} );
  $client->set( 'country', $form{'client_country'} );
  $error{'client_email'} = 1 if $form{'client_email'} !~ /.+\@.+\..+/;
  $client->set( 'email', $form{'client_email'} );
  $error{'client_phone'} = 1 if ! $form{'client_phone'};
  $client->set( 'phone', $form{'client_phone'} );
  $client->set( 'charge', &ssms::system::money_unformat( $form{'client_charge'} ) ) if defined $form{'client_charge'};
  $client->set( 'status', $form{'client_status'} );
  $tmp = $form{'client_birthday'};
  $tmp =~ s/\./\//g;
  @tmp = split( /\//, $tmp );
  $tmp[0] = '0' . $tmp[0] if length( $tmp[0] ) < 2;
  $tmp[1] = '0' . $tmp[1] if length( $tmp[1] ) < 2;
  $tmp[2] = '19' . $tmp[2] if length( $tmp[2] ) == 2;
  $error{'client_birthday'} = 1 if $tmp[0] <= 0 || $tmp[0] > 32 || $tmp[1] <= 0 || $tmp[1] > 12 || $tmp[2] < 0;
  $client->set( 'birthday', $tmp[2] . $tmp[1] . $tmp[0] );
  $client->set( 'time', time ) if ! $client->get( 'time' );
}

sub load_clients {
  my %arg = &ssms::http::split_param( shift );
  my $order = ' ORDER BY ' . $form{'order'} . ' ' . $form{'ordir'} if $form{'order'};
  &ssms::dbi::query( 'SELECT id FROM client_person' . $order . ';', 1 );
  my %row;
  $arg{from} = 0 if ! $arg{from};
  $arg{to} = -1 if ! $arg{to};
  my $position = -1;
  @client_tab = ();
  while( 1 ) {
    @row = &ssms::dbi::fetch();
    last if ! @row;
    $position ++;
    last if $arg{to} >= 0 && $arg{to} < $position;
    push @client_tab, join( '|', @row ) if $arg{from} <= $position;
  }
  return @client_tab;
}

sub init_client {
  $client->load( shift );
  $sql = 'SELECT * FROM client_category WHERE id = "' . $client->get( 'category' ) . '";';
  &ssms::dbi::query( $sql, 1 );
  %client_category = &ssms::dbi::fetch( 1 );
}

sub display_client {
  $tag = shift if @_;
  if( $tag =~ /<CLIENT_ID>/i ) { &out( $client->get( 'id' ) ); }
  elsif( $tag =~ /<CLIENT_PIN>/i ) { &out( $client->get( 'pin' ) ); }
  elsif( $tag =~ /<CLIENT_TITLE>/i ) { &out( $client->get( 'title' ) == 1 ? 'Frau' : 'Herr' ); }
  elsif( $tag =~ /<CLIENT_FIRST_NAME>/i ) { &out( $client->get( 'first_name' ) ); }
  elsif( $tag =~ /<CLIENT_LAST_NAME>/i ) { &out( $client->get( 'last_name' ) ); }
  elsif( $tag =~ /<CLIENT_STREET>/i ) { &out( $client->get( 'street' ) ); }
  elsif( $tag =~ /<CLIENT_STATE>/i ) { &out( $client->get( 'state' ) ); }
  elsif( $tag =~ /<CLIENT_ZIP>/i ) { &out( $client->get( 'zip' ) ); }
  elsif( $tag =~ /<CLIENT_CITY>/i ) { &out( $client->get( 'city' ) ); }
  elsif( $tag =~ /<CLIENT_COUNTRY>/i ) {
    &out( $client->get( 'country' ) );
  }
  elsif( $tag =~ /<CLIENT_COUNTRY_FULL>/i ) {
    &out( ( &ssms::dbi::query( 'SELECT name FROM country WHERE id = "' . $client->get( 'country' ) . '";' ) )[0] );
  }
  elsif( $tag =~ /<CLIENT_BIRTHDAY>/i ) {
    $tmp = $client->get( 'birthday' );
    &out( substr( $tmp, 6, 2 ) . '.' . substr( $tmp, 4, 2 ) . '.' . substr( $tmp, 0, 4 ) );
  }
  elsif( $tag =~ /<CLIENT_CHARGE>/i ) { &out( &ssms::system::money_format( $client->get( 'charge' ), 1 ) ); }
  elsif( $tag =~ /<CLIENT_EMAIL>/i ) { &out( $client->get( 'email' ) ); }
  elsif( $tag =~ /<CLIENT_PHONE>/i ) { &out( $client->get( 'phone' ) ); }
  elsif( $tag =~ /<CLIENT_SIGNUP_TIME>/i ) {
    ( $ftm, $fdt ) = &ssms::time::format_time( $client->get( 'time' ) );
    &out( $ftm );
  }
  elsif( $tag =~ /<CLIENT_SIGNUP_DATE>/i ) {
    ( $ftm, $fdt ) = &ssms::time::format_time( $client->get( 'time' ) );
    &out( $fdt );
  }
  elsif( $tag =~ /<CLIENT_FINGERPRINT>/i ) { &out( $client->get( 'fingerprint' ) ); }
  elsif( $tag =~ /<CLIENT_CATEGORY_(.+)>/i ) { &out( $client_category{lc($1)} ); }
  elsif( $tag =~ /<CLIENT_(.+)>/i ) { &out( $client->get( lc( $1 ) ) ); }
  elsif( $tag =~ /<IF_CLIENT_(.+)>/i ) { &add_con( $client->get( lc( $1 ) ) ); }
  elsif( $tag =~ /<IF_CLIENT_CATEGORY_(.+)>/i ) { &con( $client_category{lc($1)} ); }
  elsif( $tag =~ /<INPUT_CLIENT_ID(.*)>/i ) { &out( &input( 'client_id', $1, 'text' ) ); }
  elsif( $tag =~ /<INPUT_CLIENT_PIN(.*)>/i ) { &out( &input( 'client_pin', $1, 'text' ) ); }
  elsif( $tag =~ /<INPUT_CLIENT_FIRST_NAME(.*)>/i ) { &out( &input( 'client_first_name', $1, 'text' ) ); }
  elsif( $tag =~ /<INPUT_CLIENT_LAST_NAME(.*)>/i ) { &out( &input( 'client_last_name', $1, 'text' ) ); }
  elsif( $tag =~ /<INPUT_CLIENT_STREET(.*)>/i ) { &out( &input( 'client_street', $1, 'text' ) ); }
  elsif( $tag =~ /<INPUT_CLIENT_CITY(.*)>/i ) { &out( &input( 'client_city', $1, 'text' ) ); }
  elsif( $tag =~ /<INPUT_CLIENT_ZIP(.*)>/i ) { &out( &input( 'client_zip', $1, 'text' ) ); }
  elsif( $tag =~ /<INPUT_CLIENT_COUNTRY(.*)>/i ) {
    $out = '';
    foreach( &ssms::dbi::query( 'SELECT * FROM country ORDER BY name;' ) ) {
      @row = split( /\|/, $_ );
      $out .= "$row[0]\xFE$row[1]\xFF";
    }
    $out .= "ZZ\xFEOther";
    &out( &input( 'client_country', $1, 'select', $out ) );
  }
  elsif( $tag =~ /<INPUT_CLIENT_CATEGORY(.*)>/i ) {
    &ssms::dbi::query( 'SELECT * FROM client_category ORDER BY name;', 1 );
    $out = '';
    while( ( %row = &ssms::dbi::fetch( 1 ) ) ) {
      $out .= $row{'id'} . "\xFE" . $row{'name'} . "\xFF";
    }
    &out( &input( 'client_category', $1, 'select', $out ) );
  }
  elsif( $tag =~ /<INPUT_CLIENT_STATE(.*)>/i ) { &out( &input( 'client_state', $1, 'text' ) ); }
  elsif( $tag =~ /<INPUT_CLIENT_EMAIL(.*)>/i ) { &out( &input( 'client_email', $1, 'text' ) ); }
  elsif( $tag =~ /<INPUT_CLIENT_PHONE(.*)>/i ) { &out( &input( 'client_phone', $1, 'text' ) ); }
  elsif( $tag =~ /<INPUT_CLIENT_CHARGE(.*)>/i ) { &out( &input( 'client_charge', $1, 'text' ) ); }
  elsif( $tag =~ /<INPUT_CLIENT_BIRTHDAY(.*)>/i ) { &out( &input( 'client_birthday', $1, 'text' ) ); }
  elsif( $tag =~ /<INPUT_CLIENT_STATUS(.*)>/i ) {
    &out( &input( 'client_status', $xt = $1, 'select', "0\xFEDisabled\xFF1\xFEMember\xFF2\xFEAdministrator" ) );
  }
  elsif( $tag =~ /<INPUT_CLIENT_TITLE(.*)>/i ) {
    &out( &input( 'client_title', $xt = $1, 'select', "1\xFEFrau\xFF2\xFEHerr" ) );
  }
  else { return 0; }
  return 1;
}

sub new_client_id {
  &ssms::dbi::query( 'SELECT id FROM client_signup ORDER BY id DESC', 1 );
  my %row = &ssms::dbi::fetch( 1 );
  $row{'id'} ++;
  return $row{'id'};
}

sub new_client_pin {
  my $length = @_ ? shift : 5;
  my $i;
  my $out = '';
  for( $i = 0; $i < $length; $i ++ ) {
    $out .= int( rand( 9 ) );
  }
  return $out;
}

# client account

sub form_to_commission {
}

sub load_commissions {
  $client->account()->load( $form{'time_from'}, $form{'time_to'}, $form{'order'}, $form{'ordir'} );
  $form{'from'} = 0 if ! $form{'from'};
  $form{'count'} = 5 if ! $form{'count'};
  $form{'max'} = $client->account()->get( 'count' );
  $form{'to'} = $form{'from'} + $form{'count'};
  my @tmp = ();
  my $i = 0;
  for( $i = 0; $i < $client->account()->get( 'count' ); $i ++ ) {
    push @tmp, $i;
  }
  return @tmp;
}

sub init_commission {
  $commission = shift;
}

sub display_commission {
  $tag = shift if @_;
  if( $tag =~ /<COMMISSION_ID>/i ) { &out( $client->account()->get( 'id', $commission ) ); }
  elsif( $tag =~ /<COMMISSION_CHARGE>/i ) {
    &out( &ssms::system::money_format( $client->account()->get( 'charge', $commission ), 1 ) );
  }
  elsif( $tag =~ /<COMMISSION_AMOUNT>/i ) {
    &out( &ssms::system::money_format( $client->account()->get( 'amount', $commission ), 1 ) );
  }
  elsif( $tag =~ /<COMMISSION_TIME>/i ) {
    ( $ftm, $fdt ) = &ssms::time::format_time( $client->account()->get( 'time', $commission ) );
    &out( $ftm );
  }
  elsif( $tag =~ /<COMMISSION_DATE>/i ) {
    ( $ftm, $fdt ) = &ssms::time::format_time( $client->account()->get( 'time', $commission ) );
    &out( $fdt );
  }
  elsif( $tag =~ /<COMMISSION_SENDER>/i ) { &out( $client->account()->get( 'sender', $commission ) ); }
  elsif( $tag =~ /<COMMISSION_PREV_CHARGE>/i ) {
    &out( &ssms::system::money_format( $client->account()->get( 'charge', $commission ) - $client->account()->get( 'amount', $commission ), 1 ) );
  }
  elsif( $tag =~ /<COMMISSION_INFO>/i ) {
    $out = $client->account()->get( 'info', $commission );
    $out =~ s/\"/\\\"/g;
    eval( '$out = "' . $out . '"' );
    &out( $out );
  }
  elsif( $tag =~ /<INPUT_COMMISSION_SENDER(.*)>/i ) { &out( &input( 'commission_sender', $xt = $1, 'text' ) ); }
  elsif( $tag =~ /<INPUT_COMMISSION_AMOUNT(.*)>/i ) { &out( &input( 'commission_amount', $xt = $1, 'text' ) ); }
  elsif( $tag =~ /<INPUT_COMMISSION_INFO(.*)>/i ) { &out( &input( 'commission_info', $xt = $1, 'area' ) ); }
  else { return 0; }
  return 1;
}

1;
