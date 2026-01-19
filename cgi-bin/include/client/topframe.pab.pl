use POSIX ();
use PAB::Constant qw(S_OK E_NORESULT);

my( $Now );

#$ENV{'TZ'} = 'CEST';
#&POSIX::tzset();

($std, $dst) = POSIX::tzname();
#warn $std . ' ' . $dst;

$Now = time;
$Dt = &unixAsDatetime( $Now );
#warn $Dt . ' ' . formatTime( "%c", $Now );

$hr = &PAB::DBI::query(
	"SELECT "
	. "CONVERT_TZ('$Dt','$std','Europe/Berlin') AS Berlin"
	. ", CONVERT_TZ('$Dt','$std','America/New_York') AS New_York"
	. ", CONVERT_TZ('$Dt','$std','Europe/Moscow') AS Moscow"
	. ", CONVERT_TZ('$Dt','$std','Asia/Tokyo') AS Tokyo"
	. ", CONVERT_TZ('$Dt','$std','UTC') AS UTC"
);
die $PAB::System::LastError if $hr != S_OK;
&PAB::DBI::fetchRowArray(
	$Dt_Berlin, $Dt_New_York, $Dt_Moscow, $Dt_Tokyo, $Dt_UTC
);

$UTC = &PAB::Format::datetimeAsUnix( $Dt_UTC );
$Berlin = &PAB::Format::datetimeAsUnix( $Dt_Berlin );
$TZO_Berlin = $Berlin - $UTC;
#$TZO_New_York = 3600 * 5;
#$New_York = $UTC + $TZO_New_York;
$New_York = &PAB::Format::datetimeAsUnix( $Dt_New_York );
$TZO_New_York = $New_York - $UTC;
#warn &formatTime( '%c', $New_York );
$Moscow = &PAB::Format::datetimeAsUnix( $Dt_Moscow );
$TZO_Moscow = $Moscow - $UTC;
$Tokyo = &PAB::Format::datetimeAsUnix( $Dt_Tokyo );
$TZO_Tokyo = $Tokyo - $UTC;

#warn join( ', ', ( $Dt_UTC, $Dt_Berlin, $Dt_New_York, $Dt_Moscow, $Dt_Tokyo ) );

$hr = &PAB::makeScriptAndRun(
	'client/topframe.htm',
	'client.topframe.pl'
);
if( $hr != S_OK ) {
	die $PAB::System::LastError;
}

1;
