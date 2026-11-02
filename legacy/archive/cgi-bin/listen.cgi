#!/usr/bin/perl

require 'include/settings.inc';
require $path{'ssms'} . 'system.pl';

#&ssms::system::init( 1 );
#&ssms::system::load( $setup{'radio_stream'} );
#print "Content-Type: audio/x-scpls\n\n";
#&ssms::system::main( 1 );
#exit;

print "Content-Type: audio/x-mpegurl \n\n";
print <<EOFT;
#EXTM3U
#EXTINF:HBR1 - Futurenet Radio
$setup{'radio_stream'}
EOFT
