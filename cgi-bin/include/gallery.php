<?

if( $P['show'] == 'pic' ) {
	$picid = (int) $P['picid'];
	$pic = $Db->select_row(
		"SELECT * FROM GAP_GalleryPicture
		JOIN GAL_Gallery ON GAP_GAL_Index = GAL_Index
		WHERE GAP_Index = '{$picid}'"
	);
	if( $pic ) {
		$pic_prev = $Db->select_row(
			"SELECT GAP_Index FROM GAP_GalleryPicture
			WHERE GAP_GAL_Index = '{$pic['GAL_Index']}' AND GAP_Index < '{$picid}'
			ORDER BY GAP_Index DESC
			LIMIT 1"
		);
		$pic_next = $Db->select_row(
			"SELECT GAP_Index FROM GAP_GalleryPicture
			WHERE GAP_GAL_Index = '{$pic['GAL_Index']}' AND GAP_Index > '{$picid}'
			ORDER BY GAP_Index
			LIMIT 1"
		);
		require '../template/gallery.picture.phtml';
	}
	else {
		unset( $P['show'] );
	}
}
elseif( $P['show'] == 'gal' ) {
	$galid = (int) $P['galid'];
	$gal = $Db->select_row( "SELECT * FROM GAL_Gallery WHERE GAL_Index = '{$galid}'" );
	if( $gal ) {
		if( !$P['s_offset'] )
			$P['s_offset'] = 0;
		if( !$P['s_limit'] )
			$P['s_limit'] = 6;
		$r_pic = $Db->query(
			"SELECT SQL_CALC_FOUND_ROWS * FROM GAP_GalleryPicture
			WHERE GAP_GAL_Index = '{$galid}'
			ORDER BY GAP_Index
			LIMIT " . ($P['s_limit'] < 0 ? '18446744073709551615' : $P['s_limit']) . " OFFSET {$P['s_offset']}"
		);
		list( $rowmax ) = $Db->select_row( 'SELECT FOUND_ROWS()' );
		require '../template/gallery.main.phtml';
	}
	else {
		unset( $P['show'] );
	}
}
elseif( $P['show'] == 'cat' ) {
	$catid = (int) $P['catid'];
	$cat = $Db->select_row(
		"SELECT MCG_Index, MCG_Name FROM MCG_MediaCategory WHERE MCG_Index = '{$catid}'"
	);
	if( $cat ) {
		if( !$P['s_offset'] )
			$P['s_offset'] = 0;
		if( !$P['s_limit'] )
			$P['s_limit'] = 6;
		$r_gal = $Db->query(
			"SELECT SQL_CALC_FOUND_ROWS * FROM GAL_Gallery
			WHERE GAL_Status = '1' AND GAL_MCG_Index = '{$catid}'
			ORDER BY GAL_TimeModify DESC
			LIMIT " . ($P['s_limit'] < 0 ? '18446744073709551615' : $P['s_limit']) . " OFFSET {$P['s_offset']}"
		);
		list( $rowmax ) = $Db->select_row( 'SELECT FOUND_ROWS()' );
		require '../template/gallery.list.phtml';
	}
	else {
		unset( $P['show'] );
	}
}

if( !$P['show'] ) {
	$r_cat = $Db->query(
		"SELECT MCG_Index, MCG_Name FROM MCG_MediaCategory
		WHERE MCG_Status = 1 AND MCG_Flags = 2 ORDER BY MCG_Name"
	);
	require '../template/gallery.entrance.phtml';
}

// functions...

function get_random_cat_pic( $cat )
{
	global $Db;
	return $Db->select_row(
		"SELECT GAP_File, GAP_Copyright, GAP_Title
		FROM GAP_GalleryPicture
		JOIN GAL_Gallery ON GAP_GAL_Index = GAL_Index AND GAL_MCG_Index = '{$cat['MCG_Index']}'
		ORDER BY RAND() LIMIT 1"
	);
}

function get_random_gal_pic( $gal )
{
	global $Db;
	return $Db->select_row(
		"SELECT GAP_File, GAP_Copyright, GAP_Title
		FROM GAP_GalleryPicture WHERE GAP_GAL_Index = '{$gal['GAL_Index']}'
		ORDER BY RAND() LIMIT 1"
	);
}

function bbc_to_html( $input )
{
	if( !$input )
		return "";
	$input = preg_replace( '!\[a\=(.*?)\](.*?)\[/a\]!', '<a href\=\"$1\" target\=\"_blank\">$2</a>', $input );
	$input = preg_replace( '!\[a\](.*?)\[/a\]!', '<a href\=\"$1\" target\=\"_blank\">$1</a>', $input );
	$input = preg_replace( '!\[b\](.*?)\[/b\]!', '<b>$1</b>', $input );
	$input = preg_replace( '!\[i\](.*?)\[/i\]!', '<i>$1</i>', $input );
	$input = preg_replace( '!\[u\](.*?)\[/u\]!', '<u>$1</u>', $input );
	return $input;
}

/*
sub fetch_gallery {
	my $row = $DB->fetchrow_hashref( $r_gal ) or return undef;
	$pic = $DB->selectrow_hashref(
		'SELECT GAP_File, GAP_Copyright, GAP_Title'
		. ' FROM GAP_GalleryPicture WHERE GAP_GAL_Index = \'' . $row->{'GAL_Index'} . '\''
		. ' ORDER BY RAND() LIMIT 1'
	);
	return $row;
}

sub fetch_category {
	my $row = $DB->fetchrow_hashref( $r_cat ) or return undef;
	$pic = $DB->selectrow_hashref(
		'SELECT GAP_File, GAP_Copyright, GAP_Title'
		. ' FROM GAP_GalleryPicture'
		. ' INNER JOIN GAL_Gallery ON GAP_GAL_Index = GAL_Index AND GAL_MCG_Index = \'' . $row->{'MCG_Index'} . '\''
		. ' ORDER BY RAND() LIMIT 1'
	);
	return $row;
}

sub bbc_to_html {
	my $input = $_[0] or return '';
	$input =~ s!\[a\=(.*?)\](.*?)\[/a\]!<a href\=\"$1\" target\=\"_blank\">$2</a>!g;
	$input =~ s!\[a\](.*?)\[/a\]!<a href\=\"$1\" target\=\"_blank\">$1</a>!g;
	$input =~ s!\[b\](.*?)\[/b\]!<b>$1</b>!g;
	$input =~ s!\[i\](.*?)\[/i\]!<i>$1</i>!g;
	$input =~ s!\[u\](.*?)\[/u\]!<u>$1</u>!g;
	return $input;
}

sub cut_string {
	my( $str, $cut, $add ) = @_;
	$cut or return $str;
	my $len = length( $str );
	if( $len > $cut ) {
		my $spa = 4;
		for( my $i = $cut - 1; $i >= $spa; $i -- ) {
			if( substr( $str, $i, 1 ) eq ' ' ) {
				$cut = $i;
				last;
			}
		}
		return substr( $str, 0, $cut ) . $add;
	}
	return $str;
}
*/
