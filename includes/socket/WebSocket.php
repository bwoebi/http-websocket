<?php	

/*
 Usage: $socket = new WebSocket($host, $port);
 $socket->addHandler($onEvent, function () { });
 while (1)
	 $socket->socketListen();

 (A good idea is to use pthreads in a daemon)
*/

// RFC 6455

trait WebSocket {
	public $debug = false;

	public function isHandshake ($user, $headers) {
		return !$user->handshake && isset($headers->headers["Connection"]) && substr($headers->headers["Connection"], -7) == "Upgrade" && isset($headers->headers["Upgrade"]) && $headers->headers["Upgrade"] == "websocket"; 
	}

	public function WebSocketInput ($user, &$buffer) {
		return $this->unwrap($user, $buffer);
	}

	public function WebSocketSendTo ($user_id, $msg) {
		foreach ($this->users as $user)
			if ($user->user_id == $user_id && $user->connection_type == "WebSocket")
				$this->emit($user, $this->wrap($msg));
	}

	public abstract function emit ($user, $data);

	public function doHandshake ($user, $buffer) {
		list($resource, $host, $cookie, $origin, $secKey) = $this->getHeaders($buffer);
		$upgrade =	"HTTP/1.1 101 Switching Protocols\r\n".
					"Upgrade: WebSocket\r\n".
					"Connection: Upgrade\r\n".
					"Sec-WebSocket-Origin: $origin\r\n".
					"Sec-WebSocket-Location: ws://$host$resource\r\n".
					"Sec-WebSocket-Accept: ".$this->calcKey($secKey)."\r\n".
					"\r\n";
		socket_write($user->socket, $upgrade, strlen($upgrade) + 1);
		$this->createUserByCookieString($user, $cookie);
		$this->waitingFrames[$user->id] = "";
		return true;
	}

	public abstract function createUserByCookieString ($user, $cookie);

	public function calcKey ($secKey) {
		return base64_encode(sha1($secKey."258EAFA5-E914-47DA-95CA-C5AB0DC85B11", true));
	}

	public function getHeaders ($req) {
		$URL = preg_match("/GET (.*) HTTP/", $req, $match)?$match[1]:NULL;
		$host = preg_match("/Host: (.*)\r\n/", $req, $match)?$match[1]:NULL;
		$cookies = preg_match("/Cookie: (.*)\r\n/", $req, $match)?$match[1]:NULL;
		$origin = preg_match("/Origin: (.*)\r\n/", $req, $match)?$match[1]:NULL;
		$secKey = preg_match("/Sec-WebSocket-Key: (.*)\r\n/", $req, $match)?$match[1]:NULL;
		return [$URL, $host, $cookies, $origin, $secKey];
	}

	public function wrap ($msg = "") {
		$len = strlen($msg);

		$firstByte = 1; // is text frame

		/* frames are:

0x00: this frame continues the payload from the last.
0x01: this frame includes utf-8 text data.
0x02: this frame includes binary data.
0x08: this frame terminates the connection.
0x09: this frame is a ping.
0x10: this frame is a pong.

		*/

		$firstByte += 128; // 128: is last frame (always yes as we won't send frames with more than 8 Exabytes...)

		$raw = chr($firstByte);

		if ($len <= 125) {
			$secondByte = $len;

			$raw .= chr($secondByte);
		} else if ($len <= 1 << 16) {
			$secondByte = 126;

			$raw .= chr($secondByte).pack("n", $len);
		} else {
			$secondByte = 127;

			$raw .= chr($secondByte);
			$raw .= pack("N", $len >> 32);
			$raw .= pack("N", $len % (1 << 32));
		}

		if ($raw)
			$raw .= $msg;

		return $raw; // no mask
	}

	private $waitingFrames = [];

	public function unwrap ($user, &$raw) {
		$frame = 0;
		do {
			$actualLen = 0;
			$frames[$frame] = "";
			do {
				list($firstByte, $secondByte) = str_split($first2Bytes = substr($raw, 0, 2));

				$firstByte = ord($firstByte);
				$secondByte = ord($secondByte);

				$fin = $this->bit($firstByte, 7);

				/*
				// Not useful bits, reading them, would only cost time

				$reserved1 = $this->bit($firstByte, 6);
				$reserved2 = $this->bit($firstByte, 5);
				$reserved3 = $this->bit($firstByte, 4);
				*/

				$opcode = $firstByte % 16; // we won't use this and every time assume it's an binary or text frame
				$mask = $this->bit($secondByte, 7);

				if (($len = $this->WebSocketExpectedLength($raw)) > strlen($raw) || !$len) {
					$raw = $first2Bytes.$raw;
					break 2;
				}

				if ($opcode != 0x0 && $opcode != 0x1 && $opcode != 0x2) {
					if ($opcode == 0x8) {
						$this->disconnect($user);
						break 2;
					} else if ($opcode == 0x9) {
						$this->emit($user, "\xFA".substr($raw, 1, $len /* payload */ + ($mask?4:0) + 1 /* length information byte */));
						continue 2;
					} else {
						break 2;
					}
				}

				if ($len < 126)
					$raw = substr($raw, 2);
				elseif ($len < 65536)
					$raw = substr($raw, 4);
				else
					$raw = substr($raw, 10);

				if ($mask) {
					$key = substr($raw, 0, 4);
					$raw = substr($raw, 4);
				}

				$lenDiff = min($len - $actualLen, strlen($raw));
				if ($lenDiff < strlen($raw)) {
					$data = substr($raw, 0, $lenDiff);
					$raw = substr($raw, $lenDiff);
				} else {
					$data = $raw;
					$raw = '';
				}

				if ($fin) {
					if ($mask)
						$frames[$frame] .= $this->waitingFrames[$user->id].$this->mask($data, $key, $actualLen);
					else
						$frames[$frame] .= $this->waitingFrames[$user->id].$data;
					$this->waitingFrames[$user->id] = "";
				} else {
					if ($mask)
						$this->waitingFrames[$user->id] .= $this->mask($data, $key, $actualLen);
					else
						$this->waitingFrames[$user->id] .= $data;
				}

				$actualLen += $lenDiff;
			} while (!$fin);
			$frame++;
		} while (!empty($raw));

		return $frames;
	}

	public function WebSocketExpectedLength ($buffer) {
		if (strlen($buffer) < 2)
			return 0;

		$len = ord($buffer[1]) % 128;

		if ($len == 126) {
			$arr = unpack("nfirst", $buffer);
			$len = array_pop($arr);
		} elseif ($len == 127) {
			list( , $upper, $lower) = unpack('N2', $buffer);
			$len = ($lower + ($upper << 32));
		}
		return $len;
	}

	public function mask ($data, $key, $offset = 0) {
		$ret = "";
		$len = strlen($data);
		for ($i = 0; $i < $len; $i++)
			$ret .= chr(ord($data[$i]) ^ ord($key[($i + $offset) % 4]));
		return $ret;
	}

	public function bit ($char, $place) {
		return (bool)($char & (1 << $place));
	}

	public abstract function disconnect ($user);
}
