package HBR1_EARTHCAM;

#$_ts = Time::HiRes::time();

use LWP::UserAgent;
use Fcntl qw(:flock);
use GD;

use PAB3;
use PAB3::CGI qw(:default);

&PAB3::CGI::setenv();
&PAB3::CGI::init();

#&PAB3::require( $ENV{'SCRIPT_PATH'} . 'include/config.pab.pl' );

$url = 'http://images.earthcam.com/ecnetwork/panasonicts_640.jpg';

$path = $ENV{'DOCUMENT_ROOT'} . '/webtv/ticker/images/earthcam.jpg';

goto read_local if $_GET{'local'};

@stat = stat $path;
goto read_local if time - $stat[9] < 5;

if( -e $path . '.lock' ) {
	@stat = stat $path . '.lock';
	goto read_local if time - $stat[9] < 10;
}

$ua = LWP::UserAgent->new();
$ua->agent( 'Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 5.1)' );
$ua->timeout( 10 );

open $fh, '>', $path . '.lock';
close $fh;
$response = $ua->get( $url );
unlink $path . '.lock';

if( $response->is_success ) {
	#$image = $response->content;
	$source = GD::Image->newFromJpegData( $response->content, 1 );
	$dest = GD::Image->new( 160, 120, 1 );
	$dest->copyResampled( $source, 0, 0, 0, 0, 160, 120, 640, 480 );
	$image = $dest->jpeg( 75 );
	open $fh, '>', $path;
	flock( $fh, LOCK_EX ) or goto output_image;
	binmode $fh;
	print $fh $image;
	flock( $fh, LOCK_UN );
}
else {
read_local:
	@stat = stat $path;
	open $fh, '<', $path;
	flock( $fh, LOCK_SH );
	binmode $fh;
	sysread $fh, $image, $stat[7];
	flock( $fh, LOCK_UN );
}

output_image:
&header( "Content-Type: image/jpeg" );
print $image;

close $fh;

#&print_r( \%ENV );

#$_te = sprintf( "%0.3f", Time::HiRes::time() - $_ts );
#print "<p>generated within $_te seconds<p>\n";

1;
