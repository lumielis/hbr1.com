#!/usr/bin/perl
# SM Chat Engine 1.2 - Server Application
# Copyright 2001 by christian.mueller@europe.com

require 'include/settings.inc';

print "Connection: close \n";
print "Cache-Control: no-cache \n";
print "Content-Type: text/html \n\n";

( $action, $id, $room, $flags, $msg, $extra ) = split( /\|/, $ENV{'QUERY_STRING'} );

if( $action >= 0xFF0000 ) {
  $action -= 0xFF0000;
  $frame = 1;
  print '<body bgcolor=', $chat{'bgcolor'}, ' OnLoad=parent.OnData(this);><form><input type=hidden name=Data value=\'';
}
else {
  $frame = 0;
}

if( $action == 0 ) {
  $now = time - $chat{'time'};
  print '7|';
  # search client
  $file = $path{'data'} . '/chat/' . $room;
  &lock( $file );
  open FILE, '< ' . $file . '.txt';
  while( <FILE> ) {
    chomp;
    @row = split( /\|/, $_ );
    if( $row[0] == $id ) {
      $row[3] = time;
      $_ = join( "|", @row );
      $f_ = 1;
    }
    if( $_ && $row[3] > $now ) {
      push @tmp, $_;
      print $row[0] . "\xFF" . $row[2] . "\xFF" . ( $row[3] - $row[1] ) . "\xFF";
    }
  }
  close FILE;
  open FILE, '> ' . $file . '.txt';
  print FILE join( "\n", @tmp ), "\n" if @tmp;
  close FILE;
  &unlock( $file );
  # update client
  if( $f_ ) {
    print '|';
    # get new messages
    $file = $path{'data'} . '/chat/' . $id;
    &lock( $file );
    if( -e $file . '.txt' ) {
      open FILE, '< ' . $file . '.txt';
      print <FILE>;
      close FILE;
    }
    # clear message file
    open FILE, '> ' . $file . '.txt';
    close FILE;
    &unlock( $file );
  }
  else {
    print '3|';
  }
  &quit();
}
elsif( $action == 1 ) {
  require 'include/chat/spread.pl';
  &quit();
}
elsif( $action == 2 ) {
  require 'include/chat/move.pl';
  &quit();
}
elsif( $action == 3 ) {
  require 'include/chat/info.pl';
  &quit();
}

print 0;
&quit();

sub lock {
  my $etm = time + 15;
  while( -e $_[0] && time < $etm ) {
    open LFFH, '> ' . $_[0];
  }
  sub unlock {
    close LFFH;
    unlink $_[0];
  } 
}

sub quit {
  print '\'></form></body>' if $frame;
  exit;
}
