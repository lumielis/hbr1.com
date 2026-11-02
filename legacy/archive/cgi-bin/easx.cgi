#!/usr/bin/perl
use LWP::UserAgent;
require 'include/settings.inc';
#require $path{'ssms'} . 'system.pl';
#die $ENV{'QUERY_STRING'};
foreach( keys %station_def ) {
  *station = \%{ $station_def{ $_ } };
  last if $ENV{'QUERY_STRING'} =~ /^${ $station_def{ $_ } }{ 'id' }/i;
}
#print "Content-Type: video/x-ms-asf \n\n";
if( $station{'mms_alias'} ) {
  $ua = new LWP::UserAgent();
  $ua->agent( 'Windows Mediaplayer' );
  $request = HTTP::Request->new( 'GET', $station{'mms_alias'} );
  $request->content_type( 'video/x-ms-asf' );
  $res = $ua->request( $request );
  if( $res->content ) {
  }
  else {
    $station{'mms_alias'} = 'http://' . $station{'shout_host'} . ':' . $station{'shout_port'};
  }
}
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
