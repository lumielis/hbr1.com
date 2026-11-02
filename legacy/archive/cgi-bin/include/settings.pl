package ssms::setting;

require $main::path{'ssms'} . 'dbi/table.pl';
our @ISA = qw(ssms::dbi::table);
our $VERSION = '1.0';

sub new {
  my $proto = shift;
  my $class = ref( $proto ) || $proto;
  my $self  = {};
  bless( $self, $class );
  $self->table( 'settings' );
  return $self;
}

package main;

require $path{'ssms'} . 'form.pl';

sub load_settings {
  my( $sql, $arg, %arg );
  $arg = shift;
  %arg = &ssms::http::split_param( $arg );
  $arg{'parent'} = $form{'parent'} if $form{'parent'};
  $sql = 'SELECT id FROM settings WHERE 1';
  $sql .= ' AND parent = ' . $arg{'parent'} if $arg{'parent'};
  $sql .= ' ORDER BY id, parent';
  return &ssms::dbi::query( $sql );
}

sub init_settings {
  my $id = shift;
  $setting->load( $id );
  unless( defined $form{ 'value_' . ( $counter + 1 ) } ) {
    &to_form( 'value_' . ( $counter + 1 ), $setting->{'value'}, $setting->{'type'} );
  }
}

sub view_settings {
  $tag = shift if defined @_;
  if( $tag =~ /<IF_SETTINGS_TYPE eq (\w+)>/ ) { &con( $setting->{'type'} eq $1 ); }
  elsif( $tag =~ /<IF_ERROR_SETTING>/ ) { &con( $error{ 'value_' . ( $counter + 1 ) } ); }
  elsif( $tag =~ /<CURRENT_SETTING>/ ) {
    &to_form( 'temp', eval( $setting->{'variable'} ), $setting->{'type'} );
    &out( $form{'temp'} );
  }
  elsif( $tag =~ /<DEFAULT_SETTING>/ ) {
    &to_form( 'temp', $setting->{'default_value'}, $setting->{'type'} );
    &out( $form{'temp'} );
  }
  elsif( $tag =~ /<INPUT_SETTING(.*)>/ ) {
    if( $setting->{'choice'} ) {
      &out( &input( 'value_' . ( $counter + 1 ), $1, 'select', $setting->{'choice'} ) );
    }
    else {
      &out( &input( 'value_' . ( $counter + 1 ), $1, 'text' ) );
    }
  }
  else { return 0; }
  return 1;
}

sub write_settings {
  my( $file, @id, @out, $sql );
  $file = shift;
  @id = &load_settings();
  foreach( @id ) {
    $setting->load( $_ );
    if( $setting->{'variable'} ) {
      if( $setting->{'value'} eq '' || $setting->{'value'} =~ /[^0-9.]/ ) {
        $setting->{'value'} =~ s/\@/\\\@/g;
        push @out, $setting->{'variable'} . ' = qq(' . $setting->{'value'} . ');';
      }
      else {
        push @out, $setting->{'variable'} . ' = ' . $setting->{'value'} . ';';
      }
    }
    else {
      push @out, '# ' . $setting->{'name'};
    }
  }
  open FILE, '> ' . $file;
  print FILE join( "\n", @out );
  print FILE "\n1;\n";
  close FILE;
}

1;
