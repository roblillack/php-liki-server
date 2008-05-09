<?
if (strstr($_SERVER['SERVER_NAME'], 'localhost') === false) {
  $this->baseUrl = 'http://therealurl.example/myliki';
} else {
  $this->baseUrl = 'http://localhost/liki';
}
$this->dataDir = 'data';

// setting this to true will make the whole liki
// inaccessable without the following user/pw combo
$this->passwordProtected = false;
$this->username = 'usern';
// use `echo -n mypassword | md5[sum]` to generate
$this->password = '68ffd2d6c8fe66fdae57c5e19d12a897';

//$this->database = 'sqlite:'.dirname(__FILE__).'/data/liki.db';
$this->database = 'mysql:host=my.db.host.example; dbname=my_db_name';
$this->dbUser = 'db_username';
$this->dbPassword = 'db_passwort';
$this->dbPersistent = true;
$this->tablePrefix = 'myliki_';

$this->maximalPictureWidth = 600;
$this->likiTitle = 'My Liki';
?>
