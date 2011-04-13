<?php
require 'Predis.php';
$_REDIS = new Predis_Client(
	array(
		'host' => '127.0.0.1',
		'port' => 6379,
		'read_write_timeout' => -1
		)
	);

/**
 * Fire and forget
 */
function async_call($function, $args) {
	global $_REDIS;
	$_REDIS->lpush('queue', serialize(array($function, $args)));
}

/**
 * Async call a function with a list of arguments, and return the result
 * It's the map part of map-reduce
 */
function batch($function, $largs) {
	global $_REDIS;
	$pid = uniqid();//[TODO] uuid later?
	foreach($largs as $args) {
		if(! is_array($args)) {
			$args = array($args);
		}
		$_REDIS->lpush('queue', serialize(array($function, $args, $pid)));
	}
	$pubsub = $_REDIS->pubSubContext();
	$pubsub->subscribe("pid:$pid");
	$results = array();
	$cpt = sizeof($largs);
	$errors = array();
	foreach ($pubsub as $message) {
		if($message->kind == 'message') {
			$msg = unserialize($message->payload);
			if($msg[0] == 'r') {
				$results[] = $msg[1];
			}
			if($msg[0] == 'e') {
				$errors[] = $msg[1];
			}
			$cpt --;
			print $cpt;
			if($cpt == 0) {
				break;
			}
		}
	}
	unset($pubsub);
	return array($results, $errors);
}

/*
 * The job is done here
 */
function async_work() {
	global $_REDIS;
	while(true) {
		list($liste, $sdata) = $_REDIS->brpop('queue', 300);
		$data = unserialize($sdata);
		try {
			$result = call_user_func_array($data[0], $data[1]);
			$msg = array('r', $result);
		} catch( Exception $e) {
			$msg = array('e', $e);
		}
		if(sizeof($data) >= 3) {
			$_REDIS->publish("pid:$data[2]", serialize($msg));
		}
	}
}

if(sizeof($argv) && $argv[1] == '--worker') {
	async_work();
}