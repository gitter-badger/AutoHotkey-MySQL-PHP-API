﻿<?php

	/** ---------------------------------------------------------------------------
	 *
	 *	Improv3d MySQL/PHP API
	 *	Version: 1.2
	 *	https://github.com/kevgk/AutoHotkey-MySQL-PHP-API
	 *
	 *	You should not edit this file,
	 *	you can change your settings in the config.php file.
	 *
	 * ---------------------------------------------------------------------------*/


	error_reporting(0);
	header_remove("x-powered-by");
	require 'config.php';

	getRights();

	if (!empty($_GET["action"]) && $rights[$_GET["action"]] && isAuthorized()) {

		$mysqli	= dbConnect();

		foreach($_GET as $key => $value) {
			$_GET[$key] = $mysqli->escape_string($value);
		}


		$table = $_GET["table"];

		switch($_GET["action"]) {
			case "get":
				if (!empty($_GET["row"]) && !empty($_GET["column"])) {
					$row = $_GET["row"];
					$column = $_GET["column"];
					$primaryKey = getPrimaryKey($table);

					if (!rowExist($row, $primaryKey)) exit(imp_return(-1));
					$query = $mysqli->query("SELECT $column FROM $table WHERE $primaryKey='$row'");
					$result = $query->fetch_array();

					if (!$mysqli->errno) {
						imp_return($result[0]);
					}
				}
				break;

			case "getWhere":
				$column_where = $_GET["where"];
				$row_where = $_GET["is"];
				$column = $_GET["column"];
				$operator = $_GET['operator'];
				$operatorWhitelist = ['=', '!=', '<', '>', '>=', '<='];

				if (!empty($column_where) && !empty($row_where) && !empty($column) && in_array($operator, $operatorWhitelist)) {
					$query = $mysqli->query("SELECT $column FROM $table WHERE $column_where $operator $row_where");

					while($row = $query->fetch_array()) $result[] = $row[$column];

					if ($result) {
						if (count($result) > 1) {
							imp_isArray(1);
						}
						imp_return(implode('||', $result));
					}
				}
				break;

			case "getAll":
				$row = $_GET["row"];

				if (!empty($row)) {
					$primaryKey = getPrimaryKey($table);

					if (rowExist($row, $primaryKey)) {
						$query = $mysqli->query("SELECT * FROM $table WHERE $primaryKey='$row'");
						$result = $query->fetch_assoc();

						$vals = [];

						foreach($result as $column => $value) {
							array_push($vals, $column . "::" . $value);
						}

						if ($vals) {
							if (count($vals) > 1) {
								imp_isAssoc(1);
							}
							imp_return(implode('||', $vals));
						}
					}
					else {
						imp_return(-1);
					}
				}
				break;

			case "set":
				$row = $_GET["row"];
				$column = $_GET["column"];
				$value = $_GET["value"];

				if (!empty($row) && !empty($column)) {
					$primaryKey = getPrimaryKey($table);

					if (rowExist($row, $primaryKey)) {
						if ($mysqli->query("UPDATE `$table` SET `$column`='$value' WHERE `$primaryKey`='$row'")) {
							imp_return(1);
						}
					}
					else {
						imp_return(-1);
					}
				}
				break;

			case "create_row":
				$row = $_GET["row"];

				if (!empty($row)) {
					$primaryKey = getPrimaryKey($table);

					if (rowExist($row, $primaryKey)) {
						imp_return("-1");
					}
					else {
						if ($mysqli->query("INSERT INTO $table ($primaryKey) VALUES ('$row')")) {
							imp_return(1);
						}
					}
				}
				break;

			case "delete_row":
				$row = $_GET["row"];
				$primaryKey = getPrimaryKey($table);

				if (rowExist($row, $primaryKey)) {
					if ($mysqli->query("DELETE FROM $table WHERE $primaryKey='$row'")) {
						imp_return(1);
					}
				}
				else {
					imp_return("-1");
				}
				break;

			case "create_table":
				$name = $_GET["name"];
				$tableExist = $mysqli->query("SHOW TABLES LIKE '$name'")->num_rows;

				if ($tableExist !== 1) {
					$columns = $_GET["columns"];
					$args = explode(",", $columns);
					$queryStr = "CREATE TABLE $name (";

					foreach($args as $val) {
						$val = str_ireplace("alter", '`alter`', $val);
						$queryStr .= "$val VARCHAR (".FIELD_LENGTH."),";
					}

					$queryStr .= "PRIMARY  KEY (`$args[0]`))";
					$create = $mysqli->query($queryStr);
					$success = $mysqli->query("SHOW TABLES LIKE '$name'")->num_rows;

					if ($success) {
						imp_return(1);
					}
					else {
						imp_return(0);
					}
				}
				else {
					imp_return(-1);
				}
				break;

			case "delete_table":
				$name = $_GET["table"];
				$tableExist = $mysqli->query("SHOW TABLES LIKE '$name'")->num_rows;

				if ($tableExist) {
					$delete = $mysqli->query("DROP TABLE $name");
					$success = $mysqli->query("SHOW TABLES LIKE '$name'")->num_rows;
					if ($success !== 1) {
						imp_return(1);
					}
					else {
						imp_return(0);
					}
				}
				else {
					imp_return(-1);
				}
				break;

			case "list_columns":
					$list = $mysqli->query("SHOW COLUMNS FROM $table");
					while($column = $list->fetch_array()) {
						$columns .= $column[0] . ",";
					}
					$columns = substr($columns, 0, -1);
					imp_return($columns);
				break;

			case "list_rows":
				$primaryKey = getPrimaryKey($table);
				$rows = $mysqli->query("SELECT $primaryKey FROM $table");

				while($row = $rows->fetch_array()) {
					$output .= $row[$primaryKey] . ", ";
				}

				$output = substr($output, 0, -2);
				imp_return($output);
				break;

			case "table_exist":
				$name = $_GET["name"];
				if ($mysqli->query("SHOW TABLES LIKE '".$name."'")->num_rows) {
					imp_return(1);
				}
				break;

			case "delete_column":
				$column = $_GET["column"];

				if (!empty($column)) {
					$columnExist	= $mysqli->query("SELECT $column FROM $table LIMIT 1")->num_rows;

					if ($columnExist) {
						$delete 	= $mysqli->query("ALTER TABLE $table DROP $column");
						$success	= $mysqli->query("SELECT $column FROM $table LIMIT 1")->num_rows;
						if ($success != 1) {
							imp_return(1);
						}
						else {
							imp_return(0);
						}
					}
					else {
						imp_return(-1);
					}
				}
				break;

			case "add_column":
				$column = $_GET["column"];

				if (!empty($column)) {
					$columnExist = $mysqli->query("SELECT $column FROM $table LIMIT 1")->num_rows;

					if ($columnExist == 1) {
						imp_return(-1);
					}
					else {
						$add 		= $mysqli->query("ALTER TABLE $table ADD $column VARCHAR(128)");
						$success 	= $mysqli->query("SELECT $column FROM $table LIMIT 1")->num_rows;

						if ($success) {
							imp_return(1);
						}
						else {
							imp_return(0);
						}
					}
				}
				break;

			case "rename_column":
				$column = $_GET["column"];
				$newname = $_GET["newname"];

				if (!empty($column) && !empty($newname)) {
					$columnExist	= $mysqli->query("SELECT $column FROM $table LIMIT 1")->num_rows;

					if ($columnExist) {
						$rename		= $mysqli->query("ALTER TABLE $table CHANGE $column $newname VARCHAR(128)");
						$success	= $mysqli->query("SELECT $newname FROM $table LIMIT 1")->num_rows;

						if ($success == 1) {
							imp_return(1);
						}
						else {
							imp_return(0);
						}
					}
					else {
						imp_return(-1);
					}
				}
				break;

			case "row_exist":
				$row = $_GET["row"];

				if (!empty($row)) {
					$primaryKey = getPrimaryKey($table);

					if (rowExist($row, $primaryKey)) {
						imp_return(1);
					}
					else {
						imp_return(0);
					}
				}
				break;

			case "exec":
				$query	= $_GET['query'];
				$result = $mysqli->query($query)->fetch_assoc();

				if (is_array($result)) {
					$output = "";
					for ($i = 0, $x = sizeof($result); $i < $x; ++$i) {
						$output .= key($result)." = ".current($result).", \n";
						next($result);
					}
					imp_return($output);
				}
				else 	{
					if ($result->affected_rows >= 0) {
						imp_return(1);
					}
				}
				break;

			case "mail":
				$to = $_GET["to"];
				$subject = $_GET["subject"];
				$message = $_GET["message"];

				if (!empty($to) && !empty($message)) {
					$mail = mail($to, $subject, $message, "From: ".MAIL_SENDER);
					imp_return(($mail) ? 1 : 0);
				}
				break;

			case "hash":
				$str	= $_GET["str"];
				$algo	= $_GET["algo"];

				if (!empty($str) && !empty($algo)) {
					if (in_array($algo, hash_algos()))
						imp_return(hash($algo, $str));
				}
				break;

			case "compare":
				$row = $_GET["row"];
				$column = $_GET["column"];
				$compare = $_GET["value"];

				if (!empty($row) && !empty($column) && !empty($compare)) {
					$primaryKey = getPrimaryKey();

					if (rowExist($row, $primaryKey)) {
						$query = $mysqli->query("SELECT $column FROM $table WHERE $primaryKey='$row'");
						$result = $query->fetch_array();

						if ($result[0] == $compare) {
							imp_return(1);
						}
						else {
							imp_return(0);
						}
					}
					else {
						imp_return(-1);
					}
				}
				break;

			case "count_rows":
				$result = $mysqli->query("SELECT count(1) FROM $table");
				$row 	= $result->fetch_array();

				imp_return($row[0]);
				break;

			case "check_table":
				$query 	= $mysqli->query("SELECT * FROM $table");
				while($content = $query->fetch_assoc()) {
					$str .= serialize($content);
				}

				$query = $mysqli->query("SELECT count(1) FROM $table");
				$rows 	= $query->fetch_array();

				$str .= $rows[0];

				imp_return(md5($str));
				break;

			case "generate_key":
				imp_return(md5(random_bytes(24)));
				break;

			case "file_write":
				$file = $_GET['file'];
				$content = $_GET['content'];
				$mode = $_GET['mode'];

				switch ($mode) {
					case 'overwrite':
						$handle = fopen($file, 'w');
						break;

					case 'end':
						$handle = fopen($file, 'a');
						break;
				}

				$s = fwrite($handle, $content);
				fclose($handle);

				if (empty($content) && file_exists($file)) {
					imp_return(1);
				}
				else {
					($s>0) ? imp_return(1) : imp_return(0);
				}

				break;

			case "file_read":
				$file = $_GET['file'];
				imp_return(file_get_contents($file));
				break;

			case 'file_delete':
				$file = $_GET['file'];
				imp_return(unlink($file));
				break;

			case 'file_rename':
				$file = $_GET['file'];
				$name = $_GET['name'];
				imp_return(rename($file, $name));
				break;

			case 'file_copy':
				$file = $_GET['file'];
				$dest = $_GET['dest'];
				imp_return(copy($file, $dest));
				break;

			case 'file_exists':
				$file = $_GET['file'];
				(file_exists($file)) ? imp_return(1) : imp_return(0);
				break;

			case 'file_size':
				$file = $_GET['file'];
				$unit = $_GET['unit'];

				switch ($unit) {
					case 'b':
						$divider = 1;
						break;

					case 'kb':
						$divider = 1024;
						break;

					case 'mb':
						$divider = 1048576;
						break;

					case 'gb':
						$divider = 1073741824;
						break;
				}

				imp_return(filesize($file)/$divider);
				break;
		}
		$mysqli->close();
	}

	function dbConnect() {
		$db = new mysqli(SERVER, USER, PASSWORD, DATABASE);
		if ($db->connect_errno) {
			imp_error('Can`t connect to database.');
			exit();
		}
		else {
			return $db;
		}
	}

	function imp_return($val) {
		echo '<!--imp_return="'.$val.'"-->';
	}

	function imp_isArray($val) {
		echo '<!--imp_isArray="'.$val.'"-->';
	}

	function imp_isAssoc($val) {
		echo '<!--imp_isAssoc="'.$val.'"-->';
	}

	function imp_error($val) {
		echo '<!--imp_error="Error: '.$val.'"-->';
	}

	function getRights() {
		global $rights, $keys;
		if (!empty($_GET['key']) && is_array($keys[$_GET['key']])) {
			$nkey = $keys[$_GET['key']];

			foreach($nkey as $field => $value) {
				$rights[$field] = $value;
			}
		}

	}

	function isAuthorized() {
		global $keys;
		$key = $_GET['key'];

		if (in_array($key, $keys) || array_key_exists($key, $keys) || empty($keys) || ALLOW_UNAUTHENTICATED) {
			return true;
		}
		else {
			if (SHOW_AUTH_ERROR) {
				imp_error('Not authorized.');
			}
			else {
				header("HTTP/1.0 404 Not Found");
				exit();
			}
		}
	}

	function getPrimaryKey($table) {
		global $mysqli;
		return $mysqli->query("SHOW KEYS FROM $table WHERE Key_name='PRIMARY'")->fetch_array()[4];
	}

	function rowExist($row, $primaryKey) {
		global $mysqli, $table;
		return $mysqli->query("SELECT * FROM $table WHERE $primaryKey='$row' LIMIT 1")->num_rows;
	}

?>
