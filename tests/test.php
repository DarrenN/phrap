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
    	if ($this->filename) {
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
		$sql = "DELETE FROM test WHERE id > 0";
		$stmt = $db->prepare($sql);
		$stmt->execute();

		$sql = "TRUNCATE test";
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
	public function testModelFieldNames(TestModel $model)
	{
		$this->assertObjectHasAttribute('field_names', $model);
		$this->assertNotEmpty($model->field_names);
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
	}

	/**
	 * @depends testDbConnection
	 */
	public function testFindOne(DB $dbh)
	{
		$model = new TestModel($dbh);

		// Make sure we get back a TestModel Object in result
		$result = $model->find('first');
		$this->assertNotEmpty($result);
		$this->assertInternalType('array', $result);
		$this->assertNotEmpty($result[0]);
		$this->assertInternalType('object', $result[0]);
		$this->assertInstanceOf('TestModel', $result[0]);
		$this->assertEquals('3', $result[0]->id);
		$this->assertEquals('flaneur.txt', $result[0]->filename);
		$this->assertEquals('info@email.com', $result[0]->email);
		$this->assertEquals('95985b32e8401aed3143a6c090dfca6c969fbf76', $result[0]->file_hash);
		
		// Now lets check object itself as it should have been populated
		$this->assertEquals('3', $model->id);
		$this->assertEquals('flaneur.txt', $model->filename);
		$this->assertEquals('info@email.com', $model->email);
		$this->assertEquals('95985b32e8401aed3143a6c090dfca6c969fbf76', $model->file_hash);		
	}

	/**
	 * @depends testDbConnection
	 */
	public function testFindOneConditions(DB $dbh)
	{
		$model = new TestModel($dbh);

		// Make sure we get back a TestModel Object in result
		$result = $model->find('first', array('email' => 'dazza@email.com'));
		$this->assertNotEmpty($result);
		$this->assertInternalType('array', $result);
		$this->assertNotEmpty($result[0]);
		$this->assertInternalType('object', $result[0]);
		$this->assertInstanceOf('TestModel', $result[0]);
		$this->assertEquals('4', $result[0]->id);
		$this->assertEquals('appelschnapps.txt', $result[0]->filename);
		$this->assertEquals('dazza@email.com', $result[0]->email);
		$this->assertEquals('daf0ee72d921da625e5e08a0c13283830e610a6a', $result[0]->file_hash);
		
		// Now lets check object itself as it should have been populated
		$this->assertEquals('4', $model->id);
		$this->assertEquals('appelschnapps.txt', $model->filename);
		$this->assertEquals('dazza@email.com', $model->email);
		$this->assertEquals('daf0ee72d921da625e5e08a0c13283830e610a6a', $model->file_hash);		
	}

	/**
	 * @depends testDbConnection
	 */
	public function testFindOneConditionsFields(DB $dbh)
	{
		$model = new TestModel($dbh);

		// Make sure we get back a TestModel Object in result
		$result = $model->find('first', array('email' => 'dazza@email.com'), array('id','email'));
		$this->assertNotEmpty($result);
		$this->assertInternalType('array', $result);
		$this->assertNotEmpty($result[0]);
		$this->assertInternalType('object', $result[0]);
		$this->assertInstanceOf('TestModel', $result[0]);
		$this->assertEquals('4', $result[0]->id);
		$this->assertEquals('dazza@email.com', $result[0]->email);
		$this->assertObjectHasAttribute('filename', $result[0]);
		$this->assertNull($result[0]->filename);
		$this->assertObjectNotHasAttribute('file_hash', $result[0]);
		
		// Now lets check object itself as it should have been populated
		$this->assertEquals('4', $model->id);
		$this->assertObjectHasAttribute('filename', $model);
		$this->assertNull($model->filename);
		$this->assertEquals('dazza@email.com', $model->email);
		$this->assertObjectNotHasAttribute('file_hash', $model);
			
	}

	/**
	 * @depends testDbConnection
	 */
	public function testFindOneBadConditions(DB $dbh)
	{
		$model = new TestModel($dbh);

		// Bad Query
		$result = $model->find('first', array('email' => 'heffalump@email.com'));
		$this->assertEmpty($result);
	}

	/**
	 * @depends testDbConnection
	 */
	public function testFindAll(DB $dbh)
	{
		$model = new TestModel($dbh);
		$result = $model->find('all');
		$this->assertNotEmpty($result);
		$this->assertInternalType('array', $result);
		$this->assertEquals(2, count($result));

		// Check some values
		$this->assertEquals('95985b32e8401aed3143a6c090dfca6c969fbf76', $result[0]->file_hash);
		$this->assertEquals('daf0ee72d921da625e5e08a0c13283830e610a6a', $result[1]->file_hash);
	}

	/**
	 * @depends testDbConnection
	 */
	public function testFindAllConditions(DB $dbh)
	{
		$model = new TestModel($dbh);

		// Make sure we get back a TestModel Object in result
		$result = $model->find('all', array('email' => 'dazza@email.com'));
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
	}

	/**
	 * @depends testDbConnection
	 */
	public function testDelete(DB $dbh)
	{
		$model = new TestModel($dbh);

		$this->assertTrue($model->delete(3));
		$this->assertFalse($model->delete(42));

		$model->find('first');
		$this->assertEquals('4', $model->id);
		$this->assertTrue($model->delete());
		$this->assertFalse($model->delete());
		$this->assertObjectHasAttribute('file_hash', $model);
		$this->assertObjectHasAttribute('id', $model);
		$this->assertNull($model->id);
		$this->assertNull($model->email);
		$this->assertNull($model->file_hash);
	}

}
?>