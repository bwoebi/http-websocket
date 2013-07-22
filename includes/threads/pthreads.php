<?php

abstract class Kontext extends Thread {
	public $data;
	public $spw;

	public function __construct (DaemonData $stack) {
		$this->data = $stack;
	}

	public final function run () {
		$this->data->init($this);
		$this->main();
	}

	abstract public function main();
}

abstract class Stack extends Stackable {
	abstract public function init ($class);

	public function run () {}
}

class StackArray extends Stackable {
	public function run () {}
}

function getStack () {
	if (!(($obj = DaemonData::$instance) instanceof Thread))
		global $stack;
	else
		$stack = $obj->data;

	return $stack;
}

function getThread () {
	if (($thread = DaemonData::$instance) instanceof Thread)
		return $thread;

	return null;
}
