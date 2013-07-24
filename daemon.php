<?php

// Uncomment to enable function call tracing
declare(ticks=1);
register_tick_function(function(){
	static $func;
	$bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
	if (count($bt) > 1)
		if (((string)$func) != ($func = @$bt[1]["class"].@$bt[1]["type"].@$bt[1]["function"])) {
			$file = isset($bt[0]["file"], $bt[0]["line"])?$bt[0]["file"].":".$bt[0]["line"]:"";
			print "BT: ".$func." ($file)\n";
		}
});

define('DAEMON_PID', posix_getpid());

define('BASE_PATH', __DIR__);

require __DIR__.'/includes/constants.php';

require __DIR__.'/includes/threads/pthreads.php';
require __DIR__.'/includes/threads/DaemonData.php';
require __DIR__.'/includes/threads/SequentialParallelWorker.php';
require __DIR__.'/includes/threads/WebSocketThread.php';

require __DIR__.'/includes/socket/WebSocketPoll.php';

require __DIR__.'/includes/db.php';
require __DIR__.'/includes/User.php';

include 'includes/handlers.php';

function kill () { posix_kill(DAEMON_PID, SIGKILL); } // problem is that die() may cause segfaults...

@cli_set_process_title(PROC_TITLE); // creates sometimes a warning even when it works...

db::init();

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
