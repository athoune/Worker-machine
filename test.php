<?php
require 'lib/worker.php';

//$w = new Worker();

function test($time, $text) {
	sleep($time);
	echo "$text\n";
}

if($argv[1] == '--async') {
	for($i=0; $i < 10; $i++) {
		async_call('test', array(3, "Hello world $i"));
	}
}