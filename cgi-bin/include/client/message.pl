package hbr1::message_thread;

require $main::path{'ssms'} . 'dbi/table.pl';
use vars qw( $VERSION @ISA );
@ISA = qw( ssms::dbi::table );
$VERSION = '1.0';

sub new {
  my $proto = shift;
  my $class = ref( $proto ) || $proto;
  my $self  = {};
  bless( $self, $class );
  $self->table( 'message_thread' );
  $self->{'create_timestamp_data_type'} = 'timestamp';
  $self->{'edit_timestamp_data_type'} = 'timestamp';
  $self->{'title_data_type'} = 'tagout';
  $self->{'client_data_type'} = 'tagout';
  $self->{'info_data_type'} = 'tagout';
  return $self;
}

package main;

$ssms::dbi::debug = 1;

$url{'gallery_photo'} = qq(http://217.64.169.133/gp/pv);
$url{'gallery_tumbnail'} = qq(http://217.64.169.133/gp/tn);
$url{'gp_read'} = qq(http://217.64.169.133/cgi-bin/gp_read.cgi);

$photo_gallery = hbr1::gallery->new();

if( $form{'action'} eq 'gallery' ) {
  if( $form{'part'} eq 'edit' ) {
    #&load_client( 'gallery_login.htm' );
    $form{'gallery_id'} = 0 unless $form{'gallery_id'} > 0;
    $photo_gallery->load( $form{'gallery_id'} );
    $photo_gallery->set( 'password_save', $photo_gallery->get( 'password' ) );
    $photo_gallery->set( 'password', '' );
    $photo_gallery->set( 'submitter_save', $photo_gallery->get( 'submitter' ) );
    $photo_gallery->set( 'submitter', '' );
    if( $form{'step'} == 1 ) {
      $photo_gallery->from_form();
      $error{'gallery_title'} = 1 if length( $form{'gallery_title'} ) < 3;
      if( defined %error ) {
        &page_error( 'There are errors. Please check the red marked fields.' );
      }
      else {
        $error{'gallery_submitter'} = 1 if length( $form{'gallery_submitter'} ) < 3 || length( $form{'gallery_submitter'} ) > 32;
        $error{'gallery_password'} = 1 if length( $form{'gallery_password'} ) < 3 || length( $form{'gallery_password'} ) > 32;
        if( $form{'gallery_id'} == 0 ) {
          $error{'gallery_password'} = 1 if $form{'gallery_password'} ne $form{'gallery_password2'};
        }
        else {
          $photo_gallery->set( 'password', $photo_gallery->get( 'password_save' ) );
          $photo_gallery->set( 'submitter', $photo_gallery->get( 'submitter_save' ) );
          $error{'gallery_submitter'} = 1 if $photo_gallery->get( 'submitter' ) ne $form{'gallery_submitter'};
          $error{'gallery_password'} = 1 if $photo_gallery->get( 'password' ) ne $form{'gallery_password'};
        }
        if( defined %error ) {
          &page_error( 'Your name and the password may contain only alpha numeric chars and must have a size between 3 and 32 characters.' );
        }
      }
      if( ! defined %error ) {
        if( $form{'gallery_id'} == 0 ) {
          $photo_gallery->set( 'id', undef );
          $photo_gallery->set( 'create_timestamp', time );
          $photo_gallery->set( 'category_id', $form{'cat_id'} );
          $photo_gallery->save();
          $form{'gallery_id'} = $photo_gallery->get( 'id' );
        }
        for( $index = 1; $form{'gallery_picture_id_'.$index}; $index ++ ) {
          if( $form{'gallery_picture_remove_'.$index} ) {
            unlink( $path{'images'} . '/gallery/' . $form{'gallery_picture_file_'.$index} );
            $response = LWP::Simple::get( $url{'gp_read'} . '?action=remove&file=' . $form{'gallery_picture_file_'.$index} );
            $sql = 'DELETE FROM gallery_picture WHERE id = ' . $form{'gallery_picture_id_'.$index};
            &ssms::dbi::exec( $sql );
            #&page_message( 'Photo has been removed' );
          }
          else {
            $sql = 'UPDATE gallery_picture SET title = "'
                 . &to_sql( $form{'gallery_picture_title_'.$index} ) . '", copyright = "'
                 . &to_sql( $form{'gallery_picture_copyright_'.$index} ) . '" WHERE id = '
                 . $form{'gallery_picture_id_'.$index};
            #die $sql;
            &ssms::dbi::exec( $sql );
            #&page_message( 'Photo has been updated' );
          }
        }
        $index = 1;
        $timer = time;
        while( defined $form{'gallery_photo_file_'.$index} ) {
          $file_out = $timer . '$' . uc( &ssms::string::rnd_str( 20 ) ) . '$' . $photo_gallery->get( 'id' );
          if( $form{'gallery_photo_file_'.$index} ) {
            ( $file_size, $file ) = &upload_file( 'gallery_photo_file_' . $index, $path{'images'} . '/gallery', $file_out . '.<.FE>', 'jpg|jpeg|gif|png', 1 );
            if( $file_size == -2 ) {
              $error{'gallery_photo_file_'.$index} = 1;
              &page_error( 'Only images are permitted.' );
            }
            elsif( $file_size < 0 ) {
              $error{'gallery_photo_file_'.$index} = 1;
              &page_error( 'Unknown error has occured.' );
            }
            elsif( $file_size > 200000 ) {
              unlink $path{'images'} . '/gallery/' . $file;
              $error{'gallery_photo_file_'.$index} = 1;
              &page_error( 'One or more images are to big - 200k maximal' );
            }
            else {
              $response = LWP::Simple::get( $url{'gp_read'} . '?file=' . $file );
              if( $response =~ /^200/ ) {
                $sql = 'INSERT INTO gallery_picture (gallery_id,file,title,copyright) VALUES('
                     . $photo_gallery->get( 'id' ) . ',"'
                     . $file . '","'
                     . &to_sql( $form{'gallery_photo_title_'.$index} ) . '","'
                     . &to_sql( $form{'gallery_photo_copyright_'.$index} ) . '" )';
                &ssms::dbi::exec( $sql );
                $form{'gallery_photo_title_'.$index} = '';
                $form{'gallery_photo_copyright_'.$index} = '';
                $photo_gallery->set( 'edit_timestamp', time );
              }
              unlink $path{'images'} . '/gallery/' . $file;
              #die $response;
            }
          }
          $timer ++;
          $index ++;
        }
        if( ! defined %error ) {
          &page_message( 'The gallery has been updated.' );
        }
        $sql = 'SELECT COUNT(*) FROM gallery_picture WHERE gallery_id = ' . $photo_gallery->get( 'id' ) . ';';
        $photo_gallery->set( 'status', ( &ssms::dbi::query( $sql ) )[0] ? 1 : 0 );
        #$photo_gallery->set( 'edit_timestamp', time );
        $photo_gallery->save();
      }
    }
    if( ! $form{'step'} ) {
      $photo_gallery->to_form();
    }
    &ssms::system::add_loop( '<GALLERY_CATEGORIES(.*)>', \&load_gallery_categories, \&view_gallery_category, \&init_gallery_category );
    &ssms::system::add_loop( '<GALLERY_PICTURES(.*)>', \&load_gallery_pictures, \&view_gallery_picture, \&init_gallery_picture, 1 );
    &ssms::system::add_tag_handler( \$photo_gallery, \&view_gallery );
    &ssms::system::load( 'client/gallery_edit.htm' );
  }
  elsif( $form{'part'} eq 'photo' ) {
    &ssms::system::add_tag_handler( \&view_gallery_picture );
    &ssms::system::add_tag_handler( \$photo_gallery );
    $found = 0;
    @gallery_id = $form{'cat_id'} ? &load_galleries() : ( $form{'gallery_id'} );
    foreach $gallery_id( @gallery_id ) {
      $photo_gallery->load( $gallery_id );
      @photo_id = &load_gallery_pictures();
      foreach $photo_id( @photo_id ) {
        if( $photo_id == $form{'photo_id'} ) {
          $found = 1;
        }
        elsif( $found ) {
          $form{'next_photo'} = $photo_id;
          last;
        }
        else {
          $form{'prev_photo'} = $photo_id;
        }
      }
      last if $form{'next_photo'};
    }
    $photo_gallery->load( $form{'gallery_id'} );
    &init_gallery_picture( $form{'photo_id'} );
    &ssms::system::load( 'client/gallery_photo.htm' );
  }
  elsif( $form{'part'} eq 'view' ) {
    &ssms::system::add_tag_handler( \$photo_gallery );
    &ssms::system::add_loop( '<GALLERY_PICTURES(.*)>', \&load_gallery_pictures, \&view_gallery_picture, \&init_gallery_picture );
    &ssms::system::add_loop( '<PHOTO_GALLERIES(.*)>', \&load_galleries, \&view_gallery, \&init_gallery );
    $photo_gallery->load( $form{'gallery_id'} );
    &ssms::system::load( 'client/gallery_main.htm' );
  }
  else {
    if( defined $form{'cat_id'} ) {
      &init_gallery_category( $form{'cat_id'} );
      if( $media_category{'id'} != $form{'cat_id'} || ! $form{'cat_id'} ) {
        &ssms::system::load( 'client/empty.htm' );
      }
      else {
        &ssms::system::add_tag_handler( \&view_gallery_picture, \$photo_gallery, \&view_gallery_category );
        &ssms::system::add_loop( '<PHOTO_GALLERIES(.*)>', \&load_galleries, \&view_gallery, \&init_gallery );
        &ssms::system::add_loop( '<GALLERY_PICTURES(.*)>', \&load_gallery_pictures, \&view_gallery_picture, \&init_gallery_picture );
        &ssms::system::load( 'client/galleries.htm' );
      }
    }
    else {
      &ssms::system::add_tag_handler( \&view_gallery_picture );
      &ssms::system::add_loop( '<GALLERY_CATEGORIES(.*)>', \&load_gallery_categories, \&view_gallery_category, \&init_gallery_category );
      &ssms::system::load( 'client/gallery_categories.htm' );
    }
  }
}

sub view_photo_gallery_global {
}

# gallery categories

sub load_gallery_categories {
  my $args = shift;
  my %argv = &ssms::http::split_param( $args );
  $argv{'order'} = ' ORDER BY ' . $argv{'order'} . ' ' . $argv{'ordir'} if defined $argv{'order'};
  my $sql = 'SELECT id FROM media_category WHERE status = 1 AND flags = 2' . $argv{'order'} . ';';
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
  if( $tag =~ /<MEDIA_CATEGORY_RANDOM_PHOTO>/ ) {
    @photo_id = ();
    @gallery_id = &load_galleries( 'category=' . $media_category{'id'} );
    foreach( @gallery_id ) {
      $photo_gallery->load( $_ );
      push @photo_id, &load_gallery_pictures();
    }
    $photo_id_max = @photo_id;
    srand( time );
    &init_gallery_picture( $photo_id[ int( rand( $photo_id_max ) ) ] );
  }
  elsif( $tag =~ /<MEDIA_CATEGORY_(.+)>/ ) { &out( $media_category{lc($1)} ); }
  elsif( $tag =~ /<IF_MEDIA_CATEGORY_(.+)>/ ) { &con( $media_category{lc($1)} ); }
  else { return 0; }
  return 1;
}

# galleries

sub load_galleries {
  my $args = shift;
  my %argv = &ssms::http::split_param( $args );
  $argv{'category'} = $form{'cat_id'} if defined $form{'cat_id'};
  $argv{'category'} = defined $argv{'category'} ? ' AND category_id = ' . $argv{'category'} : defined $form{'cat_id'} ? ' AND category_id = ' . $form{'cat_id'} : '';
  $argv{'order'} = ' ORDER BY ' . $argv{'order'} . ' ' . $argv{'ordir'} if defined $argv{'order'};
  $argv{'limit'} = defined $argv{'limit'} ? ' LIMIT ' . $argv{'limit'} : '';
  my $sql = 'SELECT id FROM gallery WHERE status = 1 ' . $argv{'category'} . $argv{'order'} . $argv{'limit'} . ';';
  my @gallery_id = &ssms::dbi::query( $sql );
  $form{'max'} = @gallery_id;
  $form{'from'} = 0 unless $form{'from'};
  $form{'count'} = 8 unless $form{'count'};
  $form{'to'} = $form{'from'} + $form{'count'};
  return @gallery_id;
}

sub init_gallery {
  my $id = shift;
  #$photo_gallery = hbr1::gallery->new();
  $photo_gallery->load( $id );
}

sub view_gallery {
  $tag = shift if defined @_;
  if( $tag =~ /<FILE_GALLERY_(\w+)(.*)>/ ) { &out( &input( 'gallery_' . lc( $1 ), $2, 'file' ) ); }
  elsif( $tag =~ /<GALLERY_RANDOM_PHOTO>/ ) {
    @photo_id = &load_gallery_pictures();
    $photo_id_max = @photo_id;
    srand( time );
    &init_gallery_picture( $photo_id[ int( rand( $photo_id_max ) ) ] );
  }
  else { return 0; }
  return 1;
}

# gallery pictures

sub load_gallery_pictures {
  my $args = shift;
  my %argv = &ssms::http::split_param( $args );
  $argv{'order'} = ' ORDER BY ' . $argv{'order'} . ' ' . $argv{'ordir'} if defined $argv{'order'};
  $argv{'limit'} = defined $argv{'limit'} ? ' LIMIT ' . $argv{'limit'} : '';
  my $sql = 'SELECT id FROM gallery_picture WHERE gallery_id = ' . $photo_gallery->get( 'id' ) . $argv{'order'} . $argv{'limit'} . ';';
  my @photo_id = &ssms::dbi::query( $sql );
  $form{'max'} = @photo_id;
  $form{'from'} = 0 unless $form{'from'};
  $form{'count'} = 6 unless $form{'count'};
  $form{'to'} = $form{'from'} + $form{'count'};
  return @photo_id;
}

sub init_gallery_picture {
  my $id = shift;
  my $sql = 'SELECT * FROM gallery_picture WHERE id = ' . $id . ';';
  &ssms::dbi::query( $sql, 1 );
  %gallery_picture = &ssms::dbi::fetch( 1 );
}

sub view_gallery_picture {
  $tag = shift if defined @_;
  if( $tag =~ /<GALLERY_PICTURE_(.+)>/ ) { &out( $gallery_picture{lc($1)} ); }
  elsif( $tag =~ /<IF_GALLERY_PICTURE_(.+)>/ ) { &con( $gallery_picture{lc($1)} ); }
  elsif( $tag =~ /<INPUT_GALLERY_PICTURE_(\w+)(.*)>/ ) { &out( &input( 'gallery_picture_' . lc( $1 ), $2, 'text' ) ); }
  elsif( $tag =~ /<AREA_GALLERY_PICTURE_(\w+)(.*)>/ ) { &out( &input( 'gallery_picture_' . lc( $1 ), $2, 'area' ) ); }
  else { return 0; }
  return 1;
}

1;
