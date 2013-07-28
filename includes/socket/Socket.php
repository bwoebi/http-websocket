<?php

abstract class Socket {
	public $master;
	public $sockets;
	public $users;

	function Socket ($address, $port) {
		$this->master = socket_create(AF_INET, SOCK_STREAM, SOL_TCP) or kill(trigger_error("socket_create() failed", E_USER_ERROR));
		socket_set_option($this->master, SOL_SOCKET, SO_REUSEADDR, true) or kill(trigger_error("socket_option() failed", E_USER_ERROR));
		socket_bind($this->master, $address, $port) or kill(trigger_error("socket_bind() failed", E_USER_ERROR));
		socket_listen($this->master, 20) or kill(trigger_error("socket_listen() failed", E_USER_ERROR));
		socket_set_nonblock($this->master);
		$this->sockets[0] = $this->master;
		$this->users = $this->handlers = [];
		print "Server Started : ".date('Y-m-d H:i:s')."\n";
		print "Listening on : $address port $port\n";
		print "Master socket : {$this->master}\n\n";
	}

	public function listen () { // => while (1)
		$changed = $this->sockets;
		$write = NULL;
		$except = NULL;
		socket_select($changed, $write, $except, 0, MAX_SOCKET_SLEEP_TIME * 1000); // don't block php!
		foreach ($changed as $socket) {
			if ($socket == $this->master) {
				$client = socket_accept($this->master);
				if ($client < 0) {
					continue;
				}
				$this->connectSocket($client);
			} else {
				$user = $this->getUserBySocket($socket);
				if (@socket_recv($socket, $tmpbuf, DAEMON_SOCKET_BYTES, MSG_DONTWAIT)) {
					$user->buffer .= $tmpbuf;
					if ($user->maxBufSize === 0)
						$user->maxBufSize = $this->{($user->connection_type?:"Poll")."ExpectedLength"}($user->buffer);
					if ($user->maxBufSize <= $oldBufSize = strlen($user->buffer)) {
						$this->inputHandler($user, $user->buffer);
						$user->maxBufSize = 0;
					}
				} else
					$this->disconnect($user);
			}
		}
	}

	public function connectSocket ($socket) {
		$user = new SocketUser;
		$user->id = uniqid();
		$user->connection_type = false;
		$user->socket = $socket;
		$user->createTime = time();
		$this->users[$user->id] = $user;
		$this->sockets[$user->id] = $socket;
	}

	public function disconnectSocket ($user) {
		unset($this->users[$user->id]);
		socket_close($user->socket);
		unset($this->sockets[$user->id]);
	}

	public abstract function disconnect ($user);

	public function getUserBySocket ($socket) {
		$ret = NULL;
		foreach ($this->users as $user)
			if($user->socket == $socket) {
				$ret = $user;
				break;
			}
		return $ret;
	}

	abstract public function inputHandler ($user, &$buffer);

	public function emit ($user, $data) {
		print "> @".$user->user_id." ".$data."\n";
		$len = strlen($data);

		while (true) {
			$sent = @socket_write($user->socket, $data, $len);

			if ($sent <= 0)
				break;

			if ($sent < $len) {
				$msg = substr($data, $sent);
				$len -= $sent;
			} else
				break;
		}
	}
}

class SocketUser {
	public $id;
	public $user_id;
	public $session_id;
	public $socket;
	public $buffer;
	public $maxBufSize;
	public $handshake = false;
	public $connection_type;
	public $createTime;
	public $waiting_for_answer = false;
}
