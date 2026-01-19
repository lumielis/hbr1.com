if( $form{'action'} eq 'webtv' ) {
  if( $form{'part'} eq 'unlock' ) {
    require $path{'ssms'} . 'file.pl';
    &ssms::file::set_mode( 0777, $path{'webtv'} . '/images', 1 );
  }
  elsif( $form{'part'} eq 'lock' ) {
    require $path{'ssms'} . 'file.pl';
    &ssms::file::set_mode( 0755, $path{'webtv'} . '/images', 1 );
  }
  else {
    if( $form{'step'} == 1 ) {
      #&check_client();
      #&setup_webtv();
      my $client_id = $form{'client'};
      $form{'client'} = undef;
      $error{'login'} = 1 if ! &load_client( 'webtv.htm' );
      $form{'client'} = $client_id;
      $res = &check_client() if ! $res;
      #die $client->get( 'pin' );
      if( ! defined %error ) {
        &setup_webtv();
        $error{'webtv_refresh'} = 1 if $form{'webtv_Refresh'} < 250 || $form{'webtv_Refresh'} > 10000;
        if( defined %error ) {
          &page_error( 'There are some errors... please check the red marked fields' );
        }
      }
      if( ! defined %error ) {
        my @tmp = ();
        foreach( @{${$active_tv_folder->{options}}{Image}} ) {
          if( $form{'webtv_image_'.$_} ) {
            $path = $active_tv_folder->{path} . '/' . $_;
            unlink $path;
          }
          else {
            push @tmp, $_;
          }
        }
        @{${$active_tv_folder->{options}}{Image}} = @tmp;
        $counter = 1;
        while( defined $form{'webtv_upload_'.$counter} ) {
          if( $form{'webtv_upload_'.$counter} ) {
            @tmp = &upload_file( 'webtv_upload_' . $counter, $active_tv_folder->{'path'}, '<.FF>', 'jpg|gif|jpeg', 1 );
            if( $tmp[1] ) {
              if( ${$active_tv_folder->{options}}{Image} !~ /ARRAY\(/ ) {
                $tmp[0] = ${$active_tv_folder->{options}}{Image};
                ${$active_tv_folder->{options}}{Image} = [ $tmp[0], $tmp[1] ];
              }
              else {
                push @{${$active_tv_folder->{options}}{Image}}, $tmp[1];
              }
            }
          }
          $counter ++;
        }
        ${$active_tv_folder->{options}}{'Refresh'} = $form{'webtv_Refresh'};
        ${$active_tv_folder->{options}}{'Shuffle'} = $form{'webtv_Shuffle'};
        ${$active_tv_folder->{options}}{'Enabled'} = $form{'webtv_Enabled'};
        ${$active_tv_folder->{options}}{'Anchor'} = $form{'webtv_Anchor'};
        ${$active_tv_folder->{options}}{'Text'} = $form{'webtv_Text'} ? '5|115|' . $form{'webtv_Text'} : undef;
        $active_tv_folder->save();
        &page_message( 'Your data has been send out to space' );
      }
    }
    else {
      &load_client( 'webtv_login.htm' );
      &setup_webtv();
      &webtv_to_form( $active_tv_folder );
    }
    &ssms::system::load( 'client/webtv.htm' );
  }
}

sub setup_webtv {
  require 'include/webtv.pl';
  $webtv = ssms::webtv->new();
  $webtv->load( $path{'webtv'} . '/images' );
  $client_tv = $webtv->get_child( 'clients' );
  $client->set( 'webtv', $client_tv->add_child( $client->get( 'id' ) ) );
  $webtv->save();
  *active_tv_folder = \$client->get( 'webtv' );
  &ssms::system::add_tag_handler( \&display_webtv );
  &ssms::system::add_loop( '<WEBTV_IMAGES>', \&build_webtv_image_loop, \&display_webtv_image, \&init_webtv_image );
  &ssms::system::add_loop( '<WEBTV_FOLDERS>', \&build_webtv_folder_loop, \&display_webtv, \&init_webtv_folder );
  &ssms::system::add_tag_handler( \&display_login );
  
}

sub webtv_to_form {
  my $folder = shift;
  return 0 unless $folder;
  my( $key, $val );
  foreach $key( keys %{$folder->{options}} ) {
    $val = ${$folder->{options}}{$key};
    if( $val =~ /ARRAY\(/ ) {
      
    }
    else {
      $form{'webtv_'.$key} = $val;
    }
  }
  if( $form{'webtv_Text'} ) {
    $form{'webtv_Text'} = ( split( /\|/, $form{'webtv_Text'} ) )[2];
  }
}

sub display_webtv {
  $tag = shift if @_;
  if( $tag =~ /<WEBTV_OPTIONS_(.+)>/ ) { &out( ${$active_tv_folder->{options}}{$1} ); }
  elsif( $tag =~ /<WEBTV_(.+)>/ ) { &out( $active_tv_folder->{lc($1)} ); }
  elsif( $tag =~ /<ACTIVATE_WEBTV_CLIENT>/ ) { *active_tv_folder = \$client->get( 'webtv' ); }
  elsif( $tag =~ /<ACTIVATE_WEBTV_PARENT>/ ) {
    if( $active_tv_folder->{parent} ) {
      *active_tv_folder = \$active_tv_folder->{parent};
    }
  }
  elsif( $tag =~ /<IF_WEBTV_OPTIONS_(.+)>/ ) { &con( ${$active_tv_folder->{options}}{$1} ); }
  elsif( $tag =~ /<INPUT_WEBTV_(Enabled)(.*)>/ ) { &out( &input( 'webtv_' . $1, $2, 'select', '0þNoÿ1þYes' ) ); }
  elsif( $tag =~ /<INPUT_WEBTV_(Shuffle)(.*)>/ ) { &out( &input( 'webtv_' . $1, $2, 'select', '0þNoÿ1þYes' ) ); }
  elsif( $tag =~ /<INPUT_WEBTV_Upload_(\d+)(.*)>/ ) { &out( &input( 'webtv_upload_' . $1, $2, 'file' ) ); }
  elsif( $tag =~ /<INPUT_WEBTV_(\w+)(.*)>/ ) { &out( &input( 'webtv_' . $1, $2, 'text' ) ); }
  else { return 0; }
  return 1;
}

sub build_webtv_folder_loop {
  return () unless $active_tv_folder;
  my @out = ();
  foreach( keys %{$active_tv_folder->{child}} ) {
    push @out, \${$active_tv_folder->{child}}{$_};
  }
  return @out;
}

sub init_webtv_folder {
  my $folder_ref = shift;
  *active_tv_folder = $folder_ref;
  return () unless $active_tv_folder;
}

sub build_webtv_image_loop {
  return () unless $active_tv_folder;
  if( ${$active_tv_folder->{options}}{Image} !~ /ARRAY\(/ ) {
    return ( ${$active_tv_folder->{options}}{Image} );
  }
  return @{${$active_tv_folder->{options}}{Image}};
}

sub init_webtv_image {
  $active_tv_image = shift;
}

sub display_webtv_image {
  $tag = shift if @_;
  if( $tag =~ /<WEBTV_IMAGE>/ ) { &out( $active_tv_image ); }
  elsif( $tag =~ /<IF_WEBTV_IMAGE>/ ) { &con( $path{'webtv'} . '/clients/' . $active_tv_image ); }
  elsif( $tag =~ /<SELECT_WEBTV_IMAGE(.*)>/ ) { &out( '<input type="checkbox" name="webtv_image_' . $active_tv_image . '" value="1"' . $1 . '>' ); }
  else { return 0; }
  return 1;
}

1;
