<?

function find_mix( $artist, $title )
{
	global $Db;
	$res = $Db->query(
		"SELECT id, dj, name FROM mixes WHERE MATCH(dj) AGAINST(?) OR MATCH(name) AGAINST(?)"
		, $artist, $title
	);
	if( $Db->num_rows( $res ) ) {
		$artist = str_replace( '_', ' ', strtolower( $artist ) );
		$title = str_replace( '_', ' ', strtolower( $title ) );
		while( $row = $Db->fetch_row( $res ) ) {
			$dj = str_replace( '_', ' ', strtolower( $row['dj'] ) );
			$name = str_replace( '_', ' ', strtolower( $row['name'] ) );
			if( $artist && $dj ) {
				if( (strpos( $artist, $dj ) !== false || strpos( $dj, $artist ) !== false) &&
					(strpos( $title, $name ) !== false || strpos( $name, $title ) !== false)
				) {
					return $row['id'];
				}
			}
			else {
				if( (($dj && strpos( $artist, $dj ) !== false) || ($artist && strpos( $dj, $artist ) !== false)) &&
					(strpos( $title, $name ) !== false || strpos( $name, $title ) !== false)
				) {
					return $row['id'];
				}
			}
		}
	}
}

function station_error( $station )
{
	global $Db;
	$title = $station['Track_Artist'] . ' - ' . $station['Track_Title'];
	list( $value ) = $Db->select_row( "SELECT Value FROM runtime WHERE Name = '{$station['Id']}_Error'" );
	if( $value == $title )
		return;
	$Db->query( "UPDATE runtime SET Value = ? WHERE Name = ?", $title, $station['Id'] . '_Error' );
	$content = "Die Musik im {$station['Id']} Kanal ist abgelaufen.\nMöglicherweise hängt der Stream".
	mail_error( $station['Id'] . ' Station Fehler', $content, 'HBR1 ' . $station['Id'] . ' Station <station@hbr1.com>' );
}

function mail_error( $subject, $content, $from = 'error@hbr1.com' )
{
	$header = "From: {$from}\r\n"
		. "Content-Type: text/plain; charset=\"utf-8\"\r\n"
		. "Content-Transfer-Encoding: 8bit\r\n"
		. "MIME-Version: 1.0\r\n"
		. "Priority: high";
	$receiver = 'christian@hbr1.com, earlgrey@hbr1.com';
	mail( $receiver, $subject, $content, $header );
}

function setup_stations()
{
	global $Stations, $Db_Music;
	foreach( $Stations as &$station ) {
		#echo $station['SAMDB'], "<br>";
		$station_id = $station['Id'];
		if (!empty($station['RadioDJ_DB'])) {
			$row = $Db_Music->select_row(
				"SELECT * FROM {$station['RadioDJ_DB']}.history ORDER BY ID DESC LIMIT 1"
			);
			$durationDiv = 1;
		} else {
			$row = $Db_Music->select_row(
				"SELECT * FROM {$station['SAMDB']}.historylist ORDER BY ID DESC LIMIT 1"
			);
			$durationDiv = 1000;
		}
		if( $Server[$station_id]['Live']  ) {
			$station['Track_Data'] = true;
			$station['Track_Live'] = true;
			$station['Track_Artist'] = $Server[$station_id]['Artist'];
			$station['Track_Title'] = $Server[$station_id]['Title'];
		}
		else {
			if (empty($row)) {
				$row = [];
			}
			$station['Track_Data'] = true;
			foreach( $row as $name => $value ) {
				$key = strtoupper( substr( $name, 0, 1 ) ) . strtolower( substr( $name, 1 ) );
				$station["Track_{$key}"] = $value;
			}
			$start_time = strtotime( $row['date_played'] );
			$duration = $row['duration'] < 0 ? -1 : $row['duration'] / $durationDiv;
		}
		$end_time = $start_time + $duration;
		$time = time();
		$time_left = $end_time - $time;
		if( $duration < 0 && $time_left > -14400 ) {
			$station['Track_Error'] = 0;
			$station['Track_Duration'] = '';
		}
		elseif( $time_left < -600 ) {
			$station['Track_Error'] = 1;
			$station['Track_Duration'] = '';
			station_error( $station );
		}
		elseif( $time_left < 0 ) {
			$station['Track_Error'] = 0;
			$station['Track_Duration'] = '??:??:??';
		}
		else {
			$station['Track_Error'] = 0;
			$station['Track_Duration'] = format_duration( $time_left );
		}
		$station['MixID'] = null;
		$station['MixID'] = find_mix( $station['Track_Artist'], $station['Track_Title'] );
		if( $station['Track_Artist'] && $station['Track_Title'] ) {
			$station['Track_Caption'] = $station['Track_Artist'] . ' - ' . $station['Track_Title'];
		}
		elseif( $station['Track_Artist'] ) {
			$station['Track_Caption'] = $station['Track_Artist'];
		}
		elseif( $station['Track_Title'] ) {
			$station['Track_Caption'] = $station['Track_Title'];
		}
		else {
			$station['Track_Caption'] = '[no title]';
		}
	}
}
