<?php

class WebSocketThread extends Kontext {
	public function main () {
		SequentialParallelWorker::spawn($this, SEQUENTIAL_PARALLEL_WORKERS);
		$wsp = new WebSocketPoll(DAEMON_ADDR_LISTEN, DAEMON_PORT_LISTEN);

		foreach ($this->data->handlers as $on => $handler)
			$wsp->addHandler($on, $handler);

		$wsp->data = $this->data;
		$i = 0;

		while (true) {
			$wsp->listen();
			if ($size = count($stack = $this->data->wspStack))
			{
				// I hate segmentation faults: http://github.com/krakjoe/pthreads/issues/145
				//$stack = $this->data->wspStack->chunk($size);
				$this->data->wspStack = [];
				foreach ($stack as list($userid, $on, $data)) {
					if ($userid === -2) // broadcasting
						foreach ($wsp->users as $user)
							$wsp->sendToClient($user, $on, $data);
					else // single user
						$wsp->sendTo($userid, $on, $data);
				}
			}
			if ($i % POLL_SLEEP_TIME < MAX_SOCKET_SLEEP_TIME) {
				$wsp->sendPolls($wsp->users);
				$i = 0;
			}
			usleep(MAX_SOCKET_SLEEP_TIME * 1000);
			$i += MAX_SOCKET_SLEEP_TIME;
		}
	}
}
