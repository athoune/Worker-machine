<?php
require 'Predis.php';
$_REDIS = new Predis_Client(
	array(
		'host' => '127.0.0.1',
		'port' => 6379,
		'read_write_timeout' => -1
		)
	);

class Context {
	public $name;
	function __construct($pid) {
		$this->name = "context:$pid";
	}
	function get($key) {
		global $_REDIS;
		return $_REDIS->hget($this->name, $key);
	}
	function set($key, $value) {
		global $_REDIS;
		$_REDIS->hset($this->name, $key, $value);
	}
	function clean() {
		global $_REDIS;
		$_REDIS->del($this->name);
	}
	function dump() {
		global $_REDIS;
		return $_REDIS->hvals($this->name);
	}
}

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
	$pid = $_REDIS->incr('pid');
	echo "pid: $pid\n";
	foreach($largs as $args) {
		if(! is_array($args)) {
			$args = array($args);
		}
		$_REDIS->lpush('queue', serialize(array($function, $args, $pid)));
	}
	$context = new Context($pid);
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
	$context->clean();
	return array($results, $errors);
}

function error_as_exception($errno, $errstr) {
	throw new Exception($errstr);
}
/*
 * The job is done here
 */
function async_work() {
	global $_REDIS;
	global $_PID;
	global $_CONTEXT;
	set_error_handler('error_as_exception');
	while(true) {
		list($liste, $sdata) = $_REDIS->brpop('queue', 300);
		$data = unserialize($sdata);
		$_PID = $data[2];
		$_CONTEXT = new Context($_PID);
		try {
			$result = call_user_func_array($data[0], $data[1]);
			$msg = array('r', $result);
		} catch( Exception $e) {
			$msg = array('e', $e);
		}
		unset($_CONTEXT);
		unset($_PID);
		if(sizeof($data) >= 3) {
			$_REDIS->publish("pid:$data[2]", serialize($msg));
		}
	}
	restore_error_handler();
}

if(sizeof($argv) && $argv[1] == '--worker') {
	async_work();
}