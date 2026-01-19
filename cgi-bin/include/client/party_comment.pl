package hbr1::party::comment;

die 'system down!';

require $main::path{'ssms'} . 'dbi/table.pl';
our @ISA = qw(ssms::dbi::table);
our $VERSION = '1.0.0';

sub new {
  my $proto = shift;
  my $class = ref( $proto ) || $proto;
  my $self  = {};
  bless( $self, $class );
  $self->table( 'party_comment' );
  $self->{'create_timestamp_data_type'} = 'timestamp';
  $self->{'edit_timestamp_data_type'} = 'timestamp';
  $self->{'submitter_data_type'} = 'tagout';
  $self->{'email_data_type'} = 'email';
  $self->{'info_data_type'} = 'tagout';
  return $self;
}

package main;

$party_comment = hbr1::party::comment->new();

if( $form{'part'} eq 'comment' ) {
  &init_party( $form{'party_id'} );
  &ssms::system::add_tag_handler( \&view_party, \$party );
  &ssms::system::add_tag_handler( \$party_comment );
  &ssms::system::add_loop( '<PARTY_COMMENTS>', \&load_party_comments, \&view_party_comment, \&init_party_comment );
  if( $form{'step'} == 1 ) {
    $party_comment->from_form();
    $error{'party_comment_submitter'} = 1 if length( $form{'party_comment_submitter'} ) < 3;
    $error{'party_comment_info'} = 1 if length( $form{'party_comment_info'} ) < 5;
    if( defined %error ) {
      $party_comment = hbr1::party::comment->new();
      &page_error( 'There are errors, please check the red marked fields' );
    }
    if( ! defined %error ) {
      $party_comment->set( 'party_id', $form{'party_id'} );
      $party_comment->set( 'create_timestamp', time );
      $party_comment->save();
      $party_comment = hbr1::party::comment->new();
      $party_comment->to_form();
      &page_message( 'Your comment has been added' );
    }
  }
  &ssms::system::load( 'client/party_comment.htm' );
}

sub load_party_comments {
  my $sql = 'SELECT id FROM party_comment WHERE party_id = ' . $form{'party_id'} . ' ORDER BY create_timestamp DESC;';
  return &ssms::dbi::query( $sql );
}

sub init_party_comment {
  my $id = shift;
  $party_comment->load( $id );
  ( $ftm, $fdt ) = &ssms::time::format_time( $party_comment->get( 'create_timestamp' ) );
  $party_comment->set( 'create_timestamp_time', $ftm, 'create_timestamp_date', $fdt );
}

sub view_party_comment {
  $tag = shift if defined @_;
  if( $tag =~ /<PARTY_COMMENT_(\w+)>/ ) { &out( $party_comment->get( lc( $1 ) ) ); }
  elsif( $tag =~ /<IF_PARTY_COMMENT_(\w+)>/ ) { &con( $party_comment->get( lc( $1 ) ) ); }
  else { return 0; }
  return 1;
}

1;
