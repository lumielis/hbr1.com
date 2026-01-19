package hbr1::message;

require $main::path{'ssms'} . 'dbi/table.pl';
use vars qw( $VERSION @ISA );
@ISA = qw( ssms::dbi::table );
$VERSION = '1.0';

sub new {
  my $proto = shift;
  my $class = ref( $proto ) || $proto;
  my $self  = {};
  bless( $self, $class );
  $self->table( 'message' );
  $self->{'start_timestamp_data_type'} = 'timestamp';
  $self->{'end_timestamp_data_type'} = 'timestamp';
  $self->{'create_timestamp_data_type'} = 'timestamp';
  $self->{'edit_timestamp_data_type'} = 'timestamp';
  $self->{'title_data_type'} = 'tagout';
  $self->{'city_data_type'} = 'tagout';
  $self->{'djs_data_type'} = 'tagout';
  $self->{'live_data_type'} = 'tagout';
  $self->{'chillout_data_type'} = 'tagout';
  $self->{'deco_data_type'} = 'tagout';
  $self->{'location_data_type'} = 'tagout';
  $self->{'hotline_data_type'} = 'tagout';
  $self->{'organizer_data_type'} = 'tagout';
  $self->{'email_data_type'} = 'email';
  $self->{'url_data_type'} = 'url';
  $self->{'memo_data_type'} = 'tagout';
  return $self;
}

package main;

$party = hbr1::party->new();
$sql = 'DELETE FROM party WHERE create_timestamp < ' . ( time - 86400 ) . ' AND status = 0;';
&ssms::dbi::exec( $sql );
$form{'time_from'} = time unless $form{'time_from'};
%time_from = &ssms::time::as_hash( $form{'time_from'} );

if( $form{'action'} eq 'edit' ) {
  &ssms::time::set_format( 'H:m', 'm/d/Y' );
  if( $form{'party_id'} eq 'new' ) {
    $key = &ssms::string::rnd_str( 32 );
    $now = time;
    $sql = 'INSERT INTO party (create_timestamp,start_timestamp,end_timestamp,title) VALUES('
         . $now . ',' . $now . ',' . $now . ',"' . $key . '");';
    &ssms::dbi::exec( $sql );
    $sql = 'SELECT id FROM party WHERE create_timestamp = ' . $now . ' AND title = "' . $key . '";';
    $form{'party_id'} = ( &ssms::dbi::query( $sql ) )[0];
    $party->load( $form{'party_id'} );
    $party->set( 'title', '' );
    $party->save();
  }
  $party->load( $form{'party_id'} );
  if( ! $party->get( 'create_timestamp' ) ) {
    delete $form{'step'};
    delete $form{'action'};
    &page_error( 'This party does not exist.' );
  }
  if( $form{'step'} == 1 ) {
    $submitter = $party->get( 'submitter' );
    $password = $party->get( 'password' );
    $party->from_form();
    if( ! defined %error ) {
      $error{'party_title'} = 1 if length( $form{'party_title'} ) < 3;
      $error{'party_city'} = 1 if length( $form{'party_city'} ) < 3;
      $error{'party_location'} = 1 if length( $form{'party_location'} ) < 3;
      $error{'party_start_timestamp'} = 1 if $party->get( 'start_timestamp' ) < time && ! $form{'su'};
      $error{'party_end_timestamp'} = 1 if $party->get( 'end_timestamp' ) <= $party->get( 'start_timestamp' );
      $error{'party_end_timestamp'} = 1 if $party->get( 'end_timestamp' ) > $party->get( 'start_timestamp' ) + 86400 * 21;
    }
    if( defined %error ) {
      &page_error( 'There are some errors. Please look at the red labeled fields.' );
    }
    if( ! defined %error ) {
      $error{'party_submitter'} = 1 if length( $form{'party_submitter'} ) < 3;
      $error{'party_password'} = 1 if length( $form{'party_password'} ) < 3;
      if( ! $password ) {
        $error{'party_password'} = 1 if $form{'party_password'} ne $form{'party_password2'};
      }
      if( defined %error ) {
        &page_error( 'Your name and the password may contain only alpha numeric chars and must have a size between 3 and 32 characters.' );
      }
    }
    if( ! defined %error ) {
      if( $password ) {
        $error{'party_submitter'} = 1 if $form{'party_submitter'} ne $submitter;
        $error{'party_password'} = 1 if $form{'party_password'} ne $password;
        &page_error( 'Your personal data is not correct.' );
      }
    }
    if( ! defined %error ) {
      if( $form{'party_flyer_file'} ) {
        ( $file_size, $file ) = &upload_file( 'party_flyer_file', $path{'images'} . '/party/flyer', $party->get( 'id' ) . '.<.FE>', 'jpg|jpeg|gif|png', 1 );
        if( $file_size == -2 ) {
          $error{'party_flyer_file'} = 1;
          &page_error( 'The filetype of the fyler is not permitted.' );
        }
        elsif( $file_size < 0 ) {
          $error{'party_flyer_file'} = 1;
          &page_error( 'Unknown error has occured.' );
        }
        elsif( $file_size > 100000 ) {
          unlink $path{'images'} . '/party/flyer/' . $file;
          $error{'party_flyer_file'} = 1;
          &page_error( 'The flyer is to big - 100k maximal' );
          $party->set( 'flyer', '' );
        }
        else {
          unlink $path{'images'} . '/party/flyer/' . $party->get( 'flyer' ) if $party->get( 'flyer' ) ne $file;
          $party->set( 'flyer', $file );
        }
      }
    }
    if( ! defined %error ) {
      $party->set( 'edit_timestamp', time );
      $party->set( 'status', 1 );
      $party->save();
      $party->to_form();
      &page_message( 'The party update was successful.' );
    }
  }
  if( ! $form{'step'} ) {
    $party->set( 'password', '' );
    $party->to_form();
  }
  if( $form{'action'} ) {
    &ssms::system::add_tag_handler( \&view_party, \$party );
    &ssms::system::load( 'client/party_edit.htm' );
  }
}
if( $form{'action'} eq 'view' ) {
  &init_party( $form{'party_id'} );
  &ssms::system::add_tag_handler( \&view_party, \$party );
  &ssms::system::load( 'client/party_details.htm' );
}
elsif( $form{'action'} eq 'xml' ) {
  &ssms::time::set_format( 'H:m', 'Y/m/d' );
  &ssms::system::add_tag_handler( \$party );
  &ssms::system::add_loop( '<PARTY_TIMER>', \&load_parties, \&view_party, \&init_party );
  &ssms::system::load( 'client/party_xml.htm' );
  print "Content-Type: text/xml \n\n", '<?xml version=\'1.0\' encoding=\'iso-8859-1\' ?>', "\n";
  &ssms::system::main( 1 );
  exit;
}
elsif( $form{'action'} eq 'test' ) {
  &update_goabase();
}
if( ! $form{'action'} ) {
  &ssms::time::set_format( 'h:m p', 'D, d. M Y' );
  &ssms::system::add_tag_handler( \$party );
  &ssms::system::add_loop( '<CALENDAR_YEARS(.*)>', \&load_calendar_years, \&view_calendar, \&init_calendar, 1 );
  &ssms::system::add_loop( '<CALENDAR_MONTHS>', \&load_calendar_months, \&view_calendar, \&init_calendar, 1 );
  &ssms::system::add_loop( '<CALENDAR_DAYS>', \&load_calendar_days, \&view_calendar, \&init_calendar, 1 );
  &ssms::system::add_loop( '<PARTY_TIMER>', \&load_parties, \&view_party, \&init_party );
  &ssms::system::load( 'client/party_main.htm' );
}

sub update_goabase {
  require 'XML/Parser.pm';
  $ssms::dbi::debug = 1;
  my $url = 'http://www.goatrance.de/goabase/party_export_xml.php';
  my $content = LWP::Simple::get( $url );
  exit unless $content;
  my $parser = XML::Parser->new( Style => 'Tree' );
  $xml_data = $parser->parse( $content );
  $sql = 'SELECT source_id FROM party WHERE source_name LIKE "%goabase%" ORDER BY source_id DESC LIMIT 0,1';
  my $last_id = ( &ssms::dbi::query( $sql ) )[0];
  return unless ${$xml_data}[0] eq 'partybase';
  my $found = 0;
  my %party = ();
  foreach( @{${$xml_data}[1]} ) {
    if( /party/ ) {
      $found = 1;
      %party = ();
    }
    elsif( $found ) {
      $i = 0;
      foreach $id( @{$_} ) {
        if( ( $i % 2 ) == 1 ) {
          $party{${$_}[$i]} = ${${$_}[$i+1]}[2];
        }
        $i ++;
      }
      if( $party{'id'} > $last_id ) {
        $party = hbr1::party->new();
        $party->set( 'source_id', $party{'id'} );
        $party->set( 'source_name', 'www.goatrance.de/goabase' );
        $party->set( 'source_url', 'http://www.goatrance.de/goabase/party_details.php3?svLang=en&PID=' . $party{'id'} );
        $party->set( 'title', $party{'party_name'} );
        if( $party{'start_date'} =~ /(\d{4})\-(\d{2})\-(\d{2}) (\d{2})\:(\d{2})\:(\d{2})/ ) {
          $party->set( 'start_timestamp', Time::Local::timegm( $6, $5, $4, $3, $2 - 1, $1 ) );
        }
        if( $party{'end_date'} =~ /(\d{4})\-(\d{2})\-(\d{2}) (\d{2})\:(\d{2})\:(\d{2})/ ) {
          $party->set( 'end_timestamp', Time::Local::timegm( $6, $5, $4, $3, $2 - 1, $1 ) );
        }
        if( $party{'insert_date'} =~ /(\d{4})\-(\d{2})\-(\d{2}) (\d{2})\:(\d{2})\:(\d{2})/ ) {
          $party->set( 'create_timestamp', Time::Local::timegm( $6, $5, $4, $3, $2 - 1, $1 ) );
        }
        if( $party{'last_edit'} =~ /(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/ ) {
          $party->set( 'edit_timestamp', Time::Local::timegm( $6, $5, $4, $3, $2 - 1, $1 ) );
        }
        $party->set( 'type', $party{'type'} );
        $party->set( 'country', $party{'country'} );
        $party->set( 'city', $party{'town'} );
        $party->set( 'djs', $party{'djs'} );
        $party->set( 'live', $party{'live'} );
        $party->set( 'chillout', $party{'chillout'} );
        $party->set( 'deco', $party{'deco'} );
        $party->set( 'location', $party{'location'} );
        $party->set( 'hotline', $party{'hotline'} );
        $party->set( 'organizer', $party{'organizer'} );
        $party->set( 'email', $party{'email'} );
        $party->set( 'url', $party{'url'} );
        $party->set( 'memo', $party{'memo'} );
        $party->set( 'submitter', $party{'submitter'} );
        $party->set( 'status', 1 );
        $party->save();
        return;
      }
      $found = 0;
    }
  }
}

sub load_calendar_years {
  my $args = shift;
  my( %argv, %time, @out, $time );
  %argv = &ssms::http::split_param( $args );
  $argv{'start'} = -5 if ! defined $argv{'start'} || $argv{'start'} > 0;
  $argv{'end'} = 5 if ! defined $argv{'end'} || $argv{'end'} < 0;
  %time = &ssms::time::as_hash( $form{'time_from'} );
  @out = ();
  for( $i = $time{'Year'} + $argv{'start'}; $i < $time{'Year'} + $argv{'end'}; $i ++ ) {
    $time = Time::Local::timegm( 0, 0, 0, $time_from{'day'}, $time_from{'month'} - 1, $i );
    push @out, 'year|' . $i . '|timestamp|' . Time::Local::timegm( 0, 0, 0, $time_from{'day'}, $time_from{'month'} - 1, $i );
    $current_timestamp = $time if $i == $time_from{'Year'};
  }
  return @out;
}

sub load_calendar_months {
  my( @month, @out, $i, $time );
  @month = ( 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec' );
  @out = ();
  for( $i = 0; $i < 12; $i ++ ) {
    $time = Time::Local::timegm( 0, 0, 0, $time_from{'day'}, $i, $time_from{'Year'} );
    push @out, 'month|' . $ssms::time::Month{$month[$i]} . '|timestamp|' . $time;
    $current_timestamp = $time if $time_from{'month'} - 1 == $i;
  }
  return @out;
}

sub load_calendar_days {
  my( $month_start, $month_end ) = &ssms::time::get_month( $form{'time_from'}, 1 );
  my @out = ();
  my %time;
  %time = &ssms::time::as_hash( $month_end );
  while( $month_start < $month_end ) {
    %time = &ssms::time::as_hash( $month_start );
    push @out, 'date|' . ( $time{'day'} < 10 ? '0' : '' ) . $time{'day'} . '|day|' . $time{'Day'} . '|timestamp|' . $month_start;
    $current_timestamp = $month_start if $time_from{'day'} == $time{'day'};
    $month_start += 86400;
  }
  return @out;
}

sub init_calendar {
  my $args = shift;
  %calendar = split( /\|/, $args );
}

sub view_calendar {
  $tag = shift if defined @_;
  if( $tag =~ /<CALENDAR_(\w+)>/ ) { &out( $calendar{lc($1)} ); }
  elsif( $tag =~ /<IF_CALENDAR_CURRENT>/ ) { &con( $current_timestamp == $calendar{'timestamp'} ); }
  else { return 0; }
  return 1;
}

sub load_parties {
  $form{'time_from'} = Time::Local::timegm( 0, 0, 0, $time_from{'day'}, $time_from{'month'} - 1, $time_from{'year'} );
  $sql = 'SELECT id FROM party WHERE status <> 0 AND start_timestamp >= ' . $form{'time_from'} . ' ORDER BY start_timestamp, end_timestamp DESC;';
  @tmp = &ssms::dbi::query( $sql );
  $form{'count'} = 10 if $form{'count'} <= 0;
  $form{'max'} = @tmp;
  $form{'to'} = $form{'from'} + $form{'count'};
  return @tmp;
}

sub init_party {
  my $id = shift;
  $party->set( 'old_start_day', $party->get( 'start_day' ) );
  $party->load( $id );
  ( $ftm, $fdt ) = &ssms::time::format_time( $party->get( 'start_timestamp' ) );
  $party->set( 'start_timestamp_time', $ftm, 'start_timestamp_date', $fdt );
  ( $ftm, $fdt ) = &ssms::time::format_time( $party->get( 'end_timestamp' ) );
  $party->set( 'end_timestamp_time', $ftm, 'end_timestamp_date', $fdt );
  ( $ftm, $fdt ) = &ssms::time::format_time( $party->get( 'create_timestamp' ) );
  $party->set( 'create_timestamp_time', $ftm, 'create_timestamp_date', $fdt );
  ( $ftm, $fdt ) = &ssms::time::format_time( $party->get( 'edit_timestamp' ) );
  $party->set( 'edit_timestamp_time', $ftm, 'edit_timestamp_date', $fdt );
  %time = &ssms::time::as_hash( $party->get( 'start_timestamp' ) );
  $party->set( 'start_day', $time{'day'} );
  $sql = 'SELECT name FROM country WHERE id = "' . $party->get( 'country' ) . '";';
  $country = ( &ssms::dbi::query( $sql ) )[0];
  $party->set( 'country', $country ) if $country;
  $sql = 'SELECT COUNT(*) FROM party_comment WHERE party_id = ' . $id . ';';
  $party->set( 'comment_count', ( &ssms::dbi::query( $sql ) )[0] );
}

sub view_party {
  $tag = shift if defined @_;
  if( $tag =~ /<SELECT_PARTY_TYPE(.*)>/ ) {
    &out( &input( 'party_type', $1, 'select', 'IndoorÿOpen AirÿIndoor & Open AirÿClub' ) );
  }
  elsif( $tag =~ /<PARTY_START_TIME_DAY=(\d+)>/ ) {
    &out( Time::Local::timegm( 0, 0, 0, $1, $time_from{'month'} - 1, $time_from{'year'} ) );
  }
  elsif( $tag =~ /<PARTY_START_TIME_MONTH=(\d+)>/ ) {
    &out( Time::Local::timegm( 0, 0, 0, $time_from{'day'}, $1, $time_from{'year'} ) );
  }
  elsif( $tag =~ /<PARTY_START_TIME_YEAR=(\d+)>/ ) {
    &out( Time::Local::timegm( 0, 0, 0, $time_from{'day'}, $time_from{'month'} - 1, $1 ) );
  }
  elsif( $tag =~ /<SELECT_PARTY_COUNTRY(.*)>/ ) {
    $out = '';
    $sql = 'SELECT id, name FROM country ORDER BY name;';
    &ssms::dbi::query( $sql, 1 );
    while( @row = &ssms::dbi::fetch() ) {
      $out .= $row[0] . 'þ' . $row[1] . 'ÿ';
    }
    &out( &input( 'party_country', $1, 'select', substr( $out, 0, length( $out ) - 1 ) ) );
  }
  elsif( $tag =~ /<IF_NEW_PARTY_START_DAY>/ ) { &con( $party->get( 'start_day' ) != $party->get( 'old_start_day' ) ); }
  else { return 0; }
  return 1;
}

1;
