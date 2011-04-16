<?php
require 'lib/worker.php';

class UCEngine {
	function __construct($host, $port) {
		$this->host = $host;
		$this->port = $port;
	}
	function request($method, $path, $body=NULL) {
		$timeout = 30;
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_USERAGENT, "php-node-curl" );
		if($method == 'POST') {
			curl_setopt($ch, CURLOPT_POST, count($body));
			curl_setopt( $ch, CURLOPT_POSTFIELDS, http_build_query($body));
		} else {
			curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, $method);
		}
		curl_setopt( $ch, CURLOPT_URL, "http://$this->host:$this->port/api/0.5$path" );
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
		curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, $timeout );
		curl_setopt( $ch, CURLOPT_TIMEOUT, $timeout );
		curl_setopt( $ch, CURLOPT_MAXREDIRS, 10 );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, TRUE);
		$content = curl_exec( $ch );
		$response = curl_getinfo( $ch );
		curl_close ( $ch );
		return array($response['http_code'], json_decode($content));
	}
}

function event_listener($pattern, $id) {
	global $_REDIS;
	$_UCE = new UCEngine('localhost', 5280);
	$start = 0;
	while(TRUE) {
		list($status, $response) = $_UCE->request('GET', "$pattern&start=$start");
		if($status == 200 && $response != NULL) {
			foreach($response->result as $event) {
				$start = $event->datetime + 1;
				async_call('one_event', array($event, $id));
				// $_REDIS->lpush("event:$id", serialize($event));
			}
		}
	}
}

function one_event($evt) {
	var_dump($evt);
}

class User {
	function __construct($name) {
		$this->name = $name;
	}
	function presence($uce, $credential) {
		$this->ucengine = $uce;
		list($status, $response) = $uce->request('POST', '/presence/', array(
				'name'               => $this->name,
				'credential'         => $credential,
				'metadata[nickname]' => $this->name
			));
		if($status == 201) {
			$this->uid = $response->result->uid;
			$this->sid = $response->result->sid;
			async_call('event_listener', array('/event?' . http_build_query(array(
				'uid' => $this->uid,
				'sid' => $this->sid,
				'_async' => 'lp'
				)), $this->uid));
		}
	}
}

if(sizeof($argv) > 1 && $argv[1] == '--uce') {
	$_UCE = new UCEngine('localhost', 5280);
	$victor = new User('victor.goya@af83.com');
	$victor->presence($_UCE, 'pwd');
	var_dump($victor->uid);
}