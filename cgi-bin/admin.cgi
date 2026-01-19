#!/usr/local/bin/perl

#die 'sss';

use CGI qw/:standard/;

require 'include/init.pl';

require ssms::module::access;
require ssms::module::user::info;

push @INC, './module';

### create the classes ###

$access = new ssms::module::access( ${ $setup{'module'} }{'setup_access'} );

$access_group = $access->group();
$access_user = $access->user();

$user_info = $access->add_class( 'ssms::module::user::info', 1, ${ ${ $setup{'module'} }{'setup_user'} }{'setup_info'} );

### do access stuff ###

if( $form{'do_login'} ) {
	( $code, $result ) = $access->login( { user => $form{'login_username'}, passwd => $form{'login_password'} } );
	delete $form{'login_password'};
	$cookie{'as_user'} = $form{'login_username'};
}
elsif( $form{'do_logout'} ) {
	$cookie{'as_user'} = 'nobody';
	$code = 200;
}
else {
	#&dief( \%ENV );
	( $code, $result ) = $access->login( { user => $cookie{'as_user'}, session => $cookie{'as_key'} } );
	#die %cookie;
}
#$ssms::http::debug = 2;
if( $code =~ /^2/ ) {
	$form{'login_username'} = $cookie{'as_user'};
	$cookie{'as_key'} = $access->{'session_key'};
	&ssms::http::save_cookie( 'as_user=' . $cookie{'as_user'}, $setup{'cookie_domain'}, $setup{'cookie_path'}, time + 3600 );
	&ssms::http::save_cookie( 'as_key=' . $cookie{'as_key'}, $setup{'cookie_domain'}, $setup{'cookie_path'}, time + 3600 );
}

$form{'login_user_name'} = $access_user->{'name'};
$form{'login_group_name'} = $access_group->{'name'};
$form{'login_user_user_id'} = $access_user->{'user_id'};
$form{'login_group_group_id'} = $access_group->{'group_id'};

### do the main stuff ###

if( $form{'action'} ) {
	require 'include/admin/' . $form{'action'} . '.pl';
}
else {
  &ssms::system::load( 'admin/main.htm' );
}

&ssms::system::main();

### function stuff ###

sub view_login {
	$tag = shift if defined @_;
	if( $tag =~ /<INPUT_LOGIN_USERNAME(.*)>/ ) { &out( &input( 'login_username', $1, 'text' ) ); }
	elsif( $tag =~ /<PASSWORD_LOGIN_PASSWORD(.*)>/ ) { &out( &input( 'login_password', $1, 'password' ) ); }
  elsif( $tag =~ /<INPUT_DATE_FROM(.*)>/ ) { &out( &input( 'date_from', $1, 'text' ) ); }
  elsif( $tag =~ /<INPUT_DATE_TO(.*)>/ ) { &out( &input( 'date_to', $1, 'text' ) ); }
	else { return 0; }
	return 1;
}
