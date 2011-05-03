<?php
require 'lib/worker.php';

function getTitle($url) {
	$html = file_get_contents("http://$url");
	if($html == FALSE) {
		throw new Exception("can't fetch url");
	}
	preg_match('/<title>(.*)<\/title>/i', $html, $matches);
	return html_entity_decode($matches[1]);
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
		'freshmeat.net',
		'www.flickr.com'
	);
	$worker = new Worker();
	$onError = function($e) {
		echo "oups " . $e->getMessage() . "\n";
	};
	//map
	$n = 0;
	foreach($worker->batch('getTitle', $sites, $onError) as $title) {
		echo $n++;
		echo " $title\n";
	}
}