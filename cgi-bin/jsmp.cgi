#!/usr/bin/perl

require 'include/settings.inc';

my( $file, $lock, $image );
print "Content-Type: text/html \n\n";
exit if $ENV{'HTTP_USER_AGENT'} ne 'SMS CamTV(Win32-Delphi)';
exit if $ENV{'CONTENT_LENGTH'} < 500;
binmode STDIN;
read STDIN, $file, 32;
$ENV{'CONTENT_LENGTH'} -= 32;
$file =~ s/(.*?)\x00/$1/;
exit if ! $file;
read STDIN, $image, $ENV{'CONTENT_LENGTH'};
$lock = $file . 'lock';
$tme = time + 15;
while( -e $lock && time < $tme ) {
  open LFFH, '> ' . $lock;
}
open FILE, '> ' . $path{'chat'} . '/images/camtv/' . $file;
binmode FILE;
print FILE $image;
close FILE;
close LFFH;
unlink $lock;
#exit unless -d $path{'webtv'};
open FILE, '> ' . $path{'webtv'} . '/live/live.jpg';
binmode FILE;
print FILE $image;
close FILE;
