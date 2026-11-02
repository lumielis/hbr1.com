package hbr1::ticker;

require $main::path{'ssms'} . 'dbi/table.pl';
our @ISA = qw( ssms::dbi::table );
our $VERSION = '1.2';

sub new {
  my $proto = shift;
  my $class = ref( $proto ) || $proto;
  my $self  = {};
  bless( $self, $class );
  $self->table( 'ticker' );
  $self->{'title_data_type'} = 'tagout';
  $self->{'content_data_type'} = 'area';
  $self->{'bg_color_data_type'} = 'hex';
  $self->{'title_color_data_type'} = 'hex';
  $self->{'content_color_data_type'} = 'hex';
  $self->{'anchor_color_data_type'} = 'hex';
  $self->{'edit_timestamp_data_type'} = 'timestamp';
  $self->{'create_timestamp_data_type'} = 'timestamp';
  $self->{'border_left_data_type'} = 'int';
  $self->{'border_right_data_type'} = 'int';
  $self->{'border_top_data_type'} = 'int';
  $self->{'border_bottom_data_type'} = 'int';
  $self->{'content_font_size_value'} = '10pxÿ11pxÿ12pxÿ14pxÿ16pxÿ1ÿ2ÿ3';
  $self->{'content_font_family_value'} = 'Verdana, Arial, monoÿCourier New, Courier, monoÿHelvetica, mono';
  $self->{'title_font_size_value'} = '10pxÿ11pxÿ12pxÿ14pxÿ16pxÿ1ÿ2ÿ3';
  $self->{'title_font_family_value'} = 'Verdana, Arial, monoÿCourier New, Courier, monoÿHelvetica, mono';
  $self->{'delay_value'} = '1þ1 secondÿ3þ3 secondsÿ5þ5 secondsÿ7þ7 secondsÿ10þ10 secondsÿ13þ13 secondsÿ15þ15 secondsÿ17þ17 secondsÿ30þ30 secondsÿ45þ45 secondsÿ60þ1 minuteÿ120þ2 minutesÿ180þ3 minutesÿ240þ4 minutesÿ300þ5 minutesÿ4400þ7 minutesÿ560þ9 minutesÿ600þ10 minutes';
  $self->{'status_value'} = '0þOffÿ1þRunning';
  $self->{'object_type_value'} = 'nþNormal TextÿfþFlash Movie';
  return $self;
}

package main;

if( $form{'action'} eq 'ticker' ) {
  $ticker = new hbr1::ticker();
  &ssms::system::add_tag_handler( \$ticker );
  if( $form{'part'} eq 'edit' ) {
    &ssms::system::add_tag_handler( \&view_ticker );
    $ticker->load( $form{'ticker_id'} );
    if( $form{'step'} == 1 ) {
      unless( $form{ 'ticker_id' } ) {
        $ticker->save();
        $form{'ticker_id'} = $ticker->{'id'};
      }
      $ticker->from_form();
      if( defined %error ) {
        &page_error( 'Please check your input.' );
      }
      if( ( $form{'ticker_image_remove'} || $form{'ticker_image_upload'} ) && $form{'ticker_image'} ) {
        $file = $path{'webtv'} . '/ticker/images/' . $form{'ticker_image'};
        unlink $file;
        $ticker->{'image'} = '';
        $ticker->{'edit_timestamp'} = time;
        $ticker->save();
      }
      if( ( $form{'ticker_remove_flashmovie'} || $form{'ticker_upload_flashmovie'} ) && $form{'ticker_flash_movie'} ) {
        $file = $path{'webtv'} . '/ticker/flash/' . $form{'ticker_flash_movie'};
        unlink $file;
        $ticker->{'flash_movie'} = '';
        $ticker->{'edit_timestamp'} = time;
        $ticker->save();
      }
      unless( defined %error ) {
        if( $form{'ticker_image_upload'} ) {
          ( $ret, $file ) = &upload_file( 'ticker_image_upload', $path{'webtv'} . '/ticker/images', time . '.<.FE>', 'jpg|jpeg|png|gif', 1 );
          if( $ret == -2 ) {
            $error{'ticker_image_upload'} = 1;
            &page_error( 'The file seems to be not an image for the web.' );
          }
          elsif( $ret < 0 ) {
            $error{'ticker_image_upload'} = 1;
            &page_error( 'Internal server error.' );
          }
          else {
          	if( $ticker->{'image'} ) {
			        $file = $path{'webtv'} . '/ticker/image/' . $ticker->{'image'};
			        unlink $file;
			      }
            $ticker->{'image'} = $file;
          }
        }
      }
      unless( defined %error ) {
        if( $form{'ticker_upload_flashmovie'} ) {
          ( $ret, $file ) = &upload_file( 'ticker_upload_flashmovie', $path{'webtv'} . '/ticker/flash', time . '.swf', 'swf', 1 );
          if( $ret == -2 ) {
            $error{'ticker_upload_flashmovie'} = 1;
            &page_error( 'The file seems to be not a flash movie.' );
          }
          elsif( $ret < 0 ) {
            $error{'ticker_upload_flashmovie'} = 1;
            &page_error( 'Internal server error.' );
          }
          else {
          	if( $ticker->{'flash_movie'} ) {
			        $file = $path{'webtv'} . '/ticker/flash/' . $ticker->{'flash_movie'};
			        unlink $file;
			      }
            $ticker->{'flash_movie'} = $file;
          }
        }
      }
      unless( defined %error ) {
        $ticker->{'edit_timestamp'} = time;
        $ticker->save();
        &page_message( 'Data has been updated.' );
        &write_ticker();
        $ticker->load( $form{'ticker_id'} );
        delete $form{'part'} if $form{'submit_return'};
      }
    }
    unless( $form{'step'} ) {
      unless( $ticker->{'id'} ) {
        $ticker->{'title_color'} = $ticker->{'content_color'} = 'FFEEDD';
        $ticker->{'bg_color'} = '100040';
        $ticker->{'anchor_color'} = 'CCFF00';
        $ticker->{'delay'} = '15';
      }
      $ticker->to_form();
    }
    if( $form{'part'} ) {
      &ssms::system::load( 'admin/ticker_edit.htm' );
    }
  }
  elsif( $form{'part'} eq 'kill' ) {
    $ticker->load( $form{'ticker_id'} );
    $file = $path{'webtv'} . '/ticker/images/' . $ticker->{'image'};
    unlink $file;
    $ticker->kill();
    &page_message( 'Entry has been removed.' );
    &write_ticker();
    $form{'part'} = undef;
  }
  elsif( $form{'part'} eq 'preview' ) {
    $ticker->load( $form{'ticker_id'} );
    &ssms::system::add_tag_handler( \$ticker, \&view_ticker );
    &ssms::system::load( 'admin/ticker_template.htm' );
  }
	elsif( $form{'part'} eq 'dec_pos' ) {
		@id = &read_ticker_order();
		if( &dec_ticker_pos( $form{'ticker_id'}, \@id ) ) {
			&write_ticker_order( \@id );
		    &page_message( 'Ticker position decreased.' );
		    &write_ticker();
		}
		$form{'part'} = undef;
	}
	elsif( $form{'part'} eq 'inc_pos' ) {
		@id = &read_ticker_order();
		if( &inc_ticker_pos( $form{'ticker_id'}, \@id ) ) {
			&write_ticker_order( \@id );
		    &page_message( 'Ticker position increased.' );
		    &write_ticker();
		}
		$form{'part'} = undef;
	}
  else {
    if( $form{'step'} == 1 ) {
      $index = 1;
      while( $form{ 'ticker_id_' . $index } ) {
        $ticker = new hbr1::ticker();
        $ticker->load( $form{ 'ticker_id_' . $index } );
        $ticker->{ 'position' } = $form{ 'ticker_position_' . $index };
        $ticker->{ 'status' } = $form{ 'ticker_status_' . $index };
        $ticker->save();
        $index ++;
      }
      &write_ticker();
      &page_message( 'data has been saved' );
    }
  }
  unless( $form{'part'} ) {
    &ssms::system::add_loop( '<LOOP_TICKER(.*)>', \&load_ticker, \&view_ticker, \&init_ticker );
    &ssms::system::load( 'admin/ticker_list.htm' );
  }
}

sub load_ticker {
  my ( $arg, %arg, $sql, @id );
  $arg = shift;
  %arg = &ssms::http::split_param( $arg );
  $sql = 'SELECT id FROM ticker WHERE 1';
  $sql .= ' AND status = 1' if $arg{'enabled'};
  $sql .= ' ORDER BY ' . $arg{'order'} . ' ' . $arg{'ordir'} if $arg{'order'};
  @id = &ssms::dbi::query( $sql );
  $form{'ticker_count'} = @id;
  return @id;
}

sub init_ticker {
  my $id = shift;
  $ticker = new hbr1::ticker();
  $ticker->load( $id );
  $form{ 'ticker_position_' . ( $counter + 1 ) } = $ticker->{'position'};
  $form{ 'ticker_status_' . ( $counter + 1 ) } = $ticker->{'status'};
}

sub view_ticker {
  $tag = shift if defined @_;
  if( $tag =~ /<FILE_TICKER_IMAGE_UPLOAD(.*)>/ ) { &out( &input( 'ticker_image_upload', $1, 'file' ) ); }
  elsif( $tag =~ /<CHECK_TICKER_IMAGE_REMOVE(.*)>/ ) { &out( &input( 'ticker_image_remove', $1, 'check', 1 ) ); }
  elsif( $tag =~ /<CHECK_TICKER_SHOW_IMAGE(.*)>/ ) { &out( &input( 'ticker_show_image', $1, 'check', 1 ) ); }
  elsif( $tag =~ /<CHECK_TICKER_SHOW_TITLE(.*)>/ ) { &out( &input( 'ticker_show_title', $1, 'check', 1 ) ); }
  elsif( $tag =~ /<SELECT_TICKER_POSITION(.*)>/ ) {
    @out = ();
    for( $index = 0; $index < $form{'ticker_count'}; $index ++ ) {
      push @out, ( $index + 1 );
    }
    &out( &input( 'ticker_position_' . ( $counter + 1 ), $1, 'select', join( 'ÿ', @out ) ) );
  }
  elsif( $tag =~ /<SELECT_TICKER_STATUS(.*)>/ ) {
    &out( &input( 'ticker_status_' . ( $counter + 1 ), $1, 'select', '0þOffÿ1þRunning' ) );
  }
  else { return 0; }
  return 1;
}

sub write_ticker {
  my( @id, $index, $count, $out, $file );
  @id = &load_ticker( 'enabled="1" order="position"' );
  $count = @id;
  $index = 1;
  $file = 'content.htm';
  for( $index = 1; -e $path{'webtv'} . '/ticker/' . $file; $index ++ ) {
    unlink $path{'webtv'} . '/ticker/' . $file;
    $file = 'content' . $index . '.htm';
  }
  &ssms::system::backup();
  &ssms::system::add_tag_handler( \$ticker, \&view_ticker );
  $index = 0;
  foreach( @id ) {
    &init_ticker( $_ );
    $ticker->{'file'} = 'content' . ( $index ? $index : '' ) . '.htm';
    $index ++;
    $ticker->{'next'} = 'content' . ( $index < $count ? $index : '' ) . '.htm';
    &ssms::system::clear();
    &ssms::system::load( 'admin/ticker_template.htm' );
    &ssms::system::main( 1, $path{'webtv'} . '/ticker/' . $ticker->{'file'} );
  }
  &ssms::system::restore();
}

sub sort_ticker {
	my( $id, $index );
	&ssms::dbi::query( 'SELECT id FROM ticker ORDER BY position', 1 );
	$index = 1;
	while( ( $id ) = &ssms::dbi::fetch() ) {
		&ssms::dbi::exec( 'UPDATE ticker SET position = ' . $index . ' WHERE id = ' . $id );
		$index ++;
	}
}

sub read_ticker_order {
	return &ssms::dbi::query( 'SELECT id FROM ticker ORDER BY position' );
}

sub write_ticker_order {
	my $r_list = shift;
	my $index = 1;
	my $dbh = $ssms::dbi::dbh;
	my $sth = $dbh->prepare( 'UPDATE ticker SET position = ? WHERE id = ?' );
	foreach( @{ $r_list } ) {
		$sth->execute( $index, $_ );
		$index ++;
	}
}

sub inc_ticker_pos {
	my( $id, $r_list ) = @_;
	my( $index, $len );
	$index = 0;
	$len = scalar @$r_list;
	for( $index = 0; $index < $len; $index ++ ) {
		if( $r_list->[$index] eq $id && $index < $len - 1 ) {
			$r_list->[$index] = $r_list->[$index+1];
			$r_list->[$index+1] = $id;
			return 1;
		}
	}
}

sub dec_ticker_pos {
	my( $id, $r_list ) = @_;
	my( $index, $len );
	$index = 0;
	$len = scalar @$r_list;
	for( $index = 0; $index < $len; $index ++ ) {
		if( $r_list->[$index] eq $id && $index > 0 ) {
			$r_list->[$index] = $r_list->[$index-1];
			$r_list->[$index-1] = $id;
			return 1;
		}
	}
}

1;
