<?php

// IMPORTANT: Don't forget to SequentialParallelWorker::spawn() before SequentialParallelWorker::exec()'ing in every thread you need it

class SequentialParallelWorker extends Kontext {
	public $stack;
	private $task = NULL;

	public static function spawn (Kontext $thread, $count = 1) {
		if (!isset($thread->spw) || !$thread->spw instanceof SequentialParallelWorkerStack) {
			$thread->spw = new SequentialParallelWorkerStack();
		}
		$data = $thread->data;
		$stack = $thread->spw;
		while ($count-- > 0) {
			$stack->init($worker = new self($data));
			$worker->stack = $stack;
			$worker->start();
		}
	}

	public static function exec (callable $callback) {
		$stack = getThread()->spw;
		$args = func_get_args();
		array_shift($args);

		foreach ($stack->instances as $key => $thread)
			if ($thread->isTerminated()) {
				array_splice($stack->instances, $key, 1);
				self::spawn(getThread());
			} elseif ($thread->isWaiting())
				break;
			else
				unset($thread);

		if (isset($thread)) {
			$thread->task = [$callback, $args];
			$thread->synchronized(function ($thread) {
				$thread->notify();
			}, $thread);
		} else
			$stack->addTask($callback, $args);
	}

	public function main () {
		$this->synchronized(function () {
			do {
				if (count($this->stack->tasks))
					list($callback, $args) = array_shift($this->stack->tasks);
				else {
					$this->wait();
					list($callback, $args) = $this->task;
				}
				call_user_func_array($callback, $args);
			} while (true);
		});
	}
}

class SequentialParallelWorkerStack extends Stack {
	public $instances;
	public $tasks;

	public function __construct() {
		$this->instances = new StackArray;
		$this->tasks = new StackArray;
	}

	public function init ($instance) {
		$this->instances[] = $instance;
	}

	public function addTask (callable $callback, $args) {
		$this->tasks[] = [$callback, $args];
	}

}
