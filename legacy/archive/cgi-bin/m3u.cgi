#!/usr/bin/perl
require 'include/settings.inc';
foreach( keys %station_def ) {
  *station = \%{ $station_def{ $_ } };
  last if $ENV{'QUERY_STRING'} =~ /^${ $station_def{ $_ } }{ 'id' }/i;
}
$station{'url'} = 'http://' . $station{'shout_host'} . ':' . $station{'shout_port'};
print "Content-Type: audio/x-mpegurl \n\n";
print <<EOFT;
#EXTM3U
#EXTINF:HBR1.com - Music on Futurenet
$station{'url'}
EOFT
