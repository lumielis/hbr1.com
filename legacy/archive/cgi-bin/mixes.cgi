#!/usr/local/bin/perl

require 'include/settings.inc';
require $path{'ssms'} . 'system.pl';
require $path{'ssms'} . 'dbi.pl';

&ssms::system::init( 1 );
&ssms::dbi::connect( $dbi{'dsn'}, $dbi{'user'}, $dbi{'pass'} );

require 'include/client.pl';
require 'include/admin/mixes.pl';

if( $form{'action'} eq 'view' ) {
  &ssms::system::add_loop( '<LOOP_MIXES>', \&load_mixes, \&view_mix, \&init_mix );
  &ssms::system::load( 'client/mixes.htm' );
}
elsif( $form{'action'} eq 'playlist' ) {
  &init_mix( $form{'mix_id'} );
  &ssms::system::add_tag_handler( \&view_mix );
  &ssms::system::add_loop( '<LOOP_RELEASES>', \&load_releases, \&view_release, \&init_release );
  &ssms::system::load( 'client/mix_details.htm' );
}
else {
  &ssms::system::add_loop( '<LOOP_MIX_CATEGORIES>', \&load_mix_categories, \&view_mix_category, \&init_mix_category );
  &ssms::system::load( 'client/mix_categories.htm' );
}

&ssms::system::main();

