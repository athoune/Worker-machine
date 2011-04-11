<?php
require 'Predis.php';
$_REDIS = new Predis_Client();

class Worker {
	function __construct() {
	}
	function perform() {
	}
	
}

function async_call($function, $args) {
	global $_REDIS;
	$_REDIS->lpush('queue', serialize(array($function, $args)));
}

function async_work() {
	global $_REDIS;
	while(true) {
		list($liste, $data) = $_REDIS->brpop('queue', 300);
		$data = unserialize($data);
		call_user_func_array($data[0], $data[1]);
	}
}

if(sizeof($argv) && $argv[1] == 'worker') {
	async_work();
}