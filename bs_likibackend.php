<?php
global $bs_utils;
if (!$bs_utils) {
  die('bsLikiBackend needs bs_utils!');
}

class bsLikiBackend {
  var $table;
  var $dbh;
  var $db_host;
  var $db_database;
  var $db_user;
  var $db_password;
  var $db_prefix = '';

  function bsLikiBackend($handle_ = false, $table_ = 'bsliki') {
    global $bs_configpath;
    
    $this->table = $table_;
    if ($handle_ === false) {

      require("$bs_configpath/config_db.php");

      if (!empty($this->db_prefix))
        $this->table = $this->db_prefix.$this->table;
        
      $this->dbh = mysql_connect($this->db_host,
		                 $this->db_user,
				 $this->db_password);
	mysql_select_db($this->db_database);
    } else {
      $this->dbh = $handle_;
    }

    if (!$this->dbh) {
      die('no db-connection.');
    }

    $this->table = addslashes($this->table);

    if (!$this->tablePresent()) {
      $this->createTable() or die('no table.');
    }
  }

  function tablePresent() {
    $res = mysql_query('DESC '.$this->table, $this->dbh);
    if ($res) {
      if (mysql_num_rows($res) >= 5) {
        $cols = array();
        for ($i = 0; $i < mysql_num_rows($res); $i ++) {
          $row = mysql_fetch_array($res);
          $cols[] = $row[0];
        }
        mysql_free_result($res);
        foreach(array('name', 'content', 'lockkey', 'timestamp', 'unchanged') as $col) {
          if (!in_array($col, $cols)) {
            trigger_error("column $col missing");
            return false;
          }
        }
        return true;
      } else {
        trigger_error('not enough columns');
        return false;
      }
    } else {
      trigger_error("table ".$this->table." not present.");
    }
  }

  function createTable() {
    trigger_error("FIXME: unable to create table ATM.");
    return false;
  }

  /*function pageExists($page) {
    $page = addslashes($page);
    $res = mysql_query('SELECT name FROM '.$this->table." WHERE name='$page'");
    if ($res && mysql_num_rows($res) == 1) {
      mysql_free_result($res);
      return true;
    } else {
      @mysql_free_result($res);
      return false;
    }
  }*/

  function assurePageExists($page) {
    $page = addslashes($page);
    @mysql_query('INSERT INTO ' . $this->table . "(name, timestamp, unchanged) VALUES ('$page', 0, 0)", $this->dbh);
  }

  function autoFree($page) {
    $this->assurePageExists($page);
    $page = addslashes($page);
    $timestamp = time();
    mysql_query("UPDATE ".$this->table." SET unchanged=0, lockkey='' WHERE ".
                "(unchanged>=10 OR timestamp<$timestamp-60) AND name='$page'", $this->dbh);
  }

  function lockPage($page, $key) {
    $this->autoFree($page);
    $timestamp = time();
    $key = addslashes($key);
    $page = addslashes($page);
    $query = 'UPDATE '.$this->table." SET lockkey='$key',timestamp=$timestamp WHERE name='$page' AND lockkey=''";
    mysql_query($query, $this->dbh);

    if (mysql_affected_rows($this->dbh) != 1) {
      return false;
    } else {
      return true;
    }
  }

  function freePage($page, $key) {
    $this->assurePageExists($page);
    $page = addslashes($page);
    $key = addslashes($key);
    $timestamp = time();
    mysql_query("UPDATE ".$this->table." SET unchanged=0, lockkey='' WHERE ".
                "lockkey='$key' AND name='$page'", $this->dbh);
    if (mysql_affected_rows($this->dbh) != 1) {
      return false;
    } else {
      return true;
    }
  }

  function getLastChanges($count = 1) {
    $changes = "";
    $timestamp = time();
    $res = mysql_query("SELECT name,timestamp FROM ".$this->table." ORDER BY timestamp DESC LIMIT $count");
    if (!$res) return false;
    while ($r = mysql_fetch_assoc($res)) {
      $seconds = $timestamp - $r['timestamp'];
      $minutes = floor($seconds / 60); $seconds %= 60;
      $hours = floor($minutes / 60); $minutes %= 60;
      $days = floor($hours / 24); $hours %= 24;
      $changes .= " ".$r['name']. "/";
      if ($days > 0)
        $changes .= "${days}d+";
      if ($days > 0 || $hours > 0)
        $changes .= str_pad($hours, 2, '0', STR_PAD_LEFT).'h';
      if ($days > 0 || $hours > 0 || $minutes > 0)
        $changes .= str_pad($minutes, 2, '0', STR_PAD_LEFT).'m';
      $changes .= str_pad($seconds, 2, '0', STR_PAD_LEFT).'s';
    }
    mysql_free_result($res);
    return substr($changes,1);
  }

  function getPageList() {
    $res = mysql_query("SELECT name FROM ".$this->table." ORDER BY name ASC");
    if (!$res) {
      trigger_error("could not get page list");
      return false;
    }
    $pages = array();
    while ($r = mysql_fetch_array($res))
      $pages[] = $r[0];
    mysql_free_result($res);
    return $pages;
  }
  
  function getPagesContaining($what) {
    $res = mysql_query("SELECT name FROM ".$this->table." WHERE content like '%".addslashes($what)."%' ORDER BY name ASC");
    if (!$res) {
      trigger_error("could not get page list");
      return false;
    }
    $pages = array();
    while ($r = mysql_fetch_array($res))
      $pages[] = $r[0];
    mysql_free_result($res);
    return $pages;
  }
   
  function getPage($page) {
    $this->autoFree($page);
    $page = addslashes($page);
    $res = mysql_query("SELECT * FROM " .$this->table. " WHERE name='$page'");
    if (!$res) {
      trigger_error("could not get content of page $page");
      return false;
    }
    if (mysql_num_rows($res) != 1) {
      trigger_error("no result, when asking for content of page $page");
      return false;
    }
    $r = mysql_fetch_assoc($res);
    mysql_free_result($res);
    return $r;
  }

  function updatePage($page, $key, $content) {
    $this->autoFree($page);
    $p = $this->getPage($page);
    if ($p === false) return false;
    if ($p['content'] == $content) {
      $timestamp = '';
    } else {
      $timestamp = "timestamp=".time().",";
    }
    //$timestamp = time();
    $key = addslashes($key);
    $page = addslashes($page);
    $content = addslashes($content);
    $query = 'UPDATE '.$this->table." SET ${timestamp}content='$content' ".
             "WHERE name='$page' AND (lockkey='$key')";
             /*"(timestamp<$timestamp-60) OR ".                  // letztes update länger als ne minute her
             "(lockkey<>'$key' AND unchanged>=10))";           // jemand anderes hat unchanged updates überschritten*/
    //trigger_error($query);
    mysql_query($query, $this->dbh);

    if (mysql_affected_rows($this->dbh) != 1) {
      // Rows matched: 1  Changed: 0  Warnings: 0
      $matches = preg_replace("/^[^0-9]+matched[^0-9]+([0-9]+)\ .*$/", "$1", mysql_info());
      if ($matches == 1) { // stimmt alles, nur kein update
        return true;
      } else {          // key falsch oder seitenname falsch
        return false;
      }
    } else {
      return true;
    }
  }

  function closeConnection() {
    mysql_close($this->dbh);
  }
}
?>
