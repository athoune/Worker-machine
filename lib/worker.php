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

function batch($function, $largs) {
	global $_REDIS;
	$pid = uniqid();//[TODO] uuid later?
	foreach($largs as $args) {
		$_REDIS->lpush('queue', serialize(array($function, $args, $pid)));
	}
	$pubsub = $_REDIS->pubSubContext();
	$pubsub->subscribe("pid:$pid");
	$results = array();
	$cpt = sizeof($largs);
	foreach ($pubsub as $message) {
		if($message->kind == 'message') {
			$results[] = unserialize($message->payload);
			$cpt --;
			print $cpt;
			if($cpt == 0) {
				break;
			}
		}
	}
	unset($pubsub);
	return $results;
}

function async_work() {
	global $_REDIS;
	while(true) {
		list($liste, $sdata) = $_REDIS->brpop('queue', 300);
		$data = unserialize($sdata);
		$result = call_user_func_array($data[0], $data[1]);
		if(sizeof($data) >= 3) {
			$rresult = serialize($result);
			$_REDIS->lpush("result:$data[2]", $rresult);
			$_REDIS->publish("pid:$data[2]", $rresult);
		}
	}
}

if(sizeof($argv) && $argv[1] == '--worker') {
	async_work();
}