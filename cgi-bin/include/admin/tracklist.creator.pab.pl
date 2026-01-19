my( @tracklist, $mix_title, $mix_dj, $mix_date, $mix_cat_id, $mix_len, $hr );

use constant {
	HBR1_TL_ARTIST => 0,
	HBR1_TL_TITLE => 1,
	HBR1_TL_LABEL => 2,
	HBR1_TL_LABELURL => 3,
	HBR1_TL_ORDERURL => 4,
};

if( ! $HBR1_Mix_Cat_T ) {
	$hr = &PAB::DBI::query(
		'SELECT * FROM media_category WHERE flags = 1'
	);
	die $PAB::System::LastError if $hr != S_OK;
	$hr = &PAB::DBI::fetchTable( $fields, $HBR1_Mix_Cat_T );
	die $PAB::System::LastError if $hr != S_OK;
	$HBR1_Mix_Cat_FM = {};
	my $pos = 0;
	foreach( @$fields ) {
		$HBR1_Mix_Cat_FM->{ $_ } = $pos ++;
	}
}

if( $Form{'eval'} || $Form{'create'} ) {
	my( $data, $evalexp, %ple, $pli, @pl, $is_artist
		, $t_title, $t_artist, $t_label, $t_labelurl, $t_order, $t_orderurl );
	
	$evalexp = $Form{'evalexp'} ? $Form{'evalexp'} : '[title]\n';
	
	$mix_dj = $Form{'dj'} or do {
		&PAB::CGI::logError( "Please enter a <b>DJ</b> name" );
		$FormError{'dj'} = 1;
	};
	$mix_title = $Form{'title'} or do {
		&PAB::CGI::logError( "Please enter a mix <b>Title</b>" );
		$FormError{'title'} = 1;
	};
	$data = $Form{'playlist'} or do {
		&PAB::CGI::logError( "Your <b>Playlist</b> is empty" );
		$FormError{'playlist'} = 1;
	};
	
	$mix_len = $Form{'length'} or do {
		&PAB::CGI::logError( "Please enter the <b>Length</b> of the mix" );
		$FormError{'length'} = 1;
	};
	if( ! $FormError{'length'} ) {
		if( $mix_len =~ m!(\d+)\:(\d+)\:(\d+)! ) {
			$mix_len = $1 * 3600 + $2 * 60 + $3;
		}
		elsif( $mix_len =~ m!(\d+)\:(\d+)! ) {
			$mix_len = $1 * 60 + $2;
		}
		elsif( $mix_len =~ m!(\d+)! ) {
			$mix_len = $1;
		}
		else {
			&PAB::CGI::logError( "Invalid type mix <b>Length</b>" );
			$FormError{'length'} = 1;
		}
	}

	$mix_date = &PAB::CGI::fromForm( 'date',
		{
			'InputType' => 'date',
			'InputFormat' => '%d.%m.%Y',
			'ErrorFormat' => "The format of <b>Date</b> is invalid",
			'OutputType' => 'date',
		}
	);

	$mix_cat_id = $Form{'category'};
	
	if( $evalexp !~ m!\[title\]!i ) {
		&PAB::CGI::logError( "Could not find [title] in <b>Evalution</b>" );
		$FormError{'evalexp'} = 1;
	}
	#$evalexp =~ s!\\n$!!;
	#$evalexp =~ s!\n$!!;
	#$evalexp .= '\n';

	if( ! %FormError ) {
		
		$eval = '';
		$pli = 0;
		$evalexp = ' ' . $evalexp;
		while( $evalexp =~ s!([^\\])\[(.+?[^\\])\]!$1(.+?)! ) {
			#$ple{ lc( $1 ) } = $pli;
			$is_artist = 1 if lc( $2 ) eq 'artist';
			$eval .= ' $pl[$pli]->{\'' . lc( $2 ) . '\'} = $' . ( $pli + 1 ) . ';';
			$pli ++;
		}
		@pl = ();
		$pli = 0;
		$eval = 'while( $data =~ m!' . substr( $evalexp, 1 )
			. '!sg ) { $pl[$pli] = {}; ' . $eval . ' $pli ++; }';
		$data =~ s!\r!!go;
		if( $Form{'eval'} ) {
			&PAB::CGI::logMessage(
				"use evaluation routine:<br><code>${eval}</code><br><br>"
			);
		}
		eval( $eval );
		if( $@ ) {
			&PAB::CGI::logError( "Evaluation failed: $@" );
			$FormError{'evalexp'} = 1;
		}
		elsif( ! @pl ) {
			&PAB::CGI::logError( "No paylist entries found" );
			$FormError{'evalexp'} = 1;
			$FormError{'playlist'} = 1;
		}
	}
	if( ! %FormError ) {
		if( $Form{'eval'} ) {
			my $f_id = $HBR1_Mix_Cat_FM->{'id'};
			my $mc_name = '';
			foreach( @$HBR1_Mix_Cat_T ) {
				if( $_->[ $f_id ] == $mix_cat_id ) {
					$mc_name = $_->[ $HBR1_Mix_Cat_FM->{'name'} ];
					last;
				}
			}
			&PAB::CGI::logMessage(
				"dj=<b>$mix_dj</b>; mix=<b>$mix_title</b>; date=<b>$mix_date</b>"
				. "; len=<b>$mix_len</b>; category=<b>$mc_name</b>"
				. "<hr noshade size=1>"
			);
		}
		@tracklist = ();
		my $p = 1;
		foreach( @pl ) {
			$t_title = $_->{'title'};
			if( $is_artist ) {
				$t_artist = $_->{'artist'};
			}
			elsif( $t_title =~ s!(.+?) +- +(.+?)!$2! ) {
				$t_artist = $1;
			}
			else {
				$t_artist = '';
			}
			$t_label = $_->{'label'} or '';
			$t_labelurl = $_->{'alabel'} or '';
			$t_orderurl = $_->{'aorder'} or '';
			push @tracklist, [ $t_artist, $t_title, $t_label, $t_labelurl, $t_orderurl ];
			if( $Form{'eval'} ) {
				&PAB::CGI::logMessage(
					"$p\) artist=<b>$t_artist</b>; title=<b>$t_title</b>"
					. "; label=<b>$t_label</b>; alabel=<b>$t_labelurl</b>"
					. "; aorder=<b>$t_orderurl</b>"
				);
			}
			$p ++;
		}
	}
}

if( $Form{'create'} && ! %FormError ) {
	my( $track, $label_id, $mix_id, $track_pos, $track_title );
	my $test = 0;
	
	$sql = "INSERT INTO `mixes` "
		. "(`category`,`dj`,`name`,`timestamp`,`length`,`status`) "
		. "VALUES(?,?,?,?,?,?)";
	&PAB::CGI::logMessage( $sql );
	if( ! $test ) {
		$hr = &PAB::DBI::exec(
			$sql, undef,
			$mix_cat_id, $mix_dj, $mix_title,
			&datetimeAsUnix( $mix_date . ' 12:00:00' ),
			$mix_len, 1
		);
		die $PAB::System::LastError if $hr != S_OK;
		&PAB::DBI::lastInsertId( $mix_id );
		&PAB::CGI::logMessage( "using mix-id: $mix_id" );
	}
	$track_pos = 0;
	foreach $track( @tracklist ) {
		$track_pos ++;
		if( $track->[HBR1_TL_LABEL] ) {
			$hr = &PAB::DBI::query(
				'SELECT id FROM `labels` WHERE `name` LIKE ?',
				undef,
				$track->[HBR1_TL_LABEL]
			);
			die $PAB::System::LastError if $hr != S_OK;
			if( $PAB::DBI::RowsAffected ) {
				&PAB::DBI::fetchRowArray( $label_id );
			}
			else {
				$sql = "INSERT INTO `labels` "
					. "(`name`,`home`) "
					. "VALUES(?,?)";
				&PAB::CGI::logMessage( $sql );
				if( ! $test ) {
					$hr = &PAB::DBI::exec(
						$sql,
						undef,
						$track->[HBR1_TL_LABEL], $track->[HBR1_TL_LABELURL]
					);
					die $PAB::System::LastError if $hr != S_OK;
					&PAB::DBI::lastInsertId( $label_id );
				}
			}
			if( ! $test ) {
				&PAB::CGI::logMessage( "using label-id: $label_id" );
			}
		}
		else {
			$label_id = 0;
		}
		if( $track->[HBR1_TL_ARTIST] ) {
			$track_title = $track->[HBR1_TL_ARTIST] . ' - '
				. $track->[HBR1_TL_TITLE];
		}
		else {
			$track_title = $track->[HBR1_TL_TITLE];
		}
		$sql = "INSERT INTO `releases` "
			. "(`mix`,`name`,`label`,`url_order`,`pos`) "
			. "VALUES(?,?,?,?,?)";
		&PAB::CGI::logMessage( $sql );
		if( ! $test ) {
			$hr = &PAB::DBI::exec(
				$sql, undef,
				$mix_id, $track_title, $label_id,
				$track->[HBR1_TL_ORDERURL], $track_pos
			);
			die $PAB::System::LastError if $hr != S_OK;
		}
	}
	if( ! $test ) {
		&PAB::CGI::logMessage( "<b>tracklist has been created</b>" );
	}
}

$Form{'category'} ||= '';

&PAB::registerLoop(
	'MIXCATEGORY',
	$HBR1_Mix_Cat_T, \$Category, undef,
	'$HBR1_Mix_Cat_T', 'Category', undef
);
&PAB::addHashMap( 'MIXCATEGORY', 'Category', $HBR1_Mix_Cat_FM );

$hr = &PAB::makeScriptAndRun(
	'admin/tracklist.creator.htm',
	'admin.tracklist.creator.pl',
);
die $PAB::System::LastError if $hr != S_OK;

1;
