<?php

class User {
	const DIFFERENT_PASS = 2;
	const USER_EXISTS = 3;

	private $user;

	private function __construct ($data) { // $data => object or array
		$this->user = new StackArray;

		foreach ($data as $key => $val)
			$this->user[$key] = $val;

		$this->refcount = 1;
	}

	protected static function saveUserSession ($user) {
		$_SESSION["id"] = $user->id;
		$user->session_id = session_id();
		db::insert("sessions", (array)$user);
	}

	public static function login ($name, $pass) {
		$user = db::select(false, "users", db::c(["name"])->eq($name))->fetch_object();

		if (password_verify($pass, $user->pass)) {
			self::saveUserSession($user);
			return new self($user);
		}

		return false;
	}

	public static function register ($name, $pass, $pass_repeat) {
		if ($pass !== $pass_repeat)
			return self::DIFFERENT_PASS;

		if (self::getIdByUsername($name) !== false)
			return self::USER_EXISTS;

		db::insert("users", ["id" => NULL, "pass" => password_hash($pass, PASSWORD_BCRYPT), "name" => $name, "rand" => mt_rand(0, 9999)]);
		$user = db::select(false, "users", db::c(["name"])->eq($name))->fetch_object();
		self::saveUserSession($user);
		return new self($user);
	}

	public static function createNewUserFromId ($id) {
		$result = db::select(false, "users", db::c(["id"])->eq($id))->fetch_object();

		if (!$result)
			return false;

		return new self($result);
	}

	public static function createNewUserFromName ($name) {
		$result = db::select(false, "users", db::c(["name"])->eq($name))->fetch_object();

		if (!$result)
			return false;

		return new self($result);
	}

	public static function getUserIdBySession ($session) {
		$result = db::select(["id"], "sessions", db::c(["session_id"])->eq($session))->fetch_object();

		if ($result)
			return $result->id;
		else
			return false;
	}

	private static function getIdByUsername ($name) {
		$q = db::select("id", "users", db::c("name")->eq($name));
		return $q->num_rows < 1?false:$q->fetch_object()->id;
	}

	public function __get ($variable) {
		if (isset($this->user[$variable]))
			return $this->user[$variable];

		debug_print_backtrace();
		trigger_error("Trying to access undefined User-Variable"); // default parameter: E_USER_NOTICE
		// implicit return NULL;
	}

	public function __isset ($variable) {
		return isset($this->user[$variable]);
	}

	public function __set ($var, $val) {
		$this->user[$var] = $val;
	}
}
