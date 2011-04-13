<?php
require 'lib/worker.php';

function map($url) {
	echo "$url\n";
	$html = file_get_contents("http://$url");
	preg_match('/<title>(.*)<\/title>/i', $html, $matches);
	return $matches[1];
}

if($argv[1] == '--mapreduce') {
	$titles = batch('map', array('linuxfr.org', 'www.slashdot.org', 'www.boingboing.net', 'www.rue89.com', 'blog.makezine.com'));
	sort($titles);
	var_dump($titles);
}