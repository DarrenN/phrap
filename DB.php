<?php

const DBSERVER = '';
const DBUSER   = '';
const DBPASSWD = '';
const DBNAME   = '';
const DBSALT   = '';

class DB {
	private $dbh;
	private $dsn;

	function __construct($database = null) {
		if (!$database || !is_array($database)) {
			return false;
		}
		$this->dsn = "mysql:dbname={$database['dbname']};host={$database['dbserver']}";
		try {
			$this->dbh = new PDO($this->dsn, $database['dbuser'], $database['dbpasswd']);
			$this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // Set error mode
		}
		catch(PDOException $e)
		{
			echo $e->getMessage();
		}
	}

	public function get_connection()
	{
		return $this->dbh;
	}
}
?>