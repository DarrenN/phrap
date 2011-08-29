# Phrap
### Super-light wrapper for PDO for basic CRUD

*Background:* I often write light web-service type webapps that require simple DB access, but are far to simple to spin up a big ORM like Doctrine. *Phrap* provides simple read/write/delete to the DB using [PDO](http://us.php.net/manual/en/book.pdo.php) to handle escaping and such. I also used this little project to teach myself [PHPUnit](https://github.com/sebastianbergmann/phpunit/) - tests are in `/tests`.

###To Use:###

```
require 'DB.php';
require 'Model.php';
```