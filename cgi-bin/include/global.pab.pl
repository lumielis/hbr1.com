use PAB3;
use PAB3::HashmapCache;
use PAB3::Logger;
use PAB3::DB::Max;
use PAB3::DB::Driver::Mysql;
use PAB3::Utils;

if( ! $_PAB_Init ) {
	$Logger = PAB3::Logger->new(
		'file' => $Setup{'PathLog'} . 'hbr1v3.log',
		'level' => 2,
		'header' => '--- starting session ---',
	);
	$PAB = PAB3->new(
		'path_template' => $Setup{'PathTemplate'},
		'path_cache' => $Setup{'PathCache'},
		'logger' => $Logger,
		'hashmap_cache' => PAB3::HashmapCache->new(),
		'auto_cache' => 1,
		'prg_start' => '<*',
		'prg_end' => '*>',
	);
	$DB = PAB3::DB->new( %{$Setup{'PAB3::DB'}} );
	$_PAB_Init = 1;
}
else {
	# reset log-time and send log-header
	$Logger->reset();
	# pab parser
	$PAB->reset();
}

1;
