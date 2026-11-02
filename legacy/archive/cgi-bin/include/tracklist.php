<?php

if( $P['show'] == 'secimg' ) {
	session_start();
	require 'lib/securimage.php';
	$img = new Securimage();
	
	// You can customize the image by making changes below, some examples are included - remove the "//" to uncomment
	
	//$img->ttf_file        = './Quiff.ttf';
	//$img->captcha_type    = Securimage::SI_CAPTCHA_MATHEMATIC; // show a simple math problem instead of text
	//$img->case_sensitive  = true;                              // true to use case sensitve codes - not recommended
	//$img->image_height    = 90;                                // width in pixels of the image
	//$img->image_width     = $img->image_height * M_E;          // a good formula for image size
	//$img->perturbation    = .75;                               // 1.0 = high distortion, higher numbers = more distortion
	//$img->image_bg_color  = new Securimage_Color("#0099CC");   // image background color
	//$img->text_color      = new Securimage_Color("#EAEAEA");   // captcha text color
	//$img->num_lines       = 8;                                 // how many lines to draw over the image
	//$img->line_color      = new Securimage_Color("#0000CC");   // color of lines over the image
	//$img->image_type      = SI_IMAGE_JPEG;                     // render as a jpeg image
	//$img->signature_color = new Securimage_Color(rand(0, 64),
	//                                             rand(64, 128),
	//                                             rand(128, 255));  // random signature color
	
	// see securimage.php for more options that can be set
	
	$img->image_width = 100;
	$img->image_height = 30;
	$img->ttf_file = $PathData . 'security.ttf';
	$img->image_type = Securimage::SI_IMAGE_PNG;
	$img->charset = '1234567890';
	$img->code_length = 5;
	$img->namespace = 'hbr1';
	$img->perturbation = 0.15;
	$img->image_bg_color = new Securimage_Color( "#100060" );
	$img->text_color = new Securimage_Color( "#33ccff" );
	$img->line_color = new Securimage_Color( "#0077ff" );
	$img->noise_color = $img->line_color;
	$img->noise_level = 1;
	$img->num_lines = 2;
	
	$img->show();  // outputs the image and content headers to the browser
	exit;
}

if( $P['cmd'] == 'request' ) {
	if( $station = find_station( $P['station'] ) ) {
		$songid = (int) $P['songid'];
		$song = $Db_Music->select_row(
			"SELECT *
			FROM {$station['songsTable']}
			WHERE ID = '{$songid}' AND {$station['songTypeQuery']}"
		);
		if( empty($song) ) {
			header( "Location: {$P['backlink']}" );
			exit;
		}
	   	require '../template/tracklist.req.phtml';
		exit;
	}
}
elseif( $_POST['cmd'] == 'reqver' ) {
	session_start();
	if( $station = find_station( $P['station'] ) ) {
		$songid = (int) $P['songid'];
		$song = $Db_Music->select_row(
			"SELECT *
			FROM {$station['songsTable']}
			WHERE ID = '{$songid}' AND {$station['songTypeQuery']}"
		);
		if( empty($song) ) {
			header( "Location: {$P['backlink']}" );
			exit;
		}
		if( $_POST['code'] != $_SESSION['securimage_code_value']['hbr1'] ) {
			header( "Location: {$P['backlink2']}" );
			exit;
		}
		$status = request_song( $station, $song['ID'], $_SERVER["REMOTE_ADDR"] );
		if( !$status )
			$status = 'Station might be offline or return invalid data.';
	   	require '../template/tracklist.req.phtml';
		exit;
	}
}
elseif( $P['station'] ) {
	if( $station = find_station( $P['station'] ) ) {
		if( !isset( $P['offset'] ) ) {
			list( $max_rows ) = $Db_Music->select_row( "SELECT COUNT(*) FROM {$station['songsTable']}" );
			$P['offset'] = rand( 0, (int) $max_rows - 20 );
		}
		$P['limit'] = 20;
		$r_sng = $Db_Music->query(
			"SELECT SQL_CALC_FOUND_ROWS *, UCASE(CONCAT(artist, title)) as sort
			FROM {$station['songsTable']}
			WHERE {$station['songTypeQuery']}
			ORDER BY sort
			LIMIT {$P['limit']} OFFSET {$P['offset']}"
		);
		
		list( $max_rows ) = $Db_Music->select_row( 'SELECT FOUND_ROWS()' );
		page_navigation( $P['offset'], $P['limit'], (int) $max_rows, 7 );

	   	require '../template/tracklist.mix.phtml';
		exit;
	}
}

require '../template/tracklist.phtml';

// functions ...

function find_station( $id )
{
	global $Stations;
	foreach( $Stations as &$station ) {
		if( $id == $station['Id'] ) {
			if (!empty($station['RadioDJ_DB'])) {
				$station['songsTable'] = "{$station['RadioDJ_DB']}.songs";
				$station['songTypeQuery'] = "song_type = 0";
				$station['durationDiv'] = 1;
			} else {
				$station['songsTable'] = "{$station['SAMDB']}.songlist";
				$station['songTypeQuery'] = "songtype = 'S'";
				$station['durationDiv'] = 1000;
			}
			return $station;
		}
	}
	return null;
}

function request_song( $station, $songid, $host ) {
	if (!empty($station['RadioDJ_DB'])) {
		return request_song_radiodj($station, $songid, $host);
	} else {
		return request_song_sam($station, $songid, $host);
	}
}

function request_song_radiodj ($station, $songid, $host) {
	global $Db_Music;

	$userDailyMaxVotes = 10;
	$userTimerMaxVotes = 2;
	$userTimerSeconds = 60 * 60;
	$repeatDelayArtist = 120 * 60;
	$repeatDelayTitle = 240 * 60;
	$repeatDelayTrack = 240 * 60;

	$userDailyVotes = 0;
	$userTimerVotes = 0;

	$r_request = $Db_Music->query(
		"SELECT r.*, s.date_played, s.artist_played, s.title_played
		FROM {$station['RadioDJ_DB']}.requests r
		JOIN {$station['RadioDJ_DB']}.songs s ON s.ID = r.songID
		WHERE r.requested >= CONCAT(SUBDATE(CURRENT_DATE, 1), ' 00:00:00')
		ORDER BY r.ID"
	);
	$tsNow = strtotime('now');
	$tsToday = strtotime('today');
	while ($request = $Db_Music->fetch_row($r_request)) {
		if ($request['userIP'] == $host) {
			$ts = strtotime($request['requested']);
			if ($ts >= $tsToday) {
				if (++$userDailyVotes > $userDailyMaxVotes) {
					return "maximum daily votes reached, please try later again";
				}
			}
			if ($ts + $userTimerSeconds >= $tsNow) {
				if (++$userTimerVotes > $userTimerMaxVotes) {
					return "maximum votes reached, please try later again";
				}
			}
			if (strtotime($request['date_played']) + $repeatDelayTrack >= $tsNow) {
				return "track already played, please choose another one";
			}
			if (strtotime($request['title_played']) + $repeatDelayTitle >= $tsNow) {
				return "title already played, please choose another one";
			}
			if (strtotime($request['artist_played']) + $repeatDelayArtist >= $tsNow) {
				return "artist already played, please choose another one";
			}
		}
	}
	/*
	<string
	xmlns="http://schemas.microsoft.com/2003/10/Serialization/">200
	</string>
	*/
	$res = file_get_contents(str_replace('{id}', $songid, $station['RadioDJ_ReqUrl']));
	if (empty($res)) {
		return false;
	}
	$doc = new DOMDocument();
	$doc->loadXML($res);
	$status = $doc->documentElement;
	if ($status->textContent != '200') {
		return false;
	}
	$Db_Music->query(
		"INSERT INTO {$station['RadioDJ_DB']}.requests
		(songID,userIP,requested,played) VALUES($songid,'$host',NOW(),1)"
	);
	return true;
}

function request_song_sam ($station, $songid, $host) {
	$url = 'http://' . $station['SAMHOST'] . ':' . $station['SAMPORT'] .
		'/req/?songID=' . $songid . '&host=' . urlencode( $host );
	//return $url;
	$res = file_get_contents( $url );
	if( !$res )
		return false;
	$doc = new DOMDocument();
	$doc->loadXML( $res );
	if( false ) {
		$tmp = $doc->saveXML();
		$tmp = str_replace( '<', '&lt;', $tmp );
		$tmp = str_replace( '>', '&gt;', $tmp );
		echo "<pre>$tmp</pre>\n";
	}
	$status = $doc->getElementsByTagName( 'status' )->item( 0 );
	if( !$status )
		return false;
	$code = $status->getElementsByTagName( 'code' )->item( 0 );
	if( !$code )
		return false;
	if( $code->textContent == '200' )
		return true;
	$msg = $status->getElementsByTagName( 'message' )->item( 0 );
	if( !$msg )
		return false;
	return $msg->textContent;
}

