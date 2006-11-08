<?
if (strstr($_SERVER['SERVER_NAME'], 'localhost') === false) {
  $this->baseUrl = 'http://therealurl.example/myliki';
} else {
  $this->baseUrl = 'http://localhost/liki';
}
$this->dataDir = 'pix';

// setting this to true will make the whole liki
// inaccessable without the following user/pw combo
$this->passwordProtected = false;
$this->username = 'usern';
// use `echo -n mypassword | md5[sum]` to generate
$this->password = '68ffd2d6c8fe66fdae57c5e19d12a897';

$this->db_host = 'my.db.host.example';
$this->db_database = 'my_db_name';
$this->db_user = 'db_username';
$this->db_password = 'db_passwort';
$this->db_table = 'myveryownprivateliki';
?>
