package ssms::client;

require $main::path{'ssms'} . 'dbi/table.pl';
use vars qw( $VERSION @ISA );
@ISA = qw( ssms::dbi::table );
$VERSION = '1.2';

sub new {
  my $proto = shift;
  my $class = ref( $proto ) || $proto;
  my $self  = {};
  bless( $self, $class );
  $self->table( 'client_person' );
  $self->{'info_data_type'} = 'area';
  $self->{'account'} = ssms::client::account->new( $self );
  return $self;
}

sub DESTROY {
}

sub kill() {
  my $self = shift;
  my $sql;
  $sql = 'DELETE FROM client_person WHERE id = "' . $self->{id} . '";';
  &ssms::dbi::exec( $sql );
  $sql = 'DELETE FROM client_account WHERE client = "' . $self->{id} . '";';
  &ssms::dbi::exec( $sql );
}

sub account {
  my $self = shift;
  return $self->{account};
}

package ssms::client::account;

sub new {
  my $proto = shift;
  my $class = ref( $proto ) || $proto;
  my $self  = {};
  $self{person} = shift;
  bless( $self, $class );
  return $self;
}

sub DESTROY {
}

sub load {
  my $self = shift;
  my $from = shift;
  my $to = shift;
  my $order = shift;
  my $ordir = shift;
  $order = ' ORDER BY ' . $order . ' ' . $ordir if $order;
  $from = ' AND time BETWEEN "' . $from . '" AND "' . $to . '"' if $form{'from'} && $form{'to'};
  $sql = 'SELECT * FROM client_account WHERE client = "' . $self{person}->get( 'id' ) . '"' . $from . $order . ';';
  &ssms::dbi::query( $sql, 1 );
  $self->{count} = 0;
  while( 1 ) {
    my %commission = &ssms::dbi::fetch( 1 );
    last if ! %commission;
    foreach $key( keys %commission ) {
      if( ! $self->{$key} ) {
        @{$self->{$key}} = ( $commission{$key} );
      }
      else {
        push @{$self->{$key}}, $commission{$key};
      }
    }
    $self->{count} ++;
  }
}

sub get {
  my $self = shift;
  my $key = shift;
  my $index = shift;
  if( defined $index ) {
    return ${$self->{$key}}[$index];
  }
  return $self->{$key};
}

sub set {
  my $self = shift;
  my $key = shift;
  my $val = shift;
  my $index = shift;
  ${$self->{$key}}[$index] = $val;
}

sub commission {
  my $self = shift;
  return if ! $self{person}->get( 'id' );
  my( $amount, $info, $sender, $time ) = @_;
  $info =~ s/\"/\\\"/g;
  $sender =~ s/\"/\\\"/g;
  $time = time if ! $time;
  my $charge = $self{person}->get( 'charge' );
  $charge = 0 if ! $charge;
  $charge += $amount;
  my $sql = 'INSERT INTO client_account (client,time,amount,charge,sender,info) VALUES("' . $self{person}->get( 'id' ) . '","' . $time . '","' . $amount . '","' . $charge . '","' . $sender . '","' . $info . '" );';
  &ssms::dbi::exec( $sql );
  $self{person}->set( 'charge', $charge );
  $self{person}->save();
}

sub correction {
  my $self = shift;
  return if ! $self{person}->get( 'id' );
  my( $id, $amount, $info, $sender, $time ) = @_;
  my( $sql, %row, $diff, $old_charge, $charge, @ids, $i );
  $sql = 'SELECT id FROM client_account WHERE id >= "' . $id . '";';
  @ids = &ssms::dbi::query( $sql );
  &ssms::dbi::query( 'SELECT * FROM client_account WHERE id = "' . $ids[0] . '";', 1 );
  %row = &ssms::dbi::fetch( 1 );
  return if ! $row{'id'};
  $diff = $amount - $row{'amount'};
  $row{'info'} = $info if $info;
  $row{'sender'} = $sender if $sender;
  $row{'time'} = $time if $time;
  $charge = $row{'charge'} - $row{'amount'};
  $row{'amount'} += $diff;
  $sql = 'UPDATE client_account SET amount = "' . $row{'amount'}
       . '", info = "' . $row{'info'} . '", sender = "' . $row{'sender'}
       . '", time = "' . $row{'time'} . '" WHERE id = "' . $id . '";';
  &ssms::dbi::exec( $sql );
  return if ! $diff;
  $i = 0;
  do {
    $amount = ( &ssms::dbi::query( 'SELECT amount FROM client_account WHERE id = "' . $ids[$i] . '";' ) )[0];
    $charge += $amount;
    $sql = 'UPDATE client_account SET charge = "' . $charge . '" WHERE id = "' . $ids[$i] . '";';
    &ssms::dbi::exec( $sql );
  }
  while( $ids[$i++] );
  $self{person}->set( 'charge', $charge );
  $self{person}->save();
}

1;

__END__

