<?php

$P = &$_REQUEST;
$filename = 'main.php';

ini_set( 'display_errors', 1 );
ini_set( 'log_errors', 1 );
//set_exception_handler( 'handle_exception' );
//set_error_handler( 'handle_error', (E_ALL | E_STRICT) & ~E_NOTICE & ~2048 );
register_shutdown_function( 'handle_shutdown' ); 

require 'lib/class_db_mysqli.php';
require 'include/config.php';

$Db = new Mysql_DB( $DB_Host, $DB_User, $DB_Auth, $DB_Database, $DB_Charset, $DB_Port );
$Db_Music = new Mysql_DB( $DB_Music_Host, $DB_Music_User, $DB_Music_Auth, $DB_Music_Database, $DB_Music_Charset, $DB_Music_Port );

if( $P['module'] ) {
	$script = 'include/' . $P['module'] . '.php';
	if( !file_exists( $script ) )
		die( "module '{$P['module']}' not found!" );
	require $script;
}

// functions ...

function page_navigation( $rec_start, $rec_page, $rec_count, $page_max = 5 ) {
	$GLOBALS['Start_Record'] = $rec_start;
	$GLOBALS['End_Record'] = $rec_start + $rec_page - 1;
	$GLOBALS['Record_Count'] = $rec_page;
	$GLOBALS['Record_Max'] = $rec_count;
	$GLOBALS['Current_Page'] = (int) ($rec_start / $rec_page) + 1;
	$GLOBALS['Last_Page'] = (int) ($rec_count / $rec_page);
	if( $GLOBALS['Last_Page'] * $rec_page < $rec_count ) {
		$GLOBALS['Last_Page']++;
	}
	$GLOBALS['Start_Page'] = $GLOBALS['Current_Page'] - $page_max;
	if( $GLOBALS['Start_Page'] < 1 ) {
		$GLOBALS['Start_Page'] = 1;
	}
	$GLOBALS['End_Page'] = $GLOBALS['Current_Page'] + $page_max + ($page_max - ($GLOBALS['Current_Page'] - $GLOBALS['Start_Page']));
	if( $GLOBALS['End_Page'] > $GLOBALS['Last_Page'] ) {
		$GLOBALS['Start_Page'] -= $GLOBALS['End_Page'] - $GLOBALS['Last_Page'];
		if( $GLOBALS['Start_Page'] < 1 ) {
			$GLOBALS['Start_Page'] = 1;
		}
		$GLOBALS['End_Page'] = $GLOBALS['Last_Page'];
	}
}

function fit_string( $string, $size, $cutstr = '..', $diff = null )
{
	$len = strlen( $string );
	if( !$string || $size <= 0 || $size > $len )
		return $string;
	if( !isset( $diff ) )
		$diff = (int) ($size * 0.2);
	$tmp = substr( $string, 0, $size );
	$found = strrpos( $tmp, ' ' );
	if( $size - $found < $diff )
		$tmp = substr( $tmp, 0, $found );
	return $tmp . (strlen( $tmp ) < $len ? $cutstr : '');
}

function format_duration( $sec )
{
	$hour = (int) ($sec / 3600);
	$sec -= $hour * 3600;
	$min = (int) ($sec / 60);
	$sec -= $min * 60;
	return sprintf( '%02d:%02d:%02d', $hour, $min, $sec );
}

function handle_exception( $ex )
{
	$message = $ex->getMessage();
	$file = $ex->getFile();
	$line = $ex->getLine();
	$class = get_class( $ex );
	$msg = "$class: $message in $file:line $line";
	$trace = get_stack_trace( $ex->getTrace() );

	if( ini_get( 'display_errors' ) ) {
		echo "<body bgcolor='#ffffff'>\n";
		echo "\n<pre>$msg\n$trace</pre>\n";
	}

	if( ini_get( 'log_errors' ) )
		error_log( "$msg" );
	
	if( function_exists( 'handle_exception_local' ) )
		call_user_func( 'handle_exception_local', $ex, $msg, $trace, $class, $message, $file, $line );
}

function handle_error( $errno, $errstr, $errfile, $errline )
{
	//if( !(error_reporting() & $errno) )
	//	return;
	//echo $errno;
	switch( $errno ) {
	case E_NOTICE:
	case E_USER_NOTICE:
		$type = 'Notice';
		break;
	case E_WARNING:
	case E_USER_WARNING:
	case E_CORE_WARNING:
	case E_COMPILE_WARNING:
		$type = 'Warning';
		break;
	case E_PARSE:
	case E_ERROR:
	case E_USER_ERROR:
	case E_CORE_ERROR:
	case E_COMPILE_ERROR:
		$fatal = true;
		$type = 'Fatal Error';
		break;
	case E_RECOVERABLE_ERROR:
		$type = 'Error';
		break;
	case E_DEPRECATED:
	case E_USER_DEPRECATED:
		$type = 'Deprecated';
		break;
	default:
		$type = "Unknown ({$errno})";
		break;
	}
	$msg = "$type: $errstr in $errfile:line $errline";
	$trace = get_stack_trace( debug_backtrace() );

	if( ini_get( 'display_errors' ) ) {
		//if( !headers_sent() || $fatal )
			echo "<body bgcolor='#ffffff'>\n";
		echo "\n<pre>$msg\n$trace</pre>\n";
	}
	
	if( ini_get( 'log_errors' ) )
		error_log( $msg );
	
	if( function_exists( 'handle_error_local' ) )
		call_user_func( 'handle_error_local', $errno, $errstr, $errfile, $errline, $msg, $trace, $type, $fatal );
	
	if( $fatal )
		exit;
	
	return true;
}

function get_stack_trace( $trace )
{
	$res = "";
	$i = 0;
	foreach( $trace as $ci ) {
		$res .= "    #$i ";
		if( $ci['class'] )
			$res .= $ci['class'] . $ci['type'];
		$res .= $ci['function'] . '(';
		$args = $ci['args'];
		if( $args ) {
			$comma = '';
			foreach( $ci['args'] as $arg ) {
				$res .= $comma;
				if( is_int( $arg ) || is_float( $arg ) )
					$res .= $arg;
				elseif( is_bool( $arg ) )
					$res .= $arg ? "true" : "false";
				elseif( is_object( $arg ) ) {
					if( method_exists( $arg, "__toString" ) )
						$res .= "'$arg'";
					else
						$res .= "object(" . get_class( $arg ) . ")";
				}
				else
					$res .= "'$arg'";
				$comma = ', ';
			}
		}
		$res .= ")";
		if( $ci['file'] )
			$res .= " in " . $ci['file'] . ":line " . $ci['line'];
		else
			$res .= " *system code*";
		$res .= "\n";
		$i++;
	}
	return $res;
}

function handle_shutdown() { 
	$error = error_get_last();
	$type = (int) $error['type'];
	if( $type && $type != E_NOTICE && $type != E_USER_NOTICE && $type != 2048 ) { 
		#print_r( $error );
		handle_error( $type, $error['message'], $error['file'], $error['line'] );
	}
} 

