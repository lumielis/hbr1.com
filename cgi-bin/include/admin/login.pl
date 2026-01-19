if( $form{'action'} eq 'login' ) {
	if( $form{'step'} == 1 ) {
		if( $form{'do_login'} ) {
			if( $form{'login_username'} eq $access_user->{'name'} ) {
				&log_message( 'You have are logged in successfully.' );
			}
			else {
				&log_error( 'The username or password you have entered does not match our records. Please try again.' );
			}
		}
		elsif( $form{'do_logout'} ) {
			&log_message( 'You are logged out now.' );
		}
	}
	&ssms::system::add_tag_handler( \&view_login );
	&ssms::system::loop_message_log( '<LOOP_MESSAGE_LOG>' );
	&ssms::system::load( 'admin/login.htm' );
}

1;
