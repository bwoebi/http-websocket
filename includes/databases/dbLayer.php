<?php

interface dbLayer {
	// $conds object condition: db:c()

	public static function init ($host, $user, $pass, $name);

	// $fields: array of fields names or single string
	// $order: array: ["fieldname", db::ASC|db::DESC]|[["fieldname", db::ASC|db::DESC], ...]
	public static function select ($db, $fields, $tables, $conds = [], $order = []);

	// $fields: array: ["fieldname" => "value", ...]
	public static function update ($db, $table, $fields, $conds = []);

	// $fields: array: ["fieldname" => "value"|["value", ...], ...]
	public static function insert ($db, $table, $fields);

	// $fields: array: ["fieldname" => "value"|["value", ...], ...]
	public static function replace ($db, $table, $fields);

	public static function delete ($db, $table, $conds = []);

	public static function error ($db);

	public static function affected_rows ($db);

	public static function close ($db);
}
