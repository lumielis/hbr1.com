package hbr1::party;

require $main::path{'ssms'} . 'dbi/table.pl';
our @ISA = qw(ssms::dbi::table);
our $VERSION = '1.0.0';

sub new {
  my $proto = shift;
  my $class = ref( $proto ) || $proto;
  my $self  = {};
  bless( $self, $class );
  $self->table( 'party' );
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

if( $form{'part'} eq 'edit' ) {
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
  if( ! $party->{'create_timestamp'} ) {
    delete $form{'step'};
    delete $form{'action'};
    &page_error( 'This party does not exist.' );
  }
  if( $form{'step'} == 1 ) {
    $submitter = $party->{'submitter'};
    $password = $party->{'password'};
    $party->from_form();
    if( ! defined %error ) {
      $error{'party_title'} = 1 if length( $form{'party_title'} ) < 3;
      $error{'party_city'} = 1 if length( $form{'party_city'} ) < 3;
      $error{'party_location'} = 1 if length( $form{'party_location'} ) < 3;
      $error{'party_start_timestamp'} = 1 if $party->{'start_timestamp'} < time && ! $form{'su'};
      $error{'party_end_timestamp'} = 1 if $party->{'end_timestamp'} <= $party->{'start_timestamp'};
      $error{'party_end_timestamp'} = 1 if $party->{'end_timestamp'} > $party->{'start_timestamp'} + 86400 * 21;
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
        ( $file_size, $file ) = &upload_file( 'party_flyer_file', $path{'images'} . '/party/flyer', $party->{'id'} . '.<.FE>', 'jpg|jpeg|gif|png', 1 );
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
          unlink $path{'images'} . '/party/flyer/' . $party->{'flyer'} if $party->{'flyer'} ne $file;
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
if( $form{'part'} eq 'view' ) {
  &init_party( $form{'party_id'} );
  &ssms::system::add_tag_handler( \&view_party, \$party );
  &ssms::system::load( 'client/party_details.htm' );
}
elsif( $form{'part'} eq 'xml' ) {
  &ssms::time::set_format( 'H:m', 'Y/m/d' );
  &ssms::system::add_tag_handler( \$party );
  &ssms::system::add_loop( '<PARTY_TIMER>', \&load_parties, \&view_party, \&init_party );
  &ssms::system::load( 'client/party_xml.htm' );
  print "Content-Type: text/xml \n\n", '<?xml version=\'1.0\' encoding=\'iso-8859-1\' ?>', "\n";
  &ssms::system::main( 1 );
  exit;
}
elsif( $form{'part'} eq 'test' ) {
  &update_goabase();
}
elsif( $form{'part'} eq 'comment' ) {
	require 'include/client/party_comment.pl';
}
unless( $form{'part'} ) {
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
        $party->{'source_id'} = $party{'id'};
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
  my( %argv, %time, @out, $time, $timerange, $start_time, $end_time, $day );
  %argv = &ssms::http::split_param( $args );
  $argv{'start'} = -5 if ! defined $argv{'start'} || $argv{'start'} > 0;
  $argv{'end'} = 5 if ! defined $argv{'end'} || $argv{'end'} < 0;
  %time = &ssms::time::as_hash( $form{'time_from'} );
  @day_max = ( 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 );
  @out = ();
  for( $i = $time{'Year'} + $argv{'start'}; $i < $time{'Year'} + $argv{'end'}; $i ++ ) {
    $day = $time_from{'day'} < $day_max[ $time_from{'month'} - 1 ] ? $time_from{'day'} : $day_max[ $time_from{'month'} - 1 ];
    $time = Time::Local::timegm( 0, 0, 0, $day, $time_from{'month'} - 1, $i );
    $timerange = Time::Local::timegm( 0, 0, 0, $day, $time_from{'month'} - 1, $i + 1 ) - 1;
    $start_time = Time::Local::timegm( 0, 0, 0, 1, 0, $i );
    $end_time = Time::Local::timegm( 0, 0, 0, 1, 0, $i + 1 ) - 1;
    push @out, {
    	year => $i,
    	timestamp => $time,
    	timerange => $timerange,
    	start_time => $start_time,
    	end_time => $end_time,
    };
    $current_timestamp = $time if $i == $time_from{'Year'};
  }
  return @out;
}

sub load_calendar_months {
  my( @month, @out, $i, $time, $timerange, $start_time, $end_time );
  @month = ( 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec' );
  @out = ();
  for( $i = 0; $i < 12; $i ++ ) {
    $time = Time::Local::timegm( 0, 0, 0, 1, $i, $time_from{'Year'} );
    $start_time = $time;
    if( $i == 1 && $time_from{'day'} > 28 ) {
      $time += 27 * 86400;
    }
    else {
      $time += ( $time_from{'day'} - 1 ) * 86400;
    }
    if( $i == 11 ) {
      $timerange = Time::Local::timegm( 0, 0, 0, 1, 0, $time_from{'Year'} + 1 ) - 1;
      $timerange += $time_from{'day'} * 86400;
      $end_time = $timerange;
    }
    else {
      $timerange = Time::Local::timegm( 0, 0, 0, 1, $i + 1, $time_from{'Year'} ) - 1;
      $end_time = $timerange;
      if( $i == 1 && $time_from{'day'} > 28 ) {
        $timerange += 27 * 86400;
      }
      else {
        $timerange += ( $time_from{'day'} - 1 ) * 86400;
      }
    }
    #push @out, 'month|' . $ssms::time::Month{$month[$i]} . '|timestamp|' . $time . '|timerange|' . $timerange . '|start_time|' . $start_time . '|end_time|' . $end_time;
    push @out, {
    	month => $ssms::time::Month{$month[$i]},
    	timestamp => $time,
    	timerange => $timerange,
    	start_time => $start_time,
    	end_time => $end_time,
    };
    $current_timestamp = $time if $time_from{'month'} - 1 == $i;
  }
  return @out;
}

sub load_calendar_days {
  my( $month_start, $month_end ) = &ssms::time::get_month( $form{'time_from'}, 1 );
  my @out = ();
  my( %time, $timerange );
  %time = &ssms::time::as_hash( $month_end );
  while( $month_start < $month_end ) {
    %time = &ssms::time::as_hash( $month_start );
    #push @out, 'date|' . ( $time{'day'} < 10 ? '0' : '' ) . $time{'day'} . '|day|' . $time{'Day'} . '|timestamp|' . $month_start . '|timerange|' . ( $month_start + 86399 );
    push @out, {
    	date => ( $time{'day'} < 10 ? '0' : '' ) . $time{'day'},
    	day => $time{'Day'},
    	timestamp => $month_start,
    	timerange => $month_start + 86399,
    	start_time => $month_start,
    	end_time => $month_start + 86399,
    };
    $current_timestamp = $month_start if $time_from{'day'} == $time{'day'};
    $month_start += 86400;
  }
  return @out;
}

sub init_calendar {
  *calendar =  shift;
  $calendar{'parties'} = &parties_in_time( $calendar{'start_time'}, $calendar{'end_time'} );
}

sub view_calendar {
  $tag = shift if defined @_;
  if( $tag =~ /<CALENDAR_(\w+)>/ ) { &out( $calendar{lc($1)} ); }
  elsif( $tag =~ /<IF_CALENDAR_CURRENT>/ ) { &con( $current_timestamp == $calendar{'timestamp'} ); }
  elsif( $tag =~ /<IF_CALENDAR_(\w+)>/ ) { &con( $calendar{lc($1)} ); }
  else { return 0; }
  return 1;
}

sub load_parties {
  $form{'time_from'} = Time::Local::timegm( 0, 0, 0, $time_from{'day'}, $time_from{'month'} - 1, $time_from{'year'} );
  $sql = 'SELECT id FROM party WHERE status <> 0 AND end_timestamp >= ' . $form{'time_from'} . ' ORDER BY start_timestamp, end_timestamp DESC;';
  @tmp = &ssms::dbi::query( $sql );
  $form{'count'} = 5 if $form{'count'} <= 0;
  $form{'max'} = @tmp;
  $form{'to'} = $form{'from'} + $form{'count'};
  return @tmp;
}

sub init_party {
  my $id = shift;
  $party->set( 'old_start_day', $party->{'start_day'} );
  $party->load( $id );
  ### debugging ###
  my $gmt = $setup{'time_gmt'};
  $setup{'time_gmt'} = 0;
  ### debugging ###
  ( $party->{'start_timestamp_time'}, $party->{'start_timestamp_date'} ) = &ssms::time::format_time( $party->{'start_timestamp'} );
  ( $party->{'end_timestamp_time'}, $party->{'end_timestamp_date'} ) = &ssms::time::format_time( $party->{'end_timestamp'} );
  ( $party->{'create_timestamp_time'}, $party->{'create_timestamp_date'} ) = &ssms::time::format_time( $party->{'create_timestamp'} );
  ( $party->{'edit_timestamp_time'}, $party->{'edit_timestamp_date'} ) = &ssms::time::format_time( $party->{'edit_timestamp'} );
  %time = &ssms::time::as_hash( $party->{'start_timestamp'} );
  $party->{'start_day'} = $time{'day'};
  $sql = 'SELECT name FROM country WHERE id = "' . $party->{'country'} . '";';
  ( $country ) = &ssms::dbi::query( $sql );
  $party->{'country'} = $country if $country;
  $sql = 'SELECT COUNT(*) FROM party_comment WHERE party_id = ' . $id . ';';
  ( $party->{'comment_count'} ) = &ssms::dbi::query( $sql );
  ### debugging ###
  $setup{'time_gmt'} = $gmt;
  ### debugging ###
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
  elsif( $tag =~ /<IF_NEW_PARTY_START_DAY>/ ) { &con( $party->{'start_day'} != $party->{'old_start_day'} ); }
  else { return 0; }
  return 1;
}

sub parties_in_time {
  my( $time_from, $time_to ) = @_;
  $time_from = time unless $time_from;
  $time_to = $time_from + 86399 if $time_to - $time_from <= 0;
  my $sql = 'SELECT id FROM party WHERE start_timestamp >= ' . $time_from . ' AND start_timestamp <= ' . $time_to . ' ORDER BY start_timestamp';
  return &ssms::dbi::query( $sql );
}

1;
