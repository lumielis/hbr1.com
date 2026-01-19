#!/opt/perl/bin/perl

#use CGI qw/:standard/;
use Image::Magick;

require 'include/settings.inc';
require $path{'ssms'} . 'system.pl';

#&ssms::system::init( 1 );
print "Content-Type: text/plain \n\n";

$url{'photo_source'} = 'http://www.hbr1.com/images/gallery';
$url{'photo_target'} = $url{'www'} . '/gp';
$path{'original_target'} = $path{'www'} . '/gp';
$path{'photo_target'} = $path{'www'} . '/gp/pv';
$path{'tumb_target'} = $path{'www'} . '/gp/tn';
$form{'file'} = 'test.jpg' unless $form{'file'};

if( $form{'action'} eq 'remove' ) {
  # security bug!!!
  unlink $path{'original_target'} . '/' . $form{'file'};
  unlink $path{'photo_target'} . '/' . $form{'file'};
  unlink $path{'tumb_target'} . '/' . $form{'file'};
}
else {
  if( ! $form{'file'} ) {
    print '500 no source defined', "\n";
    exit;
  }
  $fext = $form{'file'};
  $fext =~ s/(.+)\.(\w+?)/$2/;
  $file = $1 . '.jpg';
  $fext = lc( $fext );
  if( $fext ne 'jpg' && $fext ne 'jpeg' ) {
    print '500 filetype not suported', "\n";
    exit;
  }
  $fext = '.jpg';
  $url = $url{'photo_source'} . '/' . $form{'file'};
  $content = LWP::Simple::get( $url );
  if( $content eq '' ) {
    print '500 source cannot be read', "\n";
    exit;
  }
  open FILE, '> ' . $path{'original_target'} . '/' . $file;
  binmode FILE;
  print FILE $content;
  close FILE;
  $image = Image::Magick->new;
  $x = $image->Read( $path{'original_target'} . '/' . $file );
  warn $x if $x;
  $x = $image->Resize( width => '110', height => '82' );
  warn $x if $x;
  #$image = &zoom_image( $image, 110, 82, '#000000' );
  $x = $image->Write( $path{'tumb_target'} . '/' . $file );
  warn $x if $x;
  $image = Image::Magick->new;
  $x = $image->Read( $path{'original_target'} . '/' . $file );
  warn $x if $x;
  $x = $image->Resize( width => '400', height => '300' );
  warn $x if $x;
  $x = $image->Write( $path{'photo_target'} . '/' . $file );
  warn $x if $x;
  unlink $path{'original_target'} . '/' . $file;
}

print '200 ok', "\n";

sub zoom_image {
  my( $image_source, $width, $height, $color_background ) = @_;
  my( $image_target, $source_width, $source_height );
  $image_target = Image::Magick->new( size => $width . 'x' . $height, pointsize => 24 );
  $image_target->Read( $color_background );
  ( $source_width, $source_height ) = $image_source->get( 'width', 'height' );
  print $source_width, " ", $source_height, "\n";
  if( $source_width > $source_height ) {
    $source_height = int( $height / $width * $source_width );
    $image_source->Resize( width => $width, height => $source_height );
  }
  else {
    $source_width = int( $source_width / $source_height * $height );
    $image_source->Sample( width => $source_width, height => $height );
  }
  ( $source_width, $source_height ) = $image_source->get( 'width', 'height' );
  print $source_width, " ", $source_height, "\n";
  #$image_target->Composite( image => $image_source, x => int( ( $width - $source_width ) / 2 ), y => int( ( $height - $source_height ) / 2 ) );
  $image_target->Composite( image => $image_source, gravity => 'center', x => 0, y => 0 );
  $image_target->Set( compression => 'JPEG', quality => 75 );
  return $image_target;
}