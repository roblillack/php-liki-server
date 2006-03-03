<?php
class bsLikiBackend {
  var $dbh;
  var $db_host;
  var $db_database;
  var $db_user;
  var $db_password;
  var $db_table = 'bsliki';

  function bsLikiBackend($handle_ = false) {
    global $bs_configpath;
    
    if ($handle_ === false) {
      require("config.php");
      $this->dbh = mysql_connect($this->db_host, $this->db_user, $this->db_password);
	    mysql_select_db($this->db_database);
    } else {
      $this->dbh = $handle_;
    }

    if (!$this->dbh) {
      die('no db-connection.');
    }

    if (!$this->tablePresent()) {
      $this->createTable() or die('no table.');
    }
  }

  function tablePresent() {
    $res = mysql_query('DESC '.$this->db_table, $this->dbh);
    if ($res) {
      if (mysql_num_rows($res) >= 5) {
        $cols = array();
        for ($i = 0; $i < mysql_num_rows($res); $i ++) {
          $row = mysql_fetch_array($res);
          $cols[] = $row[0];
        }
        mysql_free_result($res);
        foreach(array('name', 'content', 'lockkey',
                      'timestamp_change', 'timestamp_lock', 'timestamp_visit') as $col) {
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
      trigger_error("table ".$this->db_table." not present.");
    }
  }

  function createTable() {
    trigger_error("FIXME: unable to create table ATM.");
    return false;
  }

  /*function pageExists($page) {
    $page = addslashes($page);
    $res = mysql_query('SELECT name FROM '.$this->db_table." WHERE name='$page'");
    if ($res && mysql_num_rows($res) == 1) {
      mysql_free_result($res);
      return true;
    } else {
      @mysql_free_result($res);
      return false;
    }
  }*/

  function cleanPageName($name) {
    return preg_replace('[\'\"\]\[\%\s]', '', $name);
  }

  /*function assurePageExists($page) {
    $page = $this->cleanPageName($page);
    mysql_query('INSERT INTO ' . $this->db_table . "(name, timestamp, unchanged) VALUES ('$page', 0, 0)", $this->dbh);
  }*/

  function autoFree($page) {
    $page = $this->cleanPageName($page);
    $timestamp = time();
    return mysql_query("UPDATE ".$this->db_table." SET lockkey='' WHERE ".
                       "(timestamp_lock<$timestamp-60) AND name LIKE '$page'", $this->dbh);
  }

  function lockPage($page, $key) {
    $this->autoFree($page);
    $timestamp = time();
    $key = addslashes($key);
    $page = $this->cleanPageName($page);
    $query = 'UPDATE '.$this->db_table." SET lockkey='$key',timestamp_lock=$timestamp WHERE name LIKE '$page' AND lockkey=''";
    mysql_query($query, $this->dbh);

    if (mysql_affected_rows($this->dbh) != 1) {
      // if updating failed, the page needs to be created (were being atomic here, so no select)
      return mysql_query('INSERT INTO ' . $this->db_table . "(name, timestamp_lock, lockkey) ".
                         "VALUES ('$page', $timestamp, '$key')", $this->dbh);
    } else {
      return true;
    }
  }

  function freePage($page, $key) {
    $page = $this->cleanPageName($page);
    $key = addslashes($key);
    $timestamp = time();
    mysql_query("UPDATE ".$this->db_table." SET lockkey='' WHERE ".
                "lockkey='$key' AND name LIKE '$page'", $this->dbh);
    if (mysql_affected_rows($this->dbh) != 1) {
      return false;
    } else {
      return true;
    }
  }

  function getRecentChanges($count = 10, $column = 'timestamp_change') {
    $changes = "";
    $timestamp = time();
    $q = "SELECT name,$column FROM ".$this->db_table." ORDER BY $column DESC";
    if ($count !== false)
      $q .= " LIMIT $count";
    $res = mysql_query($q);
    if (!$res) return false;
    $list = array();
    while ($r = mysql_fetch_assoc($res)) {
      $seconds = $timestamp - $r[$column];
      $minutes = floor($seconds / 60); $seconds %= 60;
      $hours = floor($minutes / 60); $minutes %= 60;
      $days = floor($hours / 24); $hours %= 24;
      $changes = "";
      if ($days > 0)
        $changes .= "${days}d ";
      if ($days > 0 || $hours > 0)
        $changes .= str_pad($hours, 2, '0', STR_PAD_LEFT).'h';
      if ($days > 0 || $hours > 0 || $minutes > 0)
        $changes .= str_pad($minutes, 2, '0', STR_PAD_LEFT).'m';
      $changes .= str_pad($seconds, 2, '0', STR_PAD_LEFT).'s';
      $list[] = array('name'       => $r[name],
                      'howlongago' => $changes);
    }
    mysql_free_result($res);
    return $list;
  }
  
  function getRecentVisits($count = 10) {
    return $this->getRecentChanges($count, 'timestamp_visit');
  }

  function getPageList() {
    $res = mysql_query("SELECT name FROM ".$this->db_table." ORDER BY name ASC");
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
    $res = mysql_query("SELECT * FROM ".$this->db_table.
                       " WHERE content like '%".addslashes($what)."%'".
                       " OR name like '%".addslashes($what)."%'".
                       " ORDER BY name ASC");
    if (!$res) {
      trigger_error("could not get page list");
      return false;
    }
    $pages = array();
    while ($r = mysql_fetch_assoc($res))
      $pages[] = $r;
    mysql_free_result($res);
    return $pages;
  }
  
  function getPageNamesContaining($what) {
    $res = mysql_query("SELECT name FROM ".$this->db_table.
                       " WHERE content like '%".addslashes($what)."%'".
                       " OR name like '%".addslashes($what)."%'".
                       " ORDER BY name ASC");
    if (!$res) {
      die("could not get page list: ".mysql_error());
      return false;
    }
    $pages = array();
    while ($r = mysql_fetch_array($res))
      $pages[] = $r[0];
    mysql_free_result($res);
    return $pages;
  }
  
  function visitPage($page) {
    $timestamp = time();
    $page = $this->cleanPageName($page);
    $query = 'UPDATE '.$this->db_table." SET timestamp_visit=$timestamp WHERE name LIKE '$page'";
    mysql_query($query, $this->dbh);

    if (mysql_affected_rows($this->dbh) != 1) {
      return false;
    } else {
      return true;
    }
  }
  
  function getPage($page) {
    $this->autoFree($page);
    $page = $this->cleanPageName($page);
    $res = mysql_query("SELECT * FROM " .$this->db_table. " WHERE name LIKE '$page'");
    if (!$res) {
      trigger_error("could not get content of page $page");
      return false;
    }
    if (mysql_num_rows($res) != 1) {
      // page does not exist
      return array('name'             => $page,
                   'content'          => '',
                   'timestamp_change' => 1);
    }
    $r = mysql_fetch_assoc($res);
    mysql_free_result($res);
    return $r;
  }
  
  function getTimestamp($page) {
    $this->autoFree($page);
    $page = $this->cleanPageName($page);
    $res = mysql_query("SELECT timestamp_change FROM " .$this->db_table. " WHERE name LIKE '$page'");
    if (!$res) {
      trigger_error("could not get content of page $page");
      return false;
    }
    if (mysql_num_rows($res) != 1) {
      // page does not exist
      return false;
    }
    $r = mysql_fetch_assoc($res);
    mysql_free_result($res);
    return $r['timestamp_change'];
  }

  function updatePage($page, $key, $content) {
    $key = addslashes($key);
    $page = $this->cleanPageName($page);
    $content = addslashes($content);
    $this->autoFree($page);
    $p = $this->getPage($page);
    if ($p === false) return false;
    if ($p['content'] == $content) {
      return true;
    } else {
      $time = time();
      $timestamp = "timestamp_lock=$time, timestamp_change=$time, ";
    }
    $query = 'UPDATE '.$this->db_table." SET ${timestamp}content='$content' ".
             "WHERE name LIKE '$page' AND (lockkey='$key')";
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
