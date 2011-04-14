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
	$_CONTEXT->set($url, $html);
	preg_match('/<title>(.*)<\/title>/i', $html, $matches);
	return $matches[1];
}

if($argv[1] == '--mapreduce') {
	//map
	list($titles, $errors) = batch('getTitle', array(
		'linuxfr.org',
		'www.slashdot.org',
		'www.boingboing.net',
		'www.rue89.com',
		'blog.makezine.com',
		'toto.com'
	));
	//reduce
	sort($titles);
	var_dump($titles);
	var_dump($errors);
}