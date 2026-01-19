#!/usr/bin/perl

require 'include/settings.inc';
require $path{'ssms'} . 'system.pl';
require $path{'ssms'} . 'dbi.pl';

package hbr1::chat;

require $main::path{'ssms'} . 'dbi/table.pl';
our @ISA = qw(ssms::dbi::table);
our $VERSION = '1.0.0';

sub new {
  my $proto = shift;
  my $class = ref( $proto ) || $proto;
  my $self  = {};
  bless( $self, $class );
  $self->table( 'chat' );
  $self->{'name_data_type'} = 'tagout';
  $self->{'name_required'} = 1;
  $self->{'content_data_type'} = 'tagout';
  $self->{'content_required'} = 1;
  return $self;
}

package main;

&ssms::dbi::connect( $dbi{'dsn'}, $dbi{'user'}, $dbi{'pass'} );

if( $form{'part'} eq 'submit' ) {
  #die pack( 'H', 255 );
  $chat = new hbr1::chat();
  if( $form{'step'} == 1 ) {
    $chat->from_form();
    unless( defined %error ) {
      $error{'chat_name'} = 1 unless $form{'chat_name'};
      $error{'chat_content'} = 1 unless $form{'chat_content'};
    }
    unless( defined %error ) {
      $chat->{'timestamp'} = time;
      $chat->{'remote_addr'} = $ENV{'REMOTE_ADDR'};
      if( $form{'debug'} ) {
      	die &color_by_name( $chat->{'name'} );
      }
     	$chat->{'color'} = &color_by_name( $chat->{'name'} ) unless $chat->{'color'};
      #$chat->{'color'} = 'FFFFFF' unless $chat->{'color'};
      $chat->save();
      &write_chat();
      $form{'chat_content'} = '';
      $form{'chat_reload'} = 1;
      &ssms::http::save_cookie( 'chat_name=' . $form{'chat_name'}, $setup{'cookie_domain'}, $setup{'cookie_path'} );
    }
  }
  unless( $form{'step'} ) {
    $cookie{'chat_name'} = 'Nickname' unless $cookie{'chat_name'};
    $form{'chat_name'} = $cookie{'chat_name'};
    #$chat->{'timestamp'} = time;
    #$chat->{'remote_addr'} = $ENV{'REMOTE_ADDR'};
    #$chat->{'color'} = 'AAFF55';
    #$chat->{'content'} = '<i>' . $form{'chat_name'} . ' is visiting the chat room</i>';
    #$chat->save();
    #&write_chat();
    #$form{'chat_reload'} = 1;
    #hex( 100 + rand( 155 ) )
    #die &rnd_color( 100 );
  }
  &ssms::system::add_tag_handler( \$chat );
  &ssms::system::load( 'client/chat_submit.htm' );
}
elsif( $form{'part'} eq 'save' ) {
  &save_chat();
  &write_chat();
  $form{'part'} = undef;
}
unless( $form{'part'} ) {
  &ssms::system::add_loop( '<LOOP_CHAT(.*)>', \&load_chat, \&view_chat, \&init_chat );
  &ssms::system::load( 'client/chat_main.htm' );
}

&ssms::system::main();

exit;

sub save_chat {
  return;
  my $sql = 'SELECT COUNT(*) FROM chat';
  my $count = ( &ssms::dbi::query( $sql ) )[0];
  return unless $count > $setup{'chat_history_length'} * 2;
  $sql = 'SELECT * FROM chat LIMIT 0, ' . ( $count - $setup{'chat_history_length'} );
  &ssms::dbi::query( $sql, 1 );
  $sql = 'INSERT INTO chat_history (timestamp,remote_addr,name,content,color) VALUES';
  while( %row = &ssms::dbi::fetch( 1 ) ) {
    $sql .= '(' . $row{'timestamp'} . ',"' . $row{'remote_addr'} . '","'
         . $row{'name'} . '","' . $row{'content'} . '","' . $row{'color'} . '"),';
  }
  &ssms::dbi::exec( substr( $sql, 0, length( $sql ) - 1 ) );
  $sql = 'DELETE FROM chat LIMIT 0, ' . ( $count - $setup{'chat_history_length'} );
  &ssms::dbi::exec( $sql );
  #die $sql;
}

sub write_chat {
  &save_chat();
  my $out = '';
  &ssms::system::backup();
  &ssms::system::add_loop( '<LOOP_CHAT(.*)>', \&load_chat, \&view_chat, \&init_chat );
  &ssms::system::load( 'client/chat_main.htm' );
  &ssms::system::main( 1, \$out );
  open FILE, '> ' . $path{'cache'} . '/chat.html';
  print FILE $out;
  close FILE;
  &ssms::system::restore();
}

sub load_chat {
  my( $sql, @res, $index );
  $sql = 'SELECT * FROM chat ORDER BY id DESC LIMIT 0, ' . $setup{'chat_history_length'};
  &ssms::dbi::query( $sql, 1 );
  for( $index = 0; %{ $res[ $index ] } = &ssms::dbi::fetch( 1 ); $index ++ ) {}
  $#res --; # remove last index
  return @res;
}

sub init_chat {
  *chat = shift;
  ( $chat{'time'}, $chat{'date'} ) = &ssms::time::format_time( $chat{'timestamp'} );
}

sub view_chat {
  $tag = shift if defined @_;
  if( $tag =~ /<CHAT_(\w+)>/ ) { &out( $chat{ lc( $1 ) } ); }
  elsif( $tag =~ /<IF_CHAT_(\w+)>/ ) { &con( $chat{ lc( $1 ) } ); }
  else { return 0; }
  return 1;
}

sub rnd_color {
  my( $base, $rnd, $r, $g, $b );
  $base = int( shift );
  $rnd = 255 - $base;
  $r = unpack( "H*", chr( $base + rand( $rnd ) ) );
  $g = unpack( "H*", chr( $base + rand( $rnd ) ) );
  $b = unpack( "H*", chr( $base + rand( $rnd ) ) );
  return $r . $g . $b;
}

sub color_by_name {
	my $name = shift;
	my ( $index, $name_length, %special_char, @name, $red, $green, $blue );
	%special_char = ( 'A' => 1, 'E' => 1, 'I' => 1, 'O' => 1, 'a' => 1, 'e' => 1, 'i' => 1, 'o' => 1 );
	@name = split( //, $name );
	$name_length = scalar( @name );
	for( $index = 0; $index < $name_length; $index ++ ) {
		if( $special_char{ $name[ $index ] } ) {
			$red ++;
		}
		else {
			$blue ++;
		}
		$green += ord( $name[ $index ] );
	}
	$red = 150 + int( 105 / $name_length * $red );
	$green = 150 + int( 105 / ( $name_length * 255 ) * $green );
	$blue = 150 + int( 105 / $name_length * $blue );
  $red = unpack( "H*", chr( $red ) );
  $green = unpack( "H*", chr( $green ) );
  $blue = unpack( "H*", chr( $blue ) );
  return $red . $green . $blue;
}