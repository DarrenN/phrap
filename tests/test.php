<?php
require 'DB.php';
require 'Model.php';

$path = '/opt/local/lib/php';
set_include_path(get_include_path() . PATH_SEPARATOR . $path);

class TestModel extends Model
{
    public $table = "test";
    public $id;
    public $filename;
    public $userid;
    public $email;

    public function init()
    {
    	if ($this->filename && $this->id) {
    		$this->virtual_field('file_hash', sha1($this->filename . $this->id));
    	}
    }
}

class ModelTest extends PHPUnit_Framework_TestCase
{
	public static function setUpBeforeClass()
	{
		// Clear DB
		$database = array(
			'dbserver' => DBSERVER,
			'dbuser'   => DBUSER,
			'dbpasswd' => DBPASSWD,
			'dbname'   => DBNAME,
			'dbsalt'   => DBSALT
		);
		$dbh = new DB($database);
		$db = $dbh->get_connection();

		$sql = "
	DROP TABLE `test`;
	CREATE TABLE `test` (
	  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
	  `filename` varchar(255) DEFAULT NULL,
	  `userid` int(11) DEFAULT NULL,
	  `email` varchar(312) DEFAULT NULL,
	  PRIMARY KEY (`id`),
	  UNIQUE KEY `uq_email` (`email`)
	) ENGINE=MyISAM DEFAULT CHARSET=utf8;
	";
		$stmt = $db->prepare($sql);
		$stmt->execute();

		$db = null;
	}


	public function testDbArray()
	{
		$database = array(
			'dbserver' => DBSERVER,
			'dbuser'   => DBUSER,
			'dbpasswd' => DBPASSWD,
			'dbname'   => DBNAME,
			'dbsalt'   => DBSALT
		);

		$this->assertNotEmpty($database);

		return $database;
	}

	/**
	 * @depends testDbArray
	 */
	public function testDbConnection(array $database)
	{
		$dbh = new DB(); // Should fail since there is no connection information
		$this->assertNull($dbh->get_connection());
		$this->assertNotInstanceOf('PDO', $dbh->get_connection());

		$dbh = new DB($database);
		$this->assertInstanceOf('DB', $dbh);
		$this->assertInstanceOf('PDO', $dbh->get_connection());

		return $dbh;
	}

	/**
	 * @depends testDbConnection
	 */
	public function testModelInstance(DB $dbh)
	{
		$model = new TestModel($dbh);
		$this->assertInstanceOf('Model', $model);
		$this->assertInstanceOf('TestModel', $model);
		return $model;
	}

	/**
	 * @depends testModelInstance
	 */
	public function testModelVisbility(TestModel $model) {
		 $this->assertObjectHasAttribute('field_names', $model);
		 $this->assertObjectHasAttribute('virtual_fields', $model);
		 $this->assertClassHasStaticAttribute('id_autoincrement', 'Model');
	}	

	/**
	 * @depends testModelInstance
	 */
	public function testModelProperties(TestModel $model)
	{
		$this->assertNull($model->userid);

		$model->userid = 1;
		$this->assertNotNull($model->userid);
		$this->assertEquals($model->userid, 1);

		$model->filename = 'flaneur.txt';
		$this->assertEquals($model->filename, 'flaneur.txt');

		$model->filename = null;
		$this->assertNull($model->filename);
	}

	/**
	 * @depends testModelInstance
	 */
	public function testModelSet(TestModel $model)
	{
		$this->assertFalse($model->set());
		$this->assertFalse($model->set('ted'));
		$this->assertFalse($model->set(array()));

		$this->assertTrue($model->set(array('userid' => 1)));
		$this->assertEquals($model->userid, 1);

		$this->assertTrue($model->set(array('userid' => 10)));
		$this->assertEquals($model->userid, 10);

		$this->assertTrue($model->set(array('userid' => 'franklin', 'filename' => 'flaneur.txt')));
		$this->assertEquals($model->userid, 'franklin');
		$this->assertEquals($model->filename, 'flaneur.txt');

		$this->assertTrue($model->set(array('userid' => null)));
		$this->assertNull($model->userid);

		$this->assertTrue($model->set(array('hambone' => 1)));
		$this->assertObjectNotHasAttribute('hambone', $model);
	}

	/**
	 * @depends testModelInstance
	 */
	public function testModelCreate(TestModel $model)
	{
		$this->assertEmpty($model->id);

		$m = $model->create();
		$this->assertInstanceOf('TestModel', $m);
		$this->assertNotEmpty($model->id);
		$this->assertEquals(1, $model->id);

		$m->set(array('filename' => 'alice.txt'));
		$this->assertEquals('alice.txt', $m->filename);

		$m->create();
		$this->assertNotEmpty($model->id);
		$this->assertEquals(2, $model->id);
		$this->assertEmpty($m->filename);
		$this->assertNotEquals('alice.txt', $m->filename);

		$m->create();
		$this->assertNotEmpty($model->id);
		$this->assertEquals(3, $model->id);

		return $model;
	}

	/**
	 * @depends testModelCreate
	 */
	public function testModelSave(TestModel $model)
	{
		$model->set(array(
				'filename' => 'flaneur.txt',
				'userid'   => 2,
				'email'    => 'info@email.com'
			));
		$result = $model->save();
		$this->assertInternalType('string', $result);
		$this->assertEquals($model->id, $result);

		// Removing the index forces an INSERT
		// which mean a new record
		$model->id = null;
		$model->set(array(
				'filename' => 'apfelbaum.txt',
				'userid'   => 3,
				'email'    => 'dazza@email.com'
			));
		$result = $model->save();
		$this->assertInternalType('string', $result);
		$this->assertEquals(4, $result);

		// Removing the index forces an INSERT
		// and we test a fail due to Unique Index
		$model->id = null;
		$model->set(array(
				'filename' => 'oroboros.txt',
				'userid'   => 3,
				'email'    => 'info@email.com'
			));
		$result = $model->save();
		$this->assertFalse($result);
		$this->assertContains('Duplicate', $model->error);

		// This should do an UPDATE of just
		// the filename in the DB
		$model->id = 4;
		$model->set(array(
				'filename' => 'appelschnapps.txt',
				'userid'   => null,
				'email'    => null
			));
		$result = $model->save();
		$this->assertTrue($result);

		// Removing the index forces an INSERT
		// which mean a new record
		$model->id = null;
		$model->set(array(
				'filename' => 'appelschnapps.txt',
				'userid'   => 6,
				'email'    => 'gazza@email.com'
			));
		$result = $model->save();
		$this->assertInternalType('string', $result);
		$this->assertEquals(5, $result);
	}

	/**
	 * @depends testDbConnection
	 */
	 public function testQueryBuilder(DB $dbh)
	 {
	 	$model = new TestModel($dbh);
		$model->id(1);
		$this->assertEquals('1', $model->id);
		$model->id(1)->limit(10);
		$this->assertEquals('10', $model->attr('limit'));
		$this->assertEquals('1', $model->attr('id'));
		$model->id(3)->limit(10)->filter(array('filename' => 'monolith.txt'));
		$this->assertEquals('3', $model->id);
		$this->assertEquals('filename = :filename', $model->attr('filter'));
		$this->assertEquals('filename = :filename', $model->attr('conditions'));
		$model->get(array('id','filename','email'));
		$this->assertEquals('id, filename, email', $model->attr('fields'));
		$this->assertEquals('id, filename, email', $model->attr('columns'));
		$model->limit(10, 5);
		$this->assertEquals('10', $model->attr('limit'));
		$this->assertEquals('5', $model->attr('offset'));
	 }

	 /**
	 * @depends testDbConnection
	 */
	 public function testReset(DB $dbh)
	 {
	 	$model = new TestModel($dbh);
	 	$model->id(1);
		$this->assertEquals('1', $model->id);
		$model->id(1)->limit(10);
		$this->assertEquals('10', $model->attr('limit'));
		$this->assertEquals('1', $model->attr('id'));

		$reset = $model->reset();
		$this->assertInstanceOf('TestModel', $reset);
		$this->assertNull($model->attr('limit'));
		$this->assertNull($model->id);
	 }

	/**
	 * @depends testDbConnection
	 */
	public function testFindById(DB $dbh)
	{
		$model = new TestModel($dbh);
		$model->id(3);
		$result = $model->exec();
		$this->assertInstanceOf('TestModel', $result);
		$this->assertEquals('3', $result->id);
		$this->assertEquals('flaneur.txt', $result->filename);
		$this->assertEquals('info@email.com', $result->email);
		$this->assertEquals('2', $result->userid);
		$this->assertEquals('95985b32e8401aed3143a6c090dfca6c969fbf76', $result->file_hash);
	}

	/**
	 * @depends testDbConnection
	 */
	public function testFindFirst(DB $dbh)
	{
		$model = new TestModel($dbh);
		$model->first();
		$result = $model->exec();

		$this->assertInstanceOf('TestModel', $result);
		$this->assertEquals('3', $result->id);
		$this->assertEquals('flaneur.txt', $result->filename);
		$this->assertEquals('info@email.com', $result->email);
		$this->assertEquals('2', $result->userid);
		$this->assertEquals('95985b32e8401aed3143a6c090dfca6c969fbf76', $result->file_hash);

		// pull first from multiple values
		$model->first(array('filename' => 'appelschnapps.txt'));
		$result = $model->exec();

		$this->assertInstanceOf('TestModel', $result);
		$this->assertEquals('4', $result->id);
		$this->assertEquals('appelschnapps.txt', $result->filename);
		$this->assertEquals('dazza@email.com', $result->email);
		$this->assertEquals('3', $result->userid);
		$this->assertEquals('daf0ee72d921da625e5e08a0c13283830e610a6a', $result->file_hash);

		// pull first with a single column
		$model->reset()->first()->get('filename');
		$result = $model->exec();
		$this->assertInstanceOf('TestModel', $result);
		$this->assertEquals('flaneur.txt', $result->filename);
	}

	/**
	 * @depends testDbConnection
	 */
	public function testFindLast(DB $dbh)
	{
		$model = new TestModel($dbh);
		$model->last();
		$result = $model->exec();

		$this->assertInstanceOf('TestModel', $result);
		$this->assertEquals('5', $result->id);
		$this->assertEquals('appelschnapps.txt', $result->filename);
		$this->assertEquals('gazza@email.com', $result->email);
		$this->assertEquals('6', $result->userid);

		$model->reset();
		$model->last(array('email' => 'dazza@email.com'));
		$result = $model->exec();

		$this->assertInstanceOf('TestModel', $result);
		$this->assertEquals('4', $result->id);
		$this->assertEquals('appelschnapps.txt', $result->filename);
		$this->assertEquals('dazza@email.com', $result->email);
		$this->assertEquals('3', $result->userid);
		$this->assertEquals('daf0ee72d921da625e5e08a0c13283830e610a6a', $result->file_hash);

		// pull last from multiple values
		$model->reset();
		$model->last(array('filename' => 'appelschnapps.txt'));
		$result = $model->exec();

		$this->assertInstanceOf('TestModel', $result);
		$this->assertEquals('5', $result->id);
		$this->assertEquals('appelschnapps.txt', $result->filename);
		$this->assertEquals('gazza@email.com', $result->email);
		$this->assertEquals('6', $result->userid);
	}

	/**
	 * @depends testDbConnection
	 */
	public function testFindAll(DB $dbh)
	{
		$model = new TestModel($dbh);

		// find all (SELECT *)
		$model->all();
		$results = $model->exec();

		$this->assertNotEmpty($results);
		$this->assertInternalType('array', $results);
		$this->assertEquals(3, count($results));

		$properties = array('id','email','filename','userid','file_hash');
		foreach ($results as $result) {
			$this->assertInstanceOf('TestModel', $result);
			foreach ($properties as $property) {
				$this->assertObjectHasAttribute($property, $result);
			}
		}

		// find all by filename
		$results = $model->all(array('filename' => 'appelschnapps.txt'))->exec();
		//$results = $model->exec();

		$this->assertNotEmpty($results);
		$this->assertInternalType('array', $results);
		$this->assertEquals(2, count($results));

		$properties = array('id','email','filename','userid','file_hash');
		foreach ($results as $result) {
			$this->assertInstanceOf('TestModel', $result);
			foreach ($properties as $property) {
				$this->assertObjectHasAttribute($property, $result);
				$this->assertEquals('appelschnapps.txt', $result->filename);
			}
		}

		// find all returning col filename
		$model->reset()->all()->get('filename');
		$results = $model->exec();

		$this->assertNotEmpty($results);
		$this->assertInternalType('array', $results);
		$this->assertEquals(3, count($results));
		foreach ($results as $result) {
			$this->assertInstanceOf('TestModel', $result);
			$this->assertObjectHasAttribute('filename', $result);
		}

		// find all by filename returning cols: email filename & id
		$model->reset()->all(array('filename' => 'appelschnapps.txt'))->get(array('email','filename','id'));
		$results = $model->exec();

		$this->assertNotEmpty($results);
		$this->assertInternalType('array', $results);
		$this->assertEquals(2, count($results));

		$properties = array('id','email','filename','file_hash');
		foreach ($results as $result) {
			$this->assertInstanceOf('TestModel', $result);
			foreach ($properties as $property) {
				$this->assertObjectHasAttribute($property, $result);
				$this->assertEquals('appelschnapps.txt', $result->filename);
			}
		}

	}

	/**
	 * @depends testDbConnection
	 */
	public function testFindBadConditions(DB $dbh)
	{
		$model = new TestModel($dbh);

		// Bad Queries
		$model->first(array('email' => 'heffalump@email.com'));
		$result = $model->exec();
		$this->assertEmpty($result);

		$model->reset()->last(array('email' => 'heffalump@email.com'));
		$result = $model->exec();
		$this->assertEmpty($result);

		$model->reset()->all(array('email' => 'heffalump@email.com'));
		$result = $model->exec();
		$this->assertEmpty($result);

		$model->reset()->all()->get('paragraph');
		$result = $model->exec();
		$this->assertEmpty($result);
		$this->assertContains("SQLSTATE[42S22]: Column not found: 1054 Unknown column 'paragraph' in 'field list'", $model->error);
	}


	/**
	 * @depends testDbConnection
	 */
	public function testQuery(DB $dbh)
	{
		$model = new TestModel($dbh);

		// Make sure we get back a TestModel Object in result
		$result = $model->query('SELECT * FROM test WHERE id = :id', array('id' => 4));
		$this->assertNotEmpty($result);
		$this->assertInternalType('array', $result);
		$this->assertEquals(1, count($result));

		$this->assertNotEmpty($result[0]);
		$this->assertInternalType('object', $result[0]);
		$this->assertInstanceOf('TestModel', $result[0]);

		$this->assertEquals('4', $result[0]->id);
		$this->assertEquals('appelschnapps.txt', $result[0]->filename);
		$this->assertEquals('dazza@email.com', $result[0]->email);
		$this->assertEquals('daf0ee72d921da625e5e08a0c13283830e610a6a', $result[0]->file_hash);

		// Make a failing query
		$result = $model->query('SELECT * FROM test WHERE id = :id', array('id' => 40));
		$this->assertFalse($result);

		$model->reset();

		// Make a non-returning query (INSERT, REPLACE INTO, etc)
		$values = array(
			'id' => 5,
			'filename' => 'mule.txt',
			'email' => 'gazza@email.com'
			);
		$result = $model->query('REPLACE INTO test SET id = :id, email = :email, filename = :filename', $values, false);
		$this->assertTrue($result);

		// Now test that the REPLACE INTO query worked
		$model->id(5);
		$result = $model->exec();
		$this->assertNotEmpty($result);
		$this->assertEquals('5', $result->id);
		$this->assertEquals('mule.txt', $result->filename);
		$this->assertEquals('gazza@email.com', $result->email);

	}

	/**
	 * @depends testDbConnection
	 */
	public function testDelete(DB $dbh)
	{
		$model = new TestModel($dbh);

		$this->assertTrue($model->delete(3));
		$this->assertFalse($model->delete(42));

		$model->first();
		$result = $model->exec();

		$this->assertEquals('4', $result->id);
		$model->id($result->id);
		$this->assertTrue($model->delete());
		$this->assertFalse($model->delete());
		$this->assertObjectHasAttribute('file_hash', $result);
		$this->assertObjectHasAttribute('id', $result);
		$this->assertNull($model->id);
		$this->assertNull($model->email);
	}

}
?>