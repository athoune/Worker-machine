<?php
require 'lib/worker.php';

function getTitle($url) {
	global $_PID;
	global $_CONTEXT;
	echo "$_PID: $url\n";
	$html = file_get_contents("http://$url");
	if($html == FALSE) {
		throw new Exception("can't fetch url");
	}
	//$_CONTEXT->set($url, $html);
	preg_match('/<title>(.*)<\/title>/i', $html, $matches);
	return $matches[1];
}

if($argv[1] == '--mapreduce') {
	$sites = array(
		'linuxfr.org',
		'www.slashdot.org',
		'www.boingboing.net',
		'www.rue89.com',
		'blog.makezine.com',
		'toto.com',
		'www.4chan.org',
		'news.ycombinator.com',
		'danstonchat.com',
		'freshmeat.net'
	);
	$worker = new Worker(new Predis_Client(
		array(
			'host' => '127.0.0.1',
			'port' => 6379,
			'read_write_timeout' => -1
			)
		)
	);

	//map
	$n = 0;
	foreach($worker->batch('getTitle', $sites, function($e) { echo "oups " . $e->getMessage() . "\n";}) as $title) {
		echo $n++;
		echo " $title\n";
	}
}