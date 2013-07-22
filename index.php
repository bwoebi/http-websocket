<?php

require 'includes/constants.php';

?><!DOCTYPE html>
<html>
	<head>
		<script type="text/javascript" src="//code.jquery.com/jquery-latest.min.js"></script>
		<script type="text/javascript" src="js/socket.js"></script>
		<script type="text/javascript">
			socket = new socketPollHandler("<?=DAEMON_HTTP_ADDR_LISTEN?>", "<?=DAEMON_PORT_LISTEN?>");
			socket.start();
		</script>
	</head>
	<body>
	</body>
</html>
