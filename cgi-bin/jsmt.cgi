#!/usr/bin/perl
$ENV{'SL'} = '/';

require 'include/settings.inc';
require $path{'ssms'} . 'table.pl';

print "Content-Type: text/html \n\n";
exit if $ENV{'HTTP_USER_AGENT'} ne 'SMS CamTV(Win32-Delphi)';

read STDIN, $temp, $ENV{'CONTENT_LENGTH'};
@inp = split( /\|/, $temp );
@tab = &ssms::table::read( $path{'data'} . '/rooms.db' );
@out = ();
$room = 0;
foreach( @tab ) {
  @row = split( /\|/, $_ );
  if( $row[1] eq $inp[0] ) {
    $row[3] = $inp[1];
    $row[4] = $inp[2];
    $room = $row[0];
  }
  push @out, @row;
}
exit if ! $room;
&ssms::table::write( $path{'data'} . '/rooms.db', @out );

# notify clients
@tmp = ();
$file = $path{'data'} . '/chat/' . $room;
if( -e $file . '.txt' ) {
  &lock( $file );
  open FILE, '< ' . $file . '.txt';
  while( <FILE> ) {
    chomp;
    push @tmp, $_ if $_;
  }
  close FILE;
  &unlock( $file );
}
foreach( @tmp ) {
  @row = split( /\|/, $_ );
  $file = $path{'data'} . '/chat/' . $row[0];
  &lock( $file );
  open FILE, '>> ' . $file . '.txt';
  print FILE '10|', $inp[1], "\xFF", $inp[2], "\xFF", 1, '|';
  close FILE;
  &unlock( $file );
}

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

