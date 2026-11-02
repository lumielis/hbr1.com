#!perl

use PAB3 qw(:const);

&PAB3::require( $ENV{'SCRIPT_PATH'} . 'include/config.pab.pl' );
&PAB3::require_and_run( $ENV{'SCRIPT_PATH'} . 'include/global.pab.pl' );

*P = \%_REQUEST;
$GLOBAL::DEBUG = $P{'debug'};

if( $P{'show'} eq 'pic' ) {
	$pic = $DB->selectrow_hashref(
		'SELECT * FROM GAP_GalleryPicture'
		. ' INNER JOIN GAL_Gallery ON GAP_GAL_Index = GAL_Index'
		. ' WHERE GAP_Index = \'' . $P{'picid'} . '\''
	);
	goto gal_show_index if ! $pic;
	$pic_prev = $DB->selectrow_hashref(
		'SELECT GAP_Index FROM GAP_GalleryPicture'
		. ' WHERE GAP_GAL_Index = \'' . $pic->{'GAL_Index'} . '\''
			. ' AND GAP_Index < \'' . $P{'picid'} . '\''
		. ' ORDER BY GAP_Index DESC'
		. ' LIMIT 1'
	);
	$pic_next = $DB->selectrow_hashref(
		'SELECT GAP_Index FROM GAP_GalleryPicture'
		. ' WHERE GAP_GAL_Index = \'' . $pic->{'GAL_Index'} . '\''
			. ' AND GAP_Index > \'' . $P{'picid'} . '\''
		. ' ORDER BY GAP_Index'
		. ' LIMIT 1'
	);
	$PAB->make_script_and_run( 'client/gallery.picture.htm' )
		or die $PAB->error();
}
elsif( $P{'show'} eq 'gal' ) {
	$gal = $DB->selectrow_hashref(
		'SELECT *'
		. ' FROM GAL_Gallery WHERE GAL_Index = \'' . $P{'galid'} . '\''
	);
	goto gal_show_index if ! $gal;
	$P{'s_offset'} ||= 0;
	$P{'s_limit'} = 6;
	$r_pic = $DB->query(
		'SELECT SQL_CALC_FOUND_ROWS * FROM GAP_GalleryPicture'
		. ' WHERE GAP_GAL_Index = \'' . $P{'galid'} . '\''
		. ' ORDER BY GAP_Index'
		. ' LIMIT ' . ( $P{'s_limit'} < 0 ? '18446744073709551615' : $P{'s_limit'} )
			. ' OFFSET ' . $P{'s_offset'}
	);
	( $rowmax ) = $DB->selectrow_array( 'SELECT FOUND_ROWS()' );
	$PAB->register_loop( 'PICTURES',
		'fetchrow_hashref', PAB_FUNC, '$row', PAB_SCALAR, '$DB', '$r_pic'
	);
	$PAB->make_script_and_run( 'client/gallery.main.htm' )
		or die $PAB->error();
}
elsif( $P{'show'} eq 'cat' ) {
	$cat = $DB->selectrow_hashref(
		'SELECT MCG_Index, MCG_Name'
		. ' FROM MCG_MediaCategory WHERE MCG_Index = \'' . $P{'catid'} . '\''
	);
	goto gal_show_index if ! $cat;
	$P{'s_offset'} ||= 0;
	$P{'s_limit'} = 6;
	$r_gal = $DB->query(
		'SELECT SQL_CALC_FOUND_ROWS * FROM GAL_Gallery'
		. ' WHERE GAL_Status = \'1\' AND GAL_MCG_Index = \'' . $P{'catid'} . '\''
		. ' ORDER BY GAL_TimeModify DESC'
		. ' LIMIT ' . ( $P{'s_limit'} < 0 ? '18446744073709551615' : $P{'s_limit'} )
			. ' OFFSET ' . $P{'s_offset'}
	);
	( $rowmax ) = $DB->selectrow_array( 'SELECT FOUND_ROWS()' );
	$PAB->register_loop( 'GALLERIES',
		'fetch_gallery', PAB_FUNC, '$row', PAB_SCALAR
	);
	$PAB->make_script_and_run( 'client/gallery.list.htm' )
		or die $PAB->error();
}
if( ! $P{'show'} ) {
gal_show_index:
	$r_cat = $DB->query(
		'SELECT MCG_Index, MCG_Name FROM MCG_MediaCategory'
		. ' WHERE MCG_Status = 1 AND MCG_Flags = 2 ORDER BY MCG_Name'
	);
	$PAB->register_loop( 'CATEGORIES',
		'fetch_category', PAB_FUNC, '$row', PAB_SCALAR
	);
	$PAB->make_script_and_run( 'client/gallery.entrance.htm' )
		or die $PAB->error();
}

$GLOBAL::DEBUG = 0;
$Logger->info( 'session finished' );

1;

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
