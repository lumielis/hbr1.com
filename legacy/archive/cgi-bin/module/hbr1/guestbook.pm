package ssms::guestbook;

require $main::path{'ssms'} . 'dbi/table.pl';
our @ISA = qw(ssms::dbi::table);
our $VERSION = '1.0.0';

sub new {
  my $proto = shift;
  my $class = ref( $proto ) || $proto;
  my $self  = {};
  bless( $self, $class );
  $self->table( 'guestbook' );
  $self->{'submitter_data_type'} = 'tagout';
  $self->{'email_data_type'} = 'email';
  $self->{'content_data_type'} = 'tagout';
  $self->{'submitter_required'} = 1;
  $self->{'email_required'} = 1;
  $self->{'content_required'} = 1;
  return $self;
}

package main;

require $path{'ssms'} . 'crypt/xor.pl';

our @guestbook, %guestbook;

sub load_guestbook {
  my( $sql, %row, $index, $arg, %arg );
  $arg = shift;
  %arg = &ssms::http::split_param( $arg );
  $form{'view_count'} = $setup{'guestbook_entries'} unless defined $form{'view_count'};
  $arg{'count'} = $form{'view_count'} if $form{'view_count'} > 0;
  $arg{'from'} = $form{'view_from'} if $form{'view_from'} > 0;
  
  @guestbook = ();
  $sql = 'SELECT * FROM guestbook WHERE 1';
  $sql .= ' AND ' . $arg{'clausel'} if $arg{'clausel'};
  $sql .= ' ORDER BY ' . $arg{'order'} . ' ' . $arg{'ordir'} if $arg{'order'};
  if( $arg{'count'} > 0 ) {
    $sql2 = $sql;
    $sql2 =~ s/SELECT \*/SELECT COUNT\(\*\)/;
    $form{'view_max'} = ( &ssms::dbi::query( $sql2 ) )[0];
    $arg{'from'} = 0 unless $arg{'from'};
    $sql .= ' LIMIT ' . $arg{'from'} . ', ' . $arg{'count'};
    $form{'view_from'} = $arg{'from'};
    $form{'view_count'} = $arg{'count'};
    $form{'view_to'} = $form{'view_from'} + $form{'view_count'};
  }
  &ssms::dbi::query( $sql, 1 );
  $index = 0;
  while( %row = &ssms::dbi::fetch( 1 ) ) {
    foreach( keys %row ) {
      ${ $guestbook[ $index ] }{ $_ } = $row{ $_ };
    }
    $index ++;
  }
  return @guestbook;
}

sub init_guestbook {
  *guestbook = shift;
  ( $guestbook{'time'}, $guestbook{'date'} ) = &ssms::time::format_time( $guestbook{'timestamp'} );
  $guestbook{'index'} = 1 + $counter + $form{'view_from'};
}

sub view_guestbook {
  $tag = shift if defined @_;
  if( $tag =~ /<GUESTBOOK_PREV_ITEMS>/ ) {
    $out = $form{'view_from'} - $form{'view_count'};
    &out( $out > 0 ? $out : 0 );
  }
  elsif( $tag =~ /<GUESTBOOK_NEXT_ITEMS>/ ) { &out( $form{'view_to'} ); }
  elsif( $tag =~ /<GUESTBOOK_SHOW_ITEMS>/ ) { &out( $form{'view_count'} ); }
  elsif( $tag =~ /<GUESTBOOK_EMAIL_ENCRYPTED>/ ) { &out( &ssms::crypt::encrypt_hex( $guestbook{'submitter'} . ' <' . $guestbook{'email'} . '>' ) ); }
  elsif( $tag =~ /<GUESTBOOK_(\w+)>/ ) { &out( $guestbook{ lc( $1 ) } ); }
  elsif( $tag =~ /<IF_GUESTBOOK_PREV_ITEMS>/ ) { &con( $form{'view_from'} > 0 ); }
  elsif( $tag =~ /<IF_GUESTBOOK_NEXT_ITEMS>/ ) { &con( $form{'view_to'} < $form{'view_max'} ); }
  elsif( $tag =~ /<IF_GUESTBOOK_ITEM_LIMIT>/ ) { &con( $form{'view_to'} < $form{'view_max'} || $form{'view_from'} > 0 ); }
  elsif( $tag =~ /<IF_GUESTBOOK_(\w+)>/ ) { &con( $guestbook{ lc( $1 ) } ); }
  else { return 0; }
  return 1;
}

1;
