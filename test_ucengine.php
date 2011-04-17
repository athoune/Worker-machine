<?php
require 'lib/worker.php';

class UCEngine {
	function __construct($host, $port) {
		$this->host = $host;
		$this->port = $port;
		$this->ch = curl_init();
		$timeout = 30;
		curl_setopt( $this->ch, CURLOPT_USERAGENT, "php-node-curl" );
		curl_setopt( $this->ch, CURLOPT_FOLLOWLOCATION, true );
		curl_setopt( $this->ch, CURLOPT_CONNECTTIMEOUT, $timeout );
		curl_setopt( $this->ch, CURLOPT_TIMEOUT, $timeout );
		curl_setopt( $this->ch, CURLOPT_MAXREDIRS, 10 );
		curl_setopt( $this->ch, CURLOPT_RETURNTRANSFER, TRUE);
	}
	function request($method, $path, $body=NULL) {
		if($method == 'POST') {
			curl_setopt($this->ch, CURLOPT_POST, count($body));
			curl_setopt( $this->ch, CURLOPT_POSTFIELDS, http_build_query($body));
		} else {
			curl_setopt( $this->ch, CURLOPT_CUSTOMREQUEST, $method);
		}
		curl_setopt( $this->ch, CURLOPT_URL, "http://$this->host:$this->port/api/0.5$path" );
		$content = curl_exec( $this->ch );
		$response = curl_getinfo( $this->ch );
		//curl_close ( $this->ch );
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
	function presence($uce, $credential, $auto_event = FALSE) {
		$this->ucengine = $uce;
		list($status, $response) = $uce->request('POST', '/presence/', array(
				'name'               => $this->name,
				'credential'         => $credential,
				'metadata[nickname]' => $this->name
			));
		if($status == 201) {
			$this->uid = $response->result->uid;
			$this->sid = $response->result->sid;
			if($auto_event) {
				$this->start_event_loop();
			}
		}
	}
	function start_event_loop($target = '', $type = NULL, $start = NULL, $search = NULL, $parent = NULL) {
		$args = array(
			'uid' => $this->uid,
			'sid' => $this->sid,
			'_async' => 'lp'
			);
		if($type != NULL) {
			$args['type'] = $type;
		}
		if($search != NULL) {
			$args['search'] = $search;
		}
		if($parent != NULL) {
			$args['parent'] = $parent;
		}
		if($start != NULL) {
			$args['start'] = $start;
		}
		if($target == '') {
			$url = '/event?';
		} else {
			$url = "/event/$target?";
		}
		async_call('event_listener', array($url . http_build_query($args), $this->uid));
	}
}

if(sizeof($argv) > 1 && $argv[1] == '--uce') {
	$_UCE = new UCEngine('localhost', 5280);
	$victor = new User('victor.goya@af83.com');
	$victor->presence($_UCE, 'pwd', TRUE);
	var_dump($victor->uid);
}