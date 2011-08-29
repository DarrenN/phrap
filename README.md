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

// Operate on an object directly
$file = new File($dbh);
$file->filename = 'heffalump.txt';
$file->save();

$file = new File($dbh);

// assigns the next id in autoincrement
$file->create();

// will not set object property for codswoddle as it is not a DB field.
$file->set(array('filename' => 'heffalump.txt', 'codswoddle' => 'phiminster'));



```