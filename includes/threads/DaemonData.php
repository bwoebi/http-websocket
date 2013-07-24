<?php

class DaemonData extends Stack { // The store of global data (there MUST NOT be ANY resource in this object, otherwise: probable segfault!)
	public $handlers;
	public $Users;
	public $wspStack;

	public static $instance;

	public function __construct ($handlers) {
		$this->handlers = $handlers;
//		$this->wspStack = new StackArray;
		$this->wspStack = [];
		$this->Users = new StackArray;
	}

	public function sendTo ($userid, $on, $data) {
//		$this->wspStack[] = [$userid, $on, $data];
		$this->wspStack = array_merge($this->wspStack, [[$userid, $on, $data]]);
	}

	public function broadcast ($on, $data) {
		$this->sendTo(-2, $on, $data);
	}

	public function init ($class) {
		self::$instance = $class;
		register_tick_function(function(){
			static $func;
			$bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
			if (count($bt) > 1)
				if (((string)$func) != ($func = @$bt[1]["class"].@$bt[1]["type"].@$bt[1]["function"])) {
					$file = isset($bt[0]["file"], $bt[0]["line"])?$bt[0]["file"].":".$bt[0]["line"]:"";
					print "BT: ".$func." ($file)\n";
				}
		});
	}
}
