#!/usr/bin/perl
require 'include/settings.inc';
require 'include/webtv.pl';

print "Content-Type: text/html \n\n";
exit if $ENV{'HTTP_USER_AGENT'} ne 'SMS CamTV(Win32-Delphi)';

if( $ENV{'QUERY_STRING'} ) {
  &ssms::webtv::lock_folder( $path{'webtv'} . '/images', 1, 1 );
  &ssms::webtv::lock_folder( $path{'webtv'} . '/images/live', 0, 0 );
}
else {
  &ssms::webtv::lock_folder( $path{'webtv'} . '/images', 0, 1 );
  &ssms::webtv::lock_folder( $path{'webtv'} . '/images/live', 1, 0 );
}
