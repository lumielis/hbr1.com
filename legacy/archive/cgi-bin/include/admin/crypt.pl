require $path{'ssms'} . 'crypt/xor.pl';

if( $form{'action'} eq 'crypt' ) {
	$form{'crypt_key'} = 'DFSE3-KX427-A2RRG-BL5XM-CB336' unless $form{'crypt_key'};
	$form{'crypt_output'} = &ssms::crypt::xor::encrypt_hex( $form{'crypt_input'}, $form{'crypt_key'} );
	$form{'crypt_mailto_output'} = '<a href="mailto:' . $form{'crypt_mailto_name'} . '" onclick="return decrypt_email(\'' . &ssms::crypt::xor::encrypt_hex( $form{'crypt_mailto_name'} . ' <' . $form{'crypt_mailto_mail'} . '>', $form{'crypt_key'} ) . '\')">' . $form{'crypt_mailto_name'} . '</a>';
	$form{'crypt_authinfo_output'} = crypt( $form{'crypt_authinfo_password'}, 'La' );
	&ssms::system::add_tag_handler( \&view_crypt );
	&ssms::system::load( 'admin/crypt.htm' );
}

sub view_crypt {
	$tag = shift if defined @_;
	if( $tag =~ /<INPUT_(CRYPT_\w+)(.*)>/ ) { &out( &input( lc( $1 ), $2, 'text' ) ); }
	elsif( $tag =~ /<AREA_(CRYPT_\w+)(.*)>/ ) { &out( &input( lc( $1 ), $2, 'area' ) ); }
	else { return 0; }
	return 1;
}

1;
