<?php
require 'Predis.php';

class Worker {
	function __construct($predis = null) {
		if($predis == null)
			$predis = new Predis_Client(
		array(
			'host' => '127.0.0.1',
			'port' => 6379,
			'read_write_timeout' => -1
			)
		);
		$this->predis = $predis;
	}
	/**
	 * Fire and forget
	 */
	function async_call($function, $args) {
		$this->predis->lpush('queue', serialize(array($function, $args)));
	}
	
	function batch($function, $args, $onError = null) {
		return new Batch($this->predis, $function, $args, $onError);
	}
	
	/*
	 * The job is done here
	 */
	function async_work() {
		$posix_pid = posix_getpid();
		$this->predis->sadd('workers', $posix_pid);
		//set_error_handler('error_as_exception');
		while(true) {
			list($liste, $sdata) = $this->predis->brpop("posix_pid:$posix_pid", 'queue', 300);
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
					$this->predis->lpush("pid:$data[2]", serialize($msg));
				}
			}
		}
		restore_error_handler();
	}
}

abstract class Task {
	protected $context;
	protected $taskid;
	
	function __construct($taskid) {
		$this->context = new Context($taskid);
	}
	abstract function run();
}

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

class Batch implements Iterator {
	private $position = 0;
	private $results = array();
	function __construct($predis, $function, $largs, $onError = null) {
		$this->predis = $predis;
		$this->onError = $onError;
		$this->pid = $this->predis->incr('pid');
		echo "pid: $this->pid\n";
		foreach($largs as $args) {
			if(! is_array($args)) {
				$args = array($args);
			}
			$this->predis->lpush('queue', serialize(array($function, $args, $pid)));
		}
		$this->context = new Context($pid);
		$this->results = array();
		$this->cpt = sizeof($largs);
	}
	public function rewind() {
		$this->position = 0;
	}
	public function current() {
		for($a = sizeof($this->results); $a <= $this->position; $a++) {
			list($liste, $sdata) = $this->predis->brpop("pid:$pid", 300);
			$msg = unserialize($sdata);
			if($msg[0] == 'e') {
				$this->results[] = null;
				if($this->onError != null) {
					$err = $this->onError;
					$err($msg[1]);
				} else
					throw $msg[1];
			} else
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

if(sizeof($argv) > 1 && $argv[1] == '--worker') {
	$worker = new Worker();
	$worker->async_work();
}