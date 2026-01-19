<?php

$dt = new DateTime('now', new DateTimeZone('UTC'));
$now = $dt->getTimestamp();

$dt->setTimezone(new DateTimeZone('Europe/Berlin'));
$tzo_berlin = $dt->getOffset();

$dt->setTimezone(new DateTimeZone('America/New_York'));
$tzo_new_york = $dt->getOffset();

$dt->setTimezone(new DateTimeZone('Europe/Moscow'));
$tzo_moscow = $dt->getOffset();

$dt->setTimezone(new DateTimeZone('Asia/Tokyo'));
$tzo_tokyo = $dt->getOffset();

#warn join( ', ', ( $Dt_UTC, $Dt_Berlin, $Dt_New_York, $Dt_Moscow, $Dt_Tokyo ) );

require '../template/topframe.phtml';
