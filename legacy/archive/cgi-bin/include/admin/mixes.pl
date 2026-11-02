$sql = 'SELECT id, name FROM release_types;';
&ssms::dbi::query( $sql, 1 );
while( %row = &ssms::dbi::fetch( 1 ) ) {
  $release_type{$row{'id'}} = $row{'name'};
}
$sql = 'SELECT id, name FROM media_category WHERE flags = 1;';
&ssms::dbi::query( $sql, 1 );
while( %row = &ssms::dbi::fetch( 1 ) ) {
  $mix_category{$row{'id'}} = $row{'name'};
}
if( $form{'action'} eq 'mixes' ) {
  require 'include/admin/userman.pl';
  &ssms::system::add_tag_handler( \&display_client );
  if( $form{'part'} eq 'edit' ) {
    if( $form{'step'} eq 'new' ) {
      &new_mix();
    }
    elsif( $form{'step'} == 1 ) {
      &save_mix();
    }
    elsif( $form{'step'} == 2 ) {
      &add_release();
    }
    elsif( $form{'step'} == 3 ) {
      &add_label();
    }
    $sql = 'SELECT id,name FROM labels ORDER BY name;';
    &ssms::dbi::query( $sql, 1 );
    $out = '';
    $i = 0;
    while( %row = &ssms::dbi::fetch( 1 ) ) {
      $label_name{$row{'id'}} = $row{'name'};
      $label_order[$i] = $row{'id'};
      $i ++;
    }
    &init_mix( $form{'mix_id'} );
    &ssms::system::add_tag_handler( \&view_mix, \&view_release_input, \&view_label_input );
    &mix_to_form();
    &ssms::system::add_loop( '<LOOP_RELEASES>', \&load_releases, \&view_release, \&init_release );
    &ssms::system::load( 'admin/mix_edit.htm' );
  }
  unless( $form{'part'} ) {
    if( $form{'step'} == 1 ) {
      &remove_mixes();
    }
    &ssms::system::add_loop( '<LOOP_MIX_CATEGORIES>', \&load_mix_categories, \&view_mix_category, \&init_mix_category );
    &ssms::system::add_loop( '<LOOP_MIXES>', \&load_mixes, \&view_mix, \&init_mix );
    &ssms::system::add_loop( '<LOOP_RELEASES>', \&load_releases, \&view_release, \&init_release );
    &ssms::system::load( 'admin/mix_index.htm' );
  }
}

sub load_mixes {
  my( $sql );
  if( $form{'cat_id'} ) {
    $sql = 'SELECT id FROM mixes WHERE status = 1 AND category = ' . $form{'cat_id'} . ' ORDER BY name;';
  }
  else {
    $sql = 'SELECT id FROM mixes WHERE status = 1 ORDER BY category, name;';
  }
  return &ssms::dbi::query( $sql );
}

sub init_mix {
  my $id = shift;
  my( $sql, %time );
  $sql = 'SELECT * FROM mixes WHERE id = ' . $id . ';';
  &ssms::dbi::query( $sql, 1 );
  %mix = &ssms::dbi::fetch( 1 );
  $sql = 'SELECT * FROM media_category WHERE id = ' . $mix{'category'} . ';';
  &ssms::dbi::query( $sql, 1 );
  %media_category = &ssms::dbi::fetch( 1 );
  $client = ssms::client->new();
  $client->load( $mixes{'dj'} );
  %time = &ssms::time::as_hash( $mix{'length'}, 1 );
  $mix{'length_formated'} = $time{'hour'} . ':' . $time{'minute'} . ':' . $time{'second'};
  ( $mix{'time'}, $mix{'date'} ) = &ssms::time::format_time( $mix{'timestamp'} );
}

sub view_mix {
  $tag = shift if defined @_;
  if( $tag =~ /<MIX_(.+)>/ ) { &out( $mix{lc($1)} ); }
  elsif( $tag =~ /<MEDIA_CATEGORY_(.+)>/ ) { &out( $media_category{lc($1)} ); }
  elsif( $tag =~ /<IF_MIX_(.+)>/ ) { &con( $mix{lc($1)} ); }
  elsif( $tag =~ /<IF_MEDIA_CATEGORY_(.+)>/ ) { &con( $media_category{lc($1)} ); }
  elsif( $tag =~ /<INPUT_MIX_(\w+)(.*)>/ ) { &out( &input( 'mix_' . lc( $1 ), $2, 'text' ) ); }
  elsif( $tag =~ /<SELECT_MIX_CATEGORY(.*)>/ ) {
    $out = '';
    foreach( keys %mix_category ) {
      $out .= $_ . 'þ' . $mix_category{$_} . 'ÿ';
    }
    &out( &input( 'mix_category', $1, 'select', substr( $out, 0, length( $out ) - 1 ) ) );
  }
  else { return 0; }
  return 1;
}

sub mix_to_form {
  foreach( keys %mix ) {
    $form{'mix_'.$_} = $mix{$_};
  }
  %time = &ssms::time::as_hash( $mix{'length'}, 1 );
  $form{'mix_length'} = $time{'hour'} . ':' . $time{'minute'} . ':' . $time{'second'};
  %time = &ssms::time::as_hash( $mix{'timestamp'}, 1 );
  $form{'mix_date'} = $time{'year'} . '/' . $time{'month'} . '/' . $time{'day'};
}

sub save_mix {
  if( $form{'mix_length'} =~ /(\d+)\:(\d+)\:(\d+)/ ) {
    $form{'mix_length'} = $1 * 3600 + $2 * 60 + $3;
  }
  elsif( $form{'mix_length'} =~ /(\d+)\:(\d+)/ ) {
    $form{'mix_length'} = $1 * 60 + $2;
  }
  else {
    &page_error( 'Unknown Mix Length Format.' );
    return 0;
  }
  if( $form{'mix_date'} =~ /(\d+)\/(\d+)\/(\d+)/ ) {
    $form{'mix_timestamp'} = Time::Local::timegm( 0, 0, 0, $3, $2 - 1, $1 );
  }
  else {
    &page_error( 'Unknown Mix Date Format.' );
    return 0;
  }
  $sql = 'UPDATE mixes SET '
       . 'category = ' . $form{'mix_category'} . ', '
       . 'name = "' . $form{'mix_name'} . '", '
       . 'dj = "' . $form{'mix_dj'} . '", '
       . 'length = ' . $form{'mix_length'} . ', '
       . 'timestamp = ' . $form{'mix_timestamp'} . ' '
       . 'WHERE id = ' . $form{'mix_id'} . ';';
  &ssms::dbi::exec( $sql );
  $i = 0;
  while( $form{'release_'.$i.'_id'} ) {
    if( $form{'release_'.$i.'_rem'} ) {
      $sql = 'DELETE FROM releases WHERE id = ' . $form{'release_'.$i.'_id'} . ';';
      &ssms::dbi::exec( $sql );
    }
    else {
      $sql = 'UPDATE releases SET '
           . 'type = ' . $form{'release_'.$i.'_type'} . ', '
           . 'name = "' . $form{'release_'.$i.'_name'} . '", '
           . 'label = ' . $form{'release_'.$i.'_label'} . ', '
           . 'url_order = "' . $form{'release_'.$i.'_url_order'} . '", '
           . 'pos = ' . $form{'release_'.$i.'_pos'} . ' '
           . ' WHERE id = ' . $form{'release_'.$i.'_id'} . ';';
      &ssms::dbi::exec( $sql );
    }
    $i ++;
  }
  return 1;
}

sub remove_mixes {
  $i = 0;
  while( $form{'mix_'.$i.'_id'} ) {
    if( $form{'mix_'.$i.'_rem'} ) {
      $sql = 'DELETE FROM releases WHERE mix = ' . $form{'mix_'.$i.'_id'} . ';';
      &ssms::dbi::exec( $sql );
      $sql = 'DELETE FROM mixes WHERE id = ' . $form{'mix_'.$i.'_id'} . ';';
      &ssms::dbi::exec( $sql );
    }
    $i ++;
  }
}

sub new_mix {
  $now = time;
  $sql = 'INSERT INTO mixes (timestamp,status) VALUES(' . $now . ',1);';
  &ssms::dbi::exec( $sql );
  $sql = 'SELECT id FROM mixes WHERE timestamp = ' . $now . ' ORDER BY id DESC;';
  &ssms::dbi::query( $sql, 1 );
  $form{'mix_id'} = ( &ssms::dbi::fetch() )[0];
  $ssms::dbi::debug = 1;
}

sub add_release {
  $sql = 'SELECT COUNT(*) FROM releases WHERE mix = ' . $form{'mix_id'} . ';';
  $mix{'release_count'} = ( &ssms::dbi::query( $sql ) )[0];
  $i = -1;
  while( $form{'release_'.$i.'_id'} eq 'new' ) {
    $mix{'release_count'} ++;
    $sql = 'INSERT INTO releases (mix,type,name,label,url_order,pos) VALUES( '
         . $form{'mix_id'} . ', ' . $form{'release_'.$i.'_type'} . ', '
         . '"' . $form{'release_'.$i.'_name'} . '", ' . $form{'release_'.$i.'_label'} . ', '
         . '"' . $form{'release_'.$i.'_url_order'} . '", ' . $mix{'release_count'}
         . ' );';
    &ssms::dbi::exec( $sql );
    $i --;
  }
  return 1;
}

sub load_releases {
  my( $sql, @tmp );
  $sql = 'SELECT id FROM releases WHERE mix = ' . $mix{'id'} . ' ORDER BY pos;';
  @tmp = &ssms::dbi::query( $sql );
  $mix{'release_count'} = @tmp;
  return @tmp;
}

sub init_release {
  my $id = shift;
  my( $sql );
  $sql = 'SELECT * FROM releases WHERE id = ' . $id . ';';
  &ssms::dbi::query( $sql, 1 );
  %release = &ssms::dbi::fetch( 1 );
  &release_to_form();
  $sql = 'SELECT * FROM labels WHERE id = ' . $release{'label'} . ';';
  &ssms::dbi::query( $sql, 1 );
  %label = &ssms::dbi::fetch( 1 );
}

sub view_release {
  $tag = shift if defined @_;
  if( $tag =~ /<RELEASE_TYPE>/ ) { &out( $release_type{$release{'type'}} ); }
  elsif( $tag =~ /<RELEASE_(.+)>/ ) { &out( $release{lc($1)} ); }
  elsif( $tag =~ /<LABEL_(.+)>/ ) { &out( $label{lc($1)} ); }
  elsif( $tag =~ /<IF_RELEASE_(.+)>/ ) { &con( $release{lc($1)} ); }
  elsif( $tag =~ /<IF_LABEL_(.+)>/ ) { &con( $label{lc($1)} ); }
  else { return 0; }
  return 1;
}

sub view_release_input {
  $tag = shift if defined @_;
  if( $tag =~ /<INPUT_RELEASE_(\w+)(.*)>/ ) { &out( &input( 'release_' . $counter . '_' . lc( $1 ), $2, 'text' ) ); }
  elsif( $tag =~ /<SELECT_RELEASE_LABEL(.*)>/ ) {
    $out = '';
    foreach( @label_order ) {
      $out .= $_ . 'þ' . $label_name{$_} . 'ÿ';
    }
    &out( &input( 'release_' . $counter . '_label', $1, 'select', substr( $out, 0, length( $out ) - 1 ) ) );
  }
  elsif( $tag =~ /<SELECT_RELEASE_TYPE(.*)>/ ) {
    $out = '';
    foreach( keys %release_type ) {
      $out .= $_ . 'þ' . $release_type{$_} . 'ÿ';
    }
    &out( &input( 'release_' . $counter . '_type', $1, 'select', substr( $out, 0, length( $out ) - 1 ) ) );
  }
  elsif( $tag =~ /<SELECT_RELEASE_POS(.*)>/ ) {
    $out = '';
    for( $i = 1; $i <= $mix{'release_count'}; $i ++ ) {
      $out .= $i . 'ÿ';
    }
    &out( &input( 'release_' . $counter . '_pos', $1, 'select', substr( $out, 0, length( $out ) - 1 ) ) );
  }
  elsif( $tag =~ /<NEW_RELEASE>/ ) {
    $counter = $counter >= 0 ? -1 : $counter - 1;
    $form{'release_'.$counter.'_name'} = '';
    $form{'release_'.$counter.'_url_order'} = '';
    $form{'release_'.$counter.'_label'} = 0;
    $form{'release_'.$counter.'_type'} = 0;
  }
  else { return 0; }
  return 1;
}

sub release_to_form {
  foreach( keys %release ) {
    $form{'release_'.$counter.'_'.$_} = $release{$_};
  }
}

sub view_label_input {
  $tag = shift if defined @_;
  if( $tag =~ /<INPUT_LABEL_(\w+)(.*)>/ ) { &out( &input( 'label_' . lc( $1 ), $2, 'text' ) ); }
  else { return 0; }
  return 1;
}

sub add_label {
  if( ! $form{'label_name'} ) {
    &page_error( 'No Label Name Defined.' );
    return 0;
  }
  $sql = 'INSERT INTO labels (name,home,email) VALUES( '
       . '"' . $form{'label_name'} . '","' . $form{'label_home'} . '","' . $form{'label_email'} . '");';
  &ssms::dbi::exec( $sql );
  $form{'label_name'} = $form{'label_home'} = $form{'label_email'} = '';
  return 1;
}

sub load_mix_categories {
  $sql = 'SELECT id FROM media_category WHERE flags = 1;';
  return &ssms::dbi::query( $sql );
}

sub init_mix_category {
  my $id = shift;
  my( $sql );
  $sql = 'SELECT * FROM media_category WHERE id = ' . $id . ';';
  &ssms::dbi::query( $sql, 1 );
  %mix_category = &ssms::dbi::fetch( 1 );
}

sub view_mix_category {
  $tag = shift if defined @_;
  if( $tag =~ /<MIX_CATEGORY_(.+)>/ ) { &out( $mix_category{lc($1)} ); }
  elsif( $tag =~ /<IF_MIX_CATEGORY_ACTIVE>/ ) { &con( $mix_category{'id'} eq $form{'cat_id'} ); }
  elsif( $tag =~ /<IF_MIX_CATEGORY_(.+)>/ ) { &con( $mix_category{lc($1)} ); }
  else { return 0; }
  return 1;
}

1;
