#!/usr/bin/perl

require 'include/settings.inc';

my( $file, $lock, $image, $tme );
$file = $ENV{'QUERY_STRING'};
$file =~ s/(.*?)\&.*/$1/;
$lock = $file . 'lock';
$tme = time + 15;
while( -e $lock && time < $tme ) {
  open LFFH, '> ' . $lock;
}
open FILE, $path{'images'} . '/camtv/' . $file;
binmode FILE;
read FILE, $image, 0xFFFF;
close FILE;
close LFFH;
unlink $lock;
print "Connection: close \n";
print "Cache-Control: no-cache \n";
print "Content-Type: image/jpg \n\n";
binmode STDOUT;
print $image;
