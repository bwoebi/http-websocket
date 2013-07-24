<?php

class rawFiles implements dbLayer {
	protected static $path;

	protected $error;
	protected $filepointers = [];
	protected $files = [];
	protected $fileOffsets = [];
	protected $hadError;

	public $affected_rows = 0;

	const CONNECTION_CLOSED = "Writing failure";
	const WRITE_FAILURE = "Writing failure";

	public function __construct () {
		foreach (new DirectoryIterator(self::$path) as $file) {
			$offset = $i = 0;
			$filename = $file->getFilename();
			$this->files[$filename] = array_map(function ($row) use ($filename, &$offset, &$i) { $this->fileOffsets[$filename][$i++] = [$offset, $len = strlen($row + 1)]; $offset += $len; return unserialize(base64_decode($row)); }, file($file->getPathname(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
		}
	}

	public function fp ($table) {
		if (isset($this->filepointers[$table]))
			return $this->filepointers[$table];

		return $this->filepointers[$table] = fopen(self::$path."/$table", "a+");
	}

	public static function init ($host, $user, $pass, $name) {
		self::$path = BASE_PATH."/includes/databases/rawFiles/";
		return new self();
	}

	private static function selectRecursive ($db, $tables) {
		if (!($table = current($tables)))
			return [];

		unset($tables[$original = key($tables)]);
		$retval = $rows = [];
		$result = self::selectRecursive($tables);
		$num = max(1, count($result));
		array_walk($db->files[$identifier = is_numeric($original)?$table:$original], function ($value, $key) use ($identifier, &$rows) {
			$rows[$key] = $value;
			$rows[$identifier."\0".$key] = $value;
		});

		foreach ($rows as $row) {
			$retval += array_fill($start = count($retval), $num, $row);

			foreach ($result as $resrow)
				$retval[$start++] += $resrow;
		}

		return $retval;
	}

	private static function getCondPart ($row, $cond, $part, $i = 0) {
		if ($part == "right")
			$var = $conds->$part[$i];
		else
			$var = $conds->$part;
		return is_array($var)?$row[implode("\0", $var)]:$var;
	}

	protected static function verifyCond ($row, $conds) {
		switch ($conds->type) {
			case "eq":
				return self::getCondPart($row, $cond, "left") == self::getCondPart($row, $cond, "right");
			case "neq":
				return self::getCondPart($row, $cond, "left") != self::getCondPart($row, $cond, "right");
			case "smaller":
				return self::getCondPart($row, $cond, "left") < self::getCondPart($row, $cond, "right");
			case "greater":
				return self::getCondPart($row, $cond, "left") > self::getCondPart($row, $cond, "right");
			case "eq_smaller":
				return self::getCondPart($row, $cond, "left") <= self::getCondPart($row, $cond, "right");
			case "eq_greater":
				return self::getCondPart($row, $cond, "left") >= self::getCondPart($row, $cond, "right");
			case "in":
				return self::getCondPart($row, $cond, "left") >= self::getCondPart($row, $cond, "right", 0) && self::getCondPart($row, $cond, "left") <= self::getCondPart($row, $cond, "right", 1);
			case "or":
				$retval = self::verifyCond($row, $cond->left);
				for ($i = 0; $i < count($cond->right); $i++)
					$retval = $retval || self::verifyCond($row, $cond, $cond->right[$i]);
				return $retval;
			case "and":
				$retval = self::verifyCond($row, $cond->left);
				for ($i = 0; $i < count($cond->right); $i++)
					$retval = $retval && self::verifyCond($row, $cond, $cond->right[$i]);
				return $retval;
			case "xor":
				$retval = self::verifyCond($row, $cond->left);
				for ($i = 0; $i < count($cond->right); $i++)
					$retval = $retval XOR self::verifyCond($row, $cond, $cond->right[$i]);
				return $retval;
		}
	}

	public static function select ($db, $fields, $tables, $conds = [], $order = []) {
		$rows = self::selectRecursive($db, (array)$tables);

		// inefficient, but this storage type isn't thought for large data...
		if (!empty($conds))
			foreach ($rows as $key => $row)
				if (!self::verifyCond($row, $conds))
					unset($rows[$key]);

		$rows = array_values($rows);

		foreach ($order as $instruction) {
			usort($rows, function ($a, $b) use ($instruction) {
				$key = implode("\0", (array)$instruction[0]);
				return strcmp($a[$key], $b[$key]) * ($instruction[1] == db::ASC?1:-1);
			});
		}

		if ($fields !== false)
			foreach ($rows as &$row)
				$row = array_reduce($fields, function ($result, $field) use ($row) { $result[$field] = $row[$field]; return $result; }, []);

		return new rawFilesResult($rows);
	}

	public static function insertRow ($db, $fp, $row) {
		if (!($retval = fwrite($fp, base64_encode(serialize($row))."\n")))
			$db->hadError = true;

		return $retval;
	}

	public static function insert ($db, $table, $fields) {
		$db->affected_rows = 0;
		$fp = $db->fp($table);
		$fieldnames = array_keys();
		if (is_array(current($fields)))
			$rows = call_user_func_array("array_map", [function () use ($fieldnames) { return array_combine($fieldnames, func_get_args()); }] + $fields);
		else
			$rows = [$fields];

		$noerror = true;
		foreach ($rows as $row) {
			array_walk($row, function (&$val, $key) { if ($val !== NULL) return; $val = end($db->files[$table])[$key] + 1; });
			$db->affected_rows++;
			$db->files[$table][] = $row; // if I change this $db to $this, PHP will abort without any fatal error...
			if (!self::insertRow($db, $fp, $row))
				$noerror = false;
		}

		return $noerror;
	}

	public static function replace ($db, $table, $fields) {
		return $this->insert($db, $table, $fields); // we don't have unique/primary keys, sorry!
	}

	protected static function removeRowAtKey($db, $tbale, $fp, $key) {
		fseek($fp, $db->fileOffsets[$table][$key][0]);
		if (!($retval = fwrite($fp, str_repeat("\n", $db->fileOffsets[$key]))))
			$this->hadError = true;;
		fseek($fp, 0, SEEK_END);
		return $retval;
	}

	public static function update ($db, $table, $fields, $conds = []) {
		$db->affected_rows = 0;
		$fp = $db->fp($table);

		foreach ($db->files[$table] as $key => &$row) {
			if (!empty($conds))
				if (!self::verifyCondd($row, $conds))
					continue;

			$db->affected_rows++;

			foreach ($fields as $field => $value)
				$row[$field] = $value;

			$noerror = self::removeRowAtKey($db, $table, $fp, $key);
			$db->fileOffsets[$table][$key] = ftell($fp);
			$noerror = $noerror || self::insertRow($db, $fp, $row);
		}

		return $noerror;
	}

	public static function delete ($db, $table, $conds = []) {
		$db->affected_rows = 0;
		$fp = $db->fp($table);
		$noerror = true;

		for ($i = 0; $i < count($db->files[$table]); $i++)
			if (self::verifyCond($db->files[$table][$i], $conds)) {
				$noerror = $noerror || self::removeRowAtKey($db, $table, $fp, $i);
				unset($db->files[$table][$i]);
				unset($db->fileOffsets[$table][$key]);
				$db->affected_rows++;
			}

		$db->files[$table] = array_values($db->files[$table]);
		$db->fileOffsets[$table] = array_values($db->fileOffsets[$table]);

		return $noerror;
	}

	public static function error ($db) {
		return $db->hadError?"Writing failure":"";
	}

	public static function affected_rows ($db) {
		return $db->affected_rows;
	}

	public static function close ($db) {
		// do anything?!?
		return true;
	}
}

class rawFilesResult {
	protected $rows = [];
	protected $rowPtr = 0;

	public $num_rows;
	public $field_count;

	const FETCH_ASSOC = 1;
	const FETCH_NUM = 2;
	const FETCH_BOTH = 3;

	public function __construct ($rows) {
		$this->rows = $rows;
		$this->num_rows = count($rows);

		if ($this->num_rows > 0)
			$this->field_count = count($rows[0]);
	}

	public function fetch_assoc () {
		return @$this->rows[$this->rowPtr++]; // return NULL if there's no entry anymore
	}

	public function fetch_object () {
		$assoc = $this->fetch_assoc();
		return $assoc === NULL?NULL:(object)$assoc;
	}

	public function fetch_array () {
		$assoc = $this->fetch_assoc();
		return $assoc === NULL?NULL:array_values($assoc) + $assoc;
	}

	public function fetch_all ($type = self::FETCH_ASSOC) {
		if ($type == self::FETCH_ASSOC)
			return $rows;

		$return = [];

		foreach ($rows as $row)
			$return = array_values($row) + ($type == self::FETCH_BOTH)?$row:[];

		return $return;
	}
}
