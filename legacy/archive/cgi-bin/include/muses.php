<?php

foreach( $Stations as $station ) {
	if( $station['Id'] == $_GET['station'] ) {
		break;
	}
}

require '../template/muses.phtml';
