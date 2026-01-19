use POSIX ();
use PAB::Constant qw(S_OK E_NORESULT);

$PathTzData = $Setup{'PathBase'} . 'tz_data/';
%TzMonthByName = (
	'Jan' => 1, 'Feb' => 2,  'Mar' => 3,  'Apr' => 4,
	'May' => 5, 'Jun' => 6,  'Jul' => 7,  'Aug' => 8,
	'Sep' => 9, 'Oct' => 10, 'Nov' => 11, 'Dec' => 12
);

$DBG = $Form{'DEBUG'};

$t = time;
&getTimezoneOffset( 'Europe/Berlin', $t, $TZO_Berlin, $TZN_Berlin );
&getTimezoneOffset( 'America/New_York', $t, $TZO_NewYork, $TZN_NewYork );
&getTimezoneOffset( 'Australia/Sydney', $t, $TZO_Tokyo, $TZN_Tokyo );
&getTimezoneOffset( 'Asia/Jerusalem', $t, $TZO_Melbourne, $TZN_Melbourne );


#&getTimezoneOffset( 'America/Phoenix', $t, $tzo, $tzn );
#die $tzn . ': ' . $tzo if $DBG;

$hr = &PAB::makeScriptAndRun(
	'client/topframe.htm',
	'client.topframe.pl'
);
if( $hr != S_OK ) {
	die $PAB::System::LastError;
}

1;

sub getTimezoneOffset {
	my $zone = shift;
	my $time = shift || time;
	my( $hr, $country, $capital, $continent, %tzd, @t );
	if( $zone =~ m!^(\w+)/(\w+)$! ) {
		$continent = $1;
		$capitel = $2;
	}
	#($sec,$min,$hour,$mday,$mon,$year,$wday,$yday)
	@t = gmtime( $time );
	die 'ss';
	$hr = &getTimezone( \%tzd, \@t, $zone );
	$_[0] = $tzd{'offset'};
	$_[1] = $tzd{'name'};
	return S_OK;
}

sub getTimezone {
	my( $r_tzd, $r_time, $zone ) = @_;
	my(
		@data, $readz, $mz1, $mzl1, $mz2, $mzl2, %ruledata, @tz_data,
		$file, $fileindex, @rules, $rr
	);
	$hr = &PAB::DBI::query( 'SELECT * FROM tz_zones WHERE `name` = \'' . $zone . '\'' );
	if( $hr != S_OK ) {
		die $PAB::DBI::LastError;
	}
	while( S_OK == &PAB::DBI::fetchRowArrayref( $zone ) ) {
		die $zone;
	}
	do {
		@rules = ();
#		&PAB::CGI::printVar( $file );
		open FILE, $file;
		@data = <FILE>;
		close FILE;
		foreach( @data ) {
			if( substr( $_, 0, 1 ) eq "\n" || substr( $_, 0, 1 ) eq "#" ) {
				next;
			}
			if( $readz ) {
				if( m!\s\s\s! ) {
					push @rules, $_;
				}
				else {
					last;
				}
			}
			if(
				substr( $_, 0, $mzl1 ) eq $mz1 ||
				substr( $_, 0, $mzl2 ) eq $mz2
			) {
				if(
#m!^Zone\s$continent/$capitel\s([-]*)(\d+)\:(\d+)\:(\d+)\s\-\s(\w+)\s(.+)\n$!
m!^Zone\s+$continent/$capitel\s+([-]*)(\d*)\:*(\d*)\:*(\d+)\s+\-*\s*(\S+)\s+(.+)\n$!
				) {
					$readz = 1;
				}
			}
		}
		foreach( reverse @rules ) {
			# GMTOFF	RULES	FORMAT	[UNTIL]
			if(
#m!^\s+([-]*)(\d*)\:*(\d*)\:*(\d+)\s+(\S+)\s+(\S+)\s*(\S*)!
m!^\s+([-]*)(\d*)\:*(\d*)\:*(\d+)\s+(\S+)\s+(\S+)\s*([^#]*)!
#m!\s\s\s([-]*)(\d+)\:(\d+)\s(\S+)\s(\S+)\s*(.*)$!
			) {
				if( $4 ne '-' ) {
					if( ! $6 || ! &compareDate( $r_time, $6 ) ) {
						$r_tzd->{'offset'} =
							$1 ? - $2 * 60 + $3 : $2 * 60 + $3;
						$r_tzd->{'name'} = sprintf( $5 );
						%ruledata = (
							't_fym' => 0,
							't_tym' => 0xffffffff,
							'format' => $5,
							'offset' => $r_tzd->{'offset'},
						);
						&PAB::CGI::printVar( 'apply rules ' . $4 ) if $DBG;
						&getRules(
							$r_tzd, $r_time, \%ruledata, $continent, $4
						);
					}
				}
				else {
					if( ! $6 || ! &compareDate( $r_time, $6 ) ) {
						$r_tzd->{'offset'} =
							$1 ? - $2 * 60 + $3 : $2 * 60 + $3;
						$r_tzd->{'name'} = sprintf( $5 );
						%ruledata = (
							't_fym' => 0,
							't_tym' => 0xffffffff,
							'format' => $5,
							'offset' => $r_tzd->{'offset'},
						);
						&PAB::CGI::printVar(
							'use fixed timezone ' . $5
						) if $DBG;
					}
				}
				$readz ++;
			}
			else {
				return S_OK;
			}
		}
		if( $readz ) {
			return S_OK;
		}
		if( $fileindex < scalar @tz_data ) {
			$file = $PathTzData . $tz_data[$fileindex];
		}
		$fileindex ++;
	} while( $fileindex <= scalar @tz_data );
}

sub getRules {
	my( $r_tzd, $r_time, $r_ruledata, $continent, $rule ) = @_;
	my( @data, $mr, $mrl );
	$mr = "Rule\t" . $rule;
	$mrl = length( $mr );
	open FILE, $PathTzData . lc( $continent );
	@data = reverse <FILE>;
	close FILE;
	foreach( @data ) {
		if( substr( $_, 0, 1 ) eq "\n" || substr( $_, 0, 1 ) eq "#" ) {
			next;
		}
		if( substr( $_, 0, $mrl ) eq $mr ) {
			if(
#m!^Rule\t($rule)\t(.+?)\t(.+?)\t(.+?)\t(.+?)\t(.+?)\t(.+?)\t(.+?)\t(.+)$!
m!^Rule\s+($rule)\s+(\S+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(\S+)!
			) {
				&applyRule(
					$r_tzd, $r_time, $gmt_off, $r_ruledata,
					$1, $2, $3, $4, $5, $6, $7, $8, $9
				);
			}
		}
	}
}

sub applyRule {
	my(
		$r_tzd, $r_time, $gmt_off, $r_ruledata, $name, $from, $to, $type,
		$in, $on, $at, $save, $letter
	) = @_;
	my( $year, $month, $t_ym );
	$year = 1900 + $$r_time[5];
	$month = $$r_time[4] + 1;
	$t_ym = $year . $month;
	if( $from <= $year &&
		(
			( $to =~ /^\d+$/ && $to >= $year ) ||
			( $to eq 'only' && $from == $year ) ||
			( $to eq 'max' )
		)
	)
	{
		if(
			$r_ruledata->{'t_fym'} <= $t_ym &&
			$r_ruledata->{'t_tym'} >= $t_ym
		) {
# 			&PAB::CGI::printText(
#				$from . ' ' . $to . ' ' . $in . ' ' . $TzMonthByName{$in} .
#				' ' . $on . ' ' . $at . ' ' . $save
#			);
			$r_ruledata->{'in'} = $in;
			$r_ruledata->{'from'} = $from;
			$r_ruledata->{'to'} =
				$to eq 'max' ? $year : $to eq 'only' ? $from : $to;
			if( $TzMonthByName{$in} > $month ) {
				$r_ruledata->{'t_fym'} = ( $year - 1 ) . $TzMonthByName{$in};
				$r_ruledata->{'t_tym'} = $year . $TzMonthByName{$in};
			}
			else {
				$r_ruledata->{'t_fym'} = $year . $TzMonthByName{$in};
				$r_ruledata->{'t_tym'} = ( $year + 1 ) . $TzMonthByName{$in};
			}
			if( $save =~ m!(\d+)\:*(\d*)! ) {
				$r_tzd->{'offset'} =
					$r_ruledata->{'offset'} + ( $1 * 60 + $2 );
				$r_tzd->{'name'} = sprintf(
					$r_ruledata->{'format'},
					$letter eq '-' ? '' : $letter
				);
			}
		}
	}
}

sub compareDate {
	my( $r_time, $date_str ) = @_;
	my( $td );
	if( $date_str =~ m!^(\d+)\s*(\w*)\s*(\d*)! ) {
		if( $1 < ( $r_time->[5] + 1900 ) ) {
			return -1;
		}
		if( $1 > ( $r_time->[5] + 1900 ) ) {
			return 1;
		}
		if( ! $2 ) {
			return 0;
		}
		if( $TzMonthByName{$2} < $r_time->[4] + 1 ) {
			return -1;
		}
		if( $TzMonthByName{$2} > $r_time->[4] + 1 ) {
			return -1;
		}
		if( ! $3 ) {
			return 0;
		}
		if( $3 < $r_time->[3] ) {
			return -1;
		}
		if( $3 > $r_time->[3] ) {
			return 1;
		}
		return 0;
	}
}
