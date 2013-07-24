<?php

require 'Socket.php';
require 'WebSocket.php';
require 'Poll.php';

class WebSocketPoll extends Socket {
	public $data;
	public $actual_user;
	protected $handlers = [];

	use WebSocket, Poll;

	function __construct ($addr, $port) {
		$this->Socket($addr, $port);
	}

	public function sendTo ($user_id, $on, $msg) {
		$data = "$on\0".json_encode($msg);
		$this->PollSendTo($user_id, $data);
		$this->WebSocketSendTo($user_id, $data);
	}

	public function addHandler ($on, $callable) {
		$this->handlers[$on][] = $callable;
	}

	public function inputHandler ($user, &$buffer) {
		if ($user->connection_type)
			$input = $this->{$user->connection_type."Input"}($user, $buffer);
		elseif (($contents = @http_parse_message($buffer)) && isset($contents->httpVersion) && $contents->httpVersion == 1.1) { // is http header (I don't like http 1.0)
			if ($this->isHandshake($user, $contents)) {
				$user->connection_type = "WebSocket";
				$this->doHandshake($user, $buffer);
				$buffer = "";
				return;
			} elseif (!empty($input = $this->PollInput($user, $buffer))) {
				$user->connection_type = "Poll";
			}
		} else {
			$this->emit($user, "HTTP/1.1 500 Internal Server Error\r\n\r\n");
			$this->disconnect($user);
			return;
		}

		if (!empty($input))
			foreach ($input as $data)
				$this->processHandlers($user->user_id, $data);
	}

	public function disconnect ($user) {
		if (!is_null($user->user_id)) {
			$usrPtr = $this->data->Users[$user->user_id];
			if (($usrPtr->refcount -= 1) === 0) // => user has logged out / was logged out
				unset($this->data->Users[$user->user_id]);
		}

		$this->disconnectSocket($user);
	}

	public function processHandlers ($user_id, $msg) {
		print "! @{$user_id} $msg\n";
		$pos = strpos($msg, "\0");
		$this->actual_user = $user_id;

		if (!isset($this->handlers[$event = substr($msg, 0, $pos)]))
			return;

		foreach ($this->handlers[$event] as $handler)
			SequentialParallelWorker::exec($handler, $this->data->Users[$user_id], json_decode(substr($msg, $pos + 1)), $this->data);
	}

	public function createUserByCookieString ($user, $cookie) {
		if ($cookie != null && !empty($cookies = http_parse_cookie($cookie)) && isset($cookies->cookies[session_name()])) {
			switch (($id = User::getUserIdBySession($cookies->cookies[session_name()])) !== false) {
				case true:
					if (isset($this->data->Users[$id])) {
						$this->data->Users[$id]->refcount += 1;
						break;
					} else {
						if ($newUser = User::createNewUserFromId($id)) {
							$this->data->Users[$id] = $newUser;
							break;
						} // else: fall-through
					}
				case false:
					$this->disconnect($user);
					return;
			}

			$user->user_id = $id;
			$user->session_id = $cookies->cookies[session_name()];
		}
	}
}
