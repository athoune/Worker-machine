<?php
require 'Predis.php';
class Worker {
	function __construct() {
		$this->redis = new Predis_Client();
	}
}