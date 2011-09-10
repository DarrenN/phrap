# Phrap
### Super-light PHP wrapper for your database using PDO. Simple CRUD operations with no sexy magic.

**Background:** I often write light web-service type webapps that require simple DB access, but are far to simple to spin up a big ORM like Doctrine. **Phrap** provides simple read/write/delete to the DB using [PDO](http://us.php.net/manual/en/book.pdo.php) to handle escaping and such. I also used this little project to teach myself [PHPUnit](https://github.com/sebastianbergmann/phpunit/) - tests are in `/tests`.

###To Use:###

Read the tests in `/test/test.php` to get a general idea on how it works. It follows an ActiveRecord-ish pattern. There is no built in validation, etc. You can extend with your own logic for that.

```php
<?php
require 'DB.php';
require 'Model.php';

// Connect to DB

$database = array(
	'dbserver' => DBSERVER,
	'dbuser'   => DBUSER,
	'dbpasswd' => DBPASSWD,
	'dbname'   => DBNAME
);
$dbh = new DB($database);

// Define Model

class FileUpload extends Model
{
    public $table = "files";
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

// Pass the connection directly to model, this makes it easy to
// switch connections if say, you have a Read-only Slave DB and
// a separate Master DB for writes.
$file = new File($dbh);

// Switch DB
$dbh_write = new DB($database_write);
$file->switch_connection($dbh_write);

// Operate on an object directly
$file->filename = 'heffalump.txt';

// assigns the next id in autoincrement and clears properties
$file->create();

// Set mutiple properties at once, which are also filtered 
// (codswoddle will not be set since it ain't a DB field.
$file->set(array('filename' => 'heffalump.txt', 'codswoddle' => 'phiminster'));

// Save
$file->save();

// Queries
// Queries are built up by chaining together methods and then
// executing them. This allows you to operate on your queries
// before hitting the DB. The pattern is similar to Ruby Sequel.
 
// FIND BY ID
$model->id(3);
$result = $model->exec();

// Returns a Model object:
object(TestModel) {
  ["id"]=>
  string(1) "3"
  ["filename"]=>
  string(11) "flaneur.txt"
  ["userid"]=>
  string(1) "2"
  ["email"]=>
  string(14) "info@email.com"
  ["file_hash"]=>
  string(40) "95985b32e8401aed3143a6c090dfca6c969fbf76"
}

// FIND FIRST
$model->first();
$result = $model->exec();

// FIND FIRST WITH CONDITIONS
$model->first(array('filename' => 'appelschnapps.txt'));
$result = $model->exec();

// GET SINGLE COLUMN FROM FIRST ENTRY
$model->first()->get('filename');
$result = $model->exec();

// Returns a Model object:
object(TestModel) {
  ["filename"]=>
  string(11) "flaneur.txt"
}

// FIND LAST
$result = $model->last()->exec();

// FIND ALL
$results = $model->all()->exec();

// Returns an array of model objects:
array(3) {
  [0]=>
  object(TestModel)#203 (5) {
    ["id"]=>
    string(1) "3"
    ["filename"]=>
    string(11) "flaneur.txt"
    ["userid"]=>
    string(1) "2"
    ["email"]=>
    string(14) "info@email.com"
    ["file_hash"]=>
    string(40) "95985b32e8401aed3143a6c090dfca6c969fbf76"
  }
  [1]=>...
}

// FIND ALL WITH CONDITION
$results = $model->all(array('filename' => 'appelschnapps.txt'))->exec();

// FIND ALL RETURNING JUST A SPECIFIC COLUMN
$model->all()->get('filename')->exec();

// FIND ALL BY FILENAME RETURNING COLS: EMAIL FILENAME & ID
$model->all(array('filename' => 'appelschnapps.txt'))->get(array('email','filename','id'));
$results = $model->exec();

// Raw queries: you can also make raw queries but still get the 
// benefits of parameterized inputs and PDO's escaping

// Raw but Safe Query with named parameter array
$result = $file->query('SELECT * FROM files WHERE email = :email ORDER BY userid LIMIT 1', array(':email' => 'dazza@email.com'));

// Raw but Safe Query with single string parameter
$result = $file->query('SELECT * FROM files WHERE email = ? ORDER BY userid LIMIT 1', 'dazza@email.com');

// Use Raw query to operate on db rows, returns true/false
$values = array(
    'id'       => 4,
    'filename' => 'mule.txt',
    'email'    => 'gazza@email.com'
    );
$result = $model->query('REPLACE INTO test SET id = :id, email = :email, filename = :filename', $values, false);

// Delete manually by id
$file->delete(1);

// Set id in object then delete it
$file->find('first');
$file->delete();

// Init (object constructor)
public function init() // this code will run when the model is populated by DB results.

// Virtual fields
// Create calculated fields on the fly inside init();
$this->virtual_field('file_hash', sha1($this->filename . $this->id));
?>
```