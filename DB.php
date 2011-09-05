<?php
/**
 * DB CONNECTION CONSTANTS
 * 
 * const DBSERVER = '127.0.0.1';
 * const DBUSER   = 'your_db_user';
 * const DBPASSWD = 'your_db_password';
 * const DBNAME   = 'your_db_name';
 *
 * for testing we load them from .connection
 */
if (file_exists('.connection')) {
	include('.connection');
}

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