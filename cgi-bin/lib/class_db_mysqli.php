<?php

class Mysql_DB
{
  
	private $link_id;
	private $database;

	private $counter = 1;
	
	private $dbg = false;
	private $dbg_log = null;
	private $dbg_ts = 0;

	public function __construct( $host, $user, $auth, $dbname = null, $charset = null, $port = null )
	{
		if( $GLOBALS['DEBUG'] ) {
			$this->dbg = true;
			$this->dbg_log = array();
			$this->dbg_ts = microtime( true );
		}
		$this->database = 'db://' . $user . '@' . $host . ':' . $port . '/mysql/' . $dbname;
		$this->link_id = @ mysqli_connect( $host, $user, $auth, $dbname, $port ? $port : 3306 );
		if( !$this->link_id )
			$this->error( 'Connect Error (' . mysqli_connect_errno() . ') ' . mysqli_connect_error() );
		elseif( $charset )
			mysqli_set_charset( $this->link_id, $charset );
	}
	
	public function __destruct()
	{
		if( $this->dbg ) {
			print implode( "\n", $this->dbg_log );
			$rt = sprintf( '%0.3f', (microtime( true ) - $this->dbg_ts) * 1000 );
			print "<p>processed in $rt ms</p>\n";
		}
	}
  
	public function query( $query )
	{
		if( $this->dbg )
			$ts = microtime( true );
		$numargs = func_num_args();
		if( $numargs > 1 ) {
			$arglist = func_get_args();
			$stmt = mysqli_prepare( $this->link_id, $query );
			if( !$stmt )
				$this->error( "Invalid SQL: \"$query\"" );
			$list[0] = $stmt;
			$list[1] = "";
			$t = &$list[1];
			for( $i = 1; $i < $numargs; $i ++ ) {
				$v = &$arglist[$i];
				if( is_int( $v ) )
					$t .= 'd';
				elseif( is_float( $v ) )
					$t .= 'd';
				else
					$t .= 's';
				$list[] = &$v;
			}
			unset( $t, $v );
			if( !call_user_func_array( 'mysqli_stmt_bind_param', $list ) )
				$this->error( "Bind param failed: $t " . implode( ',', $l ) );
			if( !mysqli_stmt_execute( $stmt ) )
				$this->error( "Execute failed for SQL: \"$query\"" );
			$data = mysqli_stmt_result_metadata( $stmt );
			if( !$data ) {
				mysqli_stmt_close( $stmt );
				$this->query_id = true;
			}
			else {
				mysqli_stmt_store_result( $stmt );
				$this->query_id = array( $stmt, false, $this->counter++ );
			}
			if( $this->dbg ) {
				$rt = sprintf( '%0.3f', (microtime( true ) - $ts) * 1000 );
				array_shift( $list );
				array_shift( $list );
				array_push( $this->dbg_log, "<p><code>" . $query . "<br><b>arguments:</b> " . var_export( $list, true ) . "</code><br><i><font color=gray>($rt ms)</font></i></p>" );
			}
		}
		else {
			$this->query_id = mysqli_query( $this->link_id, $query );
			if( $this->dbg ) {
				$rt = sprintf( '%0.3f', (microtime( true ) - $ts) * 1000 );
				array_push( $this->dbg_log, "<p><code>" . $query . "</code><br><i><font color='gray'>($rt ms)</font></i></p>" );
			}
		}
		if( !$this->query_id )
			$this->error( "Invalid SQL: \"$query\"" );
		return $this->query_id;
	}
	
	public function prepare( $query )
	{
		if( $this->dbg ) {
			$ts = microtime( true );
			$stmt = mysqli_prepare( $this->link_id, $query );
			$rt = sprintf( '%0.3f', (microtime( true ) - $ts) * 1000 );
			array_push( $this->dbg_log, "<p><code>" . $query . "</code><br><i><font color='gray'>($rt ms)</font></i></p>" );
		}
		else {
			$stmt = mysqli_prepare( $this->link_id, $query );
		}
		if( !$stmt )
			$this->error( "Invalid SQL: \"$query\"" );
		return $stmt;
	}
	
	public function execute( $stmt )
	{
		if( $this->dbg )
			$ts = microtime( true );
		$numargs = func_num_args();
		if( $numargs > 1 ) {
			$arglist = func_get_args();
			$list[0] = &$stmt;
			$list[1] = "";
			$t = &$list[1];
			for( $i = 1; $i < $numargs; $i ++ ) {
				$v = &$arglist[$i];
				if( is_int( $v ) )
					$t .= 'd';
				elseif( is_float( $v ) )
					$t .= 'd';
				else
					$t .= 's';
				$list[] = &$v;
			}
			unset( $t, $v );
			if( !call_user_func_array( 'mysqli_stmt_bind_param', $list ) )
				$this->error( "Bind param failed: $t " . implode( ',', $l ) );
		}
		if( !mysqli_stmt_execute( $stmt ) )
			$this->error( "Execute failed for SQL: \"$query\"" );
		$data = mysqli_stmt_result_metadata( $this->stmt );
		if( !$data ) {
			mysqli_stmt_close( $stmt );
			print "bool";
			$query_id = true;
		}
		else {
			mysqli_stmt_store_result( $stmt );
			$query_id = array( $stmt, false, $this->counter++, $data );
		}
		if( $this->dbg ) {
			$rt = sprintf( '%0.3f', (microtime( true ) - $ts) * 1000 );
			if( is_array( $list ) ) {
				array_shift( $list );
				array_shift( $list );
				array_push( $this->dbg_log, "<p><code>" . $query . "</code><br><b>arguments:</b> " . var_export( $list, true ) . "<br><i><font color=gray>($rt ms)</font></i></p>" );
			}
			else {
				array_push( $this->dbg_log, "<p><code>" . $query . "</code><br><i><font color='gray'>($rt ms)</font></i></p>" );
			}
		}
		return $query_id;
	}
 
	public function fetch_row( $query_id )
	{
		if( is_array( $query_id ) ) {
			$stmt = $query_id[0];
			if( !$query_id[1] ) {
				$query_id[2] = array();
				$array = &$query_id[2];
				$data = mysqli_stmt_result_metadata( $stmt );
				$count = 0;
				$list[0] = $stmt;
				while( $field = mysqli_fetch_field( $data ) ) {
					$ref = &$array[$count++];
					$array[$field->name] = &$ref;
				    $list[$count] = &$ref;
				}
				call_user_func_array( 'mysqli_stmt_bind_result', $list );
				$query_id[1] = true;
			}
			if( !mysqli_stmt_fetch( $stmt ) )
				return false;
			return $query_id[2];
		}
		else {
			return mysqli_fetch_array( $query_id );
		}
	}

	public function free_result( $query_id )
	{
		if( is_array( $query_id ) ) {
			mysqli_stmt_free_result( $query_id[0] );
			return mysqli_stmt_close( $query_id[0] );
		}
		else {
			return mysqli_free_result( $query_id );
		}
	}

	public function select_row( $query )
	{
		$numargs = func_num_args();
		if( $numargs > 1 ) {
			$arglist = func_get_args();
			$arglist[0] = $query;
			$id = call_user_func_array( array( $this, 'query' ), $arglist );
		}
		else {
			$id = $this->query( $query );
		}
		$array = $this->fetch_row( $id );
		$this->free_result( $id );
		return $array;
	}
	
	public function num_rows( $query_id )
	{
		if( is_array( $query_id ) )
			return mysqli_stmt_num_rows( $query_id[0] );
		else
			return mysqli_num_rows( $query_id );
	}
	
	public function reset_result( $query_id, $row = 0 )
	{
		if( is_array( $query_id ) )
			return mysqli_stmt_data_seek( $query_id[0], $row );
		else
			return mysqli_data_seek ( $query_id, $row );
	}
 
	public function affected_rows()
	{
		return mysqli_affected_rows( $this->link_id );
	}
 
	public function last_insert_id()
	{
		$res = $this->query( 'SELECT LAST_INSERT_ID()' );
		list($id) = $this->fetch_array( $res );
		return (int) $id;
	}

	public function escape_string( $str )
	{
		return mysqli_real_escape_string( $this->link_id, $str );
	}

	public function quote( $s )
	{
		if( is_null( $s ) )
			return 'NULL';
		else
			return '\'' . mysqli_real_escape_string( $this->link_id, $s ) . '\'';
	}
	
	public function quote_id( $s )
	{
		return '`' . str_replace( '`', '\\`', $s ) . '`';
	}
	
	public function error( $msg )
	{
		$msg = "Db error ($this->database): $msg";
		if( $this->link_id )
			$msg .= ": (" . mysqli_errno( $this->link_id ) . ") " . mysqli_error( $this->link_id );
		trigger_error( $msg, E_USER_ERROR );
	}
}

