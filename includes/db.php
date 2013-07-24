<?php

require BASE_PATH."/includes/databases/dbLayer.php";

class db {
	const DESC = 0;
	const ASC = 1;

	public static $sql;
	public static $sqlClass;
	private static $errors = true;

	public static function init () {
		$db = DB_CLASS;
		require_once BASE_PATH."/includes/databases/$db.php";
		self::$sql = $db::init(SQL_HOST, SQL_USER, SQL_PASS, SQL_NAME);
		self::$sqlClass = get_class(self::$sql);
	}

	public static function __callStatic ($func, $args) {
		$db = DB_CLASS;
		start:

		if (!isset(self::$sqlClass) || !self::$sql instanceof self::$sqlClass)
			self::init();

		array_unshift($args, self::$sql);
		$q = @call_user_func_array("$db::$func", $args); // query may fail because of packet loss on unix socket

		if (empty($e = $db::error(self::$sql)) && $q !== false)
			return $q;

		if ((isset($php_errormsg) && substr($php_errormsg, 0, 27) == $db::WRITE_FAILURE) || $e == $db::CONNECTION_CLOSED) {
			$db::close(self::$sql);
			self::$sql = NULL; // prevents infinite recursion which ends with an excessive use of memory...
			usleep(1000); // reconnect on error!
			goto start;
		}

		if (!self::$errors)
			return false;

		trigger_error("<b>Database:</b> ".$e, E_USER_ERROR);
		exit;
	}

	public function setErrorMode($state = NULL) {
		if (is_null($state))
			self::$errors = !self::$errors;
		else
			self::$errors = $state;
	}

	public static function c ($field) { // db::c() condition
		return new condition($field);
	}

	public static function date ($timestamp = NULL) {
		return date("Y-m-d H:i:s", $timestamp === NULL?time():$timestamp);
	}
}

class condition {
	public $left;
	public $right = [];
	public $type;

	// it IS allowed to have multiple condition joining function calls of the SAME type
	const REQUIRES_FIELD_OR_STRING = 0;
	const REQUIRES_COND = 1;
	public static $types = [
		"or" => self::REQUIRES_COND,
		"and" => self::REQUIRES_COND,
		"xor" => self::REQUIRES_COND,
		"eq" => self::REQUIRES_FIELD_OR_STRING,
		"neq" => self::REQUIRES_FIELD_OR_STRING,
		"in" => self::REQUIRES_FIELD_OR_STRING,
		"smaller" => self::REQUIRES_FIELD_OR_STRING,
		"greater" => self::REQUIRES_FIELD_OR_STRING,
		"eq_smaller" => self::REQUIRES_FIELD_OR_STRING,
		"eq_greater" => self::REQUIRES_FIELD_OR_STRING,
	];

	// valid types are: and, or, eq, not, in, smaller, greater
	// fields are always in an array: ["table", "field"] or simply ["field"]

	public function __construct ($field) {
		$this->left = $field;
	}

	// we don't check the type explicitly...
	public function __call ($type, $right) {
		$this->type = $type;
		$this->right = $right;
		return $this;
	}
}
