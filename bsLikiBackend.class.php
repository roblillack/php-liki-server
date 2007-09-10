<?php
class bsLikiBackend {
  var $dbh;
  var $db_host;
  var $db_database;
  var $db_user;
  var $db_password;
  var $db_table = 'bsliki';
  var $tablePrefix = false;

  function bsLikiBackend($handle_ = false) {
    global $bs_configpath;
    
    if ($handle_ === false) {
      require("config.php");
      if ($this->tablePrefix === false) {
        $this->tablePrefix = $this->db_table.'_';
      }

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

    /*if (!$this->tablePresent($this->db_table, array('name', 'content', 'lockkey',
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
    }*/
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
    $pages = $this->tablePrefix.'pages';
    $revisions = $this->tablePrefix.'revisions';
    // unlock pages
    mysql_query("UPDATE `$pages` SET lockkey='' WHERE ".
                "(timestamp_lock < $timestamp - 180) AND lockkey != ''", $this->dbh);
    //mysql_query("UPDATE `$pages` SET revision_id=(SELECT MAX(id) FROM `$revisions` WHERE timestamp_change IS NOT NULL
    // close revision of non locked pages (race condition here)
    //mysql_query("UPDATE `$table`,`$backup` SET `$backup`.timestamp_closed=$timestamp ".
    //            "WHERE `$backup`.timestamp_closed=0 AND `$table`.name=`$backup`.name AND ".
    //            "`$table`.lockkey=''", $this->dbh);
    // delete b0rked revisions with NULL content
    //mysql_query("DELETE FROM `$backup` WHERE timestamp_closed > 0 AND ISNULL(content)", $this->dbh);
    // delete closed revisions with NO changes
    //mysql_query("DELETE A FROM `$table` AS A WHERE A.name=B.name AND A.content=B.content AND A.id>B.id AND A.timestamp_closed>0", $this->dbh);
    return true;
  }

  function lockPage($page, $key) {
    $this->autoFree();
    $timestamp = time();
    $key = addslashes($key);
    $ip = sprintf("%u", ip2long($_SERVER['REMOTE_ADDR']));
    $agent = addslashes($_SERVER['HTTP_USER_AGENT']);
    $page = $this->cleanPageName($page);
    $pages = $this->tablePrefix.'pages';
    $revisions = $this->tablePrefix.'revisions';

    // first, try locking the page (and safe the old revision for later use)
    $q = "UPDATE `$pages` ".
         "SET lockkey='$key', timestamp_lock=$timestamp ".
         "WHERE name='$page' AND (lockkey='' OR ISNULL(lockkey))";
    mysql_query($q, $this->dbh);

    if (mysql_affected_rows($this->dbh) !== 1) {
      // unable to lock page.
      return false;
    }

    // successfully locked page
    // now, create a new revision...
    $q = "INSERT INTO `$revisions` (page_id,content,remote_ip,remote_agent) ".
         "SELECT page_id, content, $ip AS remote_ip, '$agent' AS remote_agent ".
         "FROM `$revisions` WHERE id=(SELECT revision_id FROM `$pages` WHERE name='$page')";
    mysql_query($q, $this->dbh);

    if (!($id = mysql_insert_id($this->dbh))) {
      // TODO: unable to create new revision. why?
      return false;
    }

    // ok, tell the page about the new revision.
    mysql_query("UPDATE `$pages` ".
                "SET revision_id=$id ".
                "WHERE name='$page' AND lockkey='$key'",
                $this->dbh);

    if (mysql_affected_rows($this->dbh) !== 1) {
      // dammit, we lost the lock from two queries above again!
      mysql_query("DELETE FROM `$revisions` WHERE id=$id", $this->dbh);
      return false;
    }

    return true;
  }

  function freePage($page, $key) {
    $page = $this->cleanPageName($page);
    $key = addslashes($key);
    mysql_query("UPDATE `{$this->tablePrefix}pages` SET lockkey='' WHERE lockkey='$key' AND name='$page'", $this->dbh);
    if (mysql_affected_rows($this->dbh) != 1) {
      return false;
    } else {
      return true;
    }
  }
  
  function getDetailedChangeLog($count = 10) {
    $this->autoFree();
    $before = "IFNULL((SELECT content FROM `{$this->tablePrefix}revisions` AS C ".
             "WHERE C.id < A.id AND C.page_id=A.page_id ORDER BY C.id DESC LIMIT 1), '')";
    $q = "SELECT content AS `content_after`, ".
         "$before AS `content_before`, ".
         "remote_ip, remote_agent, ".
         "timestamp_change AS timestamp, ".
         "name ".
         "FROM `{$this->tablePrefix}revisions` AS A,`{$this->tablePrefix}pages` AS B ".
         "WHERE A.page_id=B.id AND A.timestamp_change IS NOT NULL ".
         "ORDER BY A.timestamp_change DESC ".
         "LIMIT $count";
    if (!($res = mysql_query($q, $this->dbh))) return false;
    $list = array();
    while ($r = mysql_fetch_assoc($res)) {
      $list[] = $r;
    }
    mysql_free_result($res);
    return $list;
  }

  function getRecentChanges($count = 10) {
    $changes = "";
    $timestamp = time();
    $q = "SELECT name,MAX(timestamp_change) AS ts FROM `{$this->tablePrefix}pages`,`{$this->tablePrefix}revisions` ".
         "WHERE `{$this->tablePrefix}pages`.id=page_id AND timestamp_change IS NOT NULL ".
         "GROUP BY page_id ORDER BY ts DESC";
    if ($count !== false)
      $q .= " LIMIT $count";
    $res = mysql_query($q);
    if (!$res) return false;
    $list = array();
    while ($r = mysql_fetch_assoc($res)) {
      $seconds = $timestamp - $r['ts'];
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
    $changes = "";
    $timestamp = time();
    $q = "SELECT name,timestamp_visit FROM `{$this->tablePrefix}pages` ".
         "ORDER BY timestamp_visit DESC";
    if ($count !== false)
      $q .= " LIMIT $count";
    $res = mysql_query($q);
    if (!$res) return false;
    $list = array();
    while ($r = mysql_fetch_assoc($res)) {
      $seconds = $timestamp - $r['timestamp_visit'];
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

  function getPageList() {
    $res = mysql_query("SELECT name FROM `{$this->tablePrefix}pages` ".
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
  
  // TODO: lists history...
  function getPagesContaining($what) {
    $res = mysql_query("SELECT name,content,timestamp_change,timestamp_visit FROM `{$this->tablePrefix}pages` AS P,`{$this->tablePrefix}revisions` AS R ".
                       "WHERE R.page_id=P.id AND R.timestamp_change IS NOT NULL AND content like '%".addslashes($what)."%' ".
                       "OR name like '%".addslashes($what)."%' ".
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
  
  // TODO
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
    $query = "UPDATE `{$this->tablePrefix}pages` SET timestamp_visit=$timestamp ".
             "WHERE name='$page'";
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
    $p = "`{$this->tablePrefix}pages`";
    $r = "`{$this->tablePrefix}revisions`";
    $res = mysql_query("SELECT revision_id,name,content,timestamp_change,timestamp_visit,timestamp_lock,remote_ip,remote_agent,lockkey FROM $p,$r ".
                       "WHERE name='$page' AND $p.revision_id=$r.id");
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
    $p = "`{$this->tablePrefix}pages`";
    $r = "`{$this->tablePrefix}revisions`";
    $res = mysql_query("SELECT timestamp_change FROM $p,$r ".
                       "WHERE name='$page' AND $p.revision_id=$r.id AND timestamp_change IS NOT NULL");
     if (!$res) {
      trigger_error("could not get timestamp of page $page");
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

  function renewLock($page, $key) {
    $key = addslashes($key);
    $page = $this->cleanPageName($page);
    $this->autoFree();
    // TODO: check for last change. don't allow having a page locked all time without changes!
    mysql_query("UPDATE `{$this->tablePrefix}pages` SET timestamp_lock=".time()." ".
                "WHERE name='$page' AND lockkey='$key'",
                $this->dbh);
    if (mysql_affected_rows($this->dbh) == 1 ||
        preg_replace("/^[^0-9]+matched[^0-9]+([0-9]+)\ .*$/", "$1", mysql_info()) == 1) {
        return true;
    }
  
    return false;
  }

  // TODO: manually calculate sums of special pages.
  function getPageMD5($page) {
    $page = $this->cleanPageName($page);
    $q = "SELECT MD5(TRIM(content)) AS md5 FROM {$this->tablePrefix}revisions,{$this->tablePrefix}pages ".
         "WHERE revision_id={$this->tablePrefix}revisions.id AND name='$page'";
    $res = mysql_query($q, $this->dbh);
    if (!$res || mysql_num_rows($res) != 1) {
      return false;
    }
    $r = mysql_fetch_assoc($res);
    mysql_free_result($res);
    return $r['md5'];
  }
 
  function updatePage($page, $key, $content) {
    $key = addslashes($key);
    $page = $this->cleanPageName($page);

    // first, renew the lock
    if (!$this->renewLock($page, $key)) {
      return false;
    }

    if (md5($content) == $this->getPageMD5($page)) {
      // no changes...
      return true;
    }

    $content = addslashes(trim($content));
    $query = "UPDATE `{$this->tablePrefix}revisions` SET timestamp_change=".time().", content='$content' ".
             "WHERE id=(SELECT revision_id FROM `{$this->tablePrefix}pages` WHERE name='$page')";
    mysql_query($query, $this->dbh);

    if (mysql_affected_rows($this->dbh) != 1 ||
        preg_replace("/^[^0-9]+matched[^0-9]+([0-9]+)\ .*$/", "$1", mysql_info()) == 1) {
      // Rows matched: 1  Changed: 0  Warnings: 0
      return true;
    }

    return false;
  }

  function closeConnection() {
    mysql_close($this->dbh);
  }
}
?>
