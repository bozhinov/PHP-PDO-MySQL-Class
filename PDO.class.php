<?php
/*
 * PHP-PDO-MySQL-Class
 * https://github.com/lincanbin/PHP-PDO-MySQL-Class
 *
 * Copyright 2015 Canbin Lin (lincanbin@hotmail.com)
 * http://www.94cb.com/
 *
 * Licensed under the Apache License, Version 2.0:
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * A PHP MySQL PDO class similar to the Python MySQLdb.
 */

class PDOIterator implements Iterator {
	private $position = 0;
	private $pdo;
	private $fetchMode;
	private $nextResult;

	public function __construct(PDOStatement $this->pdo, $this->fetchMode = PDO::FETCH_ASSOC) {
		$this->position = 0;
	}

	function rewind() {
		$this->position = 0;
		$this->pdo->execute();
		$this->nextResult = $this->pdo->fetch($this->fetchMode, PDO::FETCH_ORI_NEXT);
	}

	function current() {
		return $this->nextResult;
	}

	function key() {
		return $this->position;
	}

	function next() {
		++$this->position;
		$this->nextResult = $this->pdo->fetch($this->fetchMode, PDO::FETCH_ORI_NEXT);
	}

	function valid() {
		$invalid = $this->nextResult === false;
		if ($invalid) {
			$this->pdo->closeCursor();
		}
		return !$invalid;
	}
}

/** Class DB
 * @property PDO pdo PDO object
 * @property PDOStatement sQuery PDOStatement
 */
class DB
{
	private $Host;
	private $DBPort;
	private $DBName;
	private $DBUser;
	private $DBPassword;
	private $pdo;
	private $sQuery;
	private $parameters = [];
	public $connectionStatus = false;
	public $rowCount = 0;
	public $columnCount = 0;
	public $querycount = 0;

	/**
	 * DB constructor.
	 * @param $Host
	 * @param $DBPort
	 * @param $DBName
	 * @param $DBUser
	 * @param $DBPassword
	 */
	public function __construct($this->DBName, $this->DBUser, $this->DBPassword, $this->Host = '127.0.0.1', $this->DBPort = 3306)
	{
		try {
			$dsn = 'mysql:';
			$dsn .= 'host=' . $this->Host . ';';
			$dsn .= 'port=' . $this->DBPort . ';';
			if (!empty($this->DBName)) {
				$dsn .= 'dbname=' . $this->DBName . ';';
			}
			$dsn .= 'charset=utf8;';
			$this->pdo = new PDO($dsn,
				$this->DBUser,
				$this->DBPassword,
				[
					PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
					PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
					PDO::MYSQL_ATTR_FOUND_ROWS => true
				]
			);
			$this->connectionStatus = true;

		}
		catch (PDOException $e) {
			$this->connectionStatus = false;
			$this->pdo = null;
			die("Problem connecting to the Db");
		}
	}

	/**
	 * close pdo connection
	 */
	public function closeConnection()
	{
		$this->pdo = null;
	}

	private function Init($query, $parameters = null, $driverOptions = [])
	{
		try {
			$this->parameters = $parameters;
			$this->sQuery     = $this->pdo->prepare($this->BuildParams($query, $this->parameters), $driverOptions);

			if (!empty($this->parameters)) {
				if (array_key_exists(0, $parameters)) {
					$parametersType = true;
					array_unshift($this->parameters, "");
					unset($this->parameters[0]);
				} else {
					$parametersType = false;
				}
				foreach ($this->parameters as $column => $value) {
					$this->sQuery->bindParam($parametersType ? intval($column) : ":" . $column, $this->parameters[$column]); //It would be query after loop end(before 'sQuery->execute()').It is wrong to use $value.
				}
			}

			if (!isset($driverOptions[PDO::ATTR_CURSOR])) {
				$this->sQuery->execute();
			}
			$this->querycount++;
		}
		catch (PDOException $e) {
			die("Problem with the Db");
		}

		$this->parameters = [];
	}

	private function BuildParams($query, $params = null)
	{
		if (!empty($params)) {
			$array_parameter_found = false;
			foreach ($params as $parameter_key => $parameter) {
				if (is_array($parameter)){
					$array_parameter_found = true;
					$in = "";
					foreach ($parameter as $key => $value){
						$name_placeholder = $parameter_key."_".$key;
						// concatenates params as named placeholders
						$in .= ":".$name_placeholder.", ";
						// adds each single parameter to $params
						$params[$name_placeholder] = $value;
					}
					$in = rtrim($in, ", ");
					$query = preg_replace("/:".$parameter_key."/", $in, $query);
					// removes array form $params
					unset($params[$parameter_key]);
				}
			}

			// updates $this->params if $params and $query have changed
			if ($array_parameter_found) $this->parameters = $params;
		}
		return $query;
	}

	/**
	 * @return bool
	 */
	public function beginTransaction()
	{
		return $this->pdo->beginTransaction();
	}

	/**
	 * @return bool
	 */
	public function commit()
	{
		return $this->pdo->commit();
	}

	/**
	 * @return bool
	 */
	public function rollBack()
	{
		return $this->pdo->rollBack();
	}

	/**
	 * @return bool
	 */
	public function inTransaction()
	{
		return $this->pdo->inTransaction();
	}

	/**
	 * execute a sql query, returns an result array in the select operation, and returns the number of rows affected in other operations
	 * @param string $query
	 * @param null $params
	 * @param int $fetchMode
	 * @return array|int|null
	 */
	public function query($query, $params = null, $fetchMode = PDO::FETCH_ASSOC)
	{
		$query = trim($query);
		$rawStatement = preg_split("/( |\r|\n)/", $query);
		$this->Init($query, $params);
		$statement = strtolower($rawStatement[0]);
		if ($statement === 'select' || $statement === 'show' || $statement === 'call' || $statement === 'describe') {
			return $this->sQuery->fetchAll($fetchMode);
		} elseif ($statement === 'insert' || $statement === 'update' || $statement === 'delete') {
			return $this->sQuery->rowCount();
		} else {
			return NULL;
		}
	}

	/**
	 * execute a sql query, returns an iterator in the select operation, and returns the number of rows affected in other operations
	 * @param string $query
	 * @param null $params
	 * @param int $fetchMode
	 * @return int|null|PDOIterator
	 */
	public function iterator($query, $params = null, $fetchMode = PDO::FETCH_ASSOC)
	{
		$query = trim($query);
		$rawStatement = preg_split("/( |\r|\n)/", $query);
		$this->Init($query, $params, [PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL]);
		$statement = strtolower(trim($rawStatement[0]));
		if (in_array($statement, ['select','show','call','describe']) {
			return new PDOIterator($this->sQuery, $fetchMode);
		} elseif (in_array($statement,['insert' ,'update','delete']) {
			return $this->sQuery->rowCount();
		} else {
			return NULL;
		}
	}

	/**
	 * @param $tableName
	 * @param $params
	 * @return bool|string
	 */
	public function insert($tableName, $params)
	{
		$keys = array_keys($params);
		$rowCount = $this->query(
			'INSERT INTO `' . $tableName . '` (`' . implode('`,`', $keys) . '`)
			VALUES (:' . implode(',:', $keys) . ')',
			$params
		);
		if ($rowCount === 0) {
			return false;
		}
		return $this->lastInsertId();
	}

	/**
	 * @param $tableName
	 * @param $params
	 * @return bool|string
	 */
	public function delete($tableName, $params){
		$rowCount = $this->query(
			'DELETE FROM `' . $tableName . 
			' WHERE (:' . implode(',:', array_keys($params)) . ')',
			$params
		);
		if ($rowCount === 0) {
			return false;
		}
	}

	/**
	 * insert multi rows
	 *
	 * @param string $tableName database table name
	 * @param array $params structure like [[colname1 => value1, colname2 => value2], [colname1 => value3, colname2 => value4]]
	 * @return boolean success or not
	 */
	public function insertMulti($tableName, $params = [])
	{
		$rowCount = 0;
		if (!empty($params)) {
			$insParaStr = '';
			$insValueArray = [];

			foreach ($params as $addRow) {
				$insColStr = implode('`,`', array_keys($addRow));
				$insParaStr .= '(' . implode(",", array_fill(0, count($addRow), "?")) . '),';
				$insValueArray = array_merge($insValueArray, array_values($addRow));
			}
			$insParaStr = substr($insParaStr, 0, -1);
			$dbQuery = "INSERT INTO {$tableName} (
							`$insColStr`
						) VALUES
							$insParaStr";
			$rowCount = $this->query($dbQuery, $insValueArray);
		}
		return (bool) ($rowCount > 0);
	}

	/**
	 * update
	 *
	 * @param string $tableName
	 * @param array $params
	 * @param array $where
	 * @return int affect rows
	 */
	public function update($tableName, $params = [], $where = [])
	{
		$rowCount = 0;
		if (!empty($params)) {
			$updColStr = '';
			$whereStr = '';
			$updatePara = [];
			// Build update statement
			foreach ($params as $key => $value) {
				$updColStr .= "{$key}=?,";
			}
			$updColStr = substr($updColStr, 0, -1);
			$dbQuery = "UPDATE {$tableName}
						SET {$updColStr}";
			// where condition
			if (is_array($where)) {
				foreach ($where as $key => $value) {
					// Is there need to add "OR" condition?
					$whereStr .= "AND {$key}=?";
				}
				$dbQuery .= " WHERE 1=1 {$whereStr}";
				$updatePara = array_merge(array_values($params), array_values($where));
			} else {
				$updatePara = array_values($params);
			}
			$rowCount = $this->query($dbQuery, $updatePara);
		}
		return $rowCount;
	}

	/**
	 * @return string
	 */
	public function lastInsertId()
	{
		return $this->pdo->lastInsertId();
	}

	/**
	 * @param $query
	 * @param null $params
	 * @return array
	 */
	public function column($query, $params = null)
	{
		$this->Init($query, $params);
		$resultColumn = $this->sQuery->fetchAll(PDO::FETCH_COLUMN);
		$this->rowCount = $this->sQuery->rowCount();
		$this->columnCount = $this->sQuery->columnCount();
		$this->sQuery->closeCursor();
		return $resultColumn;
	}

	/**
	 * @param $query
	 * @param null $params
	 * @param int $fetchmode
	 * @return mixed
	 */
	public function row($query, $params = null, $fetchmode = PDO::FETCH_ASSOC)
	{
		$this->Init($query, $params);
		$resultRow = $this->sQuery->fetch($fetchmode);
		$this->rowCount = $this->sQuery->rowCount();
		$this->columnCount = $this->sQuery->columnCount();
		$this->sQuery->closeCursor();
		return $resultRow;
	}

	/**
	 * @param $query
	 * @param null $params
	 * @return mixed
	 */
	public function single($query, $params = null)
	{
		$this->Init($query, $params);
		return $this->sQuery->fetchColumn();
	}
}