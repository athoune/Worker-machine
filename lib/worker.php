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
	$results = array();
	$cpt = sizeof($largs);
	$errors = array();
	while(true) {
		list($liste, $sdata) = $_REDIS->brpop("pid:$pid", 300);
		$msg = unserialize($sdata);
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
	$_REDIS->del("pid:$pid");
	$context->clean();
	return array($results, $errors);
}

class Batch implements Iterator {
	private $position = 0;
	private $results = array();
	function __construct($function, $largs) {
		global $_REDIS;
		$this->pid = $_REDIS->incr('pid');
		echo "pid: $this->pid\n";
		foreach($largs as $args) {
			if(! is_array($args)) {
				$args = array($args);
			}
			$_REDIS->lpush('queue', serialize(array($function, $args, $pid)));
		}
		$this->context = new Context($pid);
		$this->results = array();
		$this->cpt = sizeof($largs);
	}
	public function rewind() {
		$this->position = 0;
	}
	public function current() {
		global $_REDIS;
		for($a = sizeof($this->results); $a <= $this->position; $a++) {
			list($liste, $sdata) = $_REDIS->brpop("pid:$pid", 300);
			$msg = unserialize($sdata);
			if($msg[0] == 'e') {
				$this->results[] = null;
				throw $msg[1];
			}
			$this->results[] = $msg[1];
		}
		return $this->results[$this->position];
	}
	public function key() {
		return $this->position;
	}
	public function next() {
		++$this->position;
	}
	public function valid() {
		return $this->position < $this->cpt;
	}
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
	$posix_pid = posix_getpid();
	//set_error_handler('error_as_exception');
	while(true) {
		list($liste, $sdata) = $_REDIS->brpop("posix_pid:$posix_pid", 'queue', 300);
		if($sdata != NULL) {
			$data = unserialize($sdata);
			if(sizeof($data) > 2) {
				$_PID = $data[2];
				$_CONTEXT = new Context($_PID);
			}
			try {
				//var_dump($sdata);
				$result = call_user_func_array($data[0], $data[1]);
				$msg = array('r', $result);
			} catch( Exception $e) {
				$msg = array('e', $e);
			}
			unset($_CONTEXT);
			if(sizeof($data) > 2) {
				unset($_PID);
				$_REDIS->lpush("pid:$data[2]", serialize($msg));
			}
		}
	}
	restore_error_handler();
}

if(sizeof($argv) > 1 && $argv[1] == '--worker') {
	async_work();
}