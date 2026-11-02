%CHCTAB = ();
@RGBPOS = ( 0, 1, 2 );
$j = 0;
for $i( 0 .. 255 ) {
	$CHCTAB{chr($i)} = $RGBPOS[$j++];
	$j = 0 if $j > 2;
}

if( $Form{'Action'} eq 'submit' ) {
	if( $Form{'doSend'} && $Form{'Message'} ) {
		$Name = $Form{'Nickname'} || 'Nickname';
		$Color = &getColorByName( $Name );
		$Message = &PAB::Format::escape( $Form{'Message'} );
		$hr = &PAB::DBI::exec(
			'INSERT INTO `chat`' .
			' (`name`,`timestamp`,`remote_addr`,`color`,`content`)' .
			' VALUES(?,?,?,?,?)',
			undef,
			$Name, time, $ENV{'REMOTE_ADDR'}, $Color, $Message
		);
		if( $hr != S_OK ) {
			die $PAB::System::LastError;
		}
		&writeContent();
#		&writeUserlist();
		&PAB::CGI::setCookie(
			'Nickname', $Name,
			time + 86400 * 365,
			$Setup{'CookiePath'}, $Setup{'CookieDomain'}
		);
		$Cookie{'Nickname'} = $Name;
		$ReloadChat = 1;
	}
	$hr = &PAB::makeScriptAndRun(
		'client/chat_submit.htm',
		'client.chat.submit.pl'
	);
	if( $hr != S_OK ) {
		die $PAB::System::LastError;
	}
}
if( $Form{'Action'} eq 'listUsers' ) {
	$t = time - ( $Setup{'ChatOnlineTime'} || 600 );
	$hr = &PAB::DBI::query(
		'SELECT `name`, `timestamp`, `color`, `remote_addr`' .
		' FROM `chat`' .
		' WHERE `timestamp` > ?' .
#		' GROUP BY `name`' .
		' ORDER BY `timestamp` ASC',
		undef,
		$t
	);
	if( $hr != S_OK ) {
		die $PAB::DBI::LastError;
	}
	if( $PAB::DBI::RowsAffected ) {
		#&PAB::DBI::fetchAllArrayref( $Users );
		while( S_OK == &PAB::DBI::fetchRowArrayref( $row ) ) {
			if( ! $Users{ $row->[0] } ) {
				$Users{ $row->[0] } = $row;
			}
		}
		@Users = values %Users;
	}
	else {
		#$Users = [];
	}
	&PAB::registerLoop(
		'Users',
		\@Users, \$Record, undef,
		'Users', 'Record', undef
	);
	$hr = &PAB::makeScriptAndRun(
		'client/chat_userlist.htm',
		'client.chat.userlist.pl'
	);
	if( $hr != S_OK ) {
		die $PAB::System::LastError;
	}
}
unless( $Form{'Action'} ) {
	&writeContent();
#	die -e $Setup{'PathHtml'} . 'chat_content.html';
	open FILE, '< ' . $Setup{'PathHtml'} . 'chat_content.html';
	while( <FILE> ) {
		print $_;
	}
	close FILE;
}

1;

sub getRandomColor {
  my( $base, $rnd, $r, $g, $b );
  $base = int( shift );
  $rnd = 255 - $base;
  $r = unpack( "H*", chr( $base + rand( $rnd ) ) );
  $g = unpack( "H*", chr( $base + rand( $rnd ) ) );
  $b = unpack( "H*", chr( $base + rand( $rnd ) ) );
  return $r . $g . $b;
}

sub getColorByName2 {
	my $name = shift;
	my ( $index, $name_length, %special_char, @name, $red, $green, $blue );
	%special_char = (
		'A' => 1, 'E' => 1, 'I' => 1, 'O' => 1,
		'a' => 1, 'e' => 1, 'i' => 1, 'o' => 1
	);
	@name = split( //, $name );
	$name_length = scalar( @name );
	for( $index = 0; $index < $name_length; $index ++ ) {
		if( $special_char{ $name[ $index ] } ) {
			$red ++;
		}
		else {
			$blue ++;
		}
		$green += ord( $name[ $index ] );
	}
	$red = 150 + int( 105 / $name_length * $red );
	$green = 150 + int( 105 / ( $name_length * 255 ) * $green );
	$blue = 150 + int( 105 / $name_length * $blue );
	$red = unpack( "H*", chr( $red ) );
	$green = unpack( "H*", chr( $green ) );
	$blue = unpack( "H*", chr( $blue ) );
	return $red . $green . $blue;
}

sub getColorByName {
	my $name = shift;
	my( @name, $i, @rgb, $l );
	@name = split( //, $name );
	$l = scalar @name;
	@rgb = ( 0, 0, 0 );
	for $i( 0 .. $l - 1 ) {
		$rgb[$CHCTAB{$name[$i]}] ++;
	}
	return
		unpack( "H*", chr( int( $rgb[0] > 0 ? 150 + 100 / $l * $rgb[0] * 105 : 150 ) ) )
		. unpack( "H*", chr( int( $rgb[1] > 0 ? 150 + 100 / $l * $rgb[1] * 105 : 150 ) ) )
		. unpack( "H*", chr( int( $rgb[2] > 0 ? 150 + 100 / $l * $rgb[2] * 105 : 150 ) ) );
}

sub writeUserlist {
	$t = time - ( $Setup{'ChatOnlineTime'} || 600 );
	$hr = &PAB::DBI::query(
		'SELECT `name`, `timestamp`, `color`, `remote_addr`' .
		' FROM `chat`' .
		' WHERE `timestamp` > ?' .
#		' GROUP BY `remote_addr`' .
		' ORDER BY `id` DESC',
		undef,
		$t
	);
	if( $hr != S_OK ) {
		die $PAB::DBI::LastError;
	}
	if( $PAB::DBI::RowsAffected ) {
		&PAB::DBI::fetchAllArrayref( $Users );
		%Users = ();
		@Users = ();
		foreach( @{$Users} ) {
			if( ! $Users{ $_->[3] } ) {
				$Users{ $_->[3] } = 1;
				push @Users, $_;
			}
		}
	}
	&PAB::registerLoop(
		'Users',
		\@Users, \$Record, undef,
		'Users', 'Record', undef
	);
	$Output = '';
	&PAB::setOutput( \$Output );
	$hr = &PAB::makeScriptAndRun(
		'client/chat_userlist.htm',
		'client.chat.userlist.pl'
	);
	if( $hr != S_OK ) {
		die $PAB::System::LastError;
	}
	open FILE, '> ' . $Setup{'PathHtml'} . 'chat_userlist.html';
	print FILE $Output;
	close FILE;
	&PAB::restoreOutput();
}

sub writeContent {
	$hr = &PAB::DBI::query(
		'SELECT `name`, `timestamp`, `color`, `remote_addr`, `content`' .
		' FROM `chat`' .
		' ORDER BY `id` DESC' .
		' LIMIT ' . $Setup{'ChatShowEntries'},
	);
	if( $hr != S_OK ) {
		die $PAB::DBI::LastError;
	}
	if( $PAB::DBI::RowsAffected ) {
		&PAB::DBI::fetchAllArrayref( $Chat );
	}
	else {
		$Chat = [];
	}
	&PAB::registerLoop(
		'Chat',
		$Chat, \$Record, undef,
		'$Chat', 'Record', undef
	);
	open FILE, '> ' . $Setup{'PathHtml'} . 'chat_content.html';
	&PAB::setOutput( \*FILE );
	$hr = &PAB::makeScriptAndRun(
		'client/chat_content.htm',
		'client.chat.content.pl'
	);
	if( $hr != S_OK ) {
		&PAB::restoreOutput();
		close FILE;
		die $PAB::System::LastError;
	}
	&PAB::restoreOutput();
	close FILE;
}

__END__

