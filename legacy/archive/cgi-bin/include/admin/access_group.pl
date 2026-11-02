if( $form{'action'} eq 'access_group' ) {
	
	if( $form{'part'} eq 'edit' ) {
		$edit_group = $access_group->clone();
		if( $form{'access_group_id'} ) {
			( $code, $msg ) = $edit_group->load( $form{'access_group_id'} );
			unless( $code =~ /^2/ ) {
				&log_error( "An error occured while loading group: code $code - $msg" );
			}
		}
		if( $form{'step'} == 1 ) {
			$edit_group->{'old_group_id'} = $form{'access_group_old_group_id'};
			$edit_group->{'old_name'} = $form{'access_group_old_name'};
			( $code, $msg ) = $edit_group->from_form();
			unless( $code =~ /^2/ ) {
				&log_error( "An error occured while writing data into class: code $code - $msg" );
			}
			if( $code =~ /^2/ ) {
				( $code, $msg ) = $edit_group->submit();
				unless( $code =~ /^2/ ) {
					&ssms::system::log_error( "An error occured during saving group: code $code - $msg" );
				}
				if( $code =~ /^2/ ) {
					&ssms::system::log_message( 'Group has been saved.' );
				}
			}
		}
		else {
			$edit_group->{'old_group_id'} = $edit_group->{'group_id'};
			$edit_group->{'old_name'} = $edit_group->{'name'};
		}
		$edit_group->to_form();
		&ssms::system::add_tag_handler( \$edit_group );
		&ssms::system::loop_message_log( '<LOOP_MESSAGE_LOG>' );
		&ssms::system::load( 'admin/access_group_edit.htm' );
	}
	elsif( $form{'part'} eq 'kill' ) {
		( $code, $msg ) = $access->remove_group( { group_id => $form{'group_id'} } );
		unless( $code =~ /^2/ ) {
			&log_error( "An error occured while deleting group: code $code - $msg" );
		}
		if( $code =~ /^2/ ) {
			&log_message( 'Group has been removed' );
		}
		delete $form{'part'};
	}
	unless( $form{'part'} ) {
		$access_group->add_loop();
		&ssms::system::loop_message_log( '<LOOP_MESSAGE_LOG>' );
		&ssms::system::load( 'admin/access_group_index.htm' );
	}
}

1;
