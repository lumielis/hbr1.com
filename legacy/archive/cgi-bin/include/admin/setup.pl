# require 'include/client.pl';

if( $form{'action'} eq 'setup' ) {
  if( $form{'part'} eq 'remove' ) {
    if( $form{'table'} ) {
      &ssms::dbi::exec( 'DROP TABLE IF EXISTS ' . $form{'table'} . ';' );
    }
    else {
      &removeTables();
    }
  }
  &createTables();

  $sql = 'INSERT INTO client_category (name,fixed,status) VALUES("Worker","1","1");';
  &ssms::dbi::exec( $sql );
  $sql = 'INSERT INTO client_category (name,fixed,status) VALUES("DeeJay","1","1");';
  &ssms::dbi::exec( $sql );
  $sql = 'INSERT INTO client_category (name,fixed,status) VALUES("Friend","1","1");';
  &ssms::dbi::exec( $sql );
  $sql = 'INSERT INTO thread_category (name,fixed,status) VALUES("News","1","1");';
  &ssms::dbi::exec( $sql );
  $sql = 'INSERT INTO thread_category (name,fixed,status) VALUES("Events","1","1");';
  &ssms::dbi::exec( $sql );
  $sql = 'INSERT INTO thread_category (name,fixed,status) VALUES("Timeline","1","1");';
  &ssms::dbi::exec( $sql );
  $sql = 'INSERT INTO media_category (name,path,fixed,status) VALUES("Thread Images","/threads","1","1");';
  &ssms::dbi::exec( $sql );
  $sql = 'INSERT INTO media_category (name,path,fixed,status) VALUES("Client Images","/clients","1","1");';
  &ssms::dbi::exec( $sql );
  $sql = 'INSERT INTO media_category (name,path,fixed,status) VALUES("Friend Images","/friends","1","1");';
  &ssms::dbi::exec( $sql );
  $sql = 'INSERT INTO media_category (name,path,fixed,status) VALUES("Trance Music","http://127.194.66.185/mixes/trance","1","1");';
  &ssms::dbi::exec( $sql );
  $sql = 'INSERT INTO media_category (name,path,fixed,status) VALUES("Ambient Music","http://127.194.66.185/mixes/ambient","1","1");';
  &ssms::dbi::exec( $sql );
  $sql = 'INSERT INTO media_category (name,path,fixed,status) VALUES("House Music","http://127.194.66.185/mixes/house","1","1");';
  &ssms::dbi::exec( $sql );
  $sql = 'INSERT INTO rating_category (name,fixed,status) VALUES("Music","1","1");';
  &ssms::dbi::exec( $sql );
  $sql = 'INSERT INTO rating_category (name,fixed,status) VALUES("Threads","1","1");';
  &ssms::dbi::exec( $sql );

  $client = ssms::client->new();
  $client->load( 'pille' );
  $client->set( 'id', 'Pille', 'pin', 'goa', 'first_name', 'Christian', 'last_name', 'Müller' );
  $client->set( 'street', 'Parzival Str. 10', 'state', 'Bayern', 'city', 'Munich', 'country', 'DE' );
  $client->set( 'email', 'christian@webgraphik.de', 'time', time, 'birthday', '19781002' );
  $client->set( 'zip', '80804', 'phone', '+49 (0174) 97 562 79', 'status', '2', 'title', '2' );
  $client->set( 'fingerprint', md5_hex( $client->get( 'id' ) . $client->get( 'pin' ) ) );
  $client->set( 'category', '1' );
  $client->save();

  $sql = 'SELECT id FROM newsletter_client WHERE email = "' . $client->get( 'email' ) . '";';
  if( ! ( &ssms::dbi::query( $sql ) )[0] ) {
    $sql = 'INSERT INTO newsletter_client (client,email,status) VALUES("'
         . $client->get( 'id' ) . '","' . $client->get( 'email' ) . '","1");';
    &ssms::dbi::exec( $sql );
  }

  $client->load( 'Morlack' );
  $client->set( 'id', 'Morlack', 'pin', 'ben', 'first_name', 'Ben', 'last_name', 'Morlack' );
  $client->set( 'street', 'Domagk House', 'state', 'Bayern', 'city', 'Munich', 'country', 'DE' );
  $client->set( 'email', 'christian@webgraphik.de', 'time', time, 'birthday', '19781002' );
  $client->set( 'zip', '80804', 'phone', '+49', 'status', '2', 'title', '2' );
  $client->set( 'fingerprint', md5_hex( $client->get( 'id' ) . $client->get( 'pin' ) ) );
  $client->set( 'category', '2' );
  $client->save();

  $sql = 'SELECT id FROM newsletter_client WHERE email = "' . $client->get( 'email' ) . '";';
  if( ! ( &ssms::dbi::query( $sql ) )[0] ) {
    $sql = 'INSERT INTO newsletter_client (client,email,status) VALUES("'
         . $client->get( 'id' ) . '","' . $client->get( 'email' ) . '","1");';
    &ssms::dbi::exec( $sql );
  }

  $sql = 'SELECT id FROM client_category WHERE name = "Friend";';
  $friend_client = ( &ssms::dbi::query( $sql ) )[0];
  $sql = 'SELECT id FROM media_category WHERE name = "Friend Images";';
  $friend_image = ( &ssms::dbi::query( $sql ) )[0];
  @tmp = &ssms::table::read( $path{'data'} . '/hbr_friends.db' );
  foreach( @tmp ) {
    @row = split( /\|/, $_ );
    $name = $row[2];
    $name =~ s/(.+?)\@.+/$1/;
    $client = ssms::client->new();
    $client->load( $row[1] );
    $client->set( 'category', $friend_client, 'id', $row[1], 'time', $row[0] );
    $client->set( 'email', $row[2], 'homepage', $row[3], 'first_name', $name );
    $client->save();
    $sql = 'INSERT INTO media_base (category,client,name,file,status) VALUES'
         . '("' . $friend_image . '","' . $row[1] . '","'
         . $row[1] . '","' . $row[4] . '","1");';
    &ssms::dbi::exec( $sql );
  }
  
}

sub removeTables {
  my @tables = ( 'country', 'client_category', 'client_person', 'client_account'
               , 'newsletter_client', 'newsletter_message', 'opinion_poll'
               , 'thread_category', 'thread_entry', 'object_timer'
               , 'media_category', 'media_base', 'rating_category'
               , 'rating_object' );
  foreach( @tables ) {
    &ssms::dbi::exec( 'DROP TABLE IF EXISTS ' . $_ . ';' );
  }
}

sub createTables {
  $sql = qq(
    CREATE TABLE IF NOT EXISTS country (
      id CHAR(2) PRIMARY KEY,
      name VARCHAR(100) NOT NULL
    )
  );
  &ssms::dbi::exec( $sql );
  @tmp = &ssms::table::read( $path{'data'} . '/country.db' );
  foreach( @tmp ) {
    @row = split( /\|/, $_ );
    $sql = 'INSERT INTO country (id,name) VALUES( "' . $row[0] . '","' . $row[1] . '");';
    &ssms::dbi::blind_exec( $sql );
  }
  $sql = qq(
    CREATE TABLE IF NOT EXISTS client_category (
      id INTEGER UNSIGNED PRIMARY KEY AUTO_INCREMENT,
      name VARCHAR(255) NOT NULL,
      fixed TINYINT UNSIGNED NOT NULL,
      status TINYINT UNSIGNED NOT NULL
    )
  );
  &ssms::dbi::exec( $sql );
  $sql = qq(
    CREATE TABLE IF NOT EXISTS client_person (
      id CHAR(32) PRIMARY KEY,
      pin CHAR(32) NOT NULL,
      category INTEGER UNSIGNED NOT NULL,
      first_name VARCHAR(50) NOT NULL,
      last_name VARCHAR(50) NOT NULL,
      street VARCHAR(50) NOT NULL,
      state VARCHAR(50) NOT NULL,
      zip VARCHAR(10) NOT NULL,
      city VARCHAR(50) NOT NULL,
      country CHAR(2) NOT NULL,
      birthday CHAR(8) NOT NULL,
      phone VARCHAR(30) NOT NULL,
      email VARCHAR(100) NOT NULL,
      homepage VARCHAR(255) NOT NULL,
      time BIGINT UNSIGNED NOT NULL,
      info MEDIUMBLOB NOT NULL,
      charge BIGINT NOT NULL,
      fingerprint CHAR(32) NOT NULL,
      status TINYINT UNSIGNED NOT NULL
    )
  );
  &ssms::dbi::exec( $sql );
  $sql = qq(
    CREATE TABLE IF NOT EXISTS client_account (
      id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
      client CHAR(32) NOT NULL,
      time BIGINT UNSIGNED NOT NULL,
      info BLOB NOT NULL NOT NULL,
      sender VARCHAR(32) NOT NULL,
      amount BIGINT NOT NULL,
      charge BIGINT NOT NULL
    )
  );
  &ssms::dbi::exec( $sql );
  $sql = qq(
    CREATE TABLE IF NOT EXISTS opinion_poll (
      id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
      question BLOB NOT NULL,
      answer_1 VARCHAR(100) NOT NULL,
      answer_2 VARCHAR(100) NOT NULL,
      answer_3 VARCHAR(100) NOT NULL,
      answer_4 VARCHAR(100) NOT NULL,
      answer_5 VARCHAR(100) NOT NULL,
      answer_6 VARCHAR(100) NOT NULL,
      hit_1 BIGINT UNSIGNED NOT NULL,
      hit_2 BIGINT UNSIGNED NOT NULL,
      hit_3 BIGINT UNSIGNED NOT NULL,
      hit_4 BIGINT UNSIGNED NOT NULL,
      hit_5 BIGINT UNSIGNED NOT NULL,
      hit_6 BIGINT UNSIGNED NOT NULL
    )
  );
  &ssms::dbi::exec( $sql );
  $sql = qq(
    CREATE TABLE IF NOT EXISTS newsletter_client (
      id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
      client CHAR(10) NOT NULL,
      email VARCHAR(255) NOT NULL UNIQUE,
      status TINYINT UNSIGNED NOT NULL
    )
  );
  &ssms::dbi::exec( $sql );
  $sql = qq(
    CREATE TABLE IF NOT EXISTS newsletter_message (
      id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
      time BIGINT UNSIGNED NOT NULL,
      subject VARCHAR(255) NOT NULL,
      message MEDIUMBLOB NOT NULL
    )
  );
  &ssms::dbi::exec( $sql );
  $sql = qq(
    CREATE TABLE IF NOT EXISTS thread_category (
      id INTEGER UNSIGNED PRIMARY KEY AUTO_INCREMENT,
      name VARCHAR(255) NOT NULL,
      fixed TINYINT UNSIGNED NOT NULL,
      status TINYINT UNSIGNED NOT NULL
    )
  );
  &ssms::dbi::exec( $sql );
  $sql = qq(
    CREATE TABLE IF NOT EXISTS thread_entry (
      id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
      parent BIGINT UNSIGNED NOT NULL,
      category INTEGER UNSIGNED NOT NULL,
      client BIGINT UNSIGNED NOT NULL,
      time BIGINT UNSIGNED NOT NULL,
      subject VARCHAR(255) NOT NULL,
      message MEDIUMBLOB NOT NULL,
      status TINYINT UNSIGNED NOT NULL
    )
  );
  &ssms::dbi::exec( $sql );
  $sql = qq(
    CREATE TABLE IF NOT EXISTS object_timer (
      id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
      object BIGINT UNSIGNED NOT NULL,
      begin BIGINT UNSIGNED NOT NULL,
      end BIGINT UNSIGNED NOT NULL,
      repeat INTEGER UNSIGNED NOT NULL,
      status TINYINT UNSIGNED NOT NULL
    )
  );
  &ssms::dbi::exec( $sql );
  $sql = qq(
    CREATE TABLE IF NOT EXISTS rating_category (
      id INTEGER UNSIGNED PRIMARY KEY AUTO_INCREMENT,
      name VARCHAR(255) NOT NULL,
      fixed TINYINT UNSIGNED NOT NULL,
      status TINYINT UNSIGNED NOT NULL
    )
  );
  &ssms::dbi::exec( $sql );
  $sql = qq(
    CREATE TABLE IF NOT EXISTS rating_object (
      id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
      category INTEGER UNSIGNED NOT NULL,
      object BIGINT UNSIGNED NOT NULL,
      choice_1 INT UNSIGNED NOT NULL,
      choice_2 INT UNSIGNED NOT NULL,
      choice_3 INT UNSIGNED NOT NULL,
      choice_4 INT UNSIGNED NOT NULL,
      choice_5 INT UNSIGNED NOT NULL,
      choice_6 INT UNSIGNED NOT NULL,
      result INT UNSIGNED NOT NULL,
      status TINYINT UNSIGNED NOT NULL
    )
  );
  &ssms::dbi::exec( $sql );
  $sql = qq(
    CREATE TABLE IF NOT EXISTS media_category (
      id INTEGER UNSIGNED PRIMARY KEY AUTO_INCREMENT,
      name VARCHAR(255) NOT NULL,
      path VARCHAR(255) NOT NULL,
      fixed TINYINT UNSIGNED NOT NULL,
      status TINYINT UNSIGNED NOT NULL
    )
  );
  &ssms::dbi::exec( $sql );
  $sql = qq(
    CREATE TABLE IF NOT EXISTS media_base (
      id INTEGER UNSIGNED PRIMARY KEY AUTO_INCREMENT,
      category INTEGER UNSIGNED NOT NULL,
      object BIGINT UNSIGNED NOT NULL,
      client CHAR(32) NOT NULL,
      name VARCHAR(255) NOT NULL,
      file VARCHAR(255) NOT NULL,
      i_extra_1 INTEGER UNSIGNED NOT NULL,
      c_extra_1 VARCHAR(255) NOT NULL,
      type TINYINT UNSIGNED NOT NULL,
      status TINYINT UNSIGNED NOT NULL
    )
  );
  &ssms::dbi::exec( $sql );
}

1;
