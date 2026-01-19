if( $form{'action'} eq 'news' ) {
  if( $form{'part'} eq 'clients' ) {
    if( $form{'step'} == 1 ) { # remove clients
      @clients = &load_news_clients();
      $found = 0;
      foreach( @clients ) {
        if( $form{'news_client_'.$_} ) {
          # die 'found ' . $_;
          $sql = 'DELETE FROM newsletter_client WHERE id = "' . $_ . '";';
          &ssms::dbi::exec( $sql );
          $found ++;
        }
      }
      if( $found ) {
        &log_action( $found . ' user' . ( $found != 1 ? 's' : '' ) . ' has been removed from newsletter' );
        &page_message( $found . ' user' . ( $found != 1 ? 's' : '' ) . ' has been removed' );
      }
    }
    require 'include/admin/userman.pl';
    &ssms::system::add_tag_handler( \&display_client );
    &ssms::system::add_loop( '<NEWS_CLIENTS>', \&load_news_clients, \&display_news_client, \&init_news_client );
    &ssms::system::load( 'admin/newsletter_clients.htm' );
  }
  elsif( $form{'part'} eq 'messages' ) {
    &ssms::system::add_loop( '<NEWS_MESSAGES>', \&load_news_messages, \&display_news_message, \&init_news_message );
    &ssms::system::load( 'admin/newsletter_messages.htm' );
  }
  else {
    if( $form{'step'} == 1 ) { # send message
      $error{'newsletter_message_message'} = 1 if ! $form{'newsletter_message_message'};
      $error{'newsletter_message_subject'} = 1 if ! $form{'newsletter_message_subject'};
      if( defined %error ) {
        &page_error( 'There some errors! Please check the red marked fields.' );
      }
      else {
        $mail{'bcc'} = '';
        &ssms::dbi::query( 'SELECT email FROM newsletter_client WHERE status = "1";', 1 );
        foreach( ( &ssms::dbi::fetch() )[0] ) {
          $mail{'bcc'} .= $_ . ',';
        }
        $mail{'bcc'} = substr( $mail{'bcc'}, 0, length( $mail{'bcc'} ) - 1 );
        $now = time;
        $form{'newsletter_message_message'} =~ s/\n/<br>/g if $form{'newsletter_message_break'};
        $message = '<body bgcolor="#FFFFFF" text="#000000" link="#0000FF" alink="#FF0000" vlink="#0000FF">'
                 . '<font face="' . $form{'newsletter_message_font_face'}
                 . '" size="' . $form{'newsletter_message_font_size'} . '">'
                 . $form{'newsletter_message_message'} . '</font></body>';
        $sql = 'INSERT INTO newsletter_message (time,subject,message) VALUES('
             . '"' . $now . '","' . $form{'newsletter_message_subject'} . '","'
             . $message . '");';
        &ssms::dbi::exec( $sql );
        require $path{'ssms'} . 'mail.pl';
        &ssms::mail::send( '', $form{'newsletter_message_subject'}, $form{'newsletter_message_message'}, $lotto{'newsletter_mail'} );
        &page_message( 'News has been sent.' );
      }
    }
    else {
      $form{'newsletter_message_break'} = 1;
      $form{'newsletter_message_font_face'} = 'Verdana';
      $form{'newsletter_message_font_size'} = 2;
    }
    &ssms::system::add_tag_handler( \&display_news_message );
    &ssms::system::load( 'admin/newsletter.htm' );
  }
}

# news message

sub load_news_messages {
  my $argv = shift if @_;
  my $order = ( $form{'order'} ? ' ORDER BY ' . $form{'order'} . ' ' . $form{'ordir'} : '' );
  my $sql = 'SELECT id FROM newsletter_message' . $order;
  $form{'from'} = 0 if ! $form{'from'};
  $form{'count'} = 10 if ! $form{'count'};
  $form{'to'} = $form{'from'} + $form{'count'};
  my @tmp = &ssms::dbi::query( $sql );
  $form{'max'} = @tmp;
  return @tmp;
}

sub init_news_message {
  my $id = shift;
  &ssms::dbi::query( 'SELECT * FROM newsletter_message WHERE id = "' . $id . '";', 1 );
  %news_message = &ssms::dbi::fetch( 1 );
}

sub display_news_message {
  $tag = shift if @_;
  if( $tag =~ /<NEWS_MESSAGE_TIME>/ ) {
    ( $ftm, $fdt ) = &ssms::time::format_time( $news_message{'time'} );
    &out( $ftm );
  }
  elsif( $tag =~ /<NEWS_MESSAGE_DATE>/ ) {
    ( $ftm, $fdt ) = &ssms::time::format_time( $news_message{'time'} );
    &out( $fdt );
  }
  elsif( $tag =~ /<NEWS_MESSAGE_(.+)>/ ) { &out( $news_message{lc($1)} ); }
  elsif( $tag =~ /<INPUT_NEWS_MESSAGE_MESSAGE(.*)>/ ) {
    $xt = $1;
    $form{'news_message_message'} =~ s/<br>/\n/g if $form{'news_message_break'};
    &out( &input( 'news_message_message', $xt, 'area' ) );
  }
  elsif( $tag =~ /<INPUT_NEWS_MESSAGE_SUBJECT(.*)>/ ) { &out( &input( 'newsletter_message_subject', $1, 'text' ) ); }
  elsif( $tag =~ /<INPUT_NEWS_MESSAGE_BREAK(.*)>/ ) { &out( &input( 'newsletter_message_break', $1, 'check', '1' ) ); }
  elsif( $tag =~ /<INPUT_NEWS_MESSAGE_FONT_FACE(.*)>/ ) { &out( &input( 'newsletter_message_font_face', $1, 'select', "Verdana\xFFArial\xFFCourier New\xFFTimes New Roman" ) ); }
  elsif( $tag =~ /<INPUT_NEWS_MESSAGE_FONT(.*)>/ ) { &out( &input( 'newsletter_message_font_size', $1, 'select', "1\xFF2\xFF3\xFF4" ) ); }
  else { return 0; }
  return 1;
}

# news clients

sub load_news_clients {
  my $argv = shift if @_;
  my $order = ( $form{'order'} ? ' ORDER BY ' . $form{'order'} . ' ' . $form{'ordir'} : '' );
  my $sql = 'SELECT id FROM newsletter_client' . $order;
  $form{'from'} = 0 if ! $form{'from'};
  $form{'count'} = 10 if ! $form{'count'};
  $form{'to'} = $form{'from'} + $form{'count'};
  my @tmp = &ssms::dbi::query( $sql );
  $form{'max'} = @tmp;
  return @tmp;
}

sub init_news_client {
  my $id = shift;
  &ssms::dbi::query( 'SELECT * FROM newsletter_client WHERE id = "' . $id . '";', 1 );
  %news_client = &ssms::dbi::fetch( 1 );
  $client = ssms::client->new();
  $client->load( $news_client{'client'} );
}

sub display_news_client {
  $tag = shift if @_;
  if( $tag =~ /<NEWS_CLIENT_(.+)>/ ) { &out( $news_client{lc($1)} ); }
  elsif( $tag =~ /<IF_NEWS_CLIENT_(.+) == (.+)>/ ) { &add_con( $news_client{lc($1)} == $2 ); }
  elsif( $tag =~ /<IF_NEWS_CLIENT_(.+)>/ ) { &add_con( $news_client{lc($1)} ); }
  elsif( $tag =~ /<SELECT_NEWS_CLIENT(.*)>/ ) { &out( &input( 'newsletter_client_' . $news_client{'id'}, $1, 'check', '1' ) ) }
  else { return 0; }
  return 1;
}

1;
