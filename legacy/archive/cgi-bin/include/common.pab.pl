#use threads;
#use threads::shared;

#use vars qw(%GLOBAL::GBFILTER $FM_Gb_Filter $Gb_Filter $HBR1_INIT);

sub pageNavigation {
	my( $rec_start, $rec_page, $rec_count, $page_max ) = @_;
	$page_max ||= 5;
	$Start_Record = $rec_start;
	$End_Record = $rec_start + $rec_page - 1;
	$Record_Count = $rec_page;
	$Record_Max = $rec_count;
	$Current_Page = int( $rec_start / $rec_page ) + 1;
	$Last_Page = int( $rec_count / $rec_page );
	if( $Last_Page * $rec_page < $rec_count ) {
		$Last_Page ++;
	}
	$Start_Page = $Current_Page - $page_max;
	if( $Start_Page < 1 ) {
		$Start_Page = 1;
	}
	$End_Page = $Current_Page + $page_max + ($page_max - ($Current_Page - $Start_Page));
	if( $End_Page > $Last_Page ) {
		$Start_Page -= $End_Page - $Last_Page;
		if( $Start_Page < 1 ) {
			$Start_Page = 1;
		}
		$End_Page = $Last_Page;
	}
}

sub initSystem {
	my( $hr, $h, $i );
	return if $HBR1_INIT;
	$hr = &PAB::DBI::query(
		'SELECT * FROM '
			. &PAB::DBI::quoteId( 'guestbook_filter' )
	);
	return $hr if $hr != S_OK;
	&PAB::DBI::fetchTable( $h, $Gb_Filter );
	$FM_Gb_Filter = {};
	for $i( 0 .. scalar @$h ) {
		$FM_Gb_Filter->{ $h->[$i] } = $i;
	}
	%GLOBAL::GBFILTER = () unless defined %GLOBAL::GBFILTER;
#	share( %GLOBAL::GBFILTER );
	$HBR1_INIT = 1;
	#share( $FM_Gb_Filter );
	#share( $Gb_Filter );
	#share( $HBR1_INIT );
	return S_OK;
}

sub formatDuration {
	my( $sec ) = @_;
	my( $hour, $min );
	$hour = int( $sec / 3600 );
	$sec -= $hour * 3600;
	$min = int( $sec / 60 );
	$sec -= $min * 60;
	return sprintf( '%02i:%02i:%02i', $hour, $min, $sec );
}

1;
