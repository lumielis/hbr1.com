if( $form{'part'} eq 'edit' ) {
  $form{'category_id'} = 0 unless $form{'category_id'};
  if( $form{'step'} eq 'new' ) {
    $sql = 'INSERT INTO galleries (category) VALUES(' . $form{'category_id'} . ');';
    &ssms::dbi::exec( $sql );
    $sql = 'SELECT id FROM galleries ORDER BY id DESC LIMIT 0,1;';
    $form{'photo_gallery_id'} = ( &ssms::dbi::query( $sql ) )[0];
    $form{'step'} = 0;
  }
  if( $form{'step'} == 1 ) {
    $sql = 'UPDATE galleries SET '
         . 'client = "' . $form{'photo_gallery_client'} . '", '
         . 'name = "' . $form{'photo_gallery_name'} . '", '
         . 'category = ' . $form{'category_id'} . ', '
         . 'status = ' . $form{'photo_gallery_status'}
         . ' WHERE id = ' . $form{'photo_gallery_id'} . ';';
    &ssms::dbi::exec( $sql );
    &page_message( 'Your gallery has been saved' );
  }
  &init_gallery_category( $form{'category_id'} );
  &init_gallery( $form{'photo_gallery_id'} );
  &ssms::system::add_tag_handler( \&view_gallery, \&view_gallery_category );
  if( ! defined %error ) {
    &to_form( \%photo_gallery, 'photo_gallery' );
  }
  $sql = 'SELECT id, name FROM media_category WHERE flags = 2;';
  &ssms::dbi::query( $sql, 1 );
  while( %row = &ssms::dbi::fetch( 1 ) ) {
    $photo_gallery_category{$row{'id'}} = $row{'name'};
  }
  &ssms::system::add_loop( '<GALLERY_CATEGORIES>', \&load_gallery_categories, \&view_gallery_category, \&init_gallery_category );
  &ssms::system::add_loop( '<GALLERY_PICTURES>', \&load_gallery_pictures, \&view_gallery_picture, \&init_gallery_picture );
  &ssms::system::load( 'admin/gallery_edit.htm' );
}
else {
  if( $form{'category_id'} ) {
    &init_gallery_category( $form{'category_id'} );
    &ssms::system::add_loop( '<GALLERY_PICTURES>', \&load_gallery_pictures, \&view_gallery_picture, \&init_gallery_picture );
    &ssms::system::add_loop( '<PHOTO_GALLERIES>', \&load_galleries, \&view_gallery, \&init_gallery );
    &ssms::system::load( 'admin/galleries.htm' );
  }
  else {
    &ssms::system::add_loop( '<GALLERY_CATEGORIES>', \&load_gallery_categories, \&view_gallery_category, \&init_gallery_category );
    &ssms::system::load( 'admin/gallery_categories.htm' );
  }
}

sub to_form {
  my( $rhash, $base ) = @_;
  if( $rhash !~ /^HASH/ ) { return 0; }
  foreach( keys %{$rhash} ) {
    $form{$base.'_'.$_} = ${$rhash}{$_};
  }
  return 1;
}

# gallery categories

sub load_gallery_categories {
  my $sql = 'SELECT id FROM media_category WHERE flags = 2;';
  return &ssms::dbi::query( $sql );
}

sub init_gallery_category {
  my $id = shift;
  my $sql = 'SELECT * FROM media_category WHERE id = ' . $id . ';';
  &ssms::dbi::query( $sql, 1 );
  %media_category = &ssms::dbi::fetch( 1 );
}

sub view_gallery_category {
  $tag = shift if defined @_;
  if( $tag =~ /<MEDIA_CATEGORY_(.+)>/ ) { &out( $media_category{lc($1)} ); }
  elsif( $tag =~ /<IF_MEDIA_CATEGORY_(.+)>/ ) { &con( $media_category{lc($1)} ); }
  else { return 0; }
  return 1;
}

# galleries

sub load_galleries {
  my $args = shift;
  my %argv = &ssms::http::split_param( $args );
  my $sql = 'SELECT id FROM galleries WHERE status = 1 ' . $argv{'limit'} . ';';
  return &ssms::dbi::query( $sql );
}

sub init_gallery {
  my $id = shift;
  my $sql = 'SELECT * FROM galleries WHERE id = ' . $id . ';';
  &ssms::dbi::query( $sql, 1 );
  %photo_gallery = &ssms::dbi::fetch( 1 );
}

sub view_gallery {
  $tag = shift if defined @_;
  if( $tag =~ /<PHOTO_GALLERY_(.+)>/ ) { &out( $photo_gallery{lc($1)} ); }
  elsif( $tag =~ /<IF_PHOTO_GALLERY_(.+)>/ ) { &con( $photo_gallery{lc($1)} ); }
  elsif( $tag =~ /<SELECT_PHOTO_GALLERY_CATEGORY(.*)>/ ) {
    $out = '';
    foreach( keys %photo_gallery_category ) {
      $out .= $_ . 'þ' . $photo_gallery_category{$_} . 'ÿ';
    }
    &out( &input( 'photo_gallery_category', $1, 'select', substr( $out, 0, length( $out ) - 1 ) ) );
  }
  elsif( $tag =~ /<INPUT_PHOTO_GALLERY_(\w+)(.*)>/ ) { &out( &input( 'photo_gallery_' . lc( $1 ), $2, 'text' ) ); }
  else { return 0; }
  return 1;
}

# gallery pictures

sub load_gallery_pictures {
  my $args = shift;
  my %argv = &ssms::http::split_param( $args );
  my $sql = 'SELECT id FROM pictures WHERE gallery = ' . $photo_gallery{'id'} . ' ' . $argv{'limit'} . ';';
  return &ssms::dbi::query( $sql );
}

sub init_gallery_picture {
  my $id = shift;
  my $sql = 'SELECT * FROM pictures WHERE id = ' . $id . ';';
  &ssms::dbi::query( $sql, 1 );
  %gallery_picture = &ssms::dbi::fetch( 1 );
}

sub view_gallery_picture {
  $tag = shift if defined @_;
  if( $tag =~ /<GALLERY_PICTURE_(.+)>/ ) { &out( $gallery_picture{lc($1)} ); }
  elsif( $tag =~ /<IF_GALLERY_PICTURE_(.+)>/ ) { &con( $gallery_picture{lc($1)} ); }
  else { return 0; }
  return 1;
}

1;
