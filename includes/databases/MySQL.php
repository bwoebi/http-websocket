<?php
 
class MySQL implements dbLayer {
	const CONNECTION_CLOSED = "MySQL server has gone away";
	const WRITE_FAILURE = "Error while sending QUERY packet.";

	const insert = 0;
	const replace = 1;

	private static $condTypes = [
		"eq" => ["="] ,
		"neq" => ["!="],
		"or" => ["OR"],
		"and" => ["AND"],
		"xor" => ["XOR"],
		"in" => ["BETWEEN", "AND"],
		"smaller" => ["<"],
		"greater" => [">"],
		"eq_smaller" => ["<="],
		"eq_greater" => [">="],
	];

	public static function init ($host, $user, $pass, $name) {
		$db = new mysqli($host, $user, $pass, $name);
		$db->query("SET CHARACTER SET 'utf8'");
		$db->query("SET collation_connection = 'utf8_general_ci'");
		return $db;
	}

	protected static function query ($db, $query) {
		print "$query\n";
		return $db->query($query);
	}

	protected static function parseConds ($db, $conds) {
		if (empty($conds))
			return "";

		return " WHERE ".self::parseCondsRecursive($db, $conds);
	}

	private static function parseCondsRecursive ($db, $conds) {
		$i = 0;
		if (condition::$types[$conds->type] == condition::REQUIRES_FIELD_OR_STRING)
			return self::parseCondsPart($db, $conds->left).array_reduce($conds->right, function ($result, $condPart) use ($db, $conds, &$i) { return $result." ".self::$condTypes[$conds->type][$i++]." ".self::parseCondsPart($db, $condPart); }, "");
		else
			return "(".self::parseCondsRecursive($db, $conds->left).array_reduce($conds->right, function ($result, $condPart) use ($db, $conds, &$i) { return $result." ".self::$condTypes[$conds->type][$i++]." ".self::parseCondsRecursive($db, $condPart); }, "").")";
	}

	private static function parseCondsPart ($db, $condPart) {
		if (is_array($condPart))
			return "`".implode("`.`", $condPart)."`";
		else
			return "'".$db->real_escape_string($condPart)."'";
	}

	protected static function parseOrder ($db, $order) {
		if (empty($order))
			return "";

		return " ORDER BY ".(is_array($order[0])?substr(array_reduce($order, function ($result, $order) use ($db) { return $result. ", `".implode("`.`", (array)$order[0])."` ".($order[1] == db::ASC?"ASC":"DESC"); }, ""), 2):"`".implode("`.`", (array)$order[0])."` ".($order[1] == db::ASC?"ASC":"DESC"));
	}

	public static function select ($db, $fields, $tables, $conds = [], $order = []) {
		$query = "SELECT ".(!$fields?"*":implode(", ", array_map(function ($val) { return "`".implode("`.`", (array)$val)."`"; }, (array)$fields)))." FROM ";

		foreach ((array)$tables as $original => $table)
			if (is_numeric($original))
				$query .= "`$table`, ";
			else
				$query .= "`$original` AS `$table`, ";

		return self::query($db, substr($query, 0, -2).self::parseConds($db, $conds).self::parseOrder($db, $order));
	}

	public static function update ($db, $table, $fields, $conds = []) {
		$query = "UPDATE `$table` SET ";
		foreach ($fields as $field => $value)
			$query .= "`$field` = '".$db->real_escape_string($value)."', ";
		return self::query($db, substr($query, 0, -2).self::parseConds($db, $conds));
	}

	public static function insert ($db, $table, $fields) {
		return self::replaceOrInsert($db, $table, $fields, self::insert);
	}

	public static function replace ($db, $table, $fields) {
		return self::replaceOrInsert($db, $table, $fields, self::replace);
	}

	protected static function replaceOrInsert ($db, $table, $fields, $mode) {
		switch ($mode) {
			case self::replace:
				$query = "REPLACE INTO";
				break;
			default:
				$query = "INSERT INTO";
		}

		// call_user_func_array("array_map", [function () { return "'".implode("', '", func_get_args())."'"; }] + $fields) : get row by row.
		$query .= " `$table` (`".implode("`, `", array_keys($fields))."`) VALUES ";
		if (is_array($fields[0]))
			$query .= "(".implode("), (", call_user_func_array("array_map", [function () use ($db) { return "'".implode("', '", array_map([$db, "real_escape_string"], func_get_args()))."'"; }] + $fields)).")";
		else
			$query .= "(".substr(array_reduce($fields, function ($result, $val) use ($db) { return $result.", ".($val === NULL?"NULL":"'".$db->real_escape_string($val)."'"); },""), 2).")";

		return self::query($db, $query);
	}

	public static function delete ($db, $table, $conds = []) {
		return self::query($db, "DELETE FROM `$table`".self::parseConds($db, $conds));
	}

	public static function error ($db) {
		return $db->error;
	}

	public static function affected_rows ($db) {
		return $db->affected_rows;
	}

	public static function close ($db) {
		return $db->close();
	}
}
