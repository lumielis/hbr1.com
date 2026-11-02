#!/opt/perl/bin/perl

package main;

use CGI qw/:standard/;

if( $0 =~ /(.+\/)(.+)/ ) {
    $ENV{SCRIPT_PATH} = $1;
    $ENV{SCRIPT} = $2;
}

require $ENV{SCRIPT_PATH} . './include/init.pl';

if( $ENV{'HTTP_REFERER'} !~ /hbr1\.com/i ) {
  #print "Location: http://www.hbr1.com \n\n";
  #exit;
}

if( $form{'action'} ) {
  require $ENV{SCRIPT_PATH} . './include/client/' . $form{'action'} . '.pl';
}
else { &ssms::system::load( 'client/empty.htm' ); }

&ssms::system::main();
#&ssms::access::log( 'access_struct', 'access_log' );
