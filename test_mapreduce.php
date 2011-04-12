<?php
require 'lib/worker.php';

function map($data) {
	echo $data;
	return crypt($data, '$6$rounds=5000$somesalt');
}

if($argv[1] == '--mapreduce') {
	$crypt = batch('map', array(array('Pim'), array('Pam'), array('Poum')));
	var_dump($crypt);
}