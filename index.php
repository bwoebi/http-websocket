<?php

include "includes/User.php";

?><!DOCTYPE html>
<html>
	<head>
		<title>Http-WebSocket</title>
	</head>
	<body>
		<form action="general.php" method="post">
			<h3>Sign in</h3>
			Name: <input type="text" name="name" /><br />
			Password: <input type="text" name="pass" /><br />
			<input type="submit" value="Login" />
		</form>
		<form action="general.php" method="post">
			<h3>Sign up</h3>
			Name: <input type="text" name="name" /><br />
			Password: <input type="text" name="pass" /><br />
			Repeat Password: <input type="text" name="pass_repeat" /><br />
			<input type="submit" value="Submit" />
		</form>
	</body>
</html>
