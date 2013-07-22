<?php

// RFC 2616

const OPTION_HEADER = "HTTP/1.1 200 OK\r\nContent-Type: text/html\r\nAccess-Control-Allow-Credentials: true\r\nAccess-Control-Allow-Methods: POST, OPTIONS\r\nAccess-Control-Allow-Origin: http://mmorpg.galaxycore.de\r\nAccess-Control-Allow-Headers: X-POLL\r\nAccess-Control-Max-Age: 2592000\r\nConnection: Keep-Alive\r\n";

trait Poll {
	public $pollSendStack = [];

	public function PollInput ($user, &$buffer) {
		$frames = [];

		while ($len = $this->PollExpectedLength($buffer, $data)) {
			if (!($data = @http_parse_message($buffer)))
				continue;

			$buffer = substr($buffer, $len);

			if ($data->requestMethod == "OPTIONS" || $data->requestMethod == "HEAD") {
				$this->emit($user, OPTION_HEADER."Content-Length: 0\r\n\r\n");
				continue;
			}

			parse_str($data->body, $opt);

			if (isset($data->headers["Cookie"]) && isset($data->headers["X-POLL"])?($data->headers["X-POLL"] == "true"):(isset($data->headers["X-Poll"]) && $data->headers["X-Poll"] == "true"))
				$this->createUserByCookieString($user, $data->headers["Cookie"]);
			else {
				$this->disconnect($user);
				return [];
			}

			$user->createTime = time();

			if (!isset($opt["on"]) && !isset($opt["data"]))
				break;
			else
				$frames[] = "{$opt['on']}\0{$opt['data']}"; // post-data
		}

		$user->waiting_for_answer = true;

		return $frames;
	}

	public function PollExpectedLength ($buffer, &$data = null) {
		// enough data?
		if (strlen($buffer) < 40 || !($data = @http_parse_message($buffer)))
			return 0;

		if (isset($data->headers["Content-Length"]) && ($pos = strpos($buffer, "\r\n\r\n")) !== false)
			return $pos + 4 + $data->headers["Content-Length"];
		elseif (strpos($buffer, "\r\n\r\n") === false)
			return 0;
		else
			return strlen($buffer); // message okay!
	}

	public function PollSendTo ($user_id, $msg) {
		foreach ($this->users as $user)
			if ($user->user_id == $user_id && $user->connection_type == "Poll" && !isset($used_ids[$user->session_id])) {
				$used_ids[$user->session_id] = true;
				$this->pollSendStack[$user->session_id][] = $msg;
			}
	}

	public function sendPolls ($users) {
		$stack = $this->pollSendStack;
		$this->pollSendStack = [];

		if (is_null($stack) || empty($stack))
			return;

		$maxTime = time() - POLL_MAX_ALIVE_TIME;

		foreach ($users as $user) {
			if (!$user->waiting_for_answer)
				continue;

			if ($noNulByte = isset($stack[$user->session_id]) && ($len = strlen($data = implode("\0", $stack[$user->session_id]))) !== 0) {
				unset($stack[$user->session_id]);
				$user->waiting_for_answer = false;
				$this->emit($user, OPTION_HEADER."Content-Length: $len\r\n\r\n$data");
			}

			if ($maxTime > $user->createTime) {
				if (!$noNulByte)
					$this->emit($user, OPTION_HEADER."Content-Length: 1\r\n\r\n\0");
				$this->disconnect($user);
			}
		}
		$this->pollSendStack = array_merge($stack, $this->pollSendStack);
	}

	public abstract function disconnect ($user);

	public abstract function emit ($user, $data);

	abstract public function createUserByCookieString ($user, $cookie);
}
