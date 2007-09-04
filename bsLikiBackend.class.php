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
      $this->dbh = mysql_connect($this->db_host,
                                 $this->db_user,
                                 $this->db_password);
      if ($this->dbh === false)
        die('no connection to database possible');
      if (mysql_select_db($this->db_database, $this->dbh) === false)
        die('could not select database '.$this->db_database);
    } else {
      $this->dbh = $handle_;
    }

    if (!$this->tablePresent($this->db_table, array('name', 'content', 'lockkey',
                             'timestamp_change', 'timestamp_lock', 'timestamp_visit'))) {
      $query = "CREATE TABLE `".$this->db_table."` (".
               " name             varchar(100)     NOT NULL default '',".
               " content          text             default NULL,".
               " lockkey          varchar(32)      default NULL,".
               " timestamp_visit  int(10) unsigned NOT NULL default 0,".
               " timestamp_change int(10) unsigned NOT NULL default 0,".
               " timestamp_lock   int(10) unsigned NOT NULL default 0,".
               " PRIMARY KEY(name)".
               ")";
      @mysql_query($query, $this->dbh) or die('could not create table '.$this->db_table);
    }
    if (!$this->tablePresent($this->db_table.'_backup',
                             array('id', 'name', 'content',
                                   'timestamp_opened', 'timestamp_closed'))) {
      $query = "CREATE TABLE `".$this->db_table."_backup` (".
               " id               int(10) unsigned NOT NULL auto_increment,".
               " name             varchar(100)     NOT NULL default '',".
               " content          text             default NULL,".
               " timestamp_opened int(10) unsigned NOT NULL default 0,".
               " timestamp_closed int(10) unsigned NOT NULL default 0,".
               " PRIMARY KEY(id)".
               ")";
      @mysql_query($query, $this->dbh) or die('could not create table '.$this->db_table."_backup");
    }
  }

  function tablePresent($tablename, $columns) {
    $res = @mysql_query('DESC `'.$tablename.'`', $this->dbh);
    if ($res) {
      if (mysql_num_rows($res) >= count($columns)) {
        $cols = array();
        for ($i = 0; $i < mysql_num_rows($res); $i ++) {
          $row = mysql_fetch_array($res);
          $cols[] = $row[0];
        }
        mysql_free_result($res);
        foreach($columns as $col) {
          if (!in_array($col, $cols)) die("column $col missing");
        }
      } else {
        die("not enough columns in $tablename. table layout changed?");
      }
    } else {
      return false;
    }
    return true;
  }

  function createTable() {
    $query = "CREATE TABLE `".$this->db_table."` (".
             " name             varchar(100)     NOT NULL default '',".
             " content          text             default NULL,".
             " lockkey          varchar(32)      default NULL,".
             " timestamp_visit  int(10) unsigned NOT NULL default 0,".
             " timestamp_change int(10) unsigned NOT NULL default 0,".
             " timestamp_lock   int(10) unsigned NOT NULL default 0,".
             " PRIMARY KEY(name)".
             ")";
    return mysql_query($query, $this->dbh);
  }

  function cleanPageName($name) {
    return preg_replace('[\'\"\]\[\%\s]', '', $name);
  }

  function autoFree() {
    $timestamp = time();
    $table = $this->db_table;
    $backup = $table.'_backup';
    // unlock pages
    mysql_query("UPDATE `$table` SET lockkey='' WHERE ".
                "(timestamp_lock < $timestamp - 180)", $this->dbh);
    // close revision of non locked pages
    mysql_query("UPDATE `$table`,`$backup` SET `$backup`.timestamp_closed=$timestamp ".
                "WHERE `$backup`.timestamp_closed=0 AND `$table`.name=`$backup`.name AND ".
                "`$table`.lockkey=''", $this->dbh);
    // delete b0rked revisions with NULL content
    mysql_query("DELETE FROM `$backup` WHERE timestamp_closed > 0 AND ISNULL(content)", $this->dbh);
    // delete closed revisions with NO changes
    mysql_query("DELETE A FROM `$backup` AS A, `$backup` AS B WHERE A.name=B.name AND A.content=B.content AND A.id>B.id AND A.timestamp_closed>0", $this->dbh);
    return true;
  }

  function lockPage($page, $key) {
    $this->autoFree();
    $timestamp = time();
    $key = addslashes($key);
    $page = $this->cleanPageName($page);
    $query = 'UPDATE `'.$this->db_table."` SET lockkey='$key',".
             "timestamp_lock=$timestamp WHERE name LIKE '$page' AND ".
             "lockkey=''";
    mysql_query($query, $this->dbh);

    if (mysql_affected_rows($this->dbh) != 1) {
      /** if updating failed, the page needs to be created
          (were being atomic here, so no select) */
      mysql_query('INSERT INTO `'.$this->db_table."_backup`".
                  "(name, timestamp_opened) ".
                  "VALUES ('$page', $timestamp)", $this->dbh);
      return mysql_query('INSERT INTO `'.$this->db_table."`".
                         "(name, timestamp_lock, lockkey) ".
                         "VALUES ('$page', $timestamp, '$key')", $this->dbh);
    } else {
      // the page exists. create a new backup revision
      mysql_query('INSERT INTO `'.$this->db_table.'_backup` (name,timestamp_opened,content) '.
                  "SELECT name,timestamp_lock AS timestamp_opened,content FROM `".$this->db_table."` ".
                  "WHERE name LIKE '$page' AND lockkey='$key'", $this->dbh);
      return true;
    }
  }

  function freePage($page, $key) {
    $page = $this->cleanPageName($page);
    $key = addslashes($key);
    $timestamp = time();
    $table = '`'.$this->db_table.'`';
    $backup =  '`'.$this->db_table.'_backup`';
    mysql_query("UPDATE $table AS A, $backup AS B SET A.lockkey='', B.timestamp_closed=$timestamp WHERE ".
                "A.lockkey='$key' AND A.name='$page' AND B.name='$page' AND B.timestamp_closed=0", $this->dbh);
    if (mysql_affected_rows($this->dbh) != 1) {
      return false;
    } else {
      return true;
    }
  }
  
  function getDetailedChangeLog($count = 10) {
    $this->autoFree();
    $after = "IFNULL((SELECT content FROM `{$this->db_table}_backup` AS C ".
             "WHERE C.id > A.id AND C.name = A.name ORDER BY C.id LIMIT 1), ".
             "(SELECT content FROM `{$this->db_table}` AS D WHERE D.name = A.name LIMIT 1))";
    $q = "SELECT $after AS `content_after`, ".
         "A.content AS `content_before`, ".
         "A.timestamp_opened AS timestamp_start, ".
         "A.timestamp_closed AS timestamp_end, ".
         "B.name AS name ".
         "FROM `{$this->db_table}_backup` AS A,`{$this->db_table}` AS B ".
         "WHERE A.name = B.name AND A.timestamp_closed>0 ".
         "ORDER BY A.timestamp_closed DESC ".
         "LIMIT $count";
    if (!($res = mysql_query($q, $this->dbh))) return false;
    $list = array();
    while ($r = mysql_fetch_assoc($res)) {
      $list[] = $r;
    }
    mysql_free_result($res);
    return $list;
  }

  function getRecentChanges($count = 10, $column = 'timestamp_change') {
    $changes = "";
    $timestamp = time();
    $q = "SELECT name,$column FROM `".$this->db_table."` ".
         "ORDER BY $column DESC";
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
      $list[] = array('name'       => $r['name'],
                      'howlongago' => $changes);
    }
    mysql_free_result($res);
    return $list;
  }
  
  function getRecentVisits($count = 10) {
    return $this->getRecentChanges($count, 'timestamp_visit');
  }

  function getPageList() {
    $res = mysql_query("SELECT name FROM `".$this->db_table."` ".
                       "ORDER BY name ASC");
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
    $res = mysql_query("SELECT * FROM `".$this->db_table."` ".
                       "WHERE content like '%".addslashes($what)."%'".
                       "OR name like '%".addslashes($what)."%'".
                       "ORDER BY name ASC");
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
    $res = mysql_query("SELECT name FROM `".$this->db_table."` ".
                       "WHERE content like '%".addslashes($what)."%' ".
                       "OR name like '%".addslashes($what)."%' ".
                       "ORDER BY name ASC");
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
    $query = 'UPDATE `'.$this->db_table."` SET timestamp_visit=$timestamp ".
             "WHERE name LIKE '$page'";
    mysql_query($query, $this->dbh);

    if (mysql_affected_rows($this->dbh) != 1) {
      return false;
    } else {
      return true;
    }
  }
  
  function getPage($page) {
    $this->autoFree();
    $page = $this->cleanPageName($page);
    $res = mysql_query("SELECT * FROM `" .$this->db_table. "` ".
                       "WHERE name LIKE '$page'");
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
    $this->autoFree();
    $page = $this->cleanPageName($page);
    $res = mysql_query("SELECT timestamp_change FROM `" .$this->db_table. "` ".
                       "WHERE name LIKE '$page'");
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
    $this->autoFree();
    $p = $this->getPage($page);
    if ($p === false) return false;
    if ($p['content'] == $content) {
      return true;
    } else {
      $time = time();
      $timestamp = "timestamp_lock=$time, timestamp_change=$time, ";
    }
    $content = addslashes($content);
    $query = 'UPDATE `'.$this->db_table."` SET ${timestamp}content='$content' ".
             "WHERE name LIKE '$page' AND (lockkey='$key')";
    //trigger_error($query);
    mysql_query($query, $this->dbh);

    if (mysql_affected_rows($this->dbh) != 1) {
      // Rows matched: 1  Changed: 0  Warnings: 0
      $matches = preg_replace("/^[^0-9]+matched[^0-9]+([0-9]+)\ .*$/", "$1", mysql_info());
      if ($matches == 1) { // stimmt alles, nur kein update
        return true;
      } else {          // key falsch oder seitenname falsch
        //trigger_error(mysql_info());
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
