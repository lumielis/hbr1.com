#!/usr/bin/perl
require 'include/settings.inc';
#require $path{'ssms'} . 'system.pl';
#die $ENV{'QUERY_STRING'};
foreach( keys %station_def ) {
  *station = \%{ $station_def{ $_ } };
  last if $ENV{'QUERY_STRING'} =~ /^${ $station_def{ $_ } }{ 'id' }/i;
}
#print "Content-Type: text/plain \n\n";
$station{'mms_alias'} = 'http://' . $station{'shout_host'} . ':' . $station{'shout_port'} unless $station{'mms_alias'};
print "Content-Type: video/x-ms-asf \n\n";
print <<EOFT;
<ASX VERSION="3.0">
<TITLE>HBR1</TITLE>
<ENTRY>    
  <REF HREF="$station{'mms_alias'}" />
  <TITLE>HBR1: $station{'name'}</TITLE>
  <ABSTRACT>Futurenet Radio</ABSTRACT>
  <COPYRIGHT>www.HBR1.com</COPYRIGHT>
  <AUTHOR>Futurenet Radio</AUTHOR>
	<PARAM name="Album" value="www.HBR1.com"/>
	<PARAM name="Artist" value="Futurenet Radio"/>
  <PARAM name="Genre" value="$station{'genre'}"/>
</ENTRY>
</ASX>
EOFT
