<?php

class User {
	private $user;

	private function __construct ($data) { // $data => object or array
		$this->user = new StackArray;

		foreach ($data as $key => $val)
			$this->user[$key] = $val;

		$this->refcount = 1;
	}

	public static function createNewUserFromId ($id) {
		$result = sql::query("SELECT * FROM users WHERE id=".(int)$id)->fetch_object();

		if (!$result)
			return false;

		return new self($result);
	}

	public static function getUserIdBySession ($session) {
		$result = sql::query("SELECT id FROM sessions WHERE session_id='".sql::esc($session)."'")->fetch_object();

		if ($result)
			return $result->id;
		else
			return false;
	}

	public function __get ($variable) {
		if (isset($this->user[$variable]))
			return $this->user[$variable];

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
