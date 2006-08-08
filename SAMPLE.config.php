<?
if (strstr($_SERVER['SERVER_NAME'], 'localhost') === false) {
  $this->baseUrl = 'http://therealurl.example/myliki';
} else {
  $this->baseUrl = 'http://localhost/liki';
}
$this->dataDir = 'pix';
$this->db_host = 'my.db.host.example';
$this->db_database = 'my_db_name';
$this->db_user = 'db_username';
$this->db_password = 'db_passwort';
$this->db_table = 'myveryownprivateliki';
?>
