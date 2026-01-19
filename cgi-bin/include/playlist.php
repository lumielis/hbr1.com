<?

require 'include/station.php';

setup_stations();

require '../template/playlist.phtml';

function init_station_playlist(&$station)
{
	global $Db_Music;
	//print_r( $station );
	$pl = array();
	if (!empty($station['RadioDJ_DB'])) {
		list($artist, $title) = $Db_Music->select_row(
			"SELECT artist, title FROM {$station['RadioDJ_DB']}.history ORDER BY ID DESC LIMIT 1"
		);
	} else {
		list($artist, $title) = $Db_Music->select_row(
			"SELECT artist, title FROM {$station['SAMDB']}.historylist ORDER BY ID DESC LIMIT 1"
		);
	}
	if ($artist)
		array_push($pl, $artist . ' - ' . $title);
	else
		array_push($pl, $title);
	$station['CTO'] = 0;
	if (!empty($station['RadioDJ_DB'])) {
		$res = $Db_Music->query(
			"SELECT q.artist, q.title, r.ID as requestID
			FROM {$station['RadioDJ_DB']}.queuelist q
			JOIN {$station['RadioDJ_DB']}.songs s ON q.songID = s.ID
			LEFT JOIN {$station['RadioDJ_DB']}.requests r ON r.requested >= SUBDATE(NOW(), INTERVAL 12 HOUR) AND r.songID = s.ID
			WHERE s.song_type = 0
			ORDER BY q.ID"
		);
	} else {
		$res = $Db_Music->query(
			"SELECT artist, title, requestID
			FROM {$station['SAMDB']}.queuelist
			JOIN {$station['SAMDB']}.songlist ON queuelist.songID = songlist.ID
			WHERE songtype = 'S'
			ORDER BY queuelist.sortID"
		);
	}
	while ($row = $Db_Music->fetch_row($res)) {
		if (empty(trim($row['title']))) {
			continue;
		}
		$tmp = ($row['artist'] ? $row['artist'] . ' - ' : '') . $row['title'];
		if ($row['requestID'] > 0)
			$tmp = '<font color="#9999cc"><b>*</b></font> ' . $tmp;
		array_push($pl, $tmp);
	}
	$station['Playlist'] = $pl;
}

function get_playlist($station)
{
	$len = count($station['Playlist']);
	$offset = $station['CTO'];
	$res = array();
	for ($i = 1; $i <= 8; $i++) {
		if ($offset >= $len) {
			array_push($res,
				'<a href="main.php?module=tracklist&station=' . $station['Id'] . '">'
				. '<font color="#999999">[play another mix]</font></a>');
		} else {
			array_push($res, $station['Playlist'][$offset++]);
		}
	}
	return $res;
}
