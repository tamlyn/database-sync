<?php

/**
 * Get secret or generate one if none is found
 * @return string Secret key
 */
function dbs_getSecret() {
	$key = get_option('outlandish_sync_secret');
	if (!$key) {
		$key = '';
		$length = 16;
		while ($length--) {
			$key .= chr(mt_rand(33, 126));
		}
		update_option('outlandish_sync_secret', $key);
	}

	return $key;
}

/**
 * @return string Encoded secret+URL token
 */
function dbs_getToken() {
	return trim(base64_encode(dbs_getSecret() . ' ' . get_bloginfo('wpurl')), '=');
}

/**
 * @param $url
 * @return string $url with leading http:// stripped
 */
function dbs_stripHttp($url) {
	return preg_replace('|^https?://|', '', $url);
}

/**
 * Load a series of SQL statements.
 * @param $sql string SQL dump
 */
function dbs_loadSql($sql) {
	$sql = preg_replace("|/\*.+\*/\n|", "", $sql);
	$queries = explode(";\n", $sql);
	foreach ($queries as $query) {
		if (!trim($query)) continue;
		if (mysql_query($query) === false) {
			return false;
		}
	}

	return true;
}

/**
 * Generate a URL for the plugin.
 * @param array $params
 * @return string
 */
function dbs_url($params = array()) {
	$params = array_merge(array('page'=>'dbs_options'), $params);
	return admin_url('tools.php?' . http_build_query($params));
}

/**
 * @param $url string Remote site wpurl base
 * @param $action string dbs_pull or dbs_push
 * @param $params array POST parameters
 * @return string The returned content
 * @throws RuntimeException
 */
function dbs_post($url, $action, $params) {
	$remote = $url . '/wp-admin/admin-ajax.php?action=' . $action . '&api_version=' . DBS_API_VERSION;
	$ch = curl_init($remote);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
	$result = curl_exec($ch);
	$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);

	if ($code != 200) {
		throw new RuntimeException('HTTP Error', $code);
	}
	return $result;
}

/**
 * Dump the database and save
 */
function dbs_makeBackup() {
	ob_start();
	dbs_mysqldump();
	$sql = ob_get_clean();

	$tempdir = realpath(dirname(__FILE__)).DIRECTORY_SEPARATOR.'backups';
	if (!file_exists($tempdir)) mkdir($tempdir);
	$filename = $tempdir . DIRECTORY_SEPARATOR .'db'.date('Ymd.His').'.sql.gz';
	file_put_contents($filename, gzencode($sql));

	return $filename;
}

/**
 * Dump the current MYSQL table.
 * Original code (c)2006 Huang Kai <hkai@atutility.com>
 */
function dbs_mysqldump() {
	$sql = "SHOW TABLES;";
	$result = mysql_query($sql);
	echo '/* Dump of database '.DB_NAME.' on '.$_SERVER['HTTP_HOST'].' at '.date('Y-m-d H:i:s')." */\n\n";
	while ($row = mysql_fetch_row($result)) {
		echo dbs_mysqldump_table_structure($row[0]);
		echo dbs_mysqldump_table_data($row[0]);
	}
	mysql_free_result($result);
}

/**
 * Original code (c)2006 Huang Kai <hkai@atutility.com>
 * @param $table string Table name
 * @return string SQL
 */
function dbs_mysqldump_table_structure($table) {
	echo "/* Table structure for table `$table` */\n\n";
	echo "DROP TABLE IF EXISTS `$table`;\n\n";

	$sql = "SHOW CREATE TABLE `$table`; ";
	$result = mysql_query($sql);
	if ($result) {
		if ($row = mysql_fetch_assoc($result)) {
			echo $row['Create Table'] . ";\n\n";
		}
	}
	mysql_free_result($result);
}

/**
 * Original code (c)2006 Huang Kai <hkai@atutility.com>
 * @param $table string Table name
 * @return string SQL
 */
function dbs_mysqldump_table_data($table) {
	$sql = "SELECT * FROM `$table`;";
	$result = mysql_query($sql);

	echo '';
	if ($result) {
		$num_rows = mysql_num_rows($result);
		$num_fields = mysql_num_fields($result);
		if ($num_rows > 0) {
			echo "/* dumping data for table `$table` */\n";
			$field_type = array();
			$i = 0;
			while ($i < $num_fields) {
				$meta = mysql_fetch_field($result, $i);
				array_push($field_type, $meta->type);
				$i++;
			}
			$maxInsertSize = 100000;
			$index = 0;
			$statementSql = '';
			while ($row = mysql_fetch_row($result)) {
				if (!$statementSql) $statementSql .= "INSERT INTO `$table` VALUES\n";
				$statementSql .= "(";
				for ($i = 0; $i < $num_fields; $i++) {
					if (is_null($row[$i]))
						$statementSql .= "null";
					else {
						switch ($field_type[$i]) {
							case 'int':
								$statementSql .= $row[$i];
								break;
							case 'string':
							case 'blob' :
							default:
								$statementSql .= "'" . mysql_real_escape_string($row[$i]) . "'";

						}
					}
					if ($i < $num_fields - 1)
						$statementSql .= ",";
				}
				$statementSql .= ")";

				if (strlen($statementSql) > $maxInsertSize || $index == $num_rows - 1) {
					echo $statementSql.";\n";
					$statementSql = '';
				} else {
					$statementSql .= ",\n";
				}

				$index++;
			}
		}
	}
	mysql_free_result($result);
	echo "\n";
}

