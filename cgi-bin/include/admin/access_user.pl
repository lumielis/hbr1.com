if( $form{'action'} eq 'access_user' ) {

	$user_info = $access->add_class( 'ssms::module::user::info', 1, ${ ${ $setup{'module'} }{'setup_user'} }{'setup_info'} );

	if( $form{'part'} eq 'edit' ) {
		
		$edit_user = $access_user->clone();
		$edit_user_info = $user_info->clone();
		
		if( $form{'access_user_id'} ne '' ) {
			$edit_user->load( $form{'access_user_id'} );
			$edit_user_info->load_by( 'user_id', $edit_user->{'user_id'} );
		}
		if( $form{'step'} == 1 ) {
			( $code, $msg ) = $edit_user->from_form();
			unless( $code =~ /^2/ ) {
				&ssms::system::log_error( "An error occured while writing data into class: code $code $msg" );
			}
			if( $code =~ /^2/ ) {
				$edit_user_info->from_form();
				$edit_user->{'old_user_id'} = $form{'access_user_old_user_id'};
				$edit_user->{'old_name'} = $form{'access_user_old_name'};
				#$DEBUG = 1;
				( $code, $msg ) = $edit_user->submit();
				unless( $code =~ /^2/ ) {
					&ssms::system::log_error( "An error occured during saving user: code $code $msg" );
				}
				if( $code =~ /^2/ ) {
					&ssms::system::log_message( 'Userdata has been saved.' );
					if( $form{'access_user_new_passwd_1'} ) {
						if( $form{'access_user_new_passwd_1'} ne $form{'access_user_new_passwd_2'} ) {
							$code = 460;
							$msg = 'Not Acceptable: PASSWORD CONFIRMATION FAILED';
						}
						else {
							( $code, $msg ) = $edit_user->change_passwd( $edit_user->decrypt_passwd(), $form{'access_user_new_passwd_1'} );
						}
						unless( $code =~ /^2/ ) {
							&ssms::system::log_error( "An error occured during saving user: code $code $msg" );
						}
						else {
							&ssms::system::log_message( 'The password has been changed.' );
						}
						delete $form{'access_user_new_passwd_1'};
						delete $form{'access_user_new_passwd_2'};
					}
				}
			}
			if( $code =~ /^2/ ) {
				$edit_user_info->{'user_id'} = $edit_user->{'user_id'};
				$edit_user_info->save();
				&ssms::system::log_message( 'Userinfo has been saved.' );
			}
			if( defined %error ) {
				&ssms::system::log_error( 'During saving process some errors occured.' );
			}
		}
		else {
			$edit_user->{'old_user_id'} = $edit_user->{'user_id'};
			$edit_user->{'old_name'} = $edit_user->{'name'};
		}
		#die $access_group->build_select();
		$edit_user->{'group_id_value'} = $access_group->build_select();
		#$edit_user->edit();
		$edit_user->to_form();
		$edit_user_info->to_form();
		&ssms::system::loop_message_log( '<LOOP_MESSAGE_LOG>' );
		&ssms::system::add_tag_handler( \$edit_user, \$edit_user_info );
		&ssms::system::load( 'admin/access_user_edit.htm' );
	}
	elsif( $form{'part'} eq 'kill' ) {
		if( $form{'access_user_id'} ) {
			$kill_user = $access_user->clone();
			$kill_user_info = $user_info->clone();
			$kill_user->load( $form{'access_user_id'} );
			$kill_user_info->load_by( 'user_id', $kill_user->{'user_id'} );
			( $code, $msg ) = $kill_user->kill();
			unless( $code =~ /^2/ ) {
				&ssms::system::log_error( "An error occured during removing the user: code $code $msg" );
			}
			if( $code =~ /^2/ ) {
				$kill_user_info->kill();
				&ssms::system::log_message( 'User has been removed.' );
			}
		}
		delete $form{'part'};
	}
	unless( $form{'part'} ) {
		%access_group = $access_group->values();
		$access_user->add_loop();
		&ssms::system::add_tag_handler( \$user_info, \&view_access_user );
		&ssms::system::loop_message_log( '<LOOP_MESSAGE_LOG>' );
		&ssms::system::load( 'admin/access_user_index.htm' );
	}
}

sub view_access_user {
	my $tag = shift if defined @_;
	if( $tag =~ /<ACCESS_USER_GROUP=(.+)>/ ) { &out( ${ $access_group{ $access_user->{'group_id'} } }{ $1 } ); }
	else { return 0; }
	return 1;
}

1;
