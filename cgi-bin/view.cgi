#!/usr/bin/perl

require 'include/settings.inc';
require $path{'ssms'} . 'system.pl';
&ssms::system::init( 1 );
&ssms::system::load( $form{'object'} . '.htm' );
&ssms::system::main();
