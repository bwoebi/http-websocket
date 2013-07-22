<?php

class sql {
	public static $sql;
	private static $errors = true;
	
	public static function init () {
		self::$sql = new mysqli(SQL_HOST, SQL_USER, SQL_PASS, SQL_NAME);
		self::query("SET CHARACTER SET 'utf8'");
		self::query("SET collation_connection = 'utf8_general_ci'");
	}
	
	public static function __callStatic ($func, $args) {
		start:
		if (!self::$sql instanceof mysqli)
			self::init();
		$q = @call_user_func_array([self::$sql, $func], $args); // query may fail because of packet loss on unix socket
		if ($q !== false && empty(self::$sql->error))
			return $q;
		if ((isset($php_errormsg) && substr($php_errormsg, 0, 27) == "Error while sending QUERY packet.") || self::$sql->error == "MySQL server has gone away") {
			self::$sql->close();
			self::$sql = NULL; // prevents infinite recursion which ends with an excessive use of memory...
			usleep(1000); // reconnect on error!
			goto start;
		}
		if (!self::$errors)
			return false;

		trigger_error("<b>MySQL:</b> ".self::$sql->error, E_USER_ERROR);
		exit;
	}

	public function setErrorMode($state = NULL) {
		if (is_null($state))
			self::$errors = !self::$errors;
		else
			self::$errors = $state;
	}
	
	public static function esc ($str) { // short for real_escape_string
		return self::real_escape_string($str);
	}
}
