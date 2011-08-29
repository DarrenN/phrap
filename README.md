# Phrap
### Super-light wrapper for PHP PDO to do basic CRUD

**Background:** I often write light web-service type webapps that require simple DB access, but are far to simple to spin up a big ORM like Doctrine. **Phrap** provides simple read/write/delete to the DB using [PDO](http://us.php.net/manual/en/book.pdo.php) to handle escaping and such. I also used this little project to teach myself [PHPUnit](https://github.com/sebastianbergmann/phpunit/) - tests are in `/tests`.

###To Use:###

Read the tests in `/test/test.php` to get a general idea on how it works. It follows an ActiveRecord-ish pattern. There is no built in validation, etc. You can extend with your own logic for that.

```php
require 'DB.php';
require 'Model.php';

// Connect to DB

$database = array(
	'dbserver' => DBSERVER,
	'dbuser'   => DBUSER,
	'dbpasswd' => DBPASSWD,
	'dbname'   => DBNAME,
	'dbsalt'   => DBSALT
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

$file = new File($dbh);

// Operate on an object directly
$file->filename = 'heffalump.txt';

// assigns the next id in autoincrement and clears properties
$file->create();

// Set mutiple properties at once, which are also filtered 
// (codswoddle will not be set since it ain't a DB field.
$file->set(array('filename' => 'heffalump.txt', 'codswoddle' => 'phiminster'));

// Save
$file->save();

// Query
$result = $file->find('first');
$result = $file->find('all');

// Query with conditions
$result = $file->find('first', array('email' => 'dazza@email.com'));

// Query with conditions & field whitelist
$result = $file->find('first', array('email' => 'dazza@email.com'), array('id','email'));

// Raw but Safe Query with named paramter array
$result = $file->query('SELECT * FROM files WHERE email = :email ORDER BY userid LIMIT 1', array(':email' => 'dazza@email.com'));

// Raw but Safe Query with single string parameter
$result = $file->query('SELECT * FROM files WHERE email = ? ORDER BY userid LIMIT 1', 'dazza@email.com');

// Delete manually by id
$file->delete(1);

// Set id in object then delete it
$file->find('first');
$file->delete();
```