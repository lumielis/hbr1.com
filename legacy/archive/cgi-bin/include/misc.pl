sub save_email {
  my( $table, $id, $category, $name, $email ) = @_;
  my( $tmp, $flags, @db, @row, $lm, $f_ );
  $tmp = $table;
  $tmp = $path{'data'} . $ENV{'SL'} . $table if ! -e $tmp;
  return 0 if ! -e $tmp;
  $lm = lc( $email );
  $f_ = 0;
  @db = &ssms::table::read( $tmp );
  foreach( @db ) {
    @row = split( /\|/, $_ );
    if( lc( $row[3] ) eq $lm ) {
      $row[0] = $id if $id;
      $row[1] = $category;
      $row[2] = $name if $name;
      $row[3] = $email;
      $_ = join( "|", @row );
      $f_ = 1;
      last;
    }
  }
  return &ssms::table::write( $tmp, split( /\|/, join( "|", @db ) ) ) if $f_;
  $id = time if ! $id;
  $flags = 1;
  @db = ( $id, $category, $name, $email, $flags );
  return &ssms::table::append( $tmp, @db );
}

1;
