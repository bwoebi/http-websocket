<?php

class DaemonData extends Stack { // The store of global data (there MUST NOT be ANY resource in this object, otherwise: probable segfault!)
	public $handlers;
	public $Users;
	public $wspStack;

	public static $instance;

	public function __construct ($handlers) {
		$this->handlers = $handlers;
		$this->wspStack = new StackArray;
		$this->Users = new StackArray;
	}

	public function sendTo ($userid, $on, $data) {
		$this->wspStack[] = [$userid, $on, $data];
	}

	public function broadcast ($on, $data) {
		$this->sendTo(-2, $on, $data);
	}

	public function init ($class) {
		self::$instance = $class;
	}
}
