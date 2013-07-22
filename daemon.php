<?php

require __DIR__.'/includes/constants.php';

require __DIR__.'/includes/threads/pthreads.php';
require __DIR__.'/includes/threads/DaemonData.php';
require __DIR__.'/includes/threads/SequentialParallelWorker.php';
require __DIR__.'/includes/threads/WebSocketThread.php';

require __DIR__.'/includes/socket/WebSocketPoll.php';

require __DIR__.'/includes/sql.php';
require __DIR__.'/includes/User.php';

include 'includes/handlers.php';

define('DAEMON_PID', posix_getpid());

function kill () { posix_kill(DAEMON_PID, SIGKILL); } // problem is that die() may cause segfaults...

@cli_set_process_title(PROC_TITLE); // creates sometimes a warning even when it works...

sql::init();

$stack = new DaemonData($handlers);

$threadlist = [
                "WebSocketThread",
];

foreach ($threadlist as $thread) {
	$threads[$thread] = new $thread($stack);
	$threads[$thread]->start();
}

// just sleep... (or do another thing like reacting at php://stdin input etc.)
while (1) {
	sleep(600);
}
