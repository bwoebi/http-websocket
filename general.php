<?php

// Uncomment to enable function call tracing
// declare(ticks=1);
register_tick_function(function(){
	static $func;
	$bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
	if (count($bt) > 1)
		if (((string)$func) != ($func = @$bt[1]["class"].@$bt[1]["type"].@$bt[1]["function"])) {
			$file = isset($bt[0]["file"], $bt[0]["line"])?$bt[0]["file"].":".$bt[0]["line"]:"";
			print "BT: ".$func." ($file)\n";
	}
});

define('BASE_PATH', __DIR__);

require BASE_PATH.'/includes/constants.php';
require BASE_PATH.'/includes/db.php';
require BASE_PATH.'/includes/threads/pthreads.php';
require BASE_PATH.'/includes/User.php';

session_start();

if (isset($_POST["name"], $_POST["pass"])) {
	if (isset($_POST["pass_repeat"])) {
		if (!($user = User::register($_POST["name"], $_POST["pass"], $_POST["pass_repeat"])) instanceof User) {
			header("Location: index.php?register=failure&user=".$_POST["name"]."&failureCode=".$user);
			exit;
		}
	} else {
		if (!($user = User::login($_POST["name"], $_POST["pass"])) instanceof User) {
			header("Location: index.php?login=failure&user=".$_POST["name"]);
			exit;
		}
	}
} elseif (!isset($_SESSION["id"])) {
	header("Location: index.php");
	exit;
} else
	$user = User::createNewUserFromId($_SESSION["id"]);

?><!DOCTYPE html>
<html>
	<head>
		<script type="text/javascript" src="//code.jquery.com/jquery-latest.min.js"></script>
		<script type="text/javascript" src="js/socket.js"></script>
		<script type="text/javascript">
			socket = new socketPollHandler("<?=DAEMON_HTTP_ADDR_LISTEN?>", "<?=DAEMON_PORT_LISTEN?>");
			socket.start();
		</script>
		<script type="text/javascript" src="js/js.js"></script>
		<title>Http-WebSocket</title>
	</head>
	<body>
		<input id="randomBegButton" type="submit" value="give me my unique random number!" /><span id="randNumber"></span>
	</body>
</html>
